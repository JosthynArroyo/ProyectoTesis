<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class WelcomeDemoPreviewIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.mode' => 'demo']);

        (new \Database\Seeders\DemoClinicSeeder)->run();

        $roleDoctor = \App\Models\Role::firstOrCreate(['name' => 'doctor'], ['label' => 'Doctor']);
        $doctor = \App\Models\User::firstOrCreate(
            ['email' => 'doctor.medicina@demo-clinigest.test'],
            [
                'name' => 'Dr. Fernando Alvarado',
                'password' => \Illuminate\Support\Facades\Hash::make('Demo1234!'),
                'status' => \App\Models\User::STATUS_ACTIVE,
                'must_change_password' => false,
                'email_verified_at' => now(),
            ]
        );
        $doctor->update(['must_change_password' => false, 'status' => \App\Models\User::STATUS_ACTIVE]);
        $doctor->roles()->syncWithoutDetaching([$roleDoctor->id]);
    }

    public function test_welcome_page_in_demo_mode_renders_demo_clinic_branding_and_preview_cta(): void
    {
        $response = $this->get('/demo/clinica');

        $response->assertStatus(200);
        $response->assertSee('Clínica Josthyn Arroyo');
        $response->assertSee('Explorar sistema');
        $response->assertSee(route('demo.access.selector'));
        $response->assertSee('Vista previa');

        // En modo demo el navbar no debe mostrar el acceso institucional 'Ingresar'
        $response->assertDontSee('<span>Ingresar</span>', false);
    }

    public function test_demo_clinic_secondary_ctas_route_to_demo_selector_and_do_not_trigger_institutional_login(): void
    {
        $response = $this->get('/demo/clinica');
        $response->assertStatus(200);

        // En modo demo, ningún CTA visible debe contener el disparador del modal institucional
        $response->assertDontSee('data-login-trigger', false);
        $response->assertDontSee('?login=1', false);

        // Los CTAs de agendamiento y servicios protegidos deben dirigir al selector demo
        $response->assertSee(route('demo.access.selector'));
    }

    public function test_production_welcome_preserves_institutional_login_triggers_on_all_guest_ctas(): void
    {
        config(['app.mode' => 'production']);
        Auth::logout();

        $response = $this->get('/');
        $response->assertStatus(200);

        // En producción, los CTAs de invitados deben incluir data-login-trigger
        $response->assertSee('data-login-trigger', false);
        $response->assertSee('?login=1', false);
    }

    public function test_welcome_preview_cta_navigates_to_demo_selector_and_enters_real_panel(): void
    {
        // 1. Cargar Welcome de la Clínica Demo
        $welcomeResponse = $this->get('/demo/clinica');
        $welcomeResponse->assertStatus(200);

        // 2. Navegar al selector
        $selectorResponse = $this->get('/demo/acceso');
        $selectorResponse->assertStatus(200);
        $selectorResponse->assertViewIs('demo.selector');

        // 3. Entrar como Doctor
        $doctorLoginResponse = $this->get('/demo/acceso/doctor');
        $doctorLoginResponse->assertRedirect('/doctor/dashboard');
        $this->assertTrue(Auth::check());
        $this->assertSame('doctor.medicina@demo-clinigest.test', Auth::user()->email);

        // 4. Panel real responde 200
        $dashboardResponse = $this->actingAs(Auth::user())->get('/doctor/dashboard');
        $dashboardResponse->assertStatus(200);
    }

    public function test_welcome_in_production_mode_does_not_show_any_demo_preview_ctas_or_links(): void
    {
        config(['app.mode' => 'production']);
        Auth::logout();

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertDontSee(route('demo.access.selector'));
        $response->assertDontSee('Explorar sistema');
        $response->assertDontSee('Vista previa');

        // Login normal y elementos públicos estables siguen presentes
        $response->assertSee('<span>Ingresar</span>', false);
        $response->assertSee('Contacto');
        $response->assertSee('Verificar documento');
        $response->assertDontSee('data-demo-preview-bar', false);
    }

    public function test_welcome_personalization_data_source_is_dynamic(): void
    {
        // Modificar nombre institucional en SiteSetting y LandingWelcomeSetting
        SiteSetting::updateOrCreate(
            ['key' => 'branding.name'],
            ['section' => 'branding', 'type' => 'string', 'value' => 'Hospital Especializado Demo']
        );
        \App\Models\LandingWelcomeSetting::updateOrCreate(
            ['id' => 1],
            ['header_name' => 'Hospital Especializado Demo']
        );
        app()->forgetInstance(\App\Services\LandingWelcomeService::class);
        app()->forgetInstance(\App\Services\SiteSettingsService::class);
        app()->forgetInstance(\App\Services\ClinicIdentityService::class);
        app(\App\Services\LandingWelcomeService::class)->forgetCache();
        app(\App\Services\SiteSettingsService::class)->forgetCache();

        $response = $this->get('/demo/clinica');
        $response->assertStatus(200);
        $response->assertSee('Hospital Especializado Demo');
    }
}
