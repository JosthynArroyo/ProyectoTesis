<?php

namespace Tests\Feature;

use App\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FacialRecognitionResidueCleanupTest extends TestCase
{
    /**
     * Valida que el rate limiter huérfano 'face-enroll' ya no está registrado
     * mientras que los rate limiters legítimos vigentes continúan funcionando.
     */
    public function test_face_enroll_rate_limiter_is_not_registered_and_legitimate_limiters_remain(): void
    {
        // face-enroll no debe producir un limitador activo
        $faceEnrollLimits = RateLimiter::limiter('face-enroll');
        $this->assertNull($faceEnrollLimits, "El RateLimiter 'face-enroll' residual no debe estar registrado.");

        // Rate limiters legítimos vigentes deben seguir registrados
        $this->assertNotNull(RateLimiter::limiter('slot-holds'), "El RateLimiter 'slot-holds' (Hallazgo 2) debe continuar registrado.");
        $this->assertNotNull(RateLimiter::limiter('chatbot.message'), "El RateLimiter 'chatbot.message' debe continuar registrado.");
        $this->assertNotNull(RateLimiter::limiter('chatbot.otp.send'), "El RateLimiter 'chatbot.otp.send' debe continuar registrado.");
        $this->assertNotNull(RateLimiter::limiter('chatbot.otp.verify'), "El RateLimiter 'chatbot.otp.verify' debe continuar registrado.");
        $this->assertNotNull(RateLimiter::limiter('captcha.challenge'), "El RateLimiter 'captcha.challenge' debe continuar registrado.");
        $this->assertNotNull(RateLimiter::limiter('captcha.verify'), "El RateLimiter 'captcha.verify' debe continuar registrado.");
    }

    /**
     * Valida que no existan rutas registradas de reconocimiento o enrolamiento facial.
     */
    public function test_no_facial_routes_exist_in_the_application(): void
    {
        $allRoutes = Route::getRoutes()->getRoutes();

        foreach ($allRoutes as $route) {
            $uri = $route->uri();
            $this->assertStringNotContainsString('face/login', $uri);
            $this->assertStringNotContainsString('face/enroll', $uri);
            $this->assertStringNotContainsString('face-enroll', $uri);
            $this->assertStringNotContainsString('biometric', $uri);
        }
    }

    /**
     * Valida que la configuración residual 'services.face' fue removida.
     */
    public function test_facial_services_config_is_removed(): void
    {
        $this->assertNull(config('services.face'), "La clave de configuración 'services.face' debe ser null.");
    }

    /**
     * Valida que .env.example no contenga variables FACE_*.
     */
    public function test_env_example_contains_no_face_variables(): void
    {
        $envExample = file_get_contents(base_path('.env.example'));

        $this->assertStringNotContainsString('FACE_MATCH_THRESHOLD', $envExample);
        $this->assertStringNotContainsString('FACE_MAX_FAILURES', $envExample);
    }

    /**
     * Valida que el middleware de mantenimiento no contenga bypass para 'face/login'.
     */
    public function test_maintenance_middleware_does_not_contain_face_login_bypass(): void
    {
        $middlewareCode = file_get_contents(app_path('Http/Middleware/PreventRequestsDuringMaintenance.php'));

        $this->assertStringNotContainsString('face/login', $middlewareCode);
    }
}
