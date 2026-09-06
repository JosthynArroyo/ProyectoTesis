<?php

namespace Tests\Feature;

use App\Models\AppointmentSlotHold;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Horario;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AppointmentSlotHoldRateLimitTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('slot-holds');
        Carbon::setTestNow(Carbon::parse('2026-05-01 10:00:00', 'America/Guayaquil'));
        $this->withoutMiddleware([
            \App\Http\Middleware\EnsureCaptchaVerified::class,
            \App\Http\Middleware\EnsureChatbotIdentityVerified::class,
        ]);
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('slot-holds');
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * CASO A — USO LEGÍTIMO: Un cliente dentro del límite puede crear un hold válido.
     */
    public function test_caso_a_uso_legitimo_crea_hold_correctamente(): void
    {
        [$doctor] = $this->createDoctorWithSchedule('2026-05-15');
        $paciente = $this->createUserWithRole('paciente');

        $response = $this->actingAs($paciente)
            ->postJson(route('api.slot-holds.store'), [
                'doctor_id' => $doctor->id,
                'fecha' => '2026-05-15',
                'hora' => '09:00',
            ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['ok', 'hold_token', 'expires_at', 'status']);

        $token = $response->json('hold_token');
        $this->assertNotNull($token);

        $this->assertDatabaseHas('appointment_slot_holds', [
            'doctor_id' => $doctor->id,
            'fecha' => '2026-05-15',
            'hora' => '09:00:00',
            'token' => $token,
            'status' => AppointmentSlotHold::STATUS_ACTIVE,
        ]);
    }

    /**
     * CASO B — LÍMITE EXCEDIDO: Realizar más de 10 solicitudes consecutivas genera HTTP 429 y no crea registros excedentes.
     */
    public function test_caso_b_limite_excedido_devuelve_429_y_no_crea_hold(): void
    {
        [$doctor] = $this->createDoctorWithSchedule('2026-05-15');
        $paciente = $this->createUserWithRole('paciente');

        // Enviar 10 solicitudes legítimas (hasta el límite de 10 por minuto)
        for ($i = 0; $i < 10; $i++) {
            $resp = $this->actingAs($paciente)
                ->postJson(route('api.slot-holds.store'), [
                    'doctor_id' => $doctor->id,
                    'fecha' => '2026-05-15',
                    'hora' => '09:00',
                    'token' => 'token-same-client',
                ]);
            $resp->assertOk();
        }

        // La solicitud número 11 debe ser bloqueada por rate limit con HTTP 429
        $rateLimitedResponse = $this->actingAs($paciente)
            ->postJson(route('api.slot-holds.store'), [
                'doctor_id' => $doctor->id,
                'fecha' => '2026-05-15',
                'hora' => '09:30',
                'token' => 'token-excedente',
            ]);

        $rateLimitedResponse->assertStatus(429)
            ->assertJsonPath('ok', false);

        // Verificar que la solicitud rechazada con 429 no creó un nuevo registro en DB
        $this->assertDatabaseMissing('appointment_slot_holds', [
            'token' => 'token-excedente',
        ]);
    }

    /**
     * CASO C — NO AFECTAR OTROS CLIENTES: El bloqueo por abuso en cliente A no afecta al cliente B.
     */
    public function test_caso_c_abuso_de_cliente_a_no_bloquea_a_cliente_b(): void
    {
        [$doctor] = $this->createDoctorWithSchedule('2026-05-15');
        $pacienteA = $this->createUserWithRole('paciente');
        $pacienteB = $this->createUserWithRole('paciente');

        // Agotar límite para cliente A
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($pacienteA)
                ->postJson(route('api.slot-holds.store'), [
                    'doctor_id' => $doctor->id,
                    'fecha' => '2026-05-15',
                    'hora' => '09:00',
                    'token' => 'token-client-a',
                ]);
        }

        // Cliente A recibe 429
        $this->actingAs($pacienteA)
            ->postJson(route('api.slot-holds.store'), [
                'doctor_id' => $doctor->id,
                'fecha' => '2026-05-15',
                'hora' => '09:00',
                'token' => 'token-client-a',
            ])->assertStatus(429);

        // Cliente B debe poder reservar normalmente
        $responseB = $this->actingAs($pacienteB)
            ->postJson(route('api.slot-holds.store'), [
                'doctor_id' => $doctor->id,
                'fecha' => '2026-05-15',
                'hora' => '09:30',
                'token' => 'token-client-b',
            ]);

        $responseB->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('appointment_slot_holds', [
            'token' => 'token-client-b',
            'status' => AppointmentSlotHold::STATUS_ACTIVE,
        ]);
    }

    /**
     * CASO D — SOLICITUD INVÁLIDA: Payload inválido es rechazado por validación y no crea hold.
     */
    public function test_caso_d_solicitud_invalida_es_rechazada_y_no_crea_hold(): void
    {
        $initialCount = AppointmentSlotHold::count();

        $response = $this->postJson(route('api.slot-holds.store'), [
            'doctor_id' => 999999, // Doctor inexistente
            'fecha' => 'fecha-invalida',
            'hora' => 'hora-invalida',
        ]);

        $response->assertStatus(422);
        $this->assertSame($initialCount, AppointmentSlotHold::count());
    }

    /**
     * CASO E — SLOT NO DISPONIBLE: Intentar reservar un horario ocupado por una cita u otro hold es rechazado.
     */
    public function test_caso_e_slot_no_disponible_es_rechazado_segun_reglas_existentes(): void
    {
        [$doctor, $especialidad] = $this->createDoctorWithSchedule('2026-05-15');
        $paciente = $this->createUserWithRole('paciente');

        // Crear una cita confirmada en 09:00
        Cita::create([
            'doctor_id' => $doctor->id,
            'paciente_id' => $paciente->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => '2026-05-15 00:00:00',
            'hora' => '09:00:00',
            'estado' => Cita::ESTADO_CONFIRMADA,
            'activo' => true,
            'motivo_consulta' => 'Consulta existente',
        ]);

        $response = $this->postJson(route('api.slot-holds.store'), [
            'doctor_id' => $doctor->id,
            'fecha' => '2026-05-15',
            'hora' => '09:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    /**
     * CASO F — RATE LIMIT NO ALTERA DATOS: Una solicitud 429 no modifica holds legítimos ni citas.
     */
    public function test_caso_f_rate_limit_no_altera_datos_existentes(): void
    {
        [$doctor] = $this->createDoctorWithSchedule('2026-05-15');
        $paciente = $this->createUserWithRole('paciente');

        // Hold legítimo existente
        $legitHold = AppointmentSlotHold::create([
            'doctor_id' => $doctor->id,
            'fecha' => '2026-05-15',
            'hora' => '09:00:00',
            'token' => 'legit-hold-token',
            'status' => AppointmentSlotHold::STATUS_ACTIVE,
            'expires_at' => now()->addMinutes(10),
        ]);

        // Agotar rate limit para este usuario
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($paciente)->postJson(route('api.slot-holds.store'), [
                'doctor_id' => $doctor->id,
                'fecha' => '2026-05-15',
                'hora' => '09:30',
                'token' => 'temp-token',
            ]);
        }

        // Intento 11 que recibe 429 intentando modificar el hold legítimo
        $resp429 = $this->actingAs($paciente)->postJson(route('api.slot-holds.store'), [
            'doctor_id' => $doctor->id,
            'fecha' => '2026-05-15',
            'hora' => '09:00',
            'token' => 'legit-hold-token',
        ]);

        $resp429->assertStatus(429);

        // El hold legítimo no debe haber cambiado su fecha, hora o estado
        $refreshed = $legitHold->fresh();
        $this->assertSame('09:00:00', $refreshed->hora);
        $this->assertSame(AppointmentSlotHold::STATUS_ACTIVE, $refreshed->status);
    }

    /**
     * CASO G — EXPIRACIÓN: Un hold expirado deja de bloquear la disponibilidad según scopeActive.
     */
    public function test_caso_g_hold_expirado_no_bloquea_disponibilidad(): void
    {
        [$doctor] = $this->createDoctorWithSchedule('2026-05-15');

        AppointmentSlotHold::create([
            'doctor_id' => $doctor->id,
            'fecha' => '2026-05-15',
            'hora' => '09:00:00',
            'token' => 'stale-hold-token',
            'status' => AppointmentSlotHold::STATUS_ACTIVE,
            'expires_at' => now()->subMinute(), // Ya expirado
        ]);

        // La API de slots debe mostrar el horario como libre
        $this->get(route('api.doctor.slots', ['doctor' => $doctor->id, 'fecha' => '2026-05-15']))
            ->assertOk()
            ->assertJsonFragment(['hora' => '09:00', 'estado' => 'libre']);

        // Y otro cliente puede adquirirlo
        $response = $this->postJson(route('api.slot-holds.store'), [
            'doctor_id' => $doctor->id,
            'fecha' => '2026-05-15',
            'hora' => '09:00',
            'token' => 'new-fresh-token',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true);
    }

    /**
     * CASO H — CONCURRENCIA: Dos peticiones para el mismo slot no permiten reserva duplicada.
     */
    public function test_caso_h_concurrencia_rechaza_adquisicion_duplicada(): void
    {
        [$doctor] = $this->createDoctorWithSchedule('2026-05-15');

        // Primer cliente adquiere el slot
        $resp1 = $this->postJson(route('api.slot-holds.store'), [
            'doctor_id' => $doctor->id,
            'fecha' => '2026-05-15',
            'hora' => '09:00',
            'token' => 'token-first',
        ]);
        $resp1->assertOk();

        // Segundo cliente intenta adquirir el mismo slot con diferente token
        $resp2 = $this->postJson(route('api.slot-holds.store'), [
            'doctor_id' => $doctor->id,
            'fecha' => '2026-05-15',
            'hora' => '09:00',
            'token' => 'token-second',
        ]);

        $resp2->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    protected function createDoctorWithSchedule(string $fecha): array
    {
        $doctor = $this->createUserWithRole('doctor');
        $especialidad = Especialidad::factory()->create(['nombre' => 'Medicina General '.uniqid()]);
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
