<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EnviarRecordatoriosWhatsappCitasCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-03-08 12:00:00', 'America/Guayaquil'));

        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.reminder_previous_day_hour' => 12,
            'services.whatsapp.reminder_window_minutes' => 10,
            'services.twilio.sid' => '',
            'services.twilio.auth_token' => '',
            'services.twilio.whatsapp_from' => 'whatsapp:+14155238886',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_envia_recordatorios_de_whatsapp_al_mediodia_del_dia_anterior(): void
    {
        $paciente = User::factory()->create([
            'telefono' => '0991234567',
            'status' => 'active',
            'suspended_until' => null,
        ]);
        $doctor = User::factory()->create([
            'telefono' => '0987654321',
            'status' => 'active',
            'suspended_until' => null,
        ]);
        $especialidad = Especialidad::factory()->create();

        $cita = Cita::factory()->create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => '2026-03-09',
            'hora' => '14:00:00',
            'estado' => Cita::ESTADO_CONFIRMADA,
            'activo' => true,
        ]);

        $this->artisan('citas:recordatorio-whatsapp')
            ->assertSuccessful();

        $this->assertDatabaseHas('whatsapp_messages', [
            'cita_id' => $cita->id,
            'user_id' => $paciente->id,
            'rol_receptor' => 'paciente',
            'evento' => 'recordatorio_6h',
            'estado' => 'fallido',
            'error' => 'twilio_config_incomplete',
        ]);

        $this->assertDatabaseHas('whatsapp_messages', [
            'cita_id' => $cita->id,
            'user_id' => $doctor->id,
            'rol_receptor' => 'doctor',
            'evento' => 'recordatorio_6h',
            'estado' => 'fallido',
            'error' => 'twilio_config_incomplete',
        ]);

        $this->assertSame(2, WhatsappMessage::query()->where('cita_id', $cita->id)->where('evento', 'recordatorio_6h')->count());
    }
}
