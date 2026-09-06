<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\ApplicationModeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class SessionModeIsolationTest extends TestCase
{
    use DatabaseTransactions;

    private function createDemoUsers(): void
    {
        $roles = [
            'doctor' => ['email' => 'doctor.medicina@demo-clinigest.test', 'name' => 'Dr. Fernando Alvarado'],
            'administrador' => ['email' => 'admin@demo-clinigest.test', 'name' => 'Dra. Valeria Mendoza'],
            'paciente' => ['email' => 'paciente@demo-clinigest.test', 'name' => 'Javier Espinoza'],
        ];

        foreach ($roles as $roleName => $data) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => \Illuminate\Support\Facades\Hash::make('Demo1234!'),
                    'status' => User::STATUS_ACTIVE,
                    'must_change_password' => false,
                    'email_verified_at' => now(),
                ]
            );
            $user->update(['must_change_password' => false, 'status' => User::STATUS_ACTIVE]);
            $user->roles()->syncWithoutDetaching([$role->id]);
        }
    }

    /**
     * TEST 1: Un usuario autenticado en modo producción NO debe continuar autenticado al cambiar a modo demo.
     */
    public function test_production_session_is_not_authenticated_in_demo_mode(): void
    {
        // 1. Iniciar en modo producción
        config(['app.mode' => 'production']);
        config(['session.cookie' => app(ApplicationModeService::class)->sessionCookieName()]);
        Auth::logout();

        $role = Role::firstOrCreate(['name' => 'administrador'], ['description' => 'Admin']);
        $user = User::factory()->create([
            'email' => 'admin.prod@clinica.com',
            'status' => User::STATUS_ACTIVE,
            'password' => bcrypt('password123'),
        ]);
        $user->roles()->sync([$role->id]);

        // Login real en producción vía POST
        $loginResponse = $this->post('/login', [
            'email' => 'admin.prod@clinica.com',
            'password' => 'password123',
            'remember' => '1',
        ]);
        $loginResponse->assertRedirect('/admin/dashboard');
        $this->assertAuthenticated();

        // 2. Simular cambio de entorno a modo demo
        config(['app.mode' => 'demo']);
        config(['session.cookie' => app(ApplicationModeService::class)->sessionCookieName()]);

        // Petición al entorno demo
        $demoResponse = $this->get('/demo/clinica');
        $demoResponse->assertStatus(200);

        // La identidad productiva NO debe existir en el entorno demo
        $this->assertFalse(Auth::check(), 'La sesión de producción no debe transferirse a modo demo.');
    }

    /**
     * TEST 2: Un usuario autenticado en modo demo NO debe continuar autenticado al cambiar a modo producción.
     */
    public function test_demo_session_is_not_authenticated_in_production_mode(): void
    {
        // 1. Iniciar en modo demo y sembrar cuentas canónicas
        config(['app.mode' => 'demo']);
        config(['session.cookie' => app(ApplicationModeService::class)->sessionCookieName()]);
        $this->createDemoUsers();
        Auth::logout();

        // Login en demo como Doctor Especialista
        $demoLoginResponse = $this->get('/demo/acceso/doctor');
        $demoLoginResponse->assertRedirect('/doctor/dashboard');
        $this->assertTrue(Auth::check());
        $this->assertSame('doctor.medicina@demo-clinigest.test', Auth::user()->email);

        // 2. Simular cambio de entorno a modo producción
        config(['app.mode' => 'production']);
        config(['session.cookie' => app(ApplicationModeService::class)->sessionCookieName()]);

        // Petición en producción
        $prodResponse = $this->get('/');
        $prodResponse->assertStatus(200);

        // La identidad demo NO debe existir en el entorno productivo
        $this->assertFalse(Auth::check(), 'La identidad de la demo no debe persistir al cambiar a modo producción.');
    }

    /**
     * TEST 3: La sesión en modo producción persiste normalmente en peticiones subsiguientes del mismo modo.
     */
    public function test_production_session_persists_across_production_requests(): void
    {
        config(['app.mode' => 'production']);
        config(['session.cookie' => app(ApplicationModeService::class)->sessionCookieName()]);
        Auth::logout();

        $role = Role::firstOrCreate(['name' => 'doctor'], ['description' => 'Doctor']);
        $user = User::factory()->create([
            'email' => 'doctor.prod@clinica.com',
            'status' => User::STATUS_ACTIVE,
            'password' => bcrypt('password123'),
        ]);
        $user->roles()->sync([$role->id]);

        $this->post('/login', [
            'email' => 'doctor.prod@clinica.com',
            'password' => 'password123',
            'remember' => '1',
        ]);

        $this->assertAuthenticated();

        // Segunda petición en producción
        $response = $this->get('/doctor/dashboard');
        $response->assertStatus(200);
        $this->assertTrue(Auth::check());
        $this->assertSame('doctor.prod@clinica.com', Auth::user()->email);
    }

    /**
     * TEST 4: La sesión en modo demo persiste normalmente en peticiones subsiguientes del mismo modo.
     */
    public function test_demo_session_persists_across_demo_requests(): void
    {
        config(['app.mode' => 'demo']);
        config(['session.cookie' => app(ApplicationModeService::class)->sessionCookieName()]);
        $this->createDemoUsers();
        Auth::logout();

        $this->get('/demo/acceso/paciente');
        $this->assertTrue(Auth::check());
        $this->assertSame('paciente@demo-clinigest.test', Auth::user()->email);

        // Segunda petición dentro de demo
        $response = $this->get('/paciente/dashboard');
        $response->assertStatus(200);
        $this->assertTrue(Auth::check());
        $this->assertSame('paciente@demo-clinigest.test', Auth::user()->email);
    }

    /**
     * TEST 5: En modo demo, cambiar de perfil reemplaza limpiamente la identidad y regenera la sesión.
     */
    public function test_demo_profile_switching_replaces_identity_and_regenerates_session(): void
    {
        config(['app.mode' => 'demo']);
        config(['session.cookie' => app(ApplicationModeService::class)->sessionCookieName()]);
        $this->createDemoUsers();
        Auth::logout();

        // 1. Acceder como Doctor
        $this->get('/demo/acceso/doctor');
        $this->assertSame('doctor.medicina@demo-clinigest.test', Auth::user()->email);
        $firstSessionId = session()->getId();

        // 2. Cambiar a Administrador
        $this->get('/demo/acceso/administrador');
        $this->assertSame('admin@demo-clinigest.test', Auth::user()->email);
        $secondSessionId = session()->getId();

        $this->assertNotSame($firstSessionId, $secondSessionId, 'El cambio de perfil demo debe regenerar el ID de sesión.');
    }

    /**
     * TEST 6: El logout en ambos modos invalida la sesión y redirige al destino correspondiente.
     */
    public function test_logout_in_both_modes_invalidates_session_and_redirects_correctly(): void
    {
        // En producción
        config(['app.mode' => 'production']);
        config(['session.cookie' => app(ApplicationModeService::class)->sessionCookieName()]);
        $userProd = User::factory()->create();
        $this->actingAs($userProd);
        $logoutProd = $this->post('/salir');
        $logoutProd->assertRedirect('/');
        $this->assertFalse(Auth::check());

        // En demo
        config(['app.mode' => 'demo']);
        config(['session.cookie' => app(ApplicationModeService::class)->sessionCookieName()]);
        $this->createDemoUsers();
        $this->get('/demo/acceso/doctor');
        $this->assertTrue(Auth::check());

        $logoutDemo = $this->post('/salir');
        $logoutDemo->assertRedirect(route('demo.access.selector'));
        $this->assertFalse(Auth::check());
    }

    /**
     * TEST 7: La cookie de sesión tiene nombres diferenciados e independientes por modo.
     */
    public function test_session_cookie_name_is_isolated_between_modes(): void
    {
        $service = app(ApplicationModeService::class);

        config(['app.mode' => 'production']);
        $prodCookie = $service->sessionCookieName();

        config(['app.mode' => 'demo']);
        $demoCookie = $service->sessionCookieName();

        $this->assertNotSame($prodCookie, $demoCookie, 'El nombre de la cookie de sesión debe ser diferente entre production y demo.');
        $this->assertStringContainsString('demo', $demoCookie);
    }
}
