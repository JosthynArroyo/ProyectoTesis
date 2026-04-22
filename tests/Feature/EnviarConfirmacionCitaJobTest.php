<?php

namespace Tests\Feature;

use App\Jobs\EnviarConfirmacionCitaJob;
use App\Mail\CambioEstadoCitaMail;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EnviarConfirmacionCitaJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_envia_correo_de_confirmacion_al_paciente()
    {
        Mail::fake();
        config([
            'services.whatsapp.enabled' => true,
            'services.twilio.sid' => '',
            'services.twilio.auth_token' => '',
            'services.twilio.whatsapp_from' => 'whatsapp:+14155238886',
        ]);

        $paciente = User::factory()->create([
            'telefono' => '0991234567',
        ]);
        $doctor = User::factory()->create([
            'telefono' => '0987654321',
        ]);
        $especialidad = Especialidad::factory()->create();

        $cita = Cita::factory()->create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
        ]);

        (new EnviarConfirmacionCitaJob($cita))->handle();

        Mail::assertQueued(CambioEstadoCitaMail::class, function ($mail) use ($paciente) {
            return $mail->hasTo($paciente->email)
                && $mail->rolReceptor === 'paciente'
                && $mail->evento === 'agendada';
        });

        Mail::assertQueued(CambioEstadoCitaMail::class, function ($mail) use ($doctor) {
            return $mail->hasTo($doctor->email)
                && $mail->rolReceptor === 'doctor'
                && $mail->evento === 'agendada';
        });

        $this->assertDatabaseHas('whatsapp_messages', [
            'cita_id' => $cita->id,
            'user_id' => $paciente->id,
            'rol_receptor' => 'paciente',
            'evento' => 'agendada',
            'estado' => 'fallido',
            'error' => 'twilio_config_incomplete',
        ]);

        $this->assertDatabaseHas('whatsapp_messages', [
            'cita_id' => $cita->id,
            'user_id' => $doctor->id,
            'rol_receptor' => 'doctor',
            'evento' => 'agendada',
            'estado' => 'fallido',
            'error' => 'twilio_config_incomplete',
        ]);

        $this->assertSame(2, WhatsappMessage::query()->where('cita_id', $cita->id)->where('evento', 'agendada')->count());
    }
}
