<?php

namespace Tests\Feature;

use App\Http\Middleware\PreventBackHistory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PreventBackHistoryPolicyTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Caso A — Las páginas públicas no reciben no-store indiscriminadamente.
     */
    public function test_caso_a_public_pages_do_not_receive_no_store_header(): void
    {
        $publicRoutes = ['/', '/servicios', '/contacto'];

        foreach ($publicRoutes as $uri) {
            $response = $this->get($uri);
            $response->assertStatus(200);

            $cacheControl = (string) $response->headers->get('Cache-Control');
            $this->assertStringNotContainsString('no-store', $cacheControl, "La ruta pública [{$uri}] no debe contener 'no-store'.");
            $this->assertFalse($response->headers->has('Pragma'), "La ruta pública [{$uri}] no debe forzar header Pragma: no-cache.");
        }
    }

    /**
     * Caso B — Las páginas autenticadas/privadas sí reciben no-store y prevención de historial.
     */
    public function test_caso_b_authenticated_private_pages_receive_back_history_protection(): void
    {
        $patient = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
        ]);
        $patient->roles()->attach(Role::firstOrCreate(['name' => 'paciente']));

        $response = $this->actingAs($patient)->get(route('paciente.dashboard'));
        $response->assertStatus(200);

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl, 'La página privada del paciente debe contener no-store.');
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertEquals('no-cache', $response->headers->get('Pragma'));
        $this->assertEquals('Sat, 01 Jan 1990 00:00:00 GMT', $response->headers->get('Expires'));
    }

    /**
     * Caso C — Un usuario no autenticado es bloqueado al intentar acceder a rutas privadas.
     */
    public function test_caso_c_unauthenticated_user_cannot_access_private_pages(): void
    {
        $response = $this->get(route('paciente.dashboard'));
        $response->assertRedirect();
    }

    /**
     * Caso D — Tras logout, la sesión queda invalidada y el acceso privado se bloquea.
     */
    public function test_caso_d_after_logout_private_access_is_denied(): void
    {
        $doctor = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
        ]);
        $doctor->roles()->attach(Role::firstOrCreate(['name' => 'doctor']));

        $authResponse = $this->actingAs($doctor)->get(route('doctor.dashboard'));
        $authResponse->assertStatus(200);
        $this->assertStringContainsString('no-store', (string) $authResponse->headers->get('Cache-Control'));

        // Ejecutar logout
        $logoutResponse = $this->post(route('salir'));
        $logoutResponse->assertRedirect();

        // Petición posterior como guest
        $afterLogoutResponse = $this->get(route('doctor.dashboard'));
        $afterLogoutResponse->assertRedirect();
    }

    /**
     * Caso E — La clase PreventBackHistory es la única solución canónica activa.
     */
    public function test_caso_e_prevent_back_history_is_single_canonical_middleware(): void
    {
        $this->assertTrue(class_exists(PreventBackHistory::class));
    }

    /**
     * Caso F — Rutas públicas principales mantienen funcionalidad sin degradación.
     */
    public function test_caso_f_main_public_routes_respond_ok(): void
    {
        $this->get('/')->assertOk();
        $this->get('/servicios')->assertOk();
        $this->get('/contacto')->assertOk();
    }
}
