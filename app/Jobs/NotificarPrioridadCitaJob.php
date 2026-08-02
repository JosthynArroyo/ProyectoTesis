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

class NotificarPrioridadCitaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $citaId;

    public string $nivelAnterior;

    public string $nivelNuevo;

    public function __construct(Cita $cita, string $nivelAnterior, string $nivelNuevo)
    {
        $this->citaId = $cita->id;
        $this->nivelAnterior = $nivelAnterior;
        $this->nivelNuevo = $nivelNuevo;
    }

    public function handle(): void
    {
        $cita = Cita::with(['paciente', 'doctor', 'especialidad'])->find($this->citaId);
        if (! $cita) {
            return;
        }

        if ($cita->doctor && $cita->doctor->email) {
            Mail::to($cita->doctor->email)->send(
                new CambioEstadoCitaMail($cita, 'doctor', 'prioridad', 'sistema')
            );
        }

        Log::info(sprintf(
            'Prioridad de cita actualizada. Cita ID: %d | %s -> %s',
            $cita->id,
            $this->nivelAnterior,
            $this->nivelNuevo
        ));
    }
}
