<?php

namespace App\Jobs;

use App\Mail\CertificadoMedicoMail;
use App\Models\CertificadoMedico;
use App\Services\CertificadoMedicoPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EnviarCertificadoMedicoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public bool $forceResend = false;

    public function __construct(public int $certificadoId, bool $forceResend = false)
    {
        $this->forceResend = $forceResend;
        $this->afterCommit = true;
    }

    public function handle(CertificadoMedicoPdfService $pdfs): void
    {
        $connection = DB::connection();
        $lockName = 'email_cert_'.$this->certificadoId;

        $lockResult = $connection->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lockName]);
        $acquired = isset($lockResult->acquired) ? (int) $lockResult->acquired : null;

        if ($acquired !== 1) {
            if ($acquired === null) {
                Log::warning('Error en GET_LOCK para certificado: '.$lockName);
            }

            return;
        }

        try {
            $claim = DB::transaction(function () {
                $certificado = CertificadoMedico::query()
                    ->with([
                        'paciente',
                        'dependiente.responsable',
                        'doctor',
                        'cita.especialidad',
                        'cita.dependiente.responsable',
                        'cita.paciente',
                    ])
                    ->whereKey($this->certificadoId)
                    ->lockForUpdate()
                    ->first();

                if (! $certificado) {
                    return ['action' => 'abort'];
                }

                $recipient = $this->resolveRecipient($certificado);
                if (! $recipient) {
                    $this->markPermanentFailure($certificado, 'No hay un correo registrado para enviar el certificado.');

                    return ['action' => 'abort'];
                }

                if (! $this->forceResend && $certificado->envio_estado === 'sent' && $certificado->enviado_a === $recipient) {
                    return ['action' => 'already_sent'];
                }

                $certificado->forceFill([
                    'enviado_a' => $recipient,
                    'envio_estado' => 'sending',
                    'envio_error' => null,
                    'envio_intentos' => (int) ($certificado->envio_intentos ?? 0) + 1,
                ])->saveQuietly();

                return [
                    'action' => 'send',
                    'certificado' => $certificado,
                    'recipient' => $recipient,
                ];
            });

            if (! $claim || $claim['action'] !== 'send') {
                return;
            }

            $certificado = $claim['certificado'];
            $recipient = $claim['recipient'];

            try {
                $relativePath = $pdfs->obtenerOGenerar($certificado);

                Mail::to($recipient)->send(new CertificadoMedicoMail(
                    $certificado,
                    $relativePath,
                    $certificado->nombreDescarga()
                ));

                DB::transaction(function () use ($recipient) {
                    $fresh = CertificadoMedico::query()
                        ->whereKey($this->certificadoId)
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
                $fresh = CertificadoMedico::find($this->certificadoId);
                if ($fresh && $fresh->envio_estado !== 'sent') {
                    $fresh->forceFill([
                        'envio_estado' => 'failed',
                        'envio_error' => mb_substr($e->getMessage(), 0, 2000),
                    ])->saveQuietly();
                }

                Log::error('Error enviando certificado medico (intento '.($fresh?->envio_intentos ?? 1).'): '.$e->getMessage(), [
                    'certificado_id' => $this->certificadoId,
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
            $certificado = CertificadoMedico::query()
                ->whereKey($this->certificadoId)
                ->lockForUpdate()
                ->first();

            if ($certificado && $certificado->envio_estado !== 'sent') {
                $certificado->forceFill([
                    'envio_estado' => 'failed',
                    'envio_error' => mb_substr($exception->getMessage(), 0, 2000),
                ])->saveQuietly();
            }
        });
    }

    private function resolveRecipient(CertificadoMedico $certificado): ?string
    {
        $email = $certificado->dependiente?->responsable?->email;
        if (! $email && $certificado->cita?->dependiente_id) {
            $email = $certificado->cita?->dependiente?->responsable?->email;
        }
        if (! $email) {
            $email = $certificado->paciente?->email ?: $certificado->cita?->paciente?->email;
        }

        return trim((string) $email) ?: null;
    }

    private function markPermanentFailure(CertificadoMedico $certificado, string $message): void
    {
        $certificado->forceFill([
            'envio_estado' => 'failed',
            'envio_error' => mb_substr($message, 0, 2000),
            'envio_intentos' => (int) ($certificado->envio_intentos ?? 0) + 1,
        ])->saveQuietly();
    }
}
