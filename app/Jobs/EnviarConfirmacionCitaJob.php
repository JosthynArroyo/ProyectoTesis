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
        $cita = Cita::with(['paciente', 'doctor', 'especialidad'])->findOrFail($this->cita->id);

        // Paciente (autor del agendamiento)
        if ($cita->paciente && $cita->paciente->email) {
            Mail::to($cita->paciente->email)
                ->queue(new CambioEstadoCitaMail($cita, 'paciente', 'agendada', 'paciente'));
        }

        // Doctor (notificación de agenda)
        if ($cita->doctor && $cita->doctor->email) {
            Mail::to($cita->doctor->email)
                ->queue(new CambioEstadoCitaMail($cita, 'doctor', 'agendada', 'paciente'));
        }

        Log::info(sprintf(
            'Notificaciones de cita AGENDADA enviadas. Paciente: %s <%s> | Doctor: %s <%s> | Cita ID: %d',
            $cita->paciente?->name ?? '-', $cita->paciente?->email ?? '-',
            $cita->doctor?->name ?? '-', $cita->doctor?->email ?? '-',
            $cita->id
        ));
    }
}
