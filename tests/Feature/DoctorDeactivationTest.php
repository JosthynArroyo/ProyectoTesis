<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Horario;
use App\Models\NotaSoap;
use App\Models\Receta;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class DoctorDeactivationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-04-18 09:00:00', 'America/Guayaquil'));

        foreach (['administrador', 'doctor', 'paciente', 'laboratorio'] as $role) {
            Role::query()->firstOrCreate(['name' => $role]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_active_doctor_can_login_and_inactive_doctor_cannot_login(): void
    {
        $doctor = $this->userWithRole('doctor', [
            'email' => 'doctor.login@clinic.test',
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->post('/login', [
            'email' => $doctor->email,
            'password' => 'password',
            'remember' => '0',
        ])->assertRedirect('doctor/dashboard');

        $this->assertAuthenticatedAs($doctor);

        Auth::logout();
        $this->flushSession();

        $doctor->update([
            'status' => User::STATUS_INACTIVE,
            'deactivation_reason' => 'Salida de la clinica',
        ]);

        $this->post('/login', [
            'email' => $doctor->email,
            'password' => 'password',
            'remember' => '0',
        ])
            ->assertRedirect('/?login=1')
            ->assertSessionHasErrorsIn('login', ['email']);

        $this->assertGuest();
        $this->assertFalse($doctor->fresh()->active);
    }

    public function test_deactivated_doctor_is_hidden_from_new_bookings_but_historical_records_remain(): void
    {
        $admin = $this->userWithRole('administrador', ['email' => 'admin.doctors@clinic.test']);
        $patient = $this->userWithRole('paciente', ['email' => 'patient.doctors@clinic.test']);
        $doctor = $this->userWithRole('doctor', [
            'email' => 'doctor.history@clinic.test',
            'precio_consulta' => 30,
            'moneda' => 'USD',
        ]);
        $specialty = Especialidad::factory()->create(['nombre' => 'Medicina General']);
        $doctor->especialidades()->attach($specialty->id);

        Horario::query()->create([
            'doctor_id' => $doctor->id,
            'fecha' => '2026-04-20',
            'hora_inicio' => '10:00',
            'hora_fin' => '11:00',
            'intervalo_minutos' => 30,
        ]);

        $historicalCita = Cita::factory()->create([
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $specialty->id,
            'fecha' => '2026-04-10',
            'hora' => '08:00:00',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);

        $receta = Receta::query()->create([
            'cita_id' => $historicalCita->id,
            'diagnostico' => 'Control general',
            'medicamentos' => 'Paracetamol',
            'indicaciones' => 'Reposo',
        ]);

        $nota = NotaSoap::query()->create([
            'cita_id' => $historicalCita->id,
            'estado' => NotaSoap::ESTADO_FIRMADA,
            'signed_by' => $doctor->id,
            'subjetivo_motivo' => 'Control',
            'examen_fisico' => 'Sin hallazgos agudos',
            'assessment' => 'Paciente estable',
            'plan_general' => 'Seguimiento',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.usuarios.deactivate', $doctor), [
                'reason' => 'Salida de la clinica',
            ])
            ->assertRedirect();

        $doctor->refresh();

        $this->assertSame(User::STATUS_INACTIVE, $doctor->status);
        $this->assertFalse($doctor->active);
        $this->assertDatabaseHas('users', ['id' => $doctor->id]);
        $this->assertDatabaseHas('doctor_especialidad', [
            'user_id' => $doctor->id,
            'especialidad_id' => $specialty->id,
        ]);
        $this->assertDatabaseHas('citas_medicas', ['id' => $historicalCita->id, 'doctor_id' => $doctor->id]);
        $this->assertDatabaseHas('recetas', ['id' => $receta->id, 'cita_id' => $historicalCita->id]);
        $this->assertDatabaseHas('notas_soap', ['id' => $nota->id, 'signed_by' => $doctor->id]);

        $this->get(route('especialidades.doctores', $specialty))
            ->assertOk()
            ->assertJsonCount(0);

        $this->actingAs($patient)
            ->from(route('paciente.crear-cita'))
            ->post(route('paciente.crear-cita.store'), [
                'doctor_id' => $doctor->id,
                'especialidad_id' => $specialty->id,
                'fecha' => '2026-04-20',
                'hora' => '10:00',
                'motivo_consulta' => 'Consulta general',
            ])
            ->assertRedirect(route('paciente.crear-cita'))
            ->assertSessionHasErrors('doctor_id');

        $this->assertDatabaseMissing('citas_medicas', [
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'fecha' => '2026-04-20',
            'hora' => '10:00:00',
        ]);

        $this->get(route('paciente.citas'))
            ->assertOk()
            ->assertSeeText($doctor->name);

        Auth::logout();
        $this->flushSession();

        $this->actingAs($admin)
            ->patch(route('admin.usuarios.activate', $doctor))
            ->assertRedirect();

        $this->assertTrue($doctor->fresh()->isActive());

        $this->get(route('especialidades.doctores', $specialty))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $doctor->id,
                'name' => $doctor->name,
            ]);
    }

    public function test_delete_action_on_doctor_converts_to_logical_deactivation(): void
    {
        $admin = $this->userWithRole('administrador');
        $doctor = $this->userWithRole('doctor');
        $specialty = Especialidad::factory()->create();
        $doctor->especialidades()->attach($specialty->id);

        $this->actingAs($admin)
            ->delete(route('admin.usuarios.destroy', $doctor))
            ->assertRedirect();

        $doctor->refresh();

        $this->assertSame(User::STATUS_INACTIVE, $doctor->status);
        $this->assertFalse($doctor->active);
        $this->assertDatabaseHas('users', ['id' => $doctor->id]);
        $this->assertDatabaseHas('doctor_especialidad', [
            'user_id' => $doctor->id,
            'especialidad_id' => $specialty->id,
        ]);
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'status' => User::STATUS_ACTIVE,
            'active' => true,
            'suspended_until' => null,
        ], $attributes));

        $user->roles()->sync([Role::query()->where('name', $role)->value('id')]);

        return $user;
    }
}
