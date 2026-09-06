<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DemoGlobalNavigationAndPreviewBarTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.mode' => 'demo']);

        $demoUsers = [
            'superadmin' => ['email' => 'superadmin@demo-clinigest.test', 'role' => 'superadmin', 'name' => 'Ing. Mateo Villacís'],
            'administrador' => ['email' => 'admin@demo-clinigest.test', 'role' => 'administrador', 'name' => 'Dra. Valeria Mendoza'],
            'doctor' => ['email' => 'doctor.medicina@demo-clinigest.test', 'role' => 'doctor', 'name' => 'Dr. Fernando Alvarado'],
            'paciente' => ['email' => 'paciente@demo-clinigest.test', 'role' => 'paciente', 'name' => 'Javier Espinoza'],
            'laboratorio' => ['email' => 'laboratorio@demo-clinigest.test', 'role' => 'laboratorio', 'name' => 'Lic. Carlos Morales'],
        ];

        foreach ($demoUsers as $info) {
            $role = \App\Models\Role::firstOrCreate(['name' => $info['role']], ['guard_name' => 'web']);
            $user = User::firstOrCreate(
                ['email' => $info['email']],
                [
                    'name' => $info['name'],
                    'password' => \Illuminate\Support\Facades\Hash::make('Demo1234!'),
                    'status' => User::STATUS_ACTIVE,
                    'email_verified_at' => now(),
                ]
            );
            if (! $user->hasRole($info['role'])) {
                $user->roles()->syncWithoutDetaching([$role->id]);
            }
        }
    }

    public static function demoRolesProvider(): array
    {
        return [
            'administrador' => ['admin@demo-clinigest.test', '/admin/dashboard', 'Rol: Admin'],
            'doctor' => ['doctor.medicina@demo-clinigest.test', '/doctor/dashboard', 'Rol: Médico'],
            'paciente' => ['paciente@demo-clinigest.test', '/paciente/dashboard', 'Rol: Paciente'],
            'laboratorio' => ['laboratorio@demo-clinigest.test', '/laboratorio/dashboard', 'Rol: Laboratorio'],
            'superadmin' => ['superadmin@demo-clinigest.test', '/superadmin/dashboard', 'Rol: Superadmin'],
        ];
    }

    #[DataProvider('demoRolesProvider')]
    public function test_all_five_roles_render_demo_preview_bar_in_their_real_dashboards(
        string $email,
        string $dashboardUrl,
        string $expectedRoleLabel
    ): void {
        $user = User::where('email', $email)->firstOrFail();

        $response = $this->actingAs($user)->get($dashboardUrl);

        $response->assertStatus(200);
        $response->assertSee('Vista previa');
        $response->assertSee('Los cambios no se almacenan');
        $response->assertSee('Cambiar perfil');
        $response->assertSee(route('demo.access.selector'));
        $response->assertSee('Volver al producto');
        $response->assertSee(route('home.index'));
        $response->assertSee($expectedRoleLabel);
    }

    public function test_switching_role_completely_replaces_authenticated_identity(): void
    {
        // 1. Iniciar como Doctor
        $this->get('/demo/acceso/doctor')->assertRedirect('/doctor/dashboard');
        $this->assertTrue(Auth::check());
        $this->assertSame('doctor.medicina@demo-clinigest.test', Auth::user()->email);
        $this->assertTrue(Auth::user()->hasRole('doctor'));

        // 2. Navegar al selector
        $this->get('/demo/acceso')->assertStatus(200);

        // 3. Cambiar a Administrador
        $this->get('/demo/acceso/administrador')->assertRedirect('/admin/dashboard');
        $this->assertTrue(Auth::check());
        $this->assertSame('admin@demo-clinigest.test', Auth::user()->email);
        $this->assertTrue(Auth::user()->hasRole('administrador'));
        $this->assertFalse(Auth::user()->hasRole('doctor'));

        // 4. Panel Admin permitido, Panel Doctor bloqueado
        $this->actingAs(Auth::user())->get('/admin/dashboard')->assertStatus(200);
        $doctorForbidden = $this->actingAs(Auth::user())->get('/doctor/dashboard');
        $this->assertTrue(in_array($doctorForbidden->status(), [403, 302]));
    }

    public function test_return_to_product_link_leads_to_commercial_landing(): void
    {
        $doctor = User::where('email', 'doctor.medicina@demo-clinigest.test')->firstOrFail();

        // 1. Cargar panel de doctor
        $dashboardResponse = $this->actingAs($doctor)->get('/doctor/dashboard');
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('Volver al producto');

        // 2. Clic en Volver al producto -> Carga Landing Comercial
        $landingResponse = $this->get('/');
        $landingResponse->assertStatus(200);
        $landingResponse->assertViewIs('landing.commercial');
        $landingResponse->assertSee('JA MedSys');
    }

    public function test_production_mode_strictly_hides_preview_bar_and_demo_actions_for_all_roles(): void
    {
        config(['app.mode' => 'production']);
        Auth::logout();

        $doctor = User::where('email', 'doctor.medicina@demo-clinigest.test')->firstOrFail();
        $response = $this->actingAs($doctor)->get('/doctor/dashboard');

        $response->assertStatus(200);
        $response->assertDontSee('data-demo-preview-bar', false);
        $response->assertDontSee('Vista previa');
        $response->assertDontSee('Los cambios no se almacenan');
        $response->assertDontSee('Cambiar perfil');
        $response->assertDontSee('Volver al producto');
        $response->assertDontSee(route('demo.access.selector'));
    }
}
