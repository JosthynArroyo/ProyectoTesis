<?php

namespace App\Jobs;

use App\Mail\ResultadoPedidoLaboratorioMail;
use App\Models\PedidoLaboratorioResultado;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EnviarResultadoPedidoLaboratorioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $resultadoId, public bool $force = false)
    {
    }

    public function handle(): void
    {
        $resultado = PedidoLaboratorioResultado::with(['pedido.cita.dependiente.responsable', 'pedido.cita.doctor', 'pedido.doctor', 'laboratorio'])
            ->find($this->resultadoId);

        if (! $resultado) {
            return;
        }

        $recipient = trim((string) (
            $resultado->pedido?->cita?->dependiente?->responsable?->email
            ?: $resultado->pedido?->paciente?->email
            ?: ''
        ));

        if ($recipient === '') {
            $this->markFailed($resultado, 'No hay un correo registrado para enviar el resultado.');
            return;
        }

        if (! $this->force && $resultado->envio_estado === 'sent' && $resultado->enviado_a === $recipient) {
            return;
        }

        $resultado->forceFill([
            'enviado_a' => $recipient,
            'envio_estado' => 'sending',
            'envio_error' => null,
            'envio_intentos' => (int) ($resultado->envio_intentos ?? 0) + 1,
        ])->saveQuietly();

        try {
            Mail::to($recipient)->send(new ResultadoPedidoLaboratorioMail($resultado->fresh(['pedido.cita.dependiente.responsable', 'pedido.cita.doctor', 'pedido.doctor', 'laboratorio'])));

            $resultado->forceFill([
                'enviado_a' => $recipient,
                'enviado_en' => now('America/Guayaquil'),
                'envio_estado' => 'sent',
                'envio_error' => null,
            ])->saveQuietly();
        } catch (\Throwable $e) {
            $this->markFailed($resultado, $e->getMessage());

            Log::error('Error enviando resultado de laboratorio', [
                'resultado_id' => $resultado->id,
                'recipient' => $recipient,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function markFailed(PedidoLaboratorioResultado $resultado, string $message): void
    {
        $resultado->forceFill([
            'envio_estado' => 'failed',
            'envio_error' => mb_substr($message, 0, 2000),
            'envio_intentos' => (int) ($resultado->envio_intentos ?? 0) + 1,
        ])->saveQuietly();
    }
}
