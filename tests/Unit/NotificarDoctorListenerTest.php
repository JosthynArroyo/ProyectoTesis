<?php

namespace Tests\Unit;

use App\Events\CitaAgendada;
use App\Listeners\NotificarDoctorListener;
use App\Models\Cita;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class NotificarDoctorListenerTest extends TestCase
{
    public function test_logs_notification_message(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with('Doctor Dr. House notificado de nueva cita con John Doe');

        $doctor = new User(['name' => 'Dr. House']);
        $paciente = new User(['name' => 'John Doe']);

        $cita = new Cita();
        $cita->setRelation('doctor', $doctor);
        $cita->setRelation('paciente', $paciente);

        $event = new CitaAgendada($cita);

        (new NotificarDoctorListener())->handle($event);
    }
}
