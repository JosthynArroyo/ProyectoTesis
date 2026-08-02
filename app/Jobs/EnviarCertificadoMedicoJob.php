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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EnviarCertificadoMedicoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $forceResend = false;

    public function __construct(public int $certificadoId, bool $forceResend = false)
    {
        $this->forceResend = $forceResend;
        $this->afterCommit = true;
    }

    public function handle(CertificadoMedicoPdfService $pdfs): void
    {
        $certificado = CertificadoMedico::with([
            'paciente',
            'dependiente.responsable',
            'doctor',
            'cita.especialidad',
            'cita.dependiente.responsable',
            'cita.paciente',
        ])->find($this->certificadoId);

        if (! $certificado) {
            return;
        }

        $recipient = $this->resolveRecipient($certificado);
        if (! $recipient) {
            $this->markFailed($certificado, 'No hay un correo registrado para enviar el certificado.');
            return;
        }

        if (! $this->forceResend && $certificado->envio_estado === 'sent' && $certificado->enviado_a === $recipient) {
            return;
        }

        $certificado->forceFill([
            'enviado_a' => $recipient,
            'envio_estado' => 'sending',
            'envio_error' => null,
            'envio_intentos' => (int) ($certificado->envio_intentos ?? 0) + 1,
        ])->saveQuietly();

        try {
            $relativePath = $pdfs->obtenerOGenerar($certificado);

            Mail::to($recipient)->send(new CertificadoMedicoMail(
                $certificado,
                $relativePath,
                $certificado->nombreDescarga()
            ));

            $certificado->forceFill([
                'enviado_a' => $recipient,
                'enviado_en' => now('America/Guayaquil'),
                'envio_estado' => 'sent',
                'envio_error' => null,
            ])->saveQuietly();
        } catch (\Throwable $e) {
            $this->markFailed($certificado, $e->getMessage());

            Log::error('Error enviando certificado medico', [
                'certificado_id' => $certificado->id,
                'recipient' => $recipient,
                'error' => $e->getMessage(),
            ]);
        }
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

    private function markFailed(CertificadoMedico $certificado, string $message): void
    {
        $certificado->forceFill([
            'envio_estado' => 'failed',
            'envio_error' => mb_substr($message, 0, 2000),
            'envio_intentos' => (int) ($certificado->envio_intentos ?? 0) + 1,
        ])->saveQuietly();
    }
}
