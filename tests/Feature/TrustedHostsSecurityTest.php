<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\CustomResetPasswordNotification;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Tests\TestCase;

class TrustedHostsSecurityTest extends TestCase
{
    /**
     * Host exacto de APP_URL es permitido y funciona normalmente.
     */
    public function test_host_exacto_de_app_url_es_permitido(): void
    {
        $response = $this->get('http://localhost/');
        $response->assertSuccessful();
    }

    /**
     * Localhost y 127.0.0.1 son permitidos en entorno local/testing.
     */
    public function test_localhost_y_127_0_0_1_son_permitidos_en_local_testing(): void
    {
        $response127 = $this->get('http://127.0.0.1:8000/');
        $response127->assertSuccessful();

        $responseLocal = $this->get('http://localhost:8000/');
        $responseLocal->assertSuccessful();
    }

    /**
     * Subdominio no configurado explícitamente es rechazado por defecto.
     */
    public function test_subdominio_no_configurado_es_rechazado(): void
    {
        $this->withoutExceptionHandling();

        $rejected = false;
        try {
            $this->get('http://unconfigured-subdomain.localhost/');
        } catch (\Throwable $e) {
            if ($e instanceof SuspiciousOperationException || $e instanceof BadRequestHttpException) {
                $rejected = true;
            } else {
                throw $e;
            }
        }

        $this->assertTrue($rejected, 'Un subdominio no configurado debió ser rechazado por defecto.');
    }

    /**
     * Host configurado expresamente en TRUSTED_HOSTS es permitido sin abrir subdominios arbitrarios.
     */
    public function test_host_configurado_en_trusted_hosts_es_permitido(): void
    {
        config([
            'app.url' => 'https://clinica-ejemplo.test',
            'app.trusted_hosts' => ['www.clinica-ejemplo.test'],
        ]);

        $trustedMiddleware = new \App\Http\Middleware\TrustHosts(app());
        $hosts = $trustedMiddleware->hosts();

        // Host canónico exacto
        $this->assertContains('^clinica\-ejemplo\.test$', $hosts);
        // Host explícito en TRUSTED_HOSTS
        $this->assertContains('^www\.clinica\-ejemplo\.test$', $hosts);
        // NO debe permitir subdominios arbitrarios con comodín
        $this->assertNotContains('^(.+\.)?clinica\-ejemplo\.test$', $hosts);
    }

    /**
     * Host externo arbitrario es rechazado antes de ejecutar rutas o controladores.
     */
    public function test_host_externo_arbitrario_es_rechazado(): void
    {
        $this->withoutExceptionHandling();

        $rejected = false;
        try {
            $this->get('http://unauthorized-attacker.test/');
        } catch (\Throwable $e) {
            if ($e instanceof SuspiciousOperationException || $e instanceof BadRequestHttpException) {
                $rejected = true;
            } else {
                throw $e;
            }
        }

        $this->assertTrue($rejected, 'La aplicación debió rechazar la petición con host no autorizado.');
    }

    /**
     * Recuperación de contraseña: Un encabezado Host manipulado no puede contaminar el enlace sensible.
     */
    public function test_recuperacion_de_contrasena_sigue_protegida_contra_host_manipulado(): void
    {
        Notification::fake();

        $user = User::factory()->make([
            'id' => 99999,
            'name' => 'Usuario Prueba',
            'email' => 'seguridad-test@example.com',
        ]);

        $this->withoutExceptionHandling();

        $rejected = false;
        try {
            $this->post('http://attacker-controlled-domain.test/password/email', [
                'email' => $user->email,
            ]);
        } catch (\Throwable $e) {
            if ($e instanceof SuspiciousOperationException || $e instanceof BadRequestHttpException) {
                $rejected = true;
            } else {
                throw $e;
            }
        }

        $this->assertTrue($rejected, 'La petición de restablecimiento de contraseña con host manipulado debió ser rechazada.');
        Notification::assertNothingSent();
    }

    /**
     * Flujo normal de recuperación de contraseña genera un enlace con el host canónico legítimo.
     */
    public function test_flujo_normal_recuperacion_genera_enlace_con_host_legitimo(): void
    {
        Notification::fake();

        $user = new User([
            'name' => 'Usuario Legítimo',
            'email' => 'legitimo@example.com',
        ]);

        $user->sendPasswordResetNotification('token-verificado-abc');

        Notification::assertSentTo($user, CustomResetPasswordNotification::class, function ($notification) use ($user) {
            $mail = $notification->toMail($user);
            $resetUrl = $mail->viewData['resetUrl'];

            $expectedHost = parse_url(config('app.url'), PHP_URL_HOST);
            $actualHost = parse_url($resetUrl, PHP_URL_HOST);

            $this->assertSame($expectedHost, $actualHost);
            $this->assertStringContainsString('token-verificado-abc', $resetUrl);
            $this->assertStringContainsString(urlencode($user->email), $resetUrl);

            return true;
        });
    }
}
