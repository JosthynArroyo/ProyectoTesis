<?php

namespace Tests\Feature;

use App\Events\CitaAgendada;
use App\Mail\CuentaCreadaDesdeChat;
use App\Models\Especialidad;
use App\Models\Horario;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ChatbotRegistrationCredentialsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            \App\Http\Middleware\EnsureCaptchaVerified::class,
            \App\Http\Middleware\EnsureChatbotIdentityVerified::class,
        ]);
    }

    public function test_chatbot_registers_user_immediately_when_requested(): void
    {
        Mail::fake();
        Role::query()->firstOrCreate(['name' => 'paciente']);

        $payload = [
            'nombre' => 'Paciente Registro',
            'cedula' => '1234512345',
            'email' => 'paciente.registro@example.com',
        ];

        $this->postJson(route('chatbot.registrarUsuario'), $payload)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('usuario_creado', true)
            ->assertJsonPath('credenciales_enviadas', true)
            ->assertJsonPath('paciente.email', $payload['email']);

        $paciente = User::query()->where('email', $payload['email'])->firstOrFail();

        $this->assertSame($payload['cedula'], $paciente->dni);
        $this->assertTrue($paciente->hasRole('paciente'));
        $this->assertTrue(Hash::check($payload['cedula'], $paciente->password));

        Mail::assertSent(CuentaCreadaDesdeChat::class, function (CuentaCreadaDesdeChat $mail) use ($paciente, $payload) {
            return $mail->user->is($paciente)
                && $mail->passwordPlano === $payload['cedula'];
        });
    }

    public function test_chatbot_agendar_reuses_previously_registered_user(): void
    {
        Event::fake([CitaAgendada::class]);
        Mail::fake();
        Queue::fake();

        [$doctor, $especialidad, $fecha] = $this->createDoctorWithAvailability();

        $registro = [
            'nombre' => 'Paciente Reutilizado',
            'cedula' => '1010101010',
            'email' => 'paciente.reutilizado@example.com',
        ];

        $this->postJson(route('chatbot.registrarUsuario'), $registro)
            ->assertOk()
            ->assertJsonPath('usuario_creado', true);

        $payload = [
            'nombre' => $registro['nombre'],
            'cedula' => $registro['cedula'],
            'email' => $registro['email'],
            'telefono' => '0991231234',
            'especialidad_id' => $especialidad->id,
            'doctor_id' => $doctor->id,
            'fecha' => $fecha,
            'hora' => '10:00',
            'motivo' => 'Chequeo general',
            'crear_usuario' => true,
        ];

        $this->postJson(route('chatbot.agendar'), $payload)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('usuario_creado', false)
            ->assertJsonPath('credenciales_enviadas', false);

        $paciente = User::query()->where('email', $registro['email'])->firstOrFail();

        $this->assertSame('0991231234', $paciente->fresh()->telefono);
        Mail::assertSent(CuentaCreadaDesdeChat::class, 1);
    }

    public function test_chatbot_creates_requested_access_with_email_and_cedula_as_initial_password(): void
    {
        Event::fake([CitaAgendada::class]);
        Mail::fake();
        Queue::fake();

        [$doctor, $especialidad, $fecha] = $this->createDoctorWithAvailability();

        $payload = [
            'nombre' => 'Paciente Nuevo',
            'cedula' => '1234567890',
            'email' => 'paciente.nuevo@example.com',
            'telefono' => '0991234567',
            'especialidad_id' => $especialidad->id,
            'doctor_id' => $doctor->id,
            'fecha' => $fecha,
            'hora' => '10:00',
            'motivo' => 'Chequeo general',
            'crear_usuario' => true,
        ];

        $this->postJson(route('chatbot.agendar'), $payload)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('usuario_creado', true)
            ->assertJsonPath('credenciales_enviadas', true);

        $paciente = User::query()->where('email', $payload['email'])->firstOrFail();

        $this->assertSame($payload['cedula'], $paciente->dni);
        $this->assertTrue($paciente->hasRole('paciente'));
        $this->assertTrue(Hash::check($payload['cedula'], $paciente->password));

        Mail::assertSent(CuentaCreadaDesdeChat::class, function (CuentaCreadaDesdeChat $mail) use ($paciente, $payload) {
            return $mail->user->is($paciente)
                && $mail->passwordPlano === $payload['cedula'];
        });
    }

    public function test_chatbot_keeps_a_non_predictable_password_when_user_only_wants_to_schedule(): void
    {
        Event::fake([CitaAgendada::class]);
        Mail::fake();
        Queue::fake();

        [$doctor, $especialidad, $fecha] = $this->createDoctorWithAvailability();

        $payload = [
            'nombre' => 'Paciente Agenda',
            'cedula' => '0987654321',
            'email' => 'paciente.agenda@example.com',
            'telefono' => '0997654321',
            'especialidad_id' => $especialidad->id,
            'doctor_id' => $doctor->id,
            'fecha' => $fecha,
            'hora' => '10:00',
            'motivo' => 'Consulta preventiva',
            'crear_usuario' => false,
        ];

        $this->postJson(route('chatbot.agendar'), $payload)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('usuario_creado', true)
            ->assertJsonPath('credenciales_enviadas', false);

        $paciente = User::query()->where('email', $payload['email'])->firstOrFail();

        $this->assertFalse(Hash::check($payload['cedula'], $paciente->password));
        Mail::assertNotSent(CuentaCreadaDesdeChat::class);
    }

    public function test_chatbot_guest_schedule_flow_does_not_validate_email_against_an_unidentified_profile(): void
    {
        Event::fake([CitaAgendada::class]);
        Mail::fake();
        Queue::fake();

        [$doctor, $especialidad, $fecha] = $this->createDoctorWithAvailability();

        $existingPatientRole = Role::query()->firstOrCreate(['name' => 'paciente']);
        $existingPatient = User::factory()->create([
            'name' => 'Paciente Existente',
            'email' => 'existente@example.com',
            'dni' => '1111222233',
            'status' => User::STATUS_ACTIVE,
        ]);
        $existingPatient->roles()->sync([$existingPatientRole->id]);

        $payload = [
            'nombre' => 'Visitante Nuevo',
            'cedula' => '3333444455',
            'email' => 'existente@example.com',
            'telefono' => '0997654321',
            'especialidad_id' => $especialidad->id,
            'doctor_id' => $doctor->id,
            'fecha' => $fecha,
            'hora' => '10:00',
            'motivo' => 'Consulta preventiva',
            'crear_usuario' => false,
            'paciente_id' => null,
        ];

        $this->postJson(route('chatbot.agendar'), $payload)
            ->assertStatus(409)
            ->assertJsonPath('ok', false);
    }

    public function test_chatbot_schedule_validates_email_when_patient_was_identified_previously(): void
    {
        Event::fake([CitaAgendada::class]);
        Mail::fake();
        Queue::fake();

        [$doctor, $especialidad, $fecha] = $this->createDoctorWithAvailability();

        $patientRole = Role::query()->firstOrCreate(['name' => 'paciente']);
        $patient = User::factory()->create([
            'name' => 'Paciente Identificado',
            'email' => 'paciente.identificado@example.com',
            'dni' => '1231231234',
            'telefono' => '0991231234',
            'status' => User::STATUS_ACTIVE,
        ]);
        $patient->roles()->sync([$patientRole->id]);

        $payload = [
            'nombre' => $patient->name,
            'cedula' => $patient->dni,
            'email' => 'otro.correo@example.com',
            'telefono' => $patient->telefono,
            'paciente_id' => $patient->id,
            'especialidad_id' => $especialidad->id,
            'doctor_id' => $doctor->id,
            'fecha' => $fecha,
            'hora' => '10:00',
            'motivo' => 'Chequeo general',
            'crear_usuario' => false,
        ];

        $this->postJson(route('chatbot.agendar'), $payload)
            ->assertStatus(403)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'El correo no coincide con tu perfil.');
    }

    public function test_chatbot_requires_a_real_reason_when_scheduling(): void
    {
        Event::fake([CitaAgendada::class]);
        Mail::fake();
        Queue::fake();

        [$doctor, $especialidad, $fecha] = $this->createDoctorWithAvailability();

        $payload = [
            'nombre' => 'Paciente Sin Motivo',
            'cedula' => '2233445566',
            'email' => 'paciente.sin.motivo@example.com',
            'telefono' => '0992233445',
            'especialidad_id' => $especialidad->id,
            'doctor_id' => $doctor->id,
            'fecha' => $fecha,
            'hora' => '10:00',
            'motivo' => 'ninguno',
            'crear_usuario' => false,
        ];

        $this->postJson(route('chatbot.agendar'), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['motivo']);

        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
        Mail::assertNotSent(CuentaCreadaDesdeChat::class);
    }

    public function test_chatbot_reports_when_credentials_email_cannot_be_sent(): void
    {
        Event::fake([CitaAgendada::class]);
        Queue::fake();

        [$doctor, $especialidad, $fecha] = $this->createDoctorWithAvailability();

        $payload = [
            'nombre' => 'Paciente Sin Correo',
            'cedula' => '1122334455',
            'email' => 'paciente.sin.correo@example.com',
            'telefono' => '0991122334',
            'especialidad_id' => $especialidad->id,
            'doctor_id' => $doctor->id,
            'fecha' => $fecha,
            'hora' => '10:00',
            'motivo' => 'Consulta general',
            'crear_usuario' => true,
        ];

        Mail::shouldReceive('to')
            ->once()
            ->with($payload['email'])
            ->andReturnSelf();
        Mail::shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('SMTP down'));

        $this->postJson(route('chatbot.agendar'), $payload)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('usuario_creado', true)
            ->assertJsonPath('credenciales_enviadas', false)
            ->assertJsonPath('credenciales_error', 'La cita fue registrada, pero no pudimos enviar el correo con tus credenciales.');

        $paciente = User::query()->where('email', $payload['email'])->firstOrFail();

        $this->assertTrue(Hash::check($payload['cedula'], $paciente->password));
    }

    public function test_chatbot_reports_when_immediate_registration_email_cannot_be_sent(): void
    {
        Role::query()->firstOrCreate(['name' => 'paciente']);

        $payload = [
            'nombre' => 'Paciente Registro Sin Correo',
            'cedula' => '5566778899',
            'email' => 'paciente.registro.sin.correo@example.com',
        ];

        Mail::shouldReceive('to')
            ->once()
            ->with($payload['email'])
            ->andReturnSelf();
        Mail::shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('SMTP down'));

        $this->postJson(route('chatbot.registrarUsuario'), $payload)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('usuario_creado', true)
            ->assertJsonPath('credenciales_enviadas', false)
            ->assertJsonPath('credenciales_error', 'Registramos tu usuario, pero no pudimos enviar el correo con tus credenciales.');

        $paciente = User::query()->where('email', $payload['email'])->firstOrFail();
        $this->assertTrue(Hash::check($payload['cedula'], $paciente->password));
    }

    private function createDoctorWithAvailability(): array
    {
        $doctorRole = Role::query()->firstOrCreate(['name' => 'doctor']);
        Role::query()->firstOrCreate(['name' => 'paciente']);

        $especialidad = Especialidad::query()->create([
            'nombre' => 'Medicina General',
            'descripcion' => 'Consulta general',
            'icono' => 'ri-stethoscope-line',
            'activo' => true,
            'orden' => 1,
        ]);

        $doctor = User::factory()->create([
            'name' => 'Doctor Chatbot',
            'email' => 'doctor.chatbot@example.com',
            'status' => User::STATUS_ACTIVE,
            'active' => true,
            'suspended_until' => null,
            'precio_consulta' => 25.00,
            'moneda' => 'USD',
        ]);
        $doctor->roles()->sync([$doctorRole->id]);
        $doctor->especialidades()->sync([$especialidad->id]);

        $fecha = Carbon::now(config('app.timezone', 'America/Guayaquil'))
            ->addDays(2)
            ->toDateString();

        Horario::query()->create([
            'doctor_id' => $doctor->id,
            'fecha' => $fecha,
            'hora_inicio' => '09:00',
            'hora_fin' => '12:00',
            'intervalo_minutos' => 30,
        ]);

        return [$doctor, $especialidad, $fecha];
    }
}
