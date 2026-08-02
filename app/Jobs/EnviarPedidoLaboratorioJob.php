<?php

namespace App\Jobs;

use App\Mail\PedidoLaboratorioMail;
use App\Models\PedidoLaboratorio;
use App\Services\DocumentoCsvService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class EnviarPedidoLaboratorioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $forceResend = false;

    public function __construct(public int $pedidoId, bool $forceResend = false)
    {
        $this->forceResend = $forceResend;
        $this->afterCommit = true;
    }

    public function handle(DocumentoCsvService $csvService): void
    {
        $pedido = PedidoLaboratorio::with([
            'paciente',
            'doctor',
            'cita.especialidad',
            'cita.dependiente.responsable',
            'cita.paciente',
        ])->find($this->pedidoId);

        if (! $pedido) {
            return;
        }

        $recipient = $this->resolveRecipient($pedido);
        if (! $recipient) {
            $this->markFailed($pedido, 'No hay un correo registrado para enviar el pedido de laboratorio.');
            return;
        }

        if (! $this->forceResend && $pedido->envio_estado === 'sent' && $pedido->enviado_a === $recipient) {
            return;
        }

        $pedido->forceFill([
            'enviado_a' => $recipient,
            'envio_estado' => 'sending',
            'envio_error' => null,
            'envio_intentos' => (int) ($pedido->envio_intentos ?? 0) + 1,
        ])->saveQuietly();

        try {
            $docService = app(\App\Services\LaboratoryOrderDocumentService::class);
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

            $pedido->forceFill([
                'enviado_a' => $recipient,
                'enviado_en' => now('America/Guayaquil'),
                'envio_estado' => 'sent',
                'envio_error' => null,
            ])->saveQuietly();
        } catch (\Throwable $e) {
            $this->markFailed($pedido, $e->getMessage());

            Log::error('Error enviando pedido de laboratorio', [
                'pedido_id' => $pedido->id,
                'recipient' => $recipient,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveRecipient(PedidoLaboratorio $pedido): ?string
    {
        $email = $pedido->cita?->dependiente?->responsable?->email;
        if (! $email) {
            $email = $pedido->paciente?->email ?: $pedido->cita?->paciente?->email;
        }

        return trim((string) $email) ?: null;
    }

    private function markFailed(PedidoLaboratorio $pedido, string $message): void
    {
        $pedido->forceFill([
            'envio_estado' => 'failed',
            'envio_error' => mb_substr($message, 0, 2000),
            'envio_intentos' => (int) ($pedido->envio_intentos ?? 0) + 1,
        ])->saveQuietly();
    }
}
