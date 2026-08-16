<?php

namespace Tests\Feature;

use App\Events\CitaAgendada;
use App\Http\Middleware\EnsureCaptchaVerified;
use App\Http\Middleware\EnsureChatbotIdentityVerified;
use App\Mail\CuentaCreadaDesdeChat;
use App\Models\Cita;
use App\Models\Dependiente;
use App\Models\Especialidad;
use App\Models\Horario;
use App\Models\Role;
use App\Models\User;
use App\Support\ChatbotSessionKeys;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ChatbotRegistrationCredentialsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-03-09 09:00:00', 'America/Guayaquil'));
        Role::query()->firstOrCreate(['name' => 'paciente']);
        Role::query()->firstOrCreate(['name' => 'doctor']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Caso G: Registro mediante chatbot genera contraseña aleatoria segura (no la cédula),
     * marca must_change_password => true y email_verified_at tras validar OTP.
     */
    public function test_chatbot_registers_user_with_secure_random_password_and_must_change_password(): void
    {
        Mail::fake();

        $payload = [
            'nombre' => 'Paciente Registro Seguro',
            'cedula' => '1234512345',
            'email' => 'paciente.seguro@example.com',
            'telefono' => '0991234567',
        ];

        $response = $this->withSession([
            ChatbotSessionKeys::SESSION_VERIFIED => true,
            ChatbotSessionKeys::SESSION_VERIFIED_AT => now()->timestamp,
        ])->postJson(route('chatbot.registrarUsuario'), $payload);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('usuario_creado', false)
            ->assertJsonPath('requiere_otp', true)
            ->assertJsonPath('paciente.email', $payload['email']);

        // Antes de OTP no existe en BD
        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);

        // Validar OTP
        $emailHash = hash('sha256', strtolower(trim($payload['email'])));
        $cacheKey = 'chatbot:codigo:' . sha1($payload['cedula'] . '|' . $emailHash);
        $codigo = \Illuminate\Support\Facades\Cache::get($cacheKey);

        $resVerificar = $this->postJson(route('chatbot.verificarCodigo'), [
            'cedula' => $payload['cedula'],
            'email' => $payload['email'],
            'codigo' => $codigo,
        ]);

        $resVerificar->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('usuario_creado', true);

        $paciente = User::query()->where('email', $payload['email'])->firstOrFail();

        $this->assertSame($payload['cedula'], $paciente->dni);
        $this->assertTrue($paciente->hasRole('paciente'));
        $this->assertTrue((bool) $paciente->must_change_password, 'El usuario registrado debe tener must_change_password activado.');
        $this->assertNotNull($paciente->email_verified_at);
        $this->assertFalse(Hash::check($payload['cedula'], $paciente->password), 'La contraseña NUNCA debe ser la cédula.');

        Mail::assertSent(CuentaCreadaDesdeChat::class, function (CuentaCreadaDesdeChat $mail) use ($paciente, $payload) {
            return $mail->user->is($paciente)
                && $mail->passwordPlano !== $payload['cedula']
                && strlen($mail->passwordPlano) >= 12
                && Hash::check($mail->passwordPlano, $paciente->password);
        });
    }

    /**
     * Prueba Caso 1 & Negativa:
     * Registro de usuario nuevo sin completar OTP NO crea la cuenta en base de datos
     * y /chatbot/agendar debe ser rechazado (401).
     */
    public function test_new_user_registration_without_otp_verification_does_not_create_db_user_and_cannot_schedule(): void
    {
        Mail::fake();
        [$doctor, $especialidad, $fecha] = $this->createDoctorWithAvailability();

        $payloadRegistro = [
            'nombre' => 'Paciente Sin Validar OTP',
            'cedula' => '0912345678',
            'email' => 'sin.otp@example.com',
            'telefono' => '0998765432',
        ];

        // 1. Pasa CAPTCHA y se registra
        $resRegistro = $this->withSession([
            ChatbotSessionKeys::SESSION_VERIFIED => true,
            ChatbotSessionKeys::SESSION_VERIFIED_AT => now()->timestamp,
        ])->postJson(route('chatbot.registrarUsuario'), $payloadRegistro);

        $resRegistro->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('usuario_creado', false)
            ->assertJsonPath('requiere_otp', true);

        // Confirmamos que el usuario NO existe aún en BD (evita account squatting)
        $this->assertDatabaseMissing('users', ['email' => $payloadRegistro['email']]);
        $this->assertDatabaseMissing('users', ['dni' => $payloadRegistro['cedula']]);

        // 2. Intenta llamar a /chatbot/agendar directamente sin haber verificado el código OTP
        $payloadAgendar = [
            'especialidad_id' => $especialidad->id,
            'doctor_id' => $doctor->id,
            'fecha' => $fecha,
            'hora' => '10:00',
            'motivo_consulta' => 'Consulta no verificada',
        ];

        $resAgendar = $this->postJson(route('chatbot.agendar'), $payloadAgendar);

        // Debe ser rechazado con 401
        $resAgendar->assertStatus(401)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error', 'identity_required');

        $this->assertDatabaseMissing('citas_medicas', [
            'doctor_id' => $doctor->id,
            'fecha' => $fecha,
        ]);
    }

    /**
     * Prueba Caso 2 & 3:
     * Un intento abandonado o atacante no impide que el propietario legítimo complete
     * el registro más tarde, y no se generan duplicados en la base de datos.
     */
    public function test_abandoned_unverified_registration_does_not_prevent_legitimate_registration_or_cause_duplicates(): void
    {
        Event::fake([CitaAgendada::class]);
        Mail::fake();
        Queue::fake();

        [$doctor, $especialidad, $fecha] = $this->createDoctorWithAvailability();

        $cedula = '0923456789';
        $email = 'duplicado.test@example.com';

        // Intento 1: Registro abandonado sin OTP
        $this->withSession([
            ChatbotSessionKeys::SESSION_VERIFIED => true,
            ChatbotSessionKeys::SESSION_VERIFIED_AT => now()->timestamp,
        ])->postJson(route('chatbot.registrarUsuario'), [
            'nombre' => 'Intento 1 Abandonado',
            'cedula' => $cedula,
            'email' => $email,
            'telefono' => '0990000001',
        ])->assertOk();

        $this->assertDatabaseMissing('users', ['email' => $email]);

        // Intento 2: Usuario legítimo completa el registro
        $this->withSession([
            ChatbotSessionKeys::SESSION_VERIFIED => true,
            ChatbotSessionKeys::SESSION_VERIFIED_AT => now()->timestamp,
        ])->postJson(route('chatbot.registrarUsuario'), [
            'nombre' => 'Propietario Legítimo',
            'cedula' => $cedula,
            'email' => $email,
            'telefono' => '0991122334',
        ])->assertOk();

        $emailHash = hash('sha256', strtolower(trim($email)));
        $cacheKey = 'chatbot:codigo:' . sha1($cedula . '|' . $emailHash);
        $codigo = \Illuminate\Support\Facades\Cache::get($cacheKey);

        $this->assertNotNull($codigo);

        // Valida OTP
        $resVerificar = $this->postJson(route('chatbot.verificarCodigo'), [
            'cedula' => $cedula,
            'email' => $email,
            'codigo' => $codigo,
        ]);

        $resVerificar->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('usuario_creado', true);

        // Exactamente 1 usuario en BD
        $this->assertSame(1, User::query()->where('email', $email)->count());
        $paciente = User::query()->where('email', $email)->firstOrFail();
        $this->assertSame('Propietario Legítimo', $paciente->name);
        $this->assertSame('0991122334', $paciente->telefono);

        // Puede agendar normalmente
        $this->postJson(route('chatbot.agendar'), [
            'especialidad_id' => $especialidad->id,
            'doctor_id' => $doctor->id,
            'fecha' => $fecha,
            'hora' => '10:00',
            'motivo_consulta' => 'Cita después de verificación completa',
        ])->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('citas_medicas', [
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'fecha' => $fecha,
        ]);
    }

    /**
     * Prueba: Una cuenta no verificada (intento de registro sin OTP) no existe en BD
     * y por tanto no puede autenticarse en el sistema ni actuar como cuenta activa.
     */
    public function test_unverified_registration_cannot_login_or_act_as_active_user(): void
    {
        Mail::fake();

        $email = 'no.verificado@example.com';
        $cedula = '1818181818';

        $this->withSession([
            ChatbotSessionKeys::SESSION_VERIFIED => true,
            ChatbotSessionKeys::SESSION_VERIFIED_AT => now()->timestamp,
        ])->postJson(route('chatbot.registrarUsuario'), [
            'nombre' => 'No Verificado',
            'cedula' => $cedula,
            'email' => $email,
            'telefono' => '0999999999',
        ])->assertOk();

        // El usuario NO existe en la base de datos
        $this->assertDatabaseMissing('users', ['email' => $email]);

        // Intento de login web no puede autenticar
        $this->assertFalse(
            \Illuminate\Support\Facades\Auth::attempt(['email' => $email, 'password' => 'CualquierPassword123!'])
        );
    }

    /**
     * Prueba Positiva Explícita:
     * Registro de usuario nuevo + verificación OTP válida → /chatbot/agendar funciona exitosamente.
     */
    public function test_new_user_registration_with_valid_otp_verification_can_schedule_appointment(): void
    {
        Event::fake([CitaAgendada::class]);
        Mail::fake();
        Queue::fake();

        [$doctor, $especialidad, $fecha] = $this->createDoctorWithAvailability();

        $payloadRegistro = [
            'nombre' => 'Paciente Nuevo Verificado',
            'cedula' => '0923456789',
            'email' => 'nuevo.verificado@example.com',
            'telefono' => '0991122334',
        ];

        // 1. Pasa CAPTCHA y se registra
        $resRegistro = $this->withSession([
            ChatbotSessionKeys::SESSION_VERIFIED => true,
            ChatbotSessionKeys::SESSION_VERIFIED_AT => now()->timestamp,
        ])->postJson(route('chatbot.registrarUsuario'), $payloadRegistro);

        $resRegistro->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('usuario_creado', false)
            ->assertJsonPath('requiere_otp', true);

        // 2. Recuperar el código OTP generado en caché para este usuario
        $emailHash = hash('sha256', strtolower(trim($payloadRegistro['email'])));
        $cacheKey = 'chatbot:codigo:' . sha1($payloadRegistro['cedula'] . '|' . $emailHash);
        $codigo = \Illuminate\Support\Facades\Cache::get($cacheKey);

        $this->assertNotNull($codigo, 'El código OTP debe haberse generado en caché.');

        // 3. Validar el código OTP
        $resVerificar = $this->postJson(route('chatbot.verificarCodigo'), [
            'cedula' => $payloadRegistro['cedula'],
            'email' => $payloadRegistro['email'],
            'codigo' => $codigo,
        ]);

        $resVerificar->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('usuario_creado', true);

        $paciente = User::query()->where('email', $payloadRegistro['email'])->firstOrFail();

        // 4. Ahora sí agendar la cita médica
        $payloadAgendar = [
            'especialidad_id' => $especialidad->id,
            'doctor_id' => $doctor->id,
            'fecha' => $fecha,
            'hora' => '10:00',
            'motivo_consulta' => 'Chequeo tras registro y OTP',
        ];

        $resAgendar = $this->postJson(route('chatbot.agendar'), $payloadAgendar);

        $resAgendar->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('cita.id', fn ($id) => !empty($id));

        $this->assertDatabaseHas('citas_medicas', [
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => $fecha,
            'hora' => '10:00:00',
        ]);
    }

    /**
     * Caso A: Una sesión sin identidad OTP verificada intenta llamar /chatbot/agendar.
     * Debe ser rechazada (401).
     */
    public function test_caso_a_agendar_without_otp_identity_is_rejected(): void
    {
        [$doctor, $especialidad, $fecha] = $this->createDoctorWithAvailability();

        $payload = [
            'especialidad_id' => $especialidad->id,
            'doctor_id' => $doctor->id,
            'fecha' => $fecha,
            'hora' => '10:00',
            'motivo_consulta' => 'Consulta preventiva',
        ];

        // Request with empty session (no OTP verification)
        $response = $this->postJson(route('chatbot.agendar'), $payload);

        $response->assertStatus(401)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error', 'identity_required');
    }

    /**
     * Caso B: Una sesión que únicamente pasó CAPTCHA, pero no OTP, intenta agendar.
     * Debe ser rechazada (401).
     */
    public function test_caso_b_agendar_with_only_captcha_is_rejected(): void
    {
        [$doctor, $especialidad, $fecha] = $this->createDoctorWithAvailability();

        $payload = [
            'especialidad_id' => $especialidad->id,
            'doctor_id' => $doctor->id,
            'fecha' => $fecha,
            'hora' => '10:00',
            'motivo_consulta' => 'Consulta preventiva',
        ];

        $response = $this->withSession([
            ChatbotSessionKeys::SESSION_VERIFIED => true,
            ChatbotSessionKeys::SESSION_VERIFIED_AT => now()->timestamp,
        ])->postJson(route('chatbot.agendar'), $payload);

        $response->assertStatus(401)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error', 'identity_required');
    }

    /**
     * Caso C: Una identidad OTP verificada del paciente A envía deliberadamente
     * paciente_id = paciente B. Nunca debe agendar como B (403).
     */
    public function test_caso_c_verified_patient_a_cannot_spoof_patient_b(): void
    {
        Event::fake([CitaAgendada::class]);
        Mail::fake();
        Queue::fake();

        [$doctor, $especialidad, $fecha] = $this->createDoctorWithAvailability();

        $pacienteA = $this->createPatient('Paciente A', '1111111111', 'paciente.a@example.com');
        $pacienteB = $this->createPatient('Paciente B', '2222222222', 'paciente.b@example.com');

        $payload = [
            'especialidad_id' => $especialidad->id,
            'doctor_id' => $doctor->id,
            'fecha' => $fecha,
            'hora' => '10:00',
            'motivo_consulta' => 'Intento de suplantación',
            'paciente_id' => $pacienteB->id,
        ];

        $response = $this->withSession([
            ChatbotSessionKeys::SESSION_CHATBOT_USER_ID => $pacienteA->id,
            ChatbotSessionKeys::SESSION_CHATBOT_OTP_VERIFIED => true,
        ])->postJson(route('chatbot.agendar'), $payload);

        $response->assertStatus(403)
            ->assertJsonPath('ok', false);

        $this->assertDatabaseMissing('citas_medicas', [
            'paciente_id' => $pacienteB->id,
            'doctor_id' => $doctor->id,
            'fecha' => $fecha,
        ]);
    }

    /**
     * Caso D: Con identidad OTP válida, modificar email o cédula en el body no debe permitir
     * cambiar la identidad efectiva (403).
     */
    public function test_caso_d_manipulating_email_or_cedula_in_body_is_rejected(): void
    {
        Event::fake([CitaAgendada::class]);
        Mail::fake();
        Queue::fake();

        [$doctor, $especialidad, $fecha] = $this->createDoctorWithAvailability();

        $paciente = $this->createPatient('Paciente Legítimo', '1717171717', 'paciente.legitimo@example.com');

        // Intento 1: Mandar otro correo
        $responseEmail = $this->withSession([
            ChatbotSessionKeys::SESSION_CHATBOT_USER_ID => $paciente->id,
            ChatbotSessionKeys::SESSION_CHATBOT_OTP_VERIFIED => true,
        ])->postJson(route('chatbot.agendar'), [
            'especialidad_id' => $especialidad->id,
            'doctor_id' => $doctor->id,
            'fecha' => $fecha,
            'hora' => '10:00',
            'motivo_consulta' => 'Chequeo general',
            'email' => 'otro.correo@example.com',
        ]);

        $responseEmail->assertStatus(403)
            ->assertJsonPath('ok', false);

        // Intento 2: Mandar otra cédula
        $responseCedula = $this->withSession([
            ChatbotSessionKeys::SESSION_CHATBOT_USER_ID => $paciente->id,
            ChatbotSessionKeys::SESSION_CHATBOT_OTP_VERIFIED => true,
        ])->postJson(route('chatbot.agendar'), [
            'especialidad_id' => $especialidad->id,
            'doctor_id' => $doctor->id,
            'fecha' => $fecha,
            'hora' => '10:00',
            'motivo_consulta' => 'Chequeo general',
            'cedula' => '0999999999',
        ]);

        $responseCedula->assertStatus(403)
            ->assertJsonPath('ok', false);
    }

    /**
     * Caso E: Un atacante conoce email+cédula de un paciente existente pero no ha verificado
     * esa identidad mediante OTP. No debe poder agendar ni reutilizar esa cuenta (401).
     */
    public function test_caso_e_unverified_attacker_knowing_victim_credentials_cannot_book(): void
    {
        [$doctor, $especialidad, $fecha] = $this->createDoctorWithAvailability();

        $victima = $this->createPatient('Víctima Existente', '1010101010', 'victima@example.com');

        $payload = [
            'nombre' => $victima->name,
            'cedula' => $victima->dni,
            'email' => $victima->email,
            'telefono' => '0991234567',
            'especialidad_id' => $especialidad->id,
            'doctor_id' => $doctor->id,
            'fecha' => $fecha,
            'hora' => '10:00',
            'motivo_consulta' => 'Intento de reserva fraudulenta',
        ];

        // Sin sesión OTP
        $response = $this->postJson(route('chatbot.agendar'), $payload);

        $response->assertStatus(401)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error', 'identity_required');

        $this->assertDatabaseMissing('citas_medicas', [
            'paciente_id' => $victima->id,
            'doctor_id' => $doctor->id,
            'fecha' => $fecha,
        ]);
    }

    /**
     * Caso F: El flujo CAPTCHA por sí solo no debe marcar OTP/identidad como verificada.
     */
    public function test_caso_f_captcha_flow_does_not_set_otp_or_identity_verified_keys(): void
    {
        $session = session()->all();

        $this->assertArrayNotHasKey(ChatbotSessionKeys::SESSION_CHATBOT_OTP_VERIFIED, $session);
        $this->assertArrayNotHasKey(ChatbotSessionKeys::SESSION_CHATBOT_USER_ID, $session);
    }

    /**
     * Caso H: Si intervienen dependientes, un paciente no puede proporcionar arbitrariamente
     * el ID de un dependiente perteneciente a otro paciente (403).
     */
    public function test_caso_h_verified_patient_cannot_use_foreign_dependent(): void
    {
        Event::fake([CitaAgendada::class]);
        Mail::fake();
        Queue::fake();

        [$doctor, $especialidad, $fecha] = $this->createDoctorWithAvailability();

        $pacienteA = $this->createPatient('Titular A', '1111111111', 'titular.a@example.com');
        $pacienteB = $this->createPatient('Titular B', '2222222222', 'titular.b@example.com');

        $dependienteDeB = Dependiente::create([
            'user_id' => $pacienteB->id,
            'activo' => true,
            'nombre' => 'Hijo de B',
            'dni' => '3333333333',
            'parentesco' => 'hijo',
            'fecha_nacimiento' => '2018-01-01',
        ]);

        $payload = [
            'especialidad_id' => $especialidad->id,
            'doctor_id' => $doctor->id,
            'fecha' => $fecha,
            'hora' => '10:00',
            'motivo_consulta' => 'Cita médica dependiente ajeno',
            'dependiente_id' => $dependienteDeB->id,
        ];

        $response = $this->withSession([
            ChatbotSessionKeys::SESSION_CHATBOT_USER_ID => $pacienteA->id,
            ChatbotSessionKeys::SESSION_CHATBOT_OTP_VERIFIED => true,
        ])->postJson(route('chatbot.agendar'), $payload);

        $response->assertStatus(403)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'El dependiente seleccionado no pertenece a tu cuenta.');

        $this->assertDatabaseMissing('citas_medicas', [
            'dependiente_id' => $dependienteDeB->id,
        ]);
    }

    /**
     * Agendamiento exitoso legítimo para paciente debidamente verificado por OTP.
     */
    public function test_legitimate_verified_patient_schedules_appointment_successfully(): void
    {
        Event::fake([CitaAgendada::class]);
        Mail::fake();
        Queue::fake();

        [$doctor, $especialidad, $fecha] = $this->createDoctorWithAvailability();

        $paciente = $this->createPatient('Paciente Legítimo', '1718192021', 'paciente.legitimo@example.com');

        $payload = [
            'especialidad_id' => $especialidad->id,
            'doctor_id' => $doctor->id,
            'fecha' => $fecha,
            'hora' => '10:00',
            'motivo_consulta' => 'Consulta preventiva regular',
        ];

        $response = $this->withSession([
            ChatbotSessionKeys::SESSION_CHATBOT_USER_ID => $paciente->id,
            ChatbotSessionKeys::SESSION_CHATBOT_OTP_VERIFIED => true,
        ])->postJson(route('chatbot.agendar'), $payload);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('usuario_creado', false)
            ->assertJsonPath('credenciales_enviadas', false);

        $this->assertDatabaseHas('citas_medicas', [
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => $fecha,
            'hora' => '10:00:00',
        ]);
    }

    public function test_ensure_chatbot_identity_middleware_rejects_suspended_or_blocked_patient_and_clears_session(): void
    {
        [$doctor, $especialidad, $fecha] = $this->createDoctorWithAvailability();
        $paciente = $this->createPatient('Paciente Suspendido', '0928374650', 'paciente.bloqueado@example.com');

        // Admin blocks patient after OTP was granted
        $paciente->update([
            'status' => User::STATUS_BLOCKED,
        ]);

        $payload = [
            'especialidad_id' => $especialidad->id,
            'doctor_id' => $doctor->id,
            'fecha' => $fecha,
            'hora' => '10:00',
            'motivo_consulta' => 'Consulta medica de control general',
        ];

        $response = $this->withSession([
            ChatbotSessionKeys::SESSION_CHATBOT_USER_ID => $paciente->id,
            ChatbotSessionKeys::SESSION_CHATBOT_OTP_VERIFIED => true,
        ])->postJson(route('chatbot.agendar'), $payload);

        $response->assertStatus(403)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error', 'user_inactive');

        $this->assertFalse(session()->has(ChatbotSessionKeys::SESSION_CHATBOT_OTP_VERIFIED));
        $this->assertFalse(session()->has(ChatbotSessionKeys::SESSION_CHATBOT_USER_ID));
    }

    public function test_ensure_chatbot_identity_middleware_rejects_suspended_patient_even_for_dependent_booking(): void
    {
        [$doctor, $especialidad, $fecha] = $this->createDoctorWithAvailability();
        $paciente = $this->createPatient('Paciente Titular', '0928374651', 'titular.bloqueado@example.com');

        $dependiente = Dependiente::create([
            'user_id' => $paciente->id,
            'nombre' => 'Hijo Titular',
            'dni' => '0928374652',
            'fecha_nacimiento' => '2015-05-10',
            'sexo' => 'Masculino',
            'parentesco' => 'Hijo',
            'activo' => true,
        ]);

        // Admin suspends patient
        $paciente->update([
            'suspended_until' => Carbon::now('America/Guayaquil')->addDays(3),
        ]);

        $payload = [
            'especialidad_id' => $especialidad->id,
            'doctor_id' => $doctor->id,
            'fecha' => $fecha,
            'hora' => '10:00',
            'motivo_consulta' => 'Consulta pediatrica de control general',
            'dependiente_id' => $dependiente->id,
        ];

        $response = $this->withSession([
            ChatbotSessionKeys::SESSION_CHATBOT_USER_ID => $paciente->id,
            ChatbotSessionKeys::SESSION_CHATBOT_OTP_VERIFIED => true,
        ])->postJson(route('chatbot.agendar'), $payload);

        $response->assertStatus(403)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error', 'user_inactive');

        $this->assertFalse(session()->has(ChatbotSessionKeys::SESSION_CHATBOT_OTP_VERIFIED));
        $this->assertFalse(session()->has(ChatbotSessionKeys::SESSION_CHATBOT_USER_ID));
    }

    private function createPatient(string $nombre, string $dni, string $email): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'paciente']);
        $user = User::factory()->create([
            'name' => $nombre,
            'email' => $email,
            'dni' => $dni,
            'status' => User::STATUS_ACTIVE,
        ]);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function createDoctorWithAvailability(): array
    {
        $doctorRole = Role::query()->firstOrCreate(['name' => 'doctor']);

        $especialidad = Especialidad::query()->create([
            'nombre' => 'Medicina General',
            'descripcion' => 'Consulta general',
            'icono' => 'ri-stethoscope-line',
            'activo' => true,
            'orden' => 1,
        ]);

        $doctor = User::factory()->create([
            'name' => 'Doctor Chatbot',
            'email' => 'doctor.chatbot@example.com',
            'status' => User::STATUS_ACTIVE,
            'active' => true,
            'suspended_until' => null,
            'precio_consulta' => 25.00,
            'moneda' => 'USD',
        ]);
        $doctor->roles()->sync([$doctorRole->id]);
        $doctor->especialidades()->sync([$especialidad->id]);

        $fecha = Carbon::now(config('app.timezone', 'America/Guayaquil'))
            ->addDays(2)
            ->toDateString();

        Horario::query()->create([
            'doctor_id' => $doctor->id,
            'fecha' => $fecha,
            'hora_inicio' => '09:00',
            'hora_fin' => '12:00',
            'intervalo_minutos' => 30,
        ]);

        return [$doctor, $especialidad, $fecha];
    }
}
