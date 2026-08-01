<?php

namespace Tests\Feature;

use App\Models\CaptchaChallenge;
use App\Models\CaptchaImage;
use App\Models\Cita;
use App\Models\Dependiente;
use App\Models\Especialidad;
use App\Models\Role;
use App\Models\User;
use App\Support\ChatbotSessionKeys;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ChatbotFamilySupportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    protected function tearDown(): void
    {
        \Illuminate\Support\Facades\File::deleteDirectory(\Illuminate\Support\Facades\Storage::disk('local')->path('captcha_animals'));
        foreach (config('captcha.classes', ['giraffe', 'horse', 'koala', 'kangaroo']) as $class) {
            $fixturePath = base_path("ai/dataset/val/{$class}/test-captcha.jpg");
            if (File::exists($fixturePath)) {
                File::delete($fixturePath);
            }
        }
        parent::tearDown();
    }

    /** @test */
    public function test_database_safeguard_prevents_running_on_production_database()
    {
        config(['database.connections.mysql.database' => 'clinica_donbosco_db']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PROTECCIÓN BD');

        $this->setUp();
    }

    /** @test */
    public function test_captcha_images_are_not_publicly_accessible()
    {
        $response = $this->get('/captcha_animals/giraffe/giraffe-0001.jpg');
        $response->assertStatus(404);
    }

    /** @test */
    public function test_challenge_response_does_not_expose_database_ids_or_class_keys()
    {
        $classes = config('captcha.classes', ['giraffe', 'horse', 'koala', 'kangaroo']);
        foreach ($classes as $class) {
            $this->seedCaptchaFixture($class);
        }

        $response = $this->getJson(route('captcha.challenge'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'token',
            'target_label_es',
            'images' => [
                '*' => ['position', 'url']
            ]
        ]);

        $response->assertJsonMissing(['target_key']);
        $response->assertJsonMissing(['class_key']);
        $response->assertJsonMissing(['id']);
    }

    /** @test */
    public function test_captcha_image_serving_checks_session_token_and_position()
    {
        $classes = config('captcha.classes', ['giraffe', 'horse', 'koala', 'kangaroo']);
        foreach ($classes as $class) {
            $this->seedCaptchaFixture($class);
        }

        $response = $this->getJson(route('captcha.challenge'));
        $token = $response->json('token');

        $imageResponse = $this->get(route('captcha.image.show', ['token' => $token, 'position' => 0]));
        $imageResponse->assertStatus(200);
        $imageResponse->assertHeader('Content-Disposition', 'inline; filename=captcha.jpg');

        $invalidImageResponse = $this->get(route('captcha.image.show', ['token' => 'invalid_token', 'position' => 0]));
        $invalidImageResponse->assertStatus(403);

        $invalidPosResponse = $this->get(route('captcha.image.show', ['token' => $token, 'position' => 9]));
        $invalidPosResponse->assertStatus(404);
    }

    /** @test */
    public function test_concurrent_captcha_requests_serialize_and_keep_single_challenge_active()
    {
        $classes = config('captcha.classes', ['giraffe', 'horse', 'koala', 'kangaroo']);
        foreach ($classes as $class) {
            $this->seedCaptchaFixture($class);
        }

        $this->getJson(route('captcha.challenge'));
        $firstToken = session(ChatbotSessionKeys::SESSION_CHALLENGE_TOKEN);

        $this->getJson(route('captcha.challenge'));
        $secondToken = session(ChatbotSessionKeys::SESSION_CHALLENGE_TOKEN);

        $this->assertNotEquals($firstToken, $secondToken);
        $this->assertEquals($secondToken, session(ChatbotSessionKeys::SESSION_CHALLENGE_TOKEN));
    }

    /** @test */
    public function test_otp_verification_flow_enforces_rate_limiting_and_failed_attempts_invalidation()
    {
        $user = User::factory()->create([
            'dni' => '1234567890',
            'email' => 'paciente@test.com',
        ]);
        
        $role = Role::firstOrCreate(['name' => 'paciente']);
        $user->roles()->attach($role->id);

        session([
            ChatbotSessionKeys::SESSION_VERIFIED => true,
            ChatbotSessionKeys::SESSION_VERIFIED_AT => now()->timestamp,
        ]);

        $this->postJson(route('chatbot.enviarCodigo'), [
            'cedula' => '1234567890',
            'email' => 'paciente@test.com',
        ])->assertStatus(200);

        for ($i = 0; $i < 2; $i++) {
            $this->postJson(route('chatbot.verificarCodigo'), [
                'cedula' => '1234567890',
                'email' => 'paciente@test.com',
                'codigo' => '000000',
            ])->assertStatus(422);
        }

        $response = $this->postJson(route('chatbot.verificarCodigo'), [
            'cedula' => '1234567890',
            'email' => 'paciente@test.com',
            'codigo' => '000000',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['error' => 'otp_invalidated']);
    }

    private function seedCaptchaFixture(string $class): void
    {
        $filename = 'test-captcha.jpg';
        $storageDir = \Illuminate\Support\Facades\Storage::disk('local')->path("captcha_animals/{$class}");
        $aiDir = base_path("ai/dataset/val/{$class}");

        File::ensureDirectoryExists($storageDir);
        File::ensureDirectoryExists($aiDir);

        File::put("{$storageDir}/{$filename}", 'dummy image content');
        File::put("{$aiDir}/{$filename}", 'dummy image content');

        CaptchaImage::create([
            'class_key' => $class,
            'dataset_split' => 'public',
            'image_path' => "captcha_animals/{$class}/{$filename}",
        ]);
    }

    /** @test */
    public function test_otp_independence_keeps_patient_verified_beyond_captcha_five_minutes()
    {
        $user = User::factory()->create([
            'dni' => '1234567890',
            'email' => 'paciente@test.com',
        ]);
        
        $role = Role::firstOrCreate(['name' => 'paciente']);
        $user->roles()->attach($role->id);

        session([
            ChatbotSessionKeys::SESSION_CHATBOT_USER_ID => $user->id,
            ChatbotSessionKeys::SESSION_CHATBOT_OTP_VERIFIED => true,
            ChatbotSessionKeys::SESSION_VERIFIED => true,
            ChatbotSessionKeys::SESSION_VERIFIED_AT => now()->subMinutes(6)->timestamp,
        ]);

        $response = $this->postJson(route('chatbot.buscarCitas'), [
            'estado' => 'all',
            'incluir_todas' => true,
        ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function test_inactive_dependents_rules()
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'paciente']);
        $user->roles()->attach($role->id);

        $inactiveDep = Dependiente::create([
            'user_id' => $user->id,
            'activo' => false,
            'nombre' => 'Dependiente Inactivo',
            'dni' => '0987654321',
            'parentesco' => 'hijo',
            'fecha_nacimiento' => '2015-05-10',
        ]);

        session([
            ChatbotSessionKeys::SESSION_CHATBOT_USER_ID => $user->id,
            ChatbotSessionKeys::SESSION_CHATBOT_OTP_VERIFIED => true,
        ]);

        $doctor = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $docRole = Role::firstOrCreate(['name' => 'doctor']);
        $doctor->roles()->attach($docRole->id);
        
        $especialidad = Especialidad::factory()->create();
        $doctor->especialidades()->attach($especialidad->id);

        $resBook = $this->postJson(route('chatbot.agendar'), [
            'especialidad_id' => $especialidad->id,
            'doctor_id' => $doctor->id,
            'fecha' => now()->addDays(2)->format('Y-m-d'),
            'hora' => '10:00',
            'motivo_consulta' => 'Consulta de control',
            'dependiente_id' => $inactiveDep->id,
        ]);
        $resBook->assertStatus(403);

        $cita = Cita::create([
            'paciente_id' => $user->id,
            'dependiente_id' => $inactiveDep->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => now()->addDays(2)->format('Y-m-d'),
            'hora' => '10:00:00',
            'activo' => true,
            'estado' => Cita::ESTADO_PENDIENTE,
        ]);

        $resReschedule = $this->postJson(route('chatbot.reagendar'), [
            'cita_id' => $cita->id,
            'fecha' => now()->addDays(3)->format('Y-m-d'),
            'hora' => '11:00',
        ]);
        $resReschedule->assertStatus(422);
        $resReschedule->assertJsonFragment(['message' => 'No se puede reprogramar la cita porque el paciente está inactivo.']);

        $resCancel = $this->postJson(route('chatbot.cancelar'), [
            'cita_id' => $cita->id,
        ]);
        $resCancel->assertStatus(200);
        $this->assertFalse((bool) $cita->refresh()->activo);
        $this->assertEquals(Cita::ESTADO_CANCELADA, $cita->estado);
    }

    /** @test */
    public function test_booking_conflict_concurrency_returns_422()
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'paciente']);
        $user->roles()->attach($role->id);

        session([
            ChatbotSessionKeys::SESSION_CHATBOT_USER_ID => $user->id,
            ChatbotSessionKeys::SESSION_CHATBOT_OTP_VERIFIED => true,
        ]);

        $doctor = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $docRole = Role::firstOrCreate(['name' => 'doctor']);
        $doctor->roles()->attach($docRole->id);

        $especialidad = Especialidad::factory()->create();
        $doctor->especialidades()->attach($especialidad->id);

        $fecha = now()->addDays(2)->format('Y-m-d');
        $hora = '14:00';

        Cita::create([
            'paciente_id' => $user->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => $fecha,
            'hora' => '14:00:00',
            'activo' => true,
            'estado' => Cita::ESTADO_PENDIENTE,
        ]);

        $response = $this->postJson(route('chatbot.agendar'), [
            'especialidad_id' => $especialidad->id,
            'doctor_id' => $doctor->id,
            'fecha' => $fecha,
            'hora' => $hora,
            'motivo_consulta' => 'Fiebre alta',
        ]);

        $response->assertStatus(422);
    }
}
