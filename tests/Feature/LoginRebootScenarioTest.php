<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LoginRebootScenarioTest extends TestCase
{
    use DatabaseTransactions;

    private function createUserForRole(string $roleName, string $email): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['label' => ucfirst($roleName)]);
        $user = User::factory()->create([
            'email' => $email,
            'password' => bcrypt('password123'),
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    /**
     * Caso 1: Login limpio después de iniciar Laravel.
     */
    public function test_case_1_clean_login(): void
    {
        $admin = $this->createUserForRole('administrador', 'clean_admin@example.com');

        $t0 = microtime(true);
        $response = $this->post(route('login'), [
            'email' => 'clean_admin@example.com',
            'password' => 'password123',
            'remember' => '0',
        ]);
        $duration = microtime(true) - $t0;

        $response->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($admin);
        $this->assertLessThan(5.0, $duration, "El login no debe exceder 5 segundos.");
    }

    /**
     * Caso 2: Sesión anterior inválida tras reinicio.
     */
    public function test_case_2_invalid_previous_session_reboot_login(): void
    {
        $doctor = $this->createUserForRole('doctor', 'reboot_doctor@example.com');

        // Simula request a panel protegido con sesión inválida / expirada
        $redirectResponse = $this->withCookie('laravel_session', 'stale_expired_session_token')
            ->get('/doctor/dashboard');

        $redirectResponse->assertRedirect('/?login=1');

        // El usuario envía login desde el modal/página
        $t0 = microtime(true);
        $loginResponse = $this->post(route('login'), [
            'email' => 'reboot_doctor@example.com',
            'password' => 'password123',
            'remember' => '0',
        ]);
        $duration = microtime(true) - $t0;

        $loginResponse->assertRedirect('/doctor/dashboard');
        $this->assertAuthenticatedAs($doctor);
        $this->assertLessThan(5.0, $duration, "El login tras reinicio no debe exceder 5 segundos.");
    }

    /**
     * Caso 3: Login repetido sin degradación.
     */
    public function test_case_3_repeated_login_logout_no_degradation(): void
    {
        $paciente = $this->createUserForRole('paciente', 'repeated_paciente@example.com');
        $durations = [];

        for ($i = 0; $i < 5; $i++) {
            $t0 = microtime(true);
            $loginResponse = $this->post(route('login'), [
                'email' => 'repeated_paciente@example.com',
                'password' => 'password123',
                'remember' => '0',
            ]);
            $duration = microtime(true) - $t0;
            $durations[] = $duration;

            $loginResponse->assertRedirect('/paciente/dashboard');
            $this->assertAuthenticatedAs($paciente);

            $this->post(route('salir'));
            $this->assertGuest();
        }

        foreach ($durations as $index => $dur) {
            $this->assertLessThan(3.0, $dur, "Iteración {$index} tardó más de 3 segundos.");
        }
    }

    /**
     * Caso 4: Redirección correcta para todos los roles.
     */
    public function test_case_4_role_based_dashboard_redirection(): void
    {
        $roleTargets = [
            'superadmin' => '/superadmin/dashboard',
            'administrador' => '/admin/dashboard',
            'doctor' => '/doctor/dashboard',
            'laboratorio' => '/laboratorio/dashboard',
            'paciente' => '/paciente/dashboard',
        ];

        foreach ($roleTargets as $role => $expectedPath) {
            $user = $this->createUserForRole($role, "role_{$role}@example.com");

            $response = $this->post(route('login'), [
                'email' => $user->email,
                'password' => 'password123',
                'remember' => '0',
            ]);

            $response->assertRedirect($expectedPath);
            $this->assertAuthenticatedAs($user);
            $this->post(route('salir'));
        }
    }
}
