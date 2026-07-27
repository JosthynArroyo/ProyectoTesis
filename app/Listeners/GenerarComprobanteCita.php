<?php

namespace App\Listeners;

use App\Events\CitaAgendada;
use App\Services\CitaComprobanteService;

class GenerarComprobanteCita
{
    public function __construct(private readonly CitaComprobanteService $comprobanteService) {}

    public function handle(CitaAgendada $event): void
    {
        $cita = $event->cita;
        if (! $cita || ! $cita->paciente_id) {
            return;
        }

        $this->comprobanteService->asegurarComprobante($cita);
    }
}
