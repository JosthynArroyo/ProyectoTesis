<?php

namespace Tests\Feature;

use Carbon\Carbon;
use App\Models\AppointmentSlotHold;
use App\Models\Especialidad;
use App\Models\Horario;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AppointmentSlotHoldTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-05-01 10:00:00', 'America/Guayaquil'));
        $this->withoutMiddleware([
            \App\Http\Middleware\EnsureCaptchaVerified::class,
            \App\Http\Middleware\EnsureChatbotIdentityVerified::class,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_slot_api_hides_active_hold_from_other_users_but_not_for_the_same_token(): void
    {
        [$doctor] = $this->createDoctorWithSchedule('2026-05-11');

        AppointmentSlotHold::create([
            'doctor_id' => $doctor->id,
            'fecha' => '2026-05-11',
            'hora' => '09:00:00',
            'session_id' => 'session-1',
            'token' => 'hold-123',
            'status' => AppointmentSlotHold::STATUS_ACTIVE,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->get(route('api.doctor.slots', ['doctor' => $doctor->id, 'fecha' => '2026-05-11']))
            ->assertOk()
            ->assertJsonFragment(['hora' => '09:00', 'estado' => 'ocupado'])
            ->assertJsonFragment(['hora' => '09:30', 'estado' => 'libre']);

        $this->get(route('api.doctor.slots', ['doctor' => $doctor->id, 'fecha' => '2026-05-11']).'?hold_token=hold-123')
            ->assertOk()
            ->assertJsonFragment(['hora' => '09:00', 'estado' => 'libre']);
    }

    public function test_expire_slot_holds_command_marks_stale_holds_and_frees_the_slot(): void
    {
        [$doctor] = $this->createDoctorWithSchedule('2026-05-11');

        $hold = AppointmentSlotHold::create([
            'doctor_id' => $doctor->id,
            'fecha' => '2026-05-11',
            'hora' => '09:00:00',
            'session_id' => 'session-2',
            'token' => 'hold-expired',
            'status' => AppointmentSlotHold::STATUS_ACTIVE,
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('citas:expirar-slot-holds')
            ->assertSuccessful();

        $this->assertSame(AppointmentSlotHold::STATUS_EXPIRED, $hold->refresh()->status);

        $this->get(route('api.doctor.slots', ['doctor' => $doctor->id, 'fecha' => '2026-05-11']))
            ->assertOk()
            ->assertJsonFragment(['hora' => '09:00', 'estado' => 'libre']);
    }

    public function test_chatbot_booking_marks_the_matching_hold_as_completed(): void
    {
        [$doctor, $especialidad] = $this->createDoctorWithSchedule('2026-05-12');
        $paciente = $this->createUserWithRole('paciente');

        $paciente->update([
            'name' => 'Paciente Hold',
            'email' => 'paciente@example.com',
            'dni' => '1234567890',
            'telefono' => '0991234567',
        ]);

        AppointmentSlotHold::create([
            'doctor_id' => $doctor->id,
            'paciente_id' => $paciente->id,
            'fecha' => '2026-05-12',
            'hora' => '09:00:00',
            'session_id' => 'session-3',
            'token' => 'hold-chatbot',
            'status' => AppointmentSlotHold::STATUS_ACTIVE,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->postJson(route('chatbot.agendar'), [
            'nombre' => $paciente->name,
            'cedula' => $paciente->dni,
            'email' => $paciente->email,
            'telefono' => $paciente->telefono,
            'paciente_id' => $paciente->id,
            'especialidad_id' => $especialidad->id,
            'doctor_id' => $doctor->id,
            'fecha' => '2026-05-12',
            'hora' => '09:00',
            'motivo' => 'Control general',
            'crear_usuario' => false,
            'hold_token' => 'hold-chatbot',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('citas_medicas', [
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'fecha' => '2026-05-12 00:00:00',
            'hora' => '09:00:00',
        ]);

        $this->assertSame(
            AppointmentSlotHold::STATUS_COMPLETED,
            AppointmentSlotHold::query()->where('token', 'hold-chatbot')->firstOrFail()->status
        );
    }

    public function test_patient_web_booking_marks_the_matching_hold_as_completed(): void
    {
        $paciente = $this->createUserWithRole('paciente');
        [$doctor, $especialidad] = $this->createDoctorWithSchedule('2026-05-13');

        AppointmentSlotHold::create([
            'doctor_id' => $doctor->id,
            'paciente_id' => $paciente->id,
            'fecha' => '2026-05-13',
            'hora' => '09:00:00',
            'session_id' => 'session-4',
            'token' => 'hold-web',
            'status' => AppointmentSlotHold::STATUS_ACTIVE,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->actingAs($paciente)
            ->post(route('paciente.crear-cita.store'), [
                'doctor_id' => $doctor->id,
                'especialidad_id' => $especialidad->id,
                'fecha' => '2026-05-13',
                'hora' => '09:00',
                'motivo_consulta' => 'Control general',
                'hold_token' => 'hold-web',
            ])
            ->assertRedirect(route('paciente.citas'));

        $this->assertSame(
            AppointmentSlotHold::STATUS_COMPLETED,
            AppointmentSlotHold::query()->where('token', 'hold-web')->firstOrFail()->status
        );
    }

    protected function createDoctorWithSchedule(string $fecha): array
    {
        $doctor = $this->createUserWithRole('doctor');
        $especialidad = Especialidad::factory()->create(['nombre' => 'Medicina General']);
        $doctor->especialidades()->attach($especialidad->id);

        Horario::create([
            'doctor_id' => $doctor->id,
            'fecha' => $fecha,
            'hora_inicio' => '09:00',
            'hora_fin' => '10:00',
            'intervalo_minutos' => 30,
        ]);

        return [$doctor, $especialidad];
    }

    protected function createUserWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user = User::factory()->create([
            'status' => 'active',
            'suspended_until' => null,
        ]);

        $user->roles()->attach($role->id);

        return $user;
    }
}
