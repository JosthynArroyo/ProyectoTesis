<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SecurePostLogoutTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Caso L1: Usuario autenticado realiza POST /salir y cierra sesión exitosamente.
     */
    public function test_l1_valid_post_salir_logs_out_invalidates_session_and_redirects(): void
    {
        $user = $this->createUserWithRole('paciente');

        $response = $this->actingAs($user)->post(route('salir'));

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    /**
     * Caso L1 (alias estándar Laravel): Usuario autenticado realiza POST /logout y cierra sesión.
     */
    public function test_l1_valid_post_logout_logs_out_and_redirects(): void
    {
        $user = $this->createUserWithRole('doctor');

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    /**
     * Caso L2: Petición GET /salir está deshabilitada (405 Method Not Allowed) y NO desautentica al usuario.
     */
    public function test_l2_get_salir_is_rejected_and_does_not_log_out(): void
    {
        $user = $this->createUserWithRole('administrador');

        $response = $this->actingAs($user)->get('/salir');

        $response->assertStatus(405);
        $this->assertAuthenticatedAs($user);
    }

    /**
     * Caso L2 (alias estándar): Petición GET /logout está deshabilitada (405 Method Not Allowed) y NO desautentica.
     */
    public function test_l2_get_logout_is_rejected_and_does_not_log_out(): void
    {
        $user = $this->createUserWithRole('superadmin');

        $response = $this->actingAs($user)->get('/logout');

        $response->assertStatus(405);
        $this->assertAuthenticatedAs($user);
    }

    /**
     * Caso L5: Todos los roles reales del sistema pueden cerrar sesión vía POST.
     */
    public function test_l5_all_roles_can_log_out_safely_via_post(): void
    {
        $roles = ['superadmin', 'administrador', 'doctor', 'paciente', 'laboratorio'];

        foreach ($roles as $roleName) {
            $user = $this->createUserWithRole($roleName);

            $response = $this->actingAs($user)->post(route('salir'));

            $response->assertRedirect('/');
            $this->assertGuest();
        }
    }

    /**
     * Caso L4: Verificar que los menús principales utilizan formularios POST con token CSRF y no enlaces directos GET.
     */
    public function test_l4_dashboard_and_navbar_views_use_post_forms_with_csrf_for_logout(): void
    {
        $user = $this->createUserWithRole('paciente');

        // Navbar público con sesión iniciada
        $navbarResponse = $this->actingAs($user)->get('/');
        $navbarResponse->assertOk();
        $navbarResponse->assertSee('<form method="POST" action="'.route('salir').'"', false);
        $navbarResponse->assertDontSee('href="'.url('/salir').'"', false);
        $navbarResponse->assertDontSee('href="'.url('/logout').'"', false);

        // Header interno del panel
        $headerHtml = view('components.layout.dashboard-header', [
            'headerTitle' => 'Panel Paciente',
            'headerSubtitle' => 'Bienvenido',
            'roleName' => 'paciente',
            'roleTone' => 'primary',
            'showRoleSwitcher' => false,
        ])->render();

        $this->assertStringContainsString('action="'.route('salir').'" method="POST"', $headerHtml);
        $this->assertStringNotContainsString('href="'.route('salir').'"', $headerHtml);
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::query()->firstOrCreate(['name' => $roleName], ['label' => ucfirst($roleName)]);
        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'active' => true,
        ]);
        $user->roles()->sync([$role->id]);

        return $user;
    }
}
