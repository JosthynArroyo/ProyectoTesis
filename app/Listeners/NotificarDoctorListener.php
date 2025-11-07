<?php

namespace App\Listeners;

use App\Events\CitaAgendada;
use Illuminate\Support\Facades\Log;

class NotificarDoctorListener
{
    public function handle(CitaAgendada $event)
    {
        Log::info("Doctor {$event->cita->doctor->name} notificado de nueva cita con {$event->cita->paciente->name}");
    }
}
