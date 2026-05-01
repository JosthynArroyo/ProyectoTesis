<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthDashboardRedirectPriorityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_redirects_each_role_to_its_expected_dashboard(): void
    {
        $cases = [
            ['roles' => ['superadmin'], 'expected' => '/superadmin/dashboard'],
            ['roles' => ['administrador'], 'expected' => '/admin/dashboard'],
            ['roles' => ['doctor'], 'expected' => '/doctor/dashboard'],
            ['roles' => ['laboratorio'], 'expected' => '/laboratorio/dashboard'],
            ['roles' => ['paciente'], 'expected' => '/paciente/dashboard'],
            ['roles' => ['paciente', 'superadmin'], 'expected' => '/superadmin/dashboard'],
            ['roles' => ['paciente', 'administrador'], 'expected' => '/admin/dashboard'],
            ['roles' => ['paciente', 'doctor'], 'expected' => '/doctor/dashboard'],
            ['roles' => ['paciente', 'laboratorio'], 'expected' => '/laboratorio/dashboard'],
        ];

        foreach ($cases as $index => $case) {
            $user = $this->createUserWithRoles($case['roles'], 'user'.$index.'@example.com');

            $response = $this->post(route('login'), [
                'email' => $user->email,
                'password' => 'password',
                'remember' => '0',
            ]);

            $response->assertRedirect($case['expected']);
            $this->post(route('salir'));
        }
    }

    public function test_superadmin_login_ignores_intended_patient_dashboard_redirect(): void
    {
        $user = $this->createUserWithRoles(['superadmin'], 'superadmin@example.com');

        $this->get('/paciente/dashboard')->assertRedirect(url('/').'?login=1');

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '0',
        ]);

        $response->assertRedirect('/superadmin/dashboard');
    }

    public function test_home_redirect_uses_highest_privilege_role(): void
    {
        $user = $this->createUserWithRoles(['paciente', 'doctor'], 'doctor-paciente@example.com');

        $response = $this->actingAs($user)->get('/home');

        $response->assertRedirect('/doctor/dashboard');
    }

    public function test_guest_middleware_redirects_authenticated_user_to_primary_dashboard(): void
    {
        $user = $this->createUserWithRoles(['paciente', 'doctor'], 'guest-redirect@example.com');

        $response = $this->actingAs($user)->get(route('login'));

        $response->assertRedirect('/doctor/dashboard');
    }

    public function test_role_middleware_protects_each_dashboard_from_other_roles(): void
    {
        $cases = [
            ['roles' => ['paciente'], 'forbidden' => ['/admin/dashboard', '/doctor/dashboard', '/laboratorio/dashboard', '/superadmin/dashboard']],
            ['roles' => ['administrador'], 'forbidden' => ['/paciente/dashboard', '/doctor/dashboard', '/laboratorio/dashboard', '/superadmin/dashboard']],
            ['roles' => ['doctor'], 'forbidden' => ['/paciente/dashboard', '/admin/dashboard', '/laboratorio/dashboard', '/superadmin/dashboard']],
            ['roles' => ['laboratorio'], 'forbidden' => ['/paciente/dashboard', '/admin/dashboard', '/doctor/dashboard', '/superadmin/dashboard']],
        ];

        foreach ($cases as $index => $case) {
            $user = $this->createUserWithRoles($case['roles'], 'protected'.$index.'@example.com');

            foreach ($case['forbidden'] as $uri) {
                $this->actingAs($user)->get($uri)->assertRedirect('/');
            }

            $this->post(route('salir'));
        }
    }

    public function test_superadmin_still_has_access_to_all_protected_dashboards(): void
    {
        $user = $this->createUserWithRoles(['superadmin'], 'full-access-superadmin@example.com');

        foreach ([
            '/superadmin/dashboard',
            '/admin/dashboard',
            '/doctor/dashboard',
            '/laboratorio/dashboard',
            '/paciente/dashboard',
        ] as $uri) {
            $this->actingAs($user)->get($uri)->assertOk();
        }
    }

    private function createUserWithRoles(array $roleNames, string $email): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'password' => 'password',
            'status' => User::STATUS_ACTIVE,
        ]);

        $roleIds = collect($roleNames)
            ->map(fn (string $roleName) => Role::firstOrCreate(['name' => $roleName])->id)
            ->all();

        $user->roles()->sync($roleIds);

        return $user->fresh();
    }
}
