<?php

namespace App\Jobs;

use App\Mail\CambioEstadoCitaMail;
use App\Models\Cita;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EnviarConfirmacionCitaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var \App\Models\Cita */
    protected $cita;

    public function __construct(Cita $cita)
    {
        $this->cita = $cita;
    }

    /**
     * Envía correos separados a paciente y doctor para "cita agendada".
     */
    public function handle(): void
    {
        $cita = Cita::with(['paciente', 'doctor', 'especialidad', 'dependiente.responsable'])->findOrFail($this->cita->id);

        $recipientPaciente = trim((string) (
            $cita->dependiente?->responsable?->email
            ?: $cita->paciente?->email
            ?: ''
        ));

        // Paciente / Representante (autor del agendamiento)
        if ($recipientPaciente !== '') {
            Mail::to($recipientPaciente)
                ->send(new CambioEstadoCitaMail($cita, 'paciente', 'agendada', 'paciente'));
        }

        // Doctor (notificación de agenda)
        if ($cita->doctor && $cita->doctor->email) {
            Mail::to($cita->doctor->email)
                ->send(new CambioEstadoCitaMail($cita, 'doctor', 'agendada', 'paciente'));
        }

        Log::info(sprintf(
            'Notificaciones de cita AGENDADA enviadas. Receptor Paciente/Rep: <%s> | Doctor: %s <%s> | Cita ID: %d',
            $recipientPaciente ?: '-',
            $cita->doctor?->name ?? '-', $cita->doctor?->email ?? '-',
            $cita->id
        ));
    }
}
