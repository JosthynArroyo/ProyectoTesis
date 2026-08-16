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

    public function test_verificar_paciente_rechaza_cuenta_inactiva_bloqueada_o_suspendida(): void
    {
        $rolPaciente = Role::firstOrCreate(['name' => 'paciente']);

        // Inactive patient
        $inactivo = User::factory()->create([
            'dni' => '1111111111',
            'email' => 'inactivo@example.com',
            'status' => User::STATUS_INACTIVE,
        ]);
        $inactivo->roles()->attach($rolPaciente->id);

        $resInactivo = $this->postJson(route('chatbot.verificarPaciente'), [
            'cedula' => '1111111111',
        ]);
        $resInactivo->assertStatus(403)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error', 'user_inactive');

        // Blocked patient
        $bloqueado = User::factory()->create([
            'dni' => '2222222222',
            'email' => 'bloqueado@example.com',
            'status' => User::STATUS_BLOCKED,
        ]);
        $bloqueado->roles()->attach($rolPaciente->id);

        $resBloqueado = $this->postJson(route('chatbot.verificarPaciente'), [
            'cedula' => '2222222222',
        ]);
        $resBloqueado->assertStatus(403)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error', 'user_inactive');

        // Suspended patient
        $suspendido = User::factory()->create([
            'dni' => '3333333333',
            'email' => 'suspendido@example.com',
            'status' => User::STATUS_ACTIVE,
            'suspended_until' => now()->addDays(5),
        ]);
        $suspendido->roles()->attach($rolPaciente->id);

        $resSuspendido = $this->postJson(route('chatbot.verificarPaciente'), [
            'cedula' => '3333333333',
        ]);
        $resSuspendido->assertStatus(403)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error', 'user_inactive');
    }

    public function test_enviar_codigo_rechaza_cuenta_no_activa(): void
    {
        $rolPaciente = Role::firstOrCreate(['name' => 'paciente']);

        $inactivo = User::factory()->create([
            'dni' => '4444444444',
            'email' => 'inactivo.otp@example.com',
            'status' => User::STATUS_INACTIVE,
        ]);
        $inactivo->roles()->attach($rolPaciente->id);

        $response = $this->postJson(route('chatbot.enviarCodigo'), [
            'cedula' => '4444444444',
            'email' => 'inactivo.otp@example.com',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error', 'user_inactive');
    }

    public function test_verificar_codigo_rechaza_si_cuenta_fue_suspendida_despues_de_solicitar_otp(): void
    {
        $rolPaciente = Role::firstOrCreate(['name' => 'paciente']);

        $paciente = User::factory()->create([
            'dni' => '5555555555',
            'email' => 'paciente.susp@example.com',
            'status' => User::STATUS_ACTIVE,
        ]);
        $paciente->roles()->attach($rolPaciente->id);

        $otpKey = $this->otpCacheKey($paciente->dni, $paciente->email);
        Cache::put($otpKey, '123456', now()->addMinutes(10));

        // Admin suspends account at T1
        $paciente->update(['status' => User::STATUS_BLOCKED]);

        // Patient submits valid OTP at T2
        $response = $this->postJson(route('chatbot.verificarCodigo'), [
            'cedula' => $paciente->dni,
            'email' => $paciente->email,
            'codigo' => '123456',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error', 'user_inactive');

        $this->assertFalse(session()->has(ChatbotSessionKeys::SESSION_CHATBOT_OTP_VERIFIED));
        $this->assertFalse(session()->has(ChatbotSessionKeys::SESSION_CHATBOT_USER_ID));
    }

    private function otpCacheKey(string $cedula, string $email): string
    {
        $emailHash = hash('sha256', strtolower(trim($email)));

        return 'chatbot:codigo:' . sha1($cedula . '|' . $emailHash);
    }
}
