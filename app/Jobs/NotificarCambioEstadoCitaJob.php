<?php

namespace App\Jobs;

use App\Models\Cita;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\CambioEstadoCitaMail;

class NotificarCambioEstadoCitaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var \App\Models\Cita
     */
    public $cita;

    /**
     * @var string Evento del cambio: reagendada | cancelada | aceptada
     */
    public $evento;

    /**
     * @var string Quién originó el cambio: paciente | doctor | sistema
     */
    public $quien;

    /**
     * Crear un nuevo job de notificación.
     * @param \App\Models\Cita $cita
     * @param string $evento  'reagendada'|'cancelada'|'aceptada'
     * @param string $quien   'paciente'|'doctor'|'sistema'
     */
    public function __construct(Cita $cita, string $evento, string $quien = 'sistema')
    {
        $this->cita = $cita;
        $this->evento = $evento;
        $this->quien = $quien;
    }

    public function handle(): void
    {
        $cita = Cita::with(['paciente','doctor','especialidad'])->findOrFail($this->cita->id);

        // Definir destinatarios (siempre ambas partes)
        $paraPaciente = $cita->paciente?->email;
        $paraDoctor   = $cita->doctor?->email;

        if (!$paraPaciente && !$paraDoctor) {
            Log::warning("NotificarCambioEstadoCitaJob: Cita {$cita->id} sin correos de paciente/doctor.");
            return;
        }

        // Enviar a paciente
        if ($paraPaciente) {
            Mail::to($paraPaciente)->queue(
                new CambioEstadoCitaMail($cita, 'paciente', $this->evento, $this->quien)
            );
        }

        // Enviar a doctor
        if ($paraDoctor) {
            Mail::to($paraDoctor)->queue(
                new CambioEstadoCitaMail($cita, 'doctor', $this->evento, $this->quien)
            );
        }

        Log::info("NotificarCambioEstadoCitaJob: enviados correos de evento '{$this->evento}' (quien={$this->quien}) para Cita {$cita->id}.");
    }
}
