<?php

namespace App\Jobs;

use App\Mail\PedidoLaboratorioMail;
use App\Models\PedidoLaboratorio;
use App\Services\DocumentoCsvService;
use App\Services\LaboratoryOrderDocumentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

class EnviarPedidoLaboratorioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public bool $forceResend = false;

    public function __construct(public int $pedidoId, bool $forceResend = false)
    {
        $this->forceResend = $forceResend;
        $this->afterCommit = true;
    }

    public function handle(DocumentoCsvService $csvService): void
    {
        $connection = DB::connection();
        $lockName = 'email_lab_'.$this->pedidoId;

        $lockResult = $connection->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lockName]);
        $acquired = isset($lockResult->acquired) ? (int) $lockResult->acquired : null;

        if ($acquired !== 1) {
            if ($acquired === null) {
                Log::warning('Error en GET_LOCK para pedido de laboratorio: '.$lockName);
            }

            return;
        }

        try {
            $claim = DB::transaction(function () {
                $pedido = PedidoLaboratorio::query()
                    ->with([
                        'paciente',
                        'doctor',
                        'cita.especialidad',
                        'cita.dependiente.responsable',
                        'cita.paciente',
                    ])
                    ->whereKey($this->pedidoId)
                    ->lockForUpdate()
                    ->first();

                if (! $pedido) {
                    return ['action' => 'abort'];
                }

                $recipient = $this->resolveRecipient($pedido);
                if (! $recipient) {
                    $this->markPermanentFailure($pedido, 'No hay un correo registrado para enviar el pedido de laboratorio.');

                    return ['action' => 'abort'];
                }

                if (! $this->forceResend && $pedido->envio_estado === 'sent' && $pedido->enviado_a === $recipient) {
                    return ['action' => 'already_sent'];
                }

                $pedido->forceFill([
                    'enviado_a' => $recipient,
                    'envio_estado' => 'sending',
                    'envio_error' => null,
                    'envio_intentos' => (int) ($pedido->envio_intentos ?? 0) + 1,
                ])->saveQuietly();

                return [
                    'action' => 'send',
                    'pedido' => $pedido,
                    'recipient' => $recipient,
                ];
            });

            if (! $claim || $claim['action'] !== 'send') {
                return;
            }

            $pedido = $claim['pedido'];
            $recipient = $claim['recipient'];

            try {
                $docService = app(LaboratoryOrderDocumentService::class);
                $diskName = $docService->resolveDisk($pedido->pdf_disk);
                $relativePath = $pedido->pdf_path;
                if (! $relativePath || ! Storage::disk($diskName)->exists($relativePath)) {
                    $relativePath = null;
                }

                if (! $pedido->csv) {
                    $pedido->forceFill(['csv' => $csvService->ensureCsv($pedido)])->saveQuietly();
                }

                Mail::to($recipient)->send(new PedidoLaboratorioMail(
                    $pedido->fresh(['paciente', 'doctor', 'cita.dependiente.responsable', 'cita.paciente']),
                    $relativePath ?? '',
                    $relativePath ? basename($relativePath) : ''
                ));

                DB::transaction(function () use ($recipient) {
                    $fresh = PedidoLaboratorio::query()
                        ->whereKey($this->pedidoId)
                        ->lockForUpdate()
                        ->first();

                    if ($fresh) {
                        $fresh->forceFill([
                            'enviado_a' => $recipient,
                            'enviado_en' => now('America/Guayaquil'),
                            'envio_estado' => 'sent',
                            'envio_error' => null,
                        ])->saveQuietly();
                    }
                });
            } catch (Throwable $e) {
                $fresh = PedidoLaboratorio::find($this->pedidoId);
                if ($fresh && $fresh->envio_estado !== 'sent') {
                    $fresh->forceFill([
                        'envio_estado' => 'failed',
                        'envio_error' => mb_substr($e->getMessage(), 0, 2000),
                    ])->saveQuietly();
                }

                Log::error('Error enviando pedido de laboratorio (intento '.($fresh?->envio_intentos ?? 1).'): '.$e->getMessage(), [
                    'pedido_id' => $this->pedidoId,
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
            $pedido = PedidoLaboratorio::query()
                ->whereKey($this->pedidoId)
                ->lockForUpdate()
                ->first();

            if ($pedido && $pedido->envio_estado !== 'sent') {
                $pedido->forceFill([
                    'envio_estado' => 'failed',
                    'envio_error' => mb_substr($exception->getMessage(), 0, 2000),
                ])->saveQuietly();
            }
        });
    }

    private function resolveRecipient(PedidoLaboratorio $pedido): ?string
    {
        $email = $pedido->cita?->dependiente?->responsable?->email;
        if (! $email) {
            $email = $pedido->paciente?->email ?: $pedido->cita?->paciente?->email;
        }

        return trim((string) $email) ?: null;
    }

    private function markPermanentFailure(PedidoLaboratorio $pedido, string $message): void
    {
        $pedido->forceFill([
            'envio_estado' => 'failed',
            'envio_error' => mb_substr($message, 0, 2000),
            'envio_intentos' => (int) ($pedido->envio_intentos ?? 0) + 1,
        ])->saveQuietly();
    }
}
