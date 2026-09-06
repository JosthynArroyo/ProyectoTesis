<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Pago;
use App\Models\PaymentReceipt;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\ClinicIdentityService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DemoClinicIdentityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.mode' => 'demo']);
    }

    private function createDemoPago(string $estado = Pago::ESTADO_PENDIENTE): Pago
    {
        (new \Database\Seeders\DemoClinicSeeder)->run();

        $doctorRole = \App\Models\Role::firstOrCreate(['name' => 'doctor']);
        $pacienteRole = \App\Models\Role::firstOrCreate(['name' => 'paciente']);

        $doctor = User::firstOrCreate(
            ['email' => 'doctor.medicina@demo-clinigest.test'],
            ['name' => 'Dr. Fernando Alvarado', 'password' => bcrypt('Demo1234!'), 'status' => User::STATUS_ACTIVE, 'must_change_password' => false]
        );
        $doctor->roles()->syncWithoutDetaching([$doctorRole->id]);

        $paciente = User::firstOrCreate(
            ['email' => 'paciente@demo-clinigest.test'],
            ['name' => 'Javier Espinoza', 'password' => bcrypt('Demo1234!'), 'status' => User::STATUS_ACTIVE, 'must_change_password' => false]
        );
        $paciente->roles()->syncWithoutDetaching([$pacienteRole->id]);

        $especialidad = \App\Models\Especialidad::firstOrCreate(
            ['nombre' => 'Medicina General'],
            ['descripcion' => 'General', 'activo' => true]
        );

        $cita = Cita::firstOrCreate(
            [
                'doctor_id' => $doctor->id,
                'paciente_id' => $paciente->id,
                'fecha' => now()->toDateString(),
                'hora' => '10:00:00',
            ],
            [
                'especialidad_id' => $especialidad->id,
                'motivo' => 'Consulta de prueba en demo',
                'estado' => Cita::ESTADO_REALIZADA,
            ]
        );

        $suffix = \Illuminate\Support\Str::random(8);

        return Pago::create([
            'cita_id' => $cita->id,
            'paciente_id' => $paciente->id,
            'token_publico' => 'demo_payment_token_' . $suffix,
            'monto' => 35.00,
            'moneda' => 'USD',
            'metodo_pago' => Pago::METODO_EFECTIVO,
            'estado' => $estado,
            'folio_unico' => 'OC-DEMO-' . $suffix,
            'csv' => 'OC-DEMO-' . $suffix,
        ]);
    }

    /**
     * TEST 1: En modo demo, la página pública de cobro por token (/cobro/{token})
     * debe mostrar "Clínica Josthyn Arroyo" y NO "Clínica Don Bosco" ni nombres antiguos.
     */
    public function test_demo_payment_token_page_shows_canonical_demo_clinic_identity(): void
    {
        $pago = $this->createDemoPago(Pago::ESTADO_PENDIENTE);

        $response = $this->get(route('pagos.token.show', $pago->token_publico));

        $response->assertStatus(200);
        $response->assertSee('Clínica Josthyn Arroyo');
        $response->assertDontSee('Clínica Don Bosco');
        $response->assertDontSee('Don Bosco');
        $response->assertDontSee('Clínica Médica San Rafael');
        $response->assertDontSee('Clínica San Rafael');
    }

    /**
     * TEST 2: En modo demo, la verificación pública de recibos (/verificar-recibo/{token})
     * debe mostrar "Clínica Josthyn Arroyo" y NO "Clínica Don Bosco".
     */
    public function test_demo_receipt_verification_page_shows_canonical_demo_clinic_identity(): void
    {
        $pago = $this->createDemoPago(Pago::ESTADO_PAGADO);

        $suffix = \Illuminate\Support\Str::random(8);
        $receipt = PaymentReceipt::create([
            'pago_id' => $pago->id,
            'verification_token' => 'demo_receipt_token_' . $suffix,
            'folio_recibo' => 'REC-DEMO-' . $suffix,
            'monto' => 35.00,
            'metodo_pago' => 'efectivo',
            'pdf_path' => 'receipts/demo.pdf',
            'pdf_disk' => 'local',
            'emitido_en' => now(),
        ]);

        $response = $this->get(route('recibos.verificar', $receipt->verification_token));

        $response->assertStatus(200);
        $response->assertSee('Clínica Josthyn Arroyo');
        $response->assertDontSee('Clínica Don Bosco');
        $response->assertDontSee('Don Bosco');
        $response->assertDontSee('San Rafael');
    }

    /**
     * TEST 3: ClinicIdentityService en modo demo resuelve centralmente el nombre, logo y favicon demo.
     */
    public function test_clinic_identity_service_resolves_demo_identity_and_assets(): void
    {
        (new \Database\Seeders\DemoClinicSeeder)->run();

        $service = app(ClinicIdentityService::class);

        $this->assertSame('Clínica Josthyn Arroyo', $service->name());
        $this->assertSame('Clínica Josthyn Arroyo', $service->institutionalName());
        $this->assertSame('images/demo/logo-demo.png', $service->logoPath());
        $this->assertSame('images/demo/favicon-demo.png', $service->faviconPath());

        $faviconUrl = $service->faviconUrl();
        $this->assertStringContainsString('images/demo/favicon-demo.png', $faviconUrl);

        $logoBase64 = $service->logoBase64ForPdf();
        $this->assertNotNull($logoBase64);
        $this->assertStringStartsWith('data:image/png;base64,', $logoBase64);
    }

    /**
     * TEST 4: Endpoint /favicon.ico en modo demo resuelve el favicon demo directamente con HTTP 200.
     */
    public function test_favicon_endpoint_resolves_demo_favicon(): void
    {
        (new \Database\Seeders\DemoClinicSeeder)->run();

        $response = $this->get('/favicon.ico');

        $response->assertStatus(200);
    }

    /**
     * TEST 5: Welcome /demo/clinica muestra la identidad "Clínica Josthyn Arroyo" y no identidades anteriores.
     */
    public function test_welcome_demo_shows_canonical_identity(): void
    {
        (new \Database\Seeders\DemoClinicSeeder)->run();

        $response = $this->get('/demo/clinica');
        $response->assertStatus(200);
        $response->assertSee('Clínica Josthyn Arroyo');
        $response->assertDontSee('Clínica Médica San Rafael');
        $response->assertDontSee('Clínica San Rafael');
        $response->assertDontSee('Clínica Don Bosco');
    }

    /**
     * TEST 6: En APP_MODE=production, el sistema NO fuerza la identidad ni assets de demo.
     */
    public function test_production_mode_does_not_use_demo_identity_or_assets(): void
    {
        config(['app.mode' => 'production']);
        SiteSetting::query()->delete();
        app(\App\Services\SiteSettingsService::class)->forgetCache();

        $service = app(ClinicIdentityService::class);

        $this->assertSame('Nombre de la clínica', $service->name());
        $this->assertNull($service->logoPath());
        $this->assertNull($service->faviconPath());
    }
}
