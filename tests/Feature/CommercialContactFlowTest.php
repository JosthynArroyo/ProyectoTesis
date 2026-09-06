<?php

namespace Tests\Feature;

use App\Mail\CommercialInquiryReceived;
use App\Mail\ContactoRecibido;
use App\Models\ContactMessage;
use App\Services\CommercialContactService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CommercialContactFlowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.mode' => 'demo']);
    }

    public function test_commercial_landing_contains_gmail_web_composer_links_and_no_mailto(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('https://mail.google.com/mail/?view=cm&fs=1&to=alejandroucenriquez@gmail.com', false);
        $response->assertDontSee('mailto:alejandroucenriquez@gmail.com', false);
    }

    public function test_commercial_inquiry_mailable_renders_independent_branding_and_no_clinic_elements(): void
    {
        $payload = [
            'nombre' => 'Dr. Carlos Mendoza',
            'email' => 'carlos@medicina.test',
            'telefono' => '0991122334',
            'asunto' => 'Centro Médico del Sur',
            'mensaje' => 'Solicito cotización e información sobre la instalación del software.',
        ];

        $mailable = new CommercialInquiryReceived($payload);
        $mailable->build();

        // Verify From
        $this->assertSame('JA MedSys', $mailable->from[0]['name'] ?? null);
        $this->assertSame(config('mail.from.address', 'alejandroucenriquez@gmail.com'), $mailable->from[0]['address'] ?? null);

        // Verify Reply-To
        $this->assertSame('carlos@medicina.test', $mailable->replyTo[0]['address'] ?? null);
        $this->assertSame('Dr. Carlos Mendoza', $mailable->replyTo[0]['name'] ?? null);

        // Verify Subject
        $this->assertStringContainsString('Nueva solicitud comercial', $mailable->subject);
        $this->assertStringContainsString('JA MedSys', $mailable->subject);

        // Render HTML
        $rendered = $mailable->render();

        // Must contain JA MedSys identity
        $this->assertStringContainsString('JA MedSys', $rendered);
        $this->assertStringContainsString('alejandroucenriquez@gmail.com', $rendered);
        $this->assertStringContainsString('+593 998 740 927', $rendered);
        $this->assertStringContainsString('Nueva solicitud comercial', $rendered);
        $this->assertStringContainsString('Centro Médico del Sur', $rendered);
        $this->assertStringContainsString('Dr. Carlos Mendoza', $rendered);

        // Must NOT contain any clinic identity elements
        $this->assertStringNotContainsString('Hospital Especializado Demo', $rendered);
        $this->assertStringNotContainsString('Clínica Josthyn Arroyo', $rendered);
        $this->assertStringNotContainsString('contacto@demo-clinigest.test', $rendered);
    }

    public function test_commercial_contact_form_uses_commercial_mailable_and_canonical_recipient(): void
    {
        Mail::fake();

        $signedUrl = URL::signedRoute('contacto.enviar', ['context' => 'commercial']);

        $payload = [
            'nombre' => 'Dr. Carlos Mendoza',
            'email' => 'carlos@medicina.test',
            'telefono' => '0991122334',
            'asunto' => 'Centro Médico del Sur',
            'mensaje' => 'Solicito cotización e información sobre la instalación del software.',
            'empresa' => '',
            't0' => now()->subSeconds(5)->timestamp,
        ];

        $response = $this->post($signedUrl, $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Tu mensaje ha sido enviado correctamente.');
        $response->assertSessionMissing('error');

        Mail::assertSent(CommercialInquiryReceived::class, function (CommercialInquiryReceived $mail) {
            return $mail->hasTo('alejandroucenriquez@gmail.com')
                && ($mail->datos['nombre'] ?? null) === 'Dr. Carlos Mendoza'
                && ($mail->datos['email'] ?? null) === 'carlos@medicina.test';
        });

        Mail::assertNotSent(ContactoRecibido::class);
    }

    public function test_commercial_contact_form_shows_error_and_no_false_success_when_smtp_fails(): void
    {
        $mockCommercialService = $this->createMock(CommercialContactService::class);
        $mockCommercialService->method('isAuthorizedCommercialRequest')->willReturn(true);
        $mockCommercialService->method('send')->willThrowException(
            new \RuntimeException('Connection could not be established with host smtp.gmail.com')
        );
        $this->app->instance(CommercialContactService::class, $mockCommercialService);

        $signedUrl = URL::signedRoute('contacto.enviar', ['context' => 'commercial']);

        $payload = [
            'nombre' => 'Dr. Carlos Mendoza',
            'email' => 'carlos@medicina.test',
            'telefono' => '0991122334',
            'asunto' => 'Centro Médico del Sur',
            'mensaje' => 'Solicito cotización e información sobre la instalación del software.',
            'empresa' => '',
            't0' => now()->subSeconds(5)->timestamp,
        ];

        $response = $this->post($signedUrl, $payload);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $response->assertSessionMissing('success');
    }

    public function test_tampered_or_unsigned_request_does_not_trigger_commercial_smtp(): void
    {
        Mail::fake();

        // Direct POST to /contacto without valid cryptographic signature for commercial context
        $payload = [
            'nombre' => 'Paciente Común',
            'email' => 'paciente@test.local',
            'telefono' => '0991122334',
            'asunto' => 'Consulta Médica',
            'mensaje' => 'Mensaje para el doctor de la clínica.',
            'empresa' => '',
            't0' => now()->subSeconds(5)->timestamp,
        ];

        $response = $this->post('/contacto?context=commercial', $payload);

        $response->assertRedirect();

        // Commercial inquiry mailable must NOT be sent
        Mail::assertNotSent(CommercialInquiryReceived::class);

        // In demo mode, regular contact must NOT send to commercial recipient
        Mail::assertNotSent(ContactoRecibido::class, function (ContactoRecibido $mail) {
            return $mail->hasTo('alejandroucenriquez@gmail.com');
        });
    }

    public function test_production_mode_strictly_disallows_commercial_contact_authorization(): void
    {
        config(['app.mode' => 'production']);

        $service = app(CommercialContactService::class);
        $request = \Illuminate\Http\Request::create('/contacto?context=commercial');

        $this->assertFalse($service->isAuthorizedCommercialRequest($request));
    }

    public function test_production_mode_clinical_contact_uses_clinic_mailable_and_saves_message(): void
    {
        Mail::fake();
        config(['app.mode' => 'production']);
        config(['mail.contact_to' => 'clinica_admin@example.test']);

        $payload = [
            'nombre' => 'Paciente Real Producción',
            'email' => 'paciente.prod@example.test',
            'telefono' => '0995544332',
            'asunto' => 'Consulta sobre especialidad',
            'mensaje' => 'Deseo saber si cuentan con cardiología en esta sede.',
            'empresa' => '',
            't0' => now()->subSeconds(5)->timestamp,
        ];

        $response = $this->post('/contacto', $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Must save to clinic contact messages
        $this->assertDatabaseHas('contact_messages', [
            'nombre' => 'Paciente Real Producción',
            'correo' => 'paciente.prod@example.test',
            'asunto' => 'Consulta sobre especialidad',
        ]);

        // Must send clinical mailable ContactoRecibido to clinic admin
        Mail::assertSent(ContactoRecibido::class, function (ContactoRecibido $mail) {
            return $mail->hasTo('clinica_admin@example.test')
                && ($mail->datos['nombre'] ?? null) === 'Paciente Real Producción';
        });

        // Must NEVER send CommercialInquiryReceived for clinical contact
        Mail::assertNotSent(CommercialInquiryReceived::class);
    }
}

