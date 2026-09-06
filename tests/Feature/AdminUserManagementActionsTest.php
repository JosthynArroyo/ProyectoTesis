<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminUserManagementActionsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function seedRoles(): void
    {
        foreach (['administrador', 'superadmin', 'doctor', 'paciente', 'laboratorio'] as $name) {
            Role::firstOrCreate(['name' => $name]);
        }
    }

    private function createRoleUser(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'email' => strtolower($role).'_'.uniqid().'@example.com',
        ], $attributes));
        $roleId = Role::where('name', $role)->value('id');
        if ($roleId) {
            $user->roles()->sync([$roleId]);
        }

        return $user;
    }

    /** 1. Administrador puede suspender un usuario */
    public function test_admin_can_suspend_a_user(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente = $this->createRoleUser('paciente');

        $until = now()->addDays(5)->toDateTimeString();

        $response = $this->actingAs($admin)
            ->patch(route('admin.usuarios.suspend', $paciente), [
                'until' => $until,
                'reason' => 'Suspensión temporal de prueba',
            ]);

        $response->assertRedirect();
        $this->assertNotNull($paciente->fresh()->suspended_until);
    }

    /** 2. Administrador puede reactivar un usuario suspendido */
    public function test_admin_can_activate_suspended_user(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente = $this->createRoleUser('paciente', ['suspended_until' => now()->addDays(3)]);

        $this->actingAs($admin)
            ->patch(route('admin.usuarios.activate', $paciente))
            ->assertRedirect();

        $this->assertNull($paciente->fresh()->suspended_until);
        $this->assertSame(User::STATUS_ACTIVE, $paciente->fresh()->status);
    }

    /** 3. Administrador puede bloquear un usuario */
    public function test_admin_can_block_a_user(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente = $this->createRoleUser('paciente');

        $this->actingAs($admin)
            ->patch(route('admin.usuarios.block', $paciente), ['reason' => 'Infracción grave'])
            ->assertRedirect();

        $this->assertSame(User::STATUS_BLOCKED, $paciente->fresh()->status);
    }

    /** 4. Administrador puede desbloquear (reactivar) un usuario */
    public function test_admin_can_unblock_a_user(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente = $this->createRoleUser('paciente', ['status' => User::STATUS_BLOCKED]);

        $this->actingAs($admin)
            ->patch(route('admin.usuarios.activate', $paciente))
            ->assertRedirect();

        $this->assertSame(User::STATUS_ACTIVE, $paciente->fresh()->status);
    }

    /** 5. Administrador puede desactivar un usuario */
    public function test_admin_can_deactivate_a_user(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente = $this->createRoleUser('paciente');

        $this->actingAs($admin)
            ->patch(route('admin.usuarios.deactivate', $paciente), ['reason' => 'Solicitud personal'])
            ->assertRedirect();

        $this->assertSame(User::STATUS_INACTIVE, $paciente->fresh()->status);
    }

    /** 6. Administrador puede reactivar un usuario desactivado */
    public function test_admin_can_reactivate_deactivated_user(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente = $this->createRoleUser('paciente', ['status' => User::STATUS_INACTIVE]);

        $this->actingAs($admin)
            ->patch(route('admin.usuarios.activate', $paciente))
            ->assertRedirect();

        $this->assertSame(User::STATUS_ACTIVE, $paciente->fresh()->status);
    }

    /** 7. Administrador puede eliminar un usuario común o sin relaciones protegidas */
    public function test_admin_can_delete_unprotected_user(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente = $this->createRoleUser('paciente');

        $this->actingAs($admin)
            ->delete(route('admin.usuarios.destroy', $paciente))
            ->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $paciente->id]);
    }

    /** 8. Un usuario sin rol (como los registros ficticios) puede eliminarse de forma segura por el administrador */
    public function test_admin_can_delete_user_without_role(): void
    {
        $admin = $this->createRoleUser('administrador');
        $userSinRol = User::factory()->create(['name' => 'Ficticio Test', 'email' => 'ficticio@example.com']);

        $this->actingAs($admin)
            ->delete(route('admin.usuarios.destroy', $userSinRol))
            ->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $userSinRol->id]);
    }

    /** 9. El administrador no puede eliminarse a sí mismo */
    public function test_admin_cannot_delete_self(): void
    {
        $admin = $this->createRoleUser('administrador');

        $response = $this->actingAs($admin)->delete(route('admin.usuarios.destroy', $admin));
        $response->assertStatus(302);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    /** 10. Un usuario no autorizado (paciente/doctor) es redirigido o rechazado al intentar realizar acciones de admin */
    public function test_unauthorized_user_cannot_perform_management_actions(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $otroUser = $this->createRoleUser('paciente');

        $response = $this->actingAs($paciente)->delete(route('admin.usuarios.destroy', $otroUser));
        $this->assertTrue(in_array($response->getStatusCode(), [302, 403, 401], true));
    }

    /** 11. Confirmación de que ya no existe la columna can_manage_catalog ni referencias */
    public function test_can_manage_catalog_column_does_not_exist(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'can_manage_catalog'));
    }

    /** 12. Un usuario con información clínica/administrativa no puede ser eliminado */
    public function test_user_with_clinical_records_cannot_be_deleted(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente = $this->createRoleUser('paciente');
        $doctor = $this->createRoleUser('doctor');

        $especialidad = \App\Models\Especialidad::first() ?? \App\Models\Especialidad::factory()->create();

        // Create an appointment for paciente
        \App\Models\Cita::create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => now()->format('Y-m-d'),
            'hora' => '10:00',
            'motivo_consulta' => 'Consulta de prueba',
            'estado' => \App\Models\Cita::ESTADO_CONFIRMADA,
        ]);

        $response = $this->actingAs($admin)
            ->delete(route('admin.usuarios.destroy', $paciente));

        $response->assertRedirect();
        $response->assertSessionHasErrors();
        $this->assertDatabaseHas('users', ['id' => $paciente->id]);
    }

    /** 13. El listado de usuarios muestra únicamente las acciones permitidas según el estado */
    public function test_user_list_shows_correct_actions_per_status(): void
    {
        $admin = $this->createRoleUser('administrador');
        $userActive = $this->createRoleUser('paciente', ['status' => User::STATUS_ACTIVE]);
        $userBlocked = $this->createRoleUser('paciente', ['status' => User::STATUS_BLOCKED]);
        $userInactive = $this->createRoleUser('paciente', ['status' => User::STATUS_INACTIVE]);
        $userSuspended = $this->createRoleUser('paciente', ['status' => User::STATUS_ACTIVE, 'suspended_until' => now()->addDays(2)]);

        $response = $this->actingAs($admin)->get(route('admin.usuarios.index'));
        $response->assertOk();

        // Check active user section has Bloquear & Desactivar & Suspender
        $response->assertSee('data-confirm-form="block-'.$userActive->id.'"', false);
        $response->assertSee('data-confirm-form="deactivate-'.$userActive->id.'"', false);

        // Check blocked user has Desbloquear
        $response->assertSee('data-confirm-form="activate-'.$userBlocked->id.'"', false);
        $response->assertSee('Desbloquear usuario');

        // Check inactive user has Reactivar
        $response->assertSee('data-confirm-form="activate-'.$userInactive->id.'"', false);
        $response->assertSee('Reactivar usuario');

        // Check suspended user has Levantar suspensión
        $response->assertSee('data-confirm-form="unsuspend-'.$userSuspended->id.'"', false);
        $response->assertSee('Levantar suspensión');
    }

    /** 14. El formulario de usuario utiliza clases declarativas hidden sin inline styles y JS usa classList.toggle */
    public function test_admin_user_form_declarative_visibility_and_no_inline_styles(): void
    {
        $admin = $this->createRoleUser('administrador');

        $response = $this->actingAs($admin)->get(route('admin.usuarios.create'));
        $response->assertOk()
            ->assertSee('id="patient-flags-section"', false)
            ->assertSee('id="doctor-only-esp"', false)
            ->assertSee('id="doctor-only-precio"', false)
            ->assertDontSee('style="display:none"', false);

        $bladePath = resource_path('views/admin/users/form.blade.php');
        $this->assertFileExists($bladePath);
        $blade = file_get_contents($bladePath);
        $this->assertStringNotContainsString('style=', $blade);

        $jsPath = resource_path('js/admin/users/form.js');
        $this->assertFileExists($jsPath);
        $js = file_get_contents($jsPath);
        $this->assertStringContainsString("esp.classList.toggle('hidden', !isDoctor)", $js);
        $this->assertStringContainsString("precio.classList.toggle('hidden', !(isDoctor||isLab))", $js);
        $this->assertStringContainsString("flags.classList.toggle('hidden', !isPaciente)", $js);
        $this->assertStringNotContainsString('style.display', $js);
    }
}
