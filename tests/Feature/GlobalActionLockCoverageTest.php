<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Pago;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class GlobalActionLockCoverageTest extends TestCase
{
    use DatabaseTransactions;

    public function test_core_role_views_render_global_overlay_and_contextual_action_lock_copy(): void
    {
        $patient = $this->userWithRole('paciente');
        $doctor = $this->userWithRole('doctor');
        $admin = $this->userWithRole('administrador');
        $superadmin = $this->userWithRole('superadmin');
        $lockedSuperadmin = $this->userWithRole('superadmin');
        $lockedSuperadmin->forceFill(['must_change_password' => true])->save();

        $especialidad = Especialidad::factory()->create();
        $cita = Cita::factory()->create([
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'estado' => Cita::ESTADO_CONFIRMADA,
            'fecha' => now()->toDateString(),
            'hora' => '09:00:00',
            'motivo_consulta' => 'Control programado',
        ]);

        $pago = Pago::query()->create([
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'monto' => 25.00,
            'moneda' => 'USD',
            'metodo_pago' => Pago::METODO_EFECTIVO,
            'estado' => Pago::ESTADO_PENDIENTE,
        ]);

        $this->actingAs($patient)
            ->get(route('paciente.crear-cita'))
            ->assertOk()
            ->assertSee('id="global-action-lock"', false);

        $this->actingAs($doctor)
            ->get(route('doctor.citas.soap', $cita))
            ->assertOk()
            ->assertSee('id="global-action-lock"', false);

        $this->actingAs($admin)
            ->get(route('admin.pagos.show', $pago))
            ->assertOk()
            ->assertSee('id="global-action-lock"', false)
            ->assertSee('data-action-lock', false)
            ->assertSee('Aprobar pago', false)
            ->assertSee('Rechazar pago', false)
            ->assertSee('Anular pago', false);

        $this->actingAs($lockedSuperadmin)
            ->get(route('auth.must-change-password'))
            ->assertOk()
            ->assertSee('id="global-action-lock"', false)
            ->assertSee('data-action-lock-title="Actualizando contraseña..."', false)
            ->assertSee('data-action-lock-description="Por favor, espera mientras guardamos tu nueva contraseña."', false)
            ->assertSee('data-loading-text="Actualizando contraseña..."', false)
            ->assertSee('data-action-lock-ignore', false)
            ->assertSee('Cerrar sesión', false)
            ->assertDontSee('Cerrando sesión...', false);

        $this->actingAs($superadmin)
            ->get(route('superadmin.dashboard'))
            ->assertOk()
            ->assertSee('id="global-action-lock"', false);
    }

    private function userWithRole(string $roleName): User
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
