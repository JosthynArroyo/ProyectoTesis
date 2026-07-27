<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\CitaEvento;
use App\Models\Especialidad;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCambiosCitasRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cambios_citas_index_shows_empty_state_without_events(): void
    {
        $admin = $this->createUserWithRole('administrador');

        $this->actingAs($admin)
            ->get(route('admin.cambios-citas.index'))
            ->assertOk()
            ->assertSee('No hay eventos con estos filtros.')
            ->assertDontSee('ErrorException');
    }

    public function test_admin_cambios_citas_index_lists_related_cita_data(): void
    {
        $admin = $this->createUserWithRole('administrador');
        $patient = User::factory()->create([
            'name' => 'Paciente Auditoria',
            'status' => User::STATUS_ACTIVE,
            'active' => true,
        ]);
        $doctor = User::factory()->create([
            'name' => 'Doctor Auditoria',
            'status' => User::STATUS_ACTIVE,
            'active' => true,
        ]);
        $especialidad = Especialidad::factory()->create();
        $cita = Cita::factory()->create([
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => '2026-07-19',
            'hora' => '09:30:00',
        ]);

        CitaEvento::query()->create([
            'cita_id' => $cita->id,
            'user_id' => $admin->id,
            'tipo' => 'reprogramada',
            'de_estado' => 'pendiente',
            'a_estado' => 'confirmada',
            'de_fecha' => '2026-07-18',
            'a_fecha' => '2026-07-19',
            'de_hora' => '09:00:00',
            'a_hora' => '09:30:00',
            'valor_anterior' => null,
            'valor_nuevo' => null,
            'comentario' => 'Cambio de horario confirmado por administracion.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.cambios-citas.index'))
            ->assertOk()
            ->assertSee('Paciente Auditoria')
            ->assertSee('Doctor Auditoria')
            ->assertSee('Cambio de horario confirmado por administracion.')
            ->assertDontSee('ErrorException');
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::query()->firstOrCreate(['name' => $roleName]);

        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'active' => true,
            'suspended_until' => null,
        ]);

        $user->roles()->sync([$role->id]);

        return $user;
    }
}
