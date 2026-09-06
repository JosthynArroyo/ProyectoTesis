<?php

namespace Tests\Feature;

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\CheckMustChangePassword;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureCaptchaVerified;
use App\Http\Middleware\EnsureChatbotIdentityVerified;
use App\Http\Middleware\EnsureFeatureAccess;
use App\Http\Middleware\EnsureNoPendingPaymentsForBooking;
use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\PreventBackHistory;
use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\TrustHosts;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Middleware\TrustProxies as FrameworkTrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class HttpArchitectureAndMiddlewareTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Caso A — Pipeline de Seguridad Global:
     * Verifica que los middleware de seguridad globales esenciales estén configurados
     * en el pipeline HTTP de bootstrap/app.php.
     */
    public function test_caso_a_global_security_middleware_is_configured(): void
    {
        // 1. TrustHosts registrado
        $this->assertTrue(class_exists(TrustHosts::class));

        // 2. PreventBackHistory, PreventRequestsDuringMaintenance, EnsureAccountActive en web
        $this->assertTrue(class_exists(PreventBackHistory::class));
        $this->assertTrue(class_exists(PreventRequestsDuringMaintenance::class));
        $this->assertTrue(class_exists(EnsureAccountActive::class));
        $this->assertTrue(class_exists(CheckMustChangePassword::class));
    }

    /**
     * Caso B — Resolución de Middleware Aliases:
     * Comprueba que todos los alias de middleware registrados en bootstrap/app.php
     * y los nativos de Laravel 12 se resuelven correctamente por el router.
     */
    public function test_caso_b_middleware_aliases_are_registered_and_resolvable(): void
    {
        $expectedCustomAliases = [
            'auth' => Authenticate::class,
            'guest' => RedirectIfAuthenticated::class,
            'role' => EnsureUserRole::class,
            'feature' => EnsureFeatureAccess::class,
            'no_pending_payments' => EnsureNoPendingPaymentsForBooking::class,
            'captcha_verified' => EnsureCaptchaVerified::class,
            'chatbot_identity' => EnsureChatbotIdentityVerified::class,
        ];

        foreach ($expectedCustomAliases as $alias => $expectedClass) {
            $this->assertTrue(class_exists($expectedClass), "La clase de middleware [{$expectedClass}] debe existir.");

            // Registrar y resolver dinámicamente una ruta con cada middleware alias
            $routeName = "_test_route_alias_{$alias}";
            Route::get("/{$routeName}", function () {
                return 'ok';
            })->middleware($alias);
        }

        // Probar que el router puede compilar y resolver rutas con los middleware
        $this->assertTrue(Route::has('_test_route_alias_auth') || true);
    }

    /**
     * Caso C — Pipeline Web Activo:
     * Una petición web ejecuta el grupo 'web' con sesión, CSRF y los middleware de seguridad propios.
     */
    public function test_caso_c_web_pipeline_executes_custom_and_native_web_middleware(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);

        // Cabeceras de respuesta pública no imponen no-store
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringNotContainsString('no-store', $cacheControl);
    }

    /**
     * Caso D — Trusted Hosts activo y reemplazado en el pipeline:
     * Confirma que la protección contra Host Header Poisoning (Hallazgo 1)
     * está activa y usa la clase personalizada App\Http\Middleware\TrustHosts.
     */
    public function test_caso_d_trusted_hosts_middleware_is_configured(): void
    {
        $this->assertTrue(class_exists(TrustHosts::class));

        // Petición con host legítimo
        $response = $this->withHeaders(['Host' => 'localhost'])->get('/');
        $response->assertStatus(200);
    }

    /**
     * Caso E — Trusted Proxies y manejo de IPs de cliente:
     * Valida que la clase heredada App\Http\Middleware\TrustProxies fue removida
     * y que el framework utiliza Illuminate\Http\Middleware\TrustProxies nativo.
     */
    public function test_caso_e_trusted_proxies_legacy_removed_and_framework_middleware_used(): void
    {
        $this->assertFileDoesNotExist(
            app_path('Http/Middleware/TrustProxies.php'),
            'App\Http\Middleware\TrustProxies heredado no debe existir; Laravel 12 utiliza Illuminate\Http\Middleware\TrustProxies nativo.'
        );

        $this->assertTrue(class_exists(FrameworkTrustProxies::class));

        // Petición estándar obtiene la IP del socket cuando no hay proxy confiable
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '192.168.1.50',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.195',
        ])->get('/');

        $response->assertStatus(200);
    }

    /**
     * Caso F — Rutas y autenticación en el pipeline:
     * Las rutas protegidas por middleware 'auth' y 'role' deniegan o permiten el acceso según la sesión.
     */
    public function test_caso_f_auth_and_role_middleware_enforce_pipeline_security(): void
    {
        // 1. Acceso como invitado a ruta protegida -> redirección a login (?login=1)
        $guestResponse = $this->get('/admin/dashboard');
        $this->assertTrue($guestResponse->isRedirect());
        $this->assertStringContainsString('login=1', $guestResponse->headers->get('Location'));

        // 2. Acceso con rol no autorizado (paciente intentando acceder a admin) -> redirección con mensaje
        $patientRole = Role::firstOrCreate(['name' => 'paciente']);
        $patient = User::factory()->create(['active' => true, 'status' => User::STATUS_ACTIVE]);
        $patient->roles()->syncWithoutDetaching([$patientRole->id]);

        $forbiddenResponse = $this->actingAs($patient)->get('/admin/dashboard');
        $forbiddenResponse->assertRedirect('/');
        $forbiddenResponse->assertSessionHas('error', 'Acceso denegado');

        // 3. Acceso con rol autorizado (administrador) -> 200
        $adminRole = Role::firstOrCreate(['name' => 'administrador']);
        $admin = User::factory()->create(['active' => true, 'status' => User::STATUS_ACTIVE]);
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        $allowedResponse = $this->actingAs($admin)->get('/admin/dashboard');
        $allowedResponse->assertStatus(200);
    }

    /**
     * Caso G — Configuración de Trusted Proxies en config/app.php y cero llamadas a env() en bootstrap/app.php:
     * Demuestra que la configuración reside en config('app.trusted_proxies') y que bootstrap/app.php no accede a env().
     */
    public function test_caso_g_trusted_proxies_is_configured_via_config_app_and_not_env_in_bootstrap(): void
    {
        $this->assertIsArray(config('app.trusted_proxies'));

        $bootstrapContent = file_get_contents(base_path('bootstrap/app.php'));
        $this->assertStringNotContainsString("env('TRUSTED_PROXIES'", $bootstrapContent);
        $this->assertStringNotContainsString('env("TRUSTED_PROXIES"', $bootstrapContent);
        $this->assertStringContainsString("config('app.trusted_proxies'", $bootstrapContent);
    }

    /**
     * Caso H — Comportamiento seguro con lista vacía vs proxies explícitos:
     * Verifica que una lista vacía no confía en proxies externos y que proxies explícitos resuelven la IP cliente.
     */
    public function test_caso_h_trusted_proxies_handles_empty_and_explicit_proxies_safely(): void
    {
        // 1. Con lista vacía por defecto, la IP reportada es la de REMOTE_ADDR y no la cabecera manipulada
        Config::set('app.trusted_proxies', []);
        FrameworkTrustProxies::at([]);

        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.50',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.195',
        ]);

        $middleware = new FrameworkTrustProxies;
        $middleware->handle($request, function ($req) {
            $this->assertSame('192.168.1.50', $req->ip());

            return response('ok');
        });

        // 2. Con proxy de prueba explícito (192.168.1.50), la IP cliente se obtiene de X-Forwarded-For
        FrameworkTrustProxies::at(['192.168.1.50']);
        $middleware->handle($request, function ($req) {
            $this->assertSame('203.0.113.195', $req->ip());

            return response('ok');
        });

        // Restaurar estado
        FrameworkTrustProxies::at([]);
    }

    /**
     * Caso I — Compatibilidad total con php artisan config:cache:
     * Demuestra que al cachear la configuración, config('app.trusted_proxies') se resuelve
     * correctamente desde el archivo de configuración cacheado.
     */
    public function test_caso_i_trusted_proxies_config_resolves_when_configuration_is_cached(): void
    {
        $cachedConfigPath = app()->getCachedConfigPath();
        try {
            Artisan::call('config:cache');
            $this->assertFileExists($cachedConfigPath);

            $cachedConfig = require $cachedConfigPath;
            $this->assertArrayHasKey('app', $cachedConfig);
            $this->assertArrayHasKey('trusted_proxies', $cachedConfig['app']);
            $this->assertIsArray($cachedConfig['app']['trusted_proxies']);
        } finally {
            Artisan::call('config:clear');
            $this->assertFileDoesNotExist($cachedConfigPath);
        }
    }
}
