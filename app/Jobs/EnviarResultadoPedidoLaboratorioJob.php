<?php

namespace App\Jobs;

use App\Mail\ResultadoPedidoLaboratorioMail;
use App\Models\PedidoLaboratorio;
use App\Models\PedidoLaboratorioResultado;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EnviarResultadoPedidoLaboratorioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(
        public ?int $resultadoId = null,
        public bool $force = false,
        public ?int $pedidoId = null
    ) {
        $this->afterCommit = true;
    }

    public function handle(): void
    {
        $connection = DB::connection();
        $lockName = $this->resultadoId
            ? 'email_lab_res_'.$this->resultadoId
            : ($this->pedidoId ? 'email_lab_ped_res_'.$this->pedidoId : null);

        if (! $lockName) {
            return;
        }

        $lockResult = $connection->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lockName]);
        $acquired = isset($lockResult->acquired) ? (int) $lockResult->acquired : null;

        if ($acquired !== 1) {
            if ($acquired === null) {
                Log::warning('Error en GET_LOCK para resultado de laboratorio: '.$lockName);
            }

            return;
        }

        try {
            $claim = DB::transaction(function () {
                $target = null;
                if ($this->resultadoId) {
                    $target = PedidoLaboratorioResultado::query()
                        ->with(['pedido.cita.dependiente.responsable', 'pedido.cita.doctor', 'pedido.doctor', 'laboratorio'])
                        ->whereKey($this->resultadoId)
                        ->lockForUpdate()
                        ->first();
                } elseif ($this->pedidoId) {
                    $target = PedidoLaboratorio::query()
                        ->with(['cita.dependiente.responsable', 'cita.doctor', 'doctor', 'paciente'])
                        ->whereKey($this->pedidoId)
                        ->lockForUpdate()
                        ->first();
                }

                if (! $target) {
                    return ['action' => 'abort'];
                }

                $recipient = $this->resolveRecipient($target);
                if ($recipient === '') {
                    $this->markPermanentFailure($target, 'No hay un correo registrado para enviar el resultado.');

                    return ['action' => 'abort'];
                }

                $isAlreadySent = $target instanceof PedidoLaboratorioResultado
                    ? ($target->envio_estado === 'sent' && $target->enviado_a === $recipient)
                    : ($target->resultado_enviado_at !== null && $target->envio_estado === 'sent');

                if (! $this->force && $isAlreadySent) {
                    return ['action' => 'already_sent'];
                }

                $target->forceFill([
                    'enviado_a' => $recipient,
                    'envio_estado' => 'sending',
                    'envio_error' => null,
                    'envio_intentos' => (int) ($target->envio_intentos ?? 0) + 1,
                ])->saveQuietly();

                return [
                    'action' => 'send',
                    'target' => $target,
                    'recipient' => $recipient,
                ];
            });

            if (! $claim || $claim['action'] !== 'send') {
                return;
            }

            $target = $claim['target'];
            $recipient = $claim['recipient'];

            try {
                $mailable = $target instanceof PedidoLaboratorioResultado
                    ? new ResultadoPedidoLaboratorioMail($target->fresh(['pedido.cita.dependiente.responsable', 'pedido.cita.doctor', 'pedido.doctor', 'laboratorio']))
                    : new ResultadoPedidoLaboratorioMail($target->fresh(['cita.dependiente.responsable', 'cita.doctor', 'doctor', 'paciente']));

                Mail::to($recipient)->send($mailable);

                DB::transaction(function () use ($target, $recipient) {
                    $locked = $target instanceof PedidoLaboratorioResultado
                        ? PedidoLaboratorioResultado::query()->whereKey($target->id)->lockForUpdate()->first()
                        : PedidoLaboratorio::query()->whereKey($target->id)->lockForUpdate()->first();

                    if (! $locked) {
                        return;
                    }

                    $successPayload = [
                        'enviado_a' => $recipient,
                        'enviado_en' => now('America/Guayaquil'),
                        'envio_estado' => 'sent',
                        'envio_error' => null,
                    ];

                    if ($locked instanceof PedidoLaboratorio) {
                        $successPayload['resultado_enviado_at'] = now('America/Guayaquil');
                    }

                    $locked->forceFill($successPayload)->saveQuietly();
                });
            } catch (Throwable $e) {
                $fresh = $target instanceof PedidoLaboratorioResultado
                    ? PedidoLaboratorioResultado::find($target->id)
                    : PedidoLaboratorio::find($target->id);

                if ($fresh && $fresh->envio_estado !== 'sent') {
                    $fresh->forceFill([
                        'envio_estado' => 'failed',
                        'envio_error' => mb_substr($e->getMessage(), 0, 2000),
                    ])->saveQuietly();
                }

                Log::error('Error enviando resultado de laboratorio (intento '.($fresh?->envio_intentos ?? 1).'): '.$e->getMessage(), [
                    'target_id' => $target->id,
                    'target_type' => get_class($target),
                    'recipient' => $recipient,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        } finally {
            try {
                $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
            } catch (Throwable $releaseErr) {
                Log::warning('Error liberando advisory lock MySQL '.$lockName.': '.$releaseErr->getMessage());
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        DB::transaction(function () use ($exception) {
            $target = null;
            if ($this->resultadoId) {
                $target = PedidoLaboratorioResultado::query()->whereKey($this->resultadoId)->lockForUpdate()->first();
            } elseif ($this->pedidoId) {
                $target = PedidoLaboratorio::query()->whereKey($this->pedidoId)->lockForUpdate()->first();
            }

            if ($target && $target->envio_estado !== 'sent') {
                $target->forceFill([
                    'envio_estado' => 'failed',
                    'envio_error' => mb_substr($exception->getMessage(), 0, 2000),
                ])->saveQuietly();
            }
        });
    }

    private function resolveRecipient(PedidoLaboratorioResultado|PedidoLaboratorio $target): string
    {
        if ($target instanceof PedidoLaboratorioResultado) {
            return trim((string) (
                $target->pedido?->cita?->dependiente?->responsable?->email
                ?: $target->pedido?->paciente?->email
                ?: ''
            ));
        }

        return trim((string) (
            $target->cita?->dependiente?->responsable?->email
            ?: $target->paciente?->email
            ?: ''
        ));
    }

    private function markPermanentFailure(PedidoLaboratorioResultado|PedidoLaboratorio $target, string $message): void
    {
        $target->forceFill([
            'envio_estado' => 'failed',
            'envio_error' => mb_substr($message, 0, 2000),
            'envio_intentos' => (int) ($target->envio_intentos ?? 0) + 1,
        ])->saveQuietly();
    }
}
