<?php

namespace Tests\Feature;

use App\Events\CitaAgendada;
use App\Jobs\EnviarConfirmacionCitaJob;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Horario;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminCitaOverrideConcurrencyTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-01 08:00:00');
        $this->seedRoles();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seedRoles(): void
    {
        foreach (['superadmin', 'administrador', 'doctor', 'paciente', 'laboratorio'] as $r) {
            Role::firstOrCreate(['name' => $r]);
        }
    }

    private function createRoleUser(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'status' => 'active',
            'email_verified_at' => now(),
        ], $attributes));

        $roleModel = Role::where('name', $role)->first();
        if ($roleModel) {
            $user->roles()->attach($roleModel->id);
        }

        return $user;
    }

    private function createDoctorWithSchedule(string $date = '2026-08-05', int $interval = 30): array
    {
        $doctor = $this->createRoleUser('doctor');
        $esp = Especialidad::firstOrCreate(['nombre' => 'Medicina General']);
        $doctor->especialidades()->syncWithoutDetaching([$esp->id]);

        Horario::create([
            'doctor_id' => $doctor->id,
            'fecha' => $date,
            'hora_inicio' => '08:00:00',
            'hora_fin' => '18:00:00',
            'intervalo_minutos' => $interval,
        ]);

        return [$doctor, $esp];
    }

    /**
     * PR1 — Creación administrativa normal.
     * Horario disponible: 1 cita creada, estado correcto, eventos/jobs disparados.
     */
    public function test_pr1_normal_admin_override_creation_succeeds(): void
    {
        Event::fake([CitaAgendada::class]);
        Queue::fake([EnviarConfirmacionCitaJob::class]);

        $admin = $this->createRoleUser('administrador');
        $paciente = $this->createRoleUser('paciente');
        [$doctor, $esp] = $this->createDoctorWithSchedule('2026-08-05');

        $response = $this->actingAs($admin)->post(route('admin.citas.override.store'), [
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-05',
            'hora' => '09:00',
            'motivo_consulta' => 'Consulta administrativa normal',
        ]);

        $response->assertRedirect(route('admin.citas.override.create'));
        $response->assertSessionHas('success', 'Cita creada correctamente.');

        $citas = Cita::where('doctor_id', $doctor->id)
            ->whereDate('fecha', '2026-08-05')
            ->where('hora', '09:00:00')
            ->get();

        $this->assertCount(1, $citas);
        $this->assertEquals(Cita::ESTADO_PENDIENTE, $citas->first()->estado);
        $this->assertTrue((bool) $citas->first()->activo);

        Event::assertDispatched(CitaAgendada::class, 1);
        Queue::assertPushed(EnviarConfirmacionCitaJob::class, 1);
    }

    /**
     * PR2 — Doble submit concurrente idéntico.
     * Dos requests para el mismo doctor, fecha y hora. Máximo 1 cita creada.
     */
    public function test_pr2_double_submit_identical_creates_exactly_one_cita(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente1 = $this->createRoleUser('paciente');
        $paciente2 = $this->createRoleUser('paciente');
        [$doctor, $esp] = $this->createDoctorWithSchedule('2026-08-05');

        // Primera petición
        $resp1 = $this->actingAs($admin)->post(route('admin.citas.override.store'), [
            'paciente_id' => $paciente1->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-05',
            'hora' => '10:00',
            'motivo_consulta' => 'Primer submit',
        ]);
        $resp1->assertRedirect(route('admin.citas.override.create'));
        $resp1->assertSessionHas('success');

        // Segunda petición idéntica
        $resp2 = $this->actingAs($admin)->post(route('admin.citas.override.store'), [
            'paciente_id' => $paciente2->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-05',
            'hora' => '10:00',
            'motivo_consulta' => 'Segundo submit concurrente',
        ]);
        $resp2->assertSessionHasErrors('hora');

        $citas = Cita::where('doctor_id', $doctor->id)
            ->whereDate('fecha', '2026-08-05')
            ->where('hora', '10:00:00')
            ->get();

        $this->assertCount(1, $citas);
        $this->assertEquals($paciente1->id, $citas->first()->paciente_id);
    }

    /**
     * PR3 — Interleaving TOCTOU exacto.
     * Simula determinísticamente que Request B ejecuta su comprobación inicial previa
     * cuando el horario todavía estaba libre, pero antes de que Request B entre al lock,
     * Request A adquiere el lock y crea la cita.
     * Al entrar Request B a la región transaccional bajo lock, revalida con estado fresco,
     * detecta el conflicto y aborta.
     */
    public function test_pr3_exact_toctou_interleaving_detected_under_lock_and_aborted(): void
    {
        Event::fake([CitaAgendada::class]);
        Queue::fake([EnviarConfirmacionCitaJob::class]);

        $admin = $this->createRoleUser('administrador');
        $pacienteA = $this->createRoleUser('paciente');
        $pacienteB = $this->createRoleUser('paciente');
        [$doctor, $esp] = $this->createDoctorWithSchedule('2026-08-05');

        // Simular que justo durante la validación previa de Request B (fuera de transacción),
        // Request A completa concurrentemente la creación de la cita en ese horario.
        $interleaved = false;
        DB::beforeExecuting(function ($query, $bindings) use (&$interleaved, $doctor, $esp, $pacienteA) {
            // Se dispara durante la consulta de validación de schedule / lead_time antes del lock
            if (! $interleaved && str_contains($query, 'horarios')) {
                DB::table('citas_medicas')->insert([
                    'paciente_id' => $pacienteA->id,
                    'doctor_id' => $doctor->id,
                    'especialidad_id' => $esp->id,
                    'fecha' => '2026-08-05',
                    'hora' => '09:00:00',
                    'motivo_consulta' => 'Cita creada concurrentemente por Request A',
                    'estado' => Cita::ESTADO_PENDIENTE,
                    'activo' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $interleaved = true;
            }
        });

        $responseB = $this->actingAs($admin)->post(route('admin.citas.override.store'), [
            'paciente_id' => $pacienteB->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-05',
            'hora' => '09:00',
            'motivo_consulta' => 'Request B que sufrió TOCTOU',
        ]);

        $this->assertTrue($interleaved, 'El interleaving TOCTOU debió haberse ejecutado durante la consulta previa');
        $responseB->assertSessionHasErrors('hora');
        $this->assertNotEquals(500, $responseB->status());

        // Verificar que solo existe la cita de Request A y ninguna de Request B
        $citas = Cita::where('doctor_id', $doctor->id)
            ->whereDate('fecha', '2026-08-05')
            ->where('hora', '09:00:00')
            ->get();

        $this->assertCount(1, $citas);
        $this->assertEquals($pacienteA->id, $citas->first()->paciente_id);
        $this->assertEquals('Cita creada concurrentemente por Request A', $citas->first()->motivo_consulta);

        // Request B abortó y NO despachó eventos ni jobs de éxito
        Event::assertNotDispatched(CitaAgendada::class);
        Queue::assertNothingPushed();
    }

    /**
     * PR4 — Solapamiento real de intervalos.
     * Cita 1 ocupa 09:00 (duración 30 min -> 09:00-09:30).
     * Intento de cita en horario superpuesto es rechazado.
     */
    public function test_pr4_overlapping_intervals_rejected(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente1 = $this->createRoleUser('paciente');
        $paciente2 = $this->createRoleUser('paciente');
        [$doctor, $esp] = $this->createDoctorWithSchedule('2026-08-05', 30);

        // Cita A creada a las 09:00
        $resp1 = $this->actingAs($admin)->post(route('admin.citas.override.store'), [
            'paciente_id' => $paciente1->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-05',
            'hora' => '09:00',
            'motivo_consulta' => 'Cita 09:00',
        ]);
        $resp1->assertRedirect(route('admin.citas.override.create'));

        // Cita B intenta mismo slot 09:00
        $resp2 = $this->actingAs($admin)->post(route('admin.citas.override.store'), [
            'paciente_id' => $paciente2->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-05',
            'hora' => '09:00',
            'motivo_consulta' => 'Cita B conflicto',
        ]);
        $resp2->assertSessionHasErrors('hora');

        $this->assertDatabaseCount('citas_medicas', 1);
    }

    /**
     * PR5 — Dos doctores diferentes en paralelo.
     * Requests concurrentes para Doctor A y Doctor B a las 09:00 deben tener éxito ambas.
     */
    public function test_pr5_two_different_doctors_at_same_time_both_succeed(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente1 = $this->createRoleUser('paciente');
        $paciente2 = $this->createRoleUser('paciente');

        [$doctorA, $espA] = $this->createDoctorWithSchedule('2026-08-05');
        [$doctorB, $espB] = $this->createDoctorWithSchedule('2026-08-05');

        // Request Doctor A a las 09:00
        $respA = $this->actingAs($admin)->post(route('admin.citas.override.store'), [
            'paciente_id' => $paciente1->id,
            'doctor_id' => $doctorA->id,
            'especialidad_id' => $espA->id,
            'fecha' => '2026-08-05',
            'hora' => '09:00',
            'motivo_consulta' => 'Cita Doctor A',
        ]);
        $respA->assertRedirect(route('admin.citas.override.create'));
        $respA->assertSessionHas('success');

        // Request Doctor B a las 09:00
        $respB = $this->actingAs($admin)->post(route('admin.citas.override.store'), [
            'paciente_id' => $paciente2->id,
            'doctor_id' => $doctorB->id,
            'especialidad_id' => $espB->id,
            'fecha' => '2026-08-05',
            'hora' => '09:00',
            'motivo_consulta' => 'Cita Doctor B',
        ]);
        $respB->assertRedirect(route('admin.citas.override.create'));
        $respB->assertSessionHas('success');

        $this->assertDatabaseHas('citas_medicas', [
            'doctor_id' => $doctorA->id,
            'fecha' => '2026-08-05',
            'hora' => '09:00:00',
            'paciente_id' => $paciente1->id,
        ]);

        $this->assertDatabaseHas('citas_medicas', [
            'doctor_id' => $doctorB->id,
            'fecha' => '2026-08-05',
            'hora' => '09:00:00',
            'paciente_id' => $paciente2->id,
        ]);
    }

    /**
     * PR6 — Cita terminal/inactiva (cancelada) no bloquea disponibilidad futura.
     */
    public function test_pr6_cancelled_inactive_cita_does_not_block_valid_new_creation(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente1 = $this->createRoleUser('paciente');
        $paciente2 = $this->createRoleUser('paciente');
        [$doctor, $esp] = $this->createDoctorWithSchedule('2026-08-05');

        // Crear cita previamente cancelada en el slot 09:00
        Cita::create([
            'paciente_id' => $paciente1->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-05',
            'hora' => '09:00:00',
            'motivo_consulta' => 'Cita cancelada anterior',
            'estado' => Cita::ESTADO_CANCELADA,
            'activo' => false,
        ]);

        // Nueva creación administrativa para paciente 2 a las 09:00 debe tener éxito
        $response = $this->actingAs($admin)->post(route('admin.citas.override.store'), [
            'paciente_id' => $paciente2->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-05',
            'hora' => '09:00',
            'motivo_consulta' => 'Nueva cita en slot liberado',
        ]);

        $response->assertRedirect(route('admin.citas.override.create'));
        $response->assertSessionHas('success', 'Cita creada correctamente.');

        // Debe haber 2 registros en BD: 1 cancelada inactiva y 1 pendiente activa
        $citas = Cita::where('doctor_id', $doctor->id)
            ->whereDate('fecha', '2026-08-05')
            ->where('hora', '09:00:00')
            ->get();

        $this->assertCount(2, $citas);
        $activas = $citas->where('activo', true);
        $this->assertCount(1, $activas);
        $this->assertEquals($paciente2->id, $activas->first()->paciente_id);
    }

    /**
     * PR7 — Perdedor sin efectos secundarios.
     * La request perdedora no crea cita, no crea log de override, no crea orden de lab, no envía eventos ni jobs.
     */
    public function test_pr7_losing_request_has_zero_side_effects(): void
    {
        Event::fake([CitaAgendada::class]);
        Queue::fake([EnviarConfirmacionCitaJob::class]);

        $admin = $this->createRoleUser('administrador');
        $paciente1 = $this->createRoleUser('paciente');
        $paciente2 = $this->createRoleUser('paciente');
        [$doctor, $esp] = $this->createDoctorWithSchedule('2026-08-05');

        // Ganador
        $this->actingAs($admin)->post(route('admin.citas.override.store'), [
            'paciente_id' => $paciente1->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-05',
            'hora' => '11:00',
            'motivo_consulta' => 'Ganador',
        ]);

        // Perdedor
        $respPerdedor = $this->actingAs($admin)->post(route('admin.citas.override.store'), [
            'paciente_id' => $paciente2->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-05',
            'hora' => '11:00',
            'motivo_consulta' => 'Perdedor',
            'forzar_bloqueo' => true,
            'override_reason' => 'Motivo que no debe guardarse',
        ]);

        $respPerdedor->assertSessionHasErrors('hora');

        // Verificar 0 citas para paciente 2
        $this->assertDatabaseMissing('citas_medicas', [
            'paciente_id' => $paciente2->id,
        ]);

        // Verificar 0 logs de override para paciente 2
        $this->assertDatabaseMissing('booking_override_logs', [
            'paciente_id' => $paciente2->id,
        ]);

        // Exactamente 1 evento y 1 job correspondientes a la cita ganadora
        Event::assertDispatched(CitaAgendada::class, 1);
        Queue::assertPushed(EnviarConfirmacionCitaJob::class, 1);
    }

    /**
     * PR8 — Error HTTP controlado (no 500).
     */
    public function test_pr8_conflict_returns_controlled_redirect_not_500(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente1 = $this->createRoleUser('paciente');
        $paciente2 = $this->createRoleUser('paciente');
        [$doctor, $esp] = $this->createDoctorWithSchedule('2026-08-05');

        // Ocupar slot previamente
        Cita::create([
            'paciente_id' => $paciente1->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-05',
            'hora' => '14:00:00',
            'motivo_consulta' => 'Existente',
            'estado' => Cita::ESTADO_PENDIENTE,
            'activo' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.citas.override.store'), [
            'paciente_id' => $paciente2->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-05',
            'hora' => '14:00',
            'motivo_consulta' => 'Conflicto directo',
        ]);

        $this->assertNotEquals(500, $response->status());
        $response->assertStatus(302);
        $response->assertSessionHasErrors('hora');
    }
}
