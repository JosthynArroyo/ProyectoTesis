<?php

namespace App\Listeners;

use App\Events\CitaAtendida;
use App\Services\PagoService;
use Illuminate\Support\Facades\Auth;

class GenerarOrdenCobroAlAtenderCita
{
    public function __construct(private readonly PagoService $pagoService) {}

    public function handle(CitaAtendida $event): void
    {
        $cita = $event->cita;
        if (! $cita || ! $cita->paciente_id) {
            return;
        }

        $this->pagoService->crearOrdenParaCitaRealizada($cita, Auth::user());
    }
}
