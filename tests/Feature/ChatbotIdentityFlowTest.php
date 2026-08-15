<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\ChatbotSessionKeys;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatbotIdentityFlowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            \App\Http\Middleware\EnsureCaptchaVerified::class,
            \App\Http\Middleware\EnsureChatbotIdentityVerified::class,
        ]);
    }

    public function test_verificar_paciente_acepta_cedula_sin_correo(): void
    {
        $rolPaciente = Role::firstOrCreate(['name' => 'paciente']);

        $paciente = User::factory()->create([
            'dni' => '1234567890',
            'email' => 'paciente@example.com',
            'status' => 'active',
        ]);
        $paciente->roles()->attach($rolPaciente->id);

        $response = $this->postJson(route('chatbot.verificarPaciente'), [
            'cedula' => ' 12345 67890 ',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('existe', true)
            ->assertJsonPath('paciente.id', $paciente->id);
    }

    public function test_verificar_paciente_solo_valida_correo_si_fue_enviado(): void
    {
        $rolPaciente = Role::firstOrCreate(['name' => 'paciente']);

        $paciente = User::factory()->create([
            'dni' => '1234567890',
            'email' => 'paciente@example.com',
            'status' => 'active',
        ]);
        $paciente->roles()->attach($rolPaciente->id);

        $response = $this->postJson(route('chatbot.verificarPaciente'), [
            'cedula' => '1234567890',
            'email' => 'otro@example.com',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('existe', true);
    }

    public function test_enviar_codigo_otp_no_consumes_cooldown_when_smtp_fails(): void
    {
        $rolPaciente = Role::firstOrCreate(['name' => 'paciente']);

        $paciente = User::factory()->create([
            'dni' => '1234567890',
            'email' => 'paciente.smtp.falla@example.com',
            'status' => 'active',
        ]);
        $paciente->roles()->attach($rolPaciente->id);

        $cacheKey = $this->otpCacheKey($paciente->dni, $paciente->email);
        $sessionBucket = (string) Str::uuid();

        Cache::forget($cacheKey);

        Mail::shouldReceive('raw')
            ->twice()
            ->andThrow(new \RuntimeException('SMTP down'));

        $first = $this->withSession(['chatbot_otp_send_bucket' => $sessionBucket])->postJson(route('chatbot.enviarCodigo'), [
            'cedula' => $paciente->dni,
            'email' => $paciente->email,
        ]);

        $first->assertStatus(500)
            ->assertJsonPath('ok', false);

        $this->assertFalse(Cache::has($cacheKey));
        $this->assertFalse(session()->has(ChatbotSessionKeys::SESSION_OTP_LAST_SENT_AT));

        $second = $this->postJson(route('chatbot.enviarCodigo'), [
            'cedula' => $paciente->dni,
            'email' => $paciente->email,
        ]);

        $second->assertStatus(500)
            ->assertJsonPath('ok', false);

        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_enviar_codigo_otp_locks_after_a_successful_send(): void
    {
        $rolPaciente = Role::firstOrCreate(['name' => 'paciente']);

        $paciente = User::factory()->create([
            'dni' => '0987654321',
            'email' => 'paciente.smtp.ok@example.com',
            'status' => 'active',
        ]);
        $paciente->roles()->attach($rolPaciente->id);

        $cacheKey = $this->otpCacheKey($paciente->dni, $paciente->email);
        $sessionBucket = (string) Str::uuid();
        Cache::forget($cacheKey);

        Mail::shouldReceive('raw')
            ->once()
            ->andReturnNull();

        $first = $this->withSession(['chatbot_otp_send_bucket' => $sessionBucket])->postJson(route('chatbot.enviarCodigo'), [
            'cedula' => $paciente->dni,
            'email' => $paciente->email,
        ]);

        $first->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertTrue(Cache::has($cacheKey));
        $this->assertTrue((bool) session(ChatbotSessionKeys::SESSION_OTP_LAST_SENT_AT));

        $second = $this->withSession(['chatbot_otp_send_bucket' => $sessionBucket])->postJson(route('chatbot.enviarCodigo'), [
            'cedula' => $paciente->dni,
            'email' => $paciente->email,
        ]);

        $second->assertStatus(429)
            ->assertJsonPath('ok', false);

        $this->assertGreaterThan(0, (int) $second->json('retry_after'));
    }

    private function otpCacheKey(string $cedula, string $email): string
    {
        $emailHash = hash('sha256', strtolower(trim($email)));

        return 'chatbot:codigo:' . sha1($cedula . '|' . $emailHash);
    }
}
