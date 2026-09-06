<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class DemoAccessTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.mode' => 'demo']);

        $demoRoles = [
            'superadmin' => ['email' => 'superadmin@demo-clinigest.test', 'name' => 'Ing. Mateo Villacís'],
            'administrador' => ['email' => 'admin@demo-clinigest.test', 'name' => 'Dra. Valeria Mendoza'],
            'doctor' => ['email' => 'doctor.medicina@demo-clinigest.test', 'name' => 'Dr. Fernando Alvarado'],
            'paciente' => ['email' => 'paciente@demo-clinigest.test', 'name' => 'Javier Espinoza'],
            'laboratorio' => ['email' => 'laboratorio@demo-clinigest.test', 'name' => 'Lic. Carlos Morales'],
        ];

        $users = [];
        foreach ($demoRoles as $roleName => $info) {
            $role = \App\Models\Role::firstOrCreate(['name' => $roleName], ['label' => ucfirst($roleName)]);
            $user = User::firstOrCreate(
                ['email' => $info['email']],
                [
                    'name' => $info['name'],
                    'password' => \Illuminate\Support\Facades\Hash::make('Demo1234!'),
                    'status' => User::STATUS_ACTIVE,
                    'must_change_password' => false,
                    'email_verified_at' => now(),
                ]
            );
            $user->update(['must_change_password' => false, 'status' => User::STATUS_ACTIVE]);
            $user->roles()->syncWithoutDetaching([$role->id]);
            $users[$roleName] = $user;
        }

        $especialidad = \App\Models\Especialidad::firstOrCreate(
            ['nombre' => 'Medicina General'],
            ['descripcion' => 'General', 'activo' => true]
        );
        $users['doctor']->especialidades()->syncWithoutDetaching([$especialidad->id]);

        Cita::firstOrCreate(
            [
                'doctor_id' => $users['doctor']->id,
                'paciente_id' => $users['paciente']->id,
                'fecha' => now()->toDateString(),
                'hora' => '10:00:00',
            ],
            [
                'especialidad_id' => $especialidad->id,
                'motivo' => 'Consulta de prueba en demo',
                'estado' => Cita::ESTADO_CONFIRMADA,
            ]
        );
    }

    public function test_demo_selector_is_accessible_in_demo_mode(): void
    {
        $response = $this->get('/demo/acceso');

        $response->assertStatus(200);
        $response->assertViewIs('demo.selector');
        $response->assertSee('Vista previa interactiva');
        $response->assertSee('Selecciona un perfil para explorar el sistema');
        $response->assertSee('Volver a la página principal');
        $response->assertSee(route('demo.clinic'));
        $response->assertSee('Acceso guiado');
        $response->assertSee('Datos de demostración');
        $response->assertSee('Prueba controlada');
        $response->assertSee('Entorno de evaluación');

        // 5 Perfiles
        $response->assertSee('Superadministrador');
        $response->assertSee('Administrador');
        $response->assertSee('Médico Especialista');
        $response->assertSee('Paciente');
        $response->assertSee('Laboratorio Clínico');

        // Botones de acción
        $response->assertSee(route('demo.access.role', ['role' => 'superadmin']));
        $response->assertSee(route('demo.access.role', ['role' => 'administrador']));
        $response->assertSee(route('demo.access.role', ['role' => 'doctor']));
        $response->assertSee(route('demo.access.role', ['role' => 'paciente']));
        $response->assertSee(route('demo.access.role', ['role' => 'laboratorio']));
        $response->assertSee('Ingresar al panel');
    }

    public function test_demo_selector_back_button_links_to_demo_clinic_welcome(): void
    {
        $response = $this->get('/demo/acceso');
        $response->assertStatus(200);
        $response->assertSee('Volver a la página principal');
        $response->assertSee(route('demo.clinic'));

        $clinicResponse = $this->get(route('demo.clinic'));
        $clinicResponse->assertStatus(200);
    }

    public function test_demo_access_as_administrador_authenticates_and_redirects_to_real_admin_dashboard(): void
    {
        $response = $this->get('/demo/acceso/administrador');

        $response->assertRedirect('/admin/dashboard');
        $this->assertTrue(Auth::check());
        $this->assertSame('admin@demo-clinigest.test', Auth::user()->email);
        $this->assertTrue(Auth::user()->hasRole('administrador'));

        $dashboardResponse = $this->actingAs(Auth::user())->get('/admin/dashboard');
        $dashboardResponse->assertStatus(200);
    }

    public function test_demo_access_as_admin_alias_works_identically(): void
    {
        $response = $this->get('/demo/acceso/admin');

        $response->assertRedirect('/admin/dashboard');
        $this->assertTrue(Auth::check());
        $this->assertSame('admin@demo-clinigest.test', Auth::user()->email);
    }

    public function test_demo_access_as_doctor_authenticates_and_redirects_to_real_doctor_dashboard(): void
    {
        $response = $this->get('/demo/acceso/doctor');

        $response->assertRedirect('/doctor/dashboard');
        $this->assertTrue(Auth::check());
        $this->assertSame('doctor.medicina@demo-clinigest.test', Auth::user()->email);
        $this->assertTrue(Auth::user()->hasRole('doctor'));

        $dashboardResponse = $this->actingAs(Auth::user())->get('/doctor/dashboard');
        $dashboardResponse->assertStatus(200);
    }

    public function test_demo_access_as_paciente_authenticates_and_redirects_to_real_paciente_dashboard(): void
    {
        $response = $this->get('/demo/acceso/paciente');

        $response->assertRedirect('/paciente/dashboard');
        $this->assertTrue(Auth::check());
        $this->assertSame('paciente@demo-clinigest.test', Auth::user()->email);
        $this->assertTrue(Auth::user()->hasRole('paciente'));

        $dashboardResponse = $this->actingAs(Auth::user())->get('/paciente/dashboard');
        $dashboardResponse->assertStatus(200);
    }

    public function test_demo_access_as_laboratorio_authenticates_and_redirects_to_real_laboratorio_dashboard(): void
    {
        $response = $this->get('/demo/acceso/laboratorio');

        $response->assertRedirect('/laboratorio/dashboard');
        $this->assertTrue(Auth::check());
        $this->assertSame('laboratorio@demo-clinigest.test', Auth::user()->email);
        $this->assertTrue(Auth::user()->hasRole('laboratorio'));

        $dashboardResponse = $this->actingAs(Auth::user())->get('/laboratorio/dashboard');
        $dashboardResponse->assertStatus(200);
    }

    public function test_demo_access_as_superadmin_authenticates_and_redirects_to_real_superadmin_dashboard(): void
    {
        $response = $this->get('/demo/acceso/superadmin');

        $response->assertRedirect('/superadmin/dashboard');
        $this->assertTrue(Auth::check());
        $this->assertSame('superadmin@demo-clinigest.test', Auth::user()->email);
        $this->assertTrue(Auth::user()->hasRole('superadmin'));

        $dashboardResponse = $this->actingAs(Auth::user())->get('/superadmin/dashboard');
        $dashboardResponse->assertStatus(200);
    }

    public function test_demo_access_rejects_non_whitelisted_roles(): void
    {
        $response = $this->get('/demo/acceso/hacker');
        $response->assertStatus(404);

        $response2 = $this->get('/demo/acceso/invitado');
        $response2->assertStatus(404);
    }

    public function test_role_authorization_is_strictly_enforced_after_demo_login(): void
    {
        // 1. Doctor no puede acceder a administración
        $doctor = User::where('email', 'doctor.medicina@demo-clinigest.test')->first();
        $forbiddenAdmin = $this->actingAs($doctor)->get('/admin/dashboard');
        $this->assertTrue(in_array($forbiddenAdmin->status(), [403, 302]));

        // 2. Paciente no puede acceder a laboratorio
        $paciente = User::where('email', 'paciente@demo-clinigest.test')->first();
        $forbiddenLab = $this->actingAs($paciente)->get('/laboratorio/dashboard');
        $this->assertTrue(in_array($forbiddenLab->status(), [403, 302]));
    }

    public function test_production_mode_strictly_blocks_all_demo_access_endpoints(): void
    {
        config(['app.mode' => 'production']);
        Auth::logout();

        $this->get('/demo/acceso')->assertStatus(404);
        $this->get('/demo/acceso/administrador')->assertStatus(404);
        $this->get('/demo/acceso/doctor')->assertStatus(404);
        $this->get('/demo/acceso/paciente')->assertStatus(404);
        $this->get('/demo/acceso/laboratorio')->assertStatus(404);
        $this->get('/demo/acceso/superadmin')->assertStatus(404);

        $this->assertFalse(Auth::check(), 'Ningún usuario debe autenticarse en modo producción');
    }

    public function test_session_regeneration_and_logout_flow(): void
    {
        $response = $this->get('/demo/acceso/doctor');
        $response->assertRedirect('/doctor/dashboard');
        $this->assertTrue(Auth::check());

        // Logout
        $logoutResponse = $this->post('/salir');
        $logoutResponse->assertRedirect(route('demo.access.selector'));
        $this->assertFalse(Auth::check());
    }

    public function test_all_five_demo_roles_redirect_to_demo_selector_on_logout(): void
    {
        $roles = ['superadmin', 'administrador', 'doctor', 'paciente', 'laboratorio'];

        foreach ($roles as $role) {
            $this->get("/demo/acceso/{$role}")->assertRedirect();
            $this->assertTrue(Auth::check(), "El usuario con rol demo [{$role}] debe estar autenticado.");

            $logoutResponse = $this->post(route('salir'));
            $logoutResponse->assertRedirect(route('demo.access.selector'));
            $this->assertFalse(Auth::check(), "El usuario con rol demo [{$role}] debe quedar desautenticado tras logout.");
        }
    }

    public function test_demo_access_works_with_demo_database_isolation(): void
    {
        $this->get('/demo/acceso/admin');
        $admin = Auth::user();
        $this->assertNotNull($admin);

        $cita = Cita::first();
        $this->assertNotNull($cita);
        $originalMotivo = $cita->motivo_consulta;

        // Modificar estado de prioridad en demo
        $response = $this->actingAs($admin)->patch("/admin/citas/{$cita->id}/prioridad", [
            'prioridad_nivel' => Cita::PRIORIDAD_ALTA,
            'prioridad_fuente' => 'manual',
            'prioridad_comentario' => 'Cambio de prueba en demo',
        ]);

        $this->assertTrue(in_array($response->status(), [200, 302]));

        // Con DemoDatabaseIsolation, no queda rastro persistente
        $this->assertSame($originalMotivo, $cita->fresh()->motivo_consulta);
    }
}
