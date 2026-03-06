<?php

namespace App\Listeners;

use App\Events\CitaAgendada;
use App\Services\PagoService;
use Illuminate\Support\Facades\Auth;

class CrearPagoPendiente
{
    public function __construct(private readonly PagoService $pagoService) {}

    public function handle(CitaAgendada $event): void
    {
        $cita = $event->cita;
        if (!$cita || !$cita->paciente_id) {
            return;
        }

        $this->pagoService->crearParaCita($cita, Auth::user());
    }
}
