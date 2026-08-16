<?php

namespace Tests\Feature;

use Tests\TestCase;

class DemoNotificacionesContactoTest extends TestCase
{
    /**
     * D1 — Registro válido:
     * Acceder a la ruta con un ID existente devuelve HTTP 200,
     * renderiza la vista correcta y muestra los datos principales del mensaje simulado.
     */
    public function test_d1_valid_contact_notification_renders_successfully(): void
    {
        $response = $this->get(route('demo.admin.contacto.mensajes.show', 1));

        $response->assertOk();
        $response->assertViewIs('demo.admin.notificaciones-contacto-show');
        $response->assertSeeText('Mensaje de contacto #1');
        $response->assertSeeText('Roberto Ibarra');
        $response->assertSeeText('roberto.ibarra@example.com');
        $response->assertSeeText('+593 95 000 1111');
        $response->assertSeeText('Disponibilidad de laboratorio');
        $response->assertSeeText('Buenas tardes, quisiera consultar la disponibilidad');
    }

    /**
     * D2 — Registro inexistente:
     * Acceder con un ID inexistente o no numérico devuelve 404, nunca 500.
     */
    public function test_d2_non_existent_contact_notification_returns_404(): void
    {
        $response = $this->get(route('demo.admin.contacto.mensajes.show', 99999));
        $response->assertNotFound();

        $responseInvalid = $this->get('/demo/admin/notificaciones-contacto/no-valido');
        $responseInvalid->assertNotFound();
    }

    /**
     * D3 — Vista resoluble:
     * Confirma que la vista demo.admin.notificaciones-contacto-show existe y puede renderizarse.
     */
    public function test_d3_view_is_resolvable_and_compiles_cleanly(): void
    {
        $this->assertTrue(view()->exists('demo.admin.notificaciones-contacto-show'));

        $rendered = view('demo.admin.notificaciones-contacto-show', [
            'mensaje' => (object) [
                'id' => 3,
                'name' => 'Paciente Demo',
                'email' => 'paciente.demo@example.com',
                'phone' => '+593 99 000 0000',
                'subject' => 'Consulta general',
                'status' => 'Nuevo',
                'status_tone' => 'warning',
                'received_at' => '28/04/2026 10:00',
                'message' => 'Mensaje de prueba para compilación de vista.',
            ],
            'clinicIdentity' => app(\App\Services\ClinicIdentityService::class),
            'clinicName' => 'Nombre de la clinica',
            'demoRole' => 'admin',
            'demoUser' => [
                'name' => 'Administrador Demo',
                'email' => 'admin.demo@clinica.test',
                'roleLabel' => 'Panel Admin',
                'avatarInitials' => 'AD',
            ],
            'headerTitle' => 'Mensaje de contacto #3',
            'headerSubtitle' => 'Solicitud recibida desde el formulario público (Demo)',
            'roleName' => 'admin',
            'roleTone' => 'primary',
            'activeSidebarKey' => 'notificaciones-contacto',
            'showRoleSwitcher' => true,
            'logoutUrl' => '#',
        ])->render();

        $this->assertStringContainsString('Paciente Demo', $rendered);
        $this->assertStringContainsString('paciente.demo@example.com', $rendered);
    }

    /**
     * D4 — Autorización / Middleware demo:
     * La ruta está registrada en el grupo demo.admin y accesible como página de demostración.
     */
    public function test_d4_route_is_accessible_under_demo_isolation(): void
    {
        $responseList = $this->get(route('demo.admin.notificaciones-contacto'));
        $responseList->assertOk();
        $responseList->assertSeeText('Mensajes de contacto');
        $responseList->assertSee(route('demo.admin.contacto.mensajes.show', 1));
        $responseList->assertSee(route('demo.admin.contacto.mensajes.show', 2));
    }
}
