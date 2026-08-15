<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Pago;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminPagoAdministrativeStateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pago_detail_only_renders_valid_actions_while_reviewable_and_locks_after_approval(): void
    {
        $admin = $this->createUserWithRole('administrador');
        $patient = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'active' => true,
        ]);
        $doctor = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'active' => true,
        ]);
        $especialidad = Especialidad::factory()->create();
        $cita = Cita::factory()->create([
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
        ]);
        $pago = Pago::query()->create([
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'monto' => 25.00,
            'moneda' => 'USD',
            'metodo_pago' => Pago::METODO_EFECTIVO,
            'estado' => Pago::ESTADO_PENDIENTE,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.pagos.show', $pago))
            ->assertOk()
            ->assertSee('Aprobar pago')
            ->assertSee('Rechazar pago')
            ->assertSee('Anular pago')
            ->assertSee('data-action-lock', false);

        $this->actingAs($admin)
            ->from(route('admin.pagos.show', $pago))
            ->post(route('admin.pagos.aprobar', $pago), [
                'observacion_admin' => 'Validado en panel.',
            ])
            ->assertRedirect(route('admin.pagos.show', $pago))
            ->assertSessionHas('success', 'Pago aprobado correctamente.');

        $pago->refresh();
        $this->assertSame(Pago::ESTADO_PAGADO, $pago->estado);

        $this->actingAs($admin)
            ->get(route('admin.pagos.show', $pago))
            ->assertOk()
            ->assertSee($pago->administrativeStateMessage())
            ->assertDontSee('Aprobar pago')
            ->assertDontSee('Rechazar pago')
            ->assertDontSee('Anular pago');

        $this->actingAs($admin)
            ->from(route('admin.pagos.show', $pago))
            ->post(route('admin.pagos.rechazar', $pago), [
                'observacion_admin' => 'Intento manual.',
            ])
            ->assertRedirect(route('admin.pagos.show', $pago))
            ->assertSessionHasErrors([
                'error' => 'Este pago ya fue aprobado y no admite más acciones administrativas.',
            ]);

        $this->assertSame(Pago::ESTADO_PAGADO, $pago->fresh()->estado);
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
