<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CommercialLandingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.mode' => 'demo']);

        (new \Database\Seeders\DemoClinicSeeder)->run();

        $role = Role::firstOrCreate(['name' => 'doctor'], ['guard_name' => 'web']);
        $doctor = User::firstOrCreate(
            ['email' => 'doctor.medicina@demo-clinigest.test'],
            [
                'name' => 'Dr. Carlos Mendoza',
                'password' => Hash::make('demo1234*'),
                'status' => User::STATUS_ACTIVE,
                'email_verified_at' => now(),
            ]
        );
        if (! $doctor->hasRole('doctor')) {
            $doctor->roles()->syncWithoutDetaching([$role->id]);
        }
    }

    public function test_root_path_in_demo_mode_serves_commercial_landing_with_all_required_sections(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewIs('landing.commercial');

        // Brand & Hero
        $response->assertSee('JA MedSys');
        $response->assertSee('Sistema web integral para la gestión de clínicas');
        $response->assertSee('Más control y eficiencia');
        $response->assertSee('Solicitar información');

        // 4 Benefits
        $response->assertSee('Gestión centralizada');
        $response->assertSee('Atención clínica');
        $response->assertSee('Laboratorio integrado');
        $response->assertSee('Personalización white-label');

        // 6 Screenshots Showcases
        $response->assertSee('Dashboard Administrativo');
        $response->assertSee('Gestión de Citas');
        $response->assertSee('Historial Clínico');
        $response->assertSee('Laboratorio');
        $response->assertSee('Portal del Paciente');
        $response->assertSee('Personalización');

        // 10 Modules
        $response->assertSee('Citas');
        $response->assertSee('Pacientes');
        $response->assertSee('Documentos');
        $response->assertSee('Cobros');
        $response->assertSee('Reportes');
        $response->assertSee('Usuarios y roles');

        // 5 Roles
        $response->assertSee('Administrador');
        $response->assertSee('Médico');
        $response->assertSee('Paciente');
        $response->assertSee('Superadministrador');

        // White-label & Highlight Callout
        $response->assertSee('White-label: tu clínica, tu marca');
        $response->assertSee('Vista previa del sistema');
        $response->assertSee('ABRIR VISTA PREVIA');
        $response->assertSee(route('demo.clinic'));

        // Qué incluye
        $response->assertSee('Qué incluye');
        $response->assertSee('Sistema completo');
        $response->assertSee('Código fuente');
        $response->assertSee('Instalación y configuración');

        // Commercial Contact & FAQ
        $response->assertSee('alejandroucenriquez@gmail.com');
        $response->assertSee('¿En qué servidores puede instalarse?');
        $response->assertSee('¿El sistema incluye código fuente?');
        $response->assertSee('¿Puedo personalizar el sistema?');
        $response->assertSee('¿Qué soporte incluye?');
        $response->assertSee('¿El sistema es web y responsive?');
    }

    public function test_preview_flow_from_commercial_landing_to_demo_clinic_to_real_panels(): void
    {
        // 1. Visitar Landing Comercial
        $landingResponse = $this->get('/');
        $landingResponse->assertStatus(200);
        $landingResponse->assertSee(route('demo.clinic'));

        // 2. Clic en Vista previa -> Carga Welcome de la Clínica Demo
        $clinicResponse = $this->get('/demo/clinica');
        $clinicResponse->assertStatus(200);
        $clinicResponse->assertViewIs('welcome');
        $clinicResponse->assertSee('Clínica Josthyn Arroyo');
        $clinicResponse->assertSee('Explorar sistema');

        // 3. Clic en Explorar sistema -> Carga Selector de Roles
        $selectorResponse = $this->get('/demo/acceso');
        $selectorResponse->assertStatus(200);
        $selectorResponse->assertViewIs('demo.selector');

        // 4. Elegir rol Doctor -> Autentica y redirige a /doctor/dashboard
        $loginResponse = $this->get('/demo/acceso/doctor');
        $loginResponse->assertRedirect('/doctor/dashboard');
        $this->assertTrue(Auth::check());
        $this->assertSame('doctor.medicina@demo-clinigest.test', Auth::user()->email);

        // 5. Panel real responde 200 OK
        $dashboardResponse = $this->actingAs(Auth::user())->get('/doctor/dashboard');
        $dashboardResponse->assertStatus(200);
    }

    public function test_root_path_in_production_mode_serves_real_clinic_and_blocks_commercial_and_demo(): void
    {
        config(['app.mode' => 'production']);
        Auth::logout();

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewIs('welcome');
        $response->assertSee('Clínica Josthyn Arroyo');

        // No contiene elementos comerciales de JA MedSys
        $response->assertDontSee('JA MedSys');
        $response->assertDontSee('alejandroucenriquez@gmail.com');
        $response->assertDontSee('ABRIR VISTA PREVIA');
        $response->assertDontSee(route('demo.clinic'));
        $response->assertDontSee(route('demo.access.selector'));

        // Endpoints demo están estrictamente bloqueados con 404
        $this->get('/demo/clinica')->assertStatus(404);
        $this->get('/demo/acceso')->assertStatus(404);
        $this->get('/demo/acceso/doctor')->assertStatus(404);
    }

    public function test_commercial_contact_and_clinic_contact_are_strictly_separated(): void
    {
        // 1. Landing comercial tiene contacto de ventas de software
        $landing = $this->get('/');
        $landing->assertSee('alejandroucenriquez@gmail.com');
        $landing->assertDontSee('contacto@demo-clinigest.test');

        // 2. Welcome de la clínica tiene contacto de atención a pacientes
        $clinic = $this->get('/demo/clinica');
        $clinic->assertSee('contacto@demo-clinigest.test');
        $clinic->assertDontSee('alejandroucenriquez@gmail.com');
    }
}
