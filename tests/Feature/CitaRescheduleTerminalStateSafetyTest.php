<?php

namespace Tests\Feature;

use App\Jobs\NotificarCambioEstadoCitaJob;
use App\Mail\CambioEstadoCitaMail;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Horario;
use App\Models\Role;
use App\Models\User;
use App\Support\ChatbotSessionKeys;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CitaRescheduleTerminalStateSafetyTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-01 08:00:00');
        Mail::fake();
        $this->seedRoles();
        $this->withoutMiddleware([
            \App\Http\Middleware\EnsureCaptchaVerified::class,
            \App\Http\Middleware\EnsureChatbotIdentityVerified::class,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seedRoles(): void
    {
        foreach (['superadmin', 'administrador', 'doctor', 'paciente', 'laboratorio'] as $r) {
            Role::firstOrCreate(['name' => $r]);
        }
    }

    private function createRoleUser(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'status' => 'active',
            'email_verified_at' => now(),
        ], $attributes));

        $roleModel = Role::where('name', $role)->first();
        if ($roleModel) {
            $user->roles()->attach($roleModel->id);
        }

        return $user;
    }

    private function createDoctorWithSchedule(string $date = '2026-08-05'): array
    {
        $doctor = $this->createRoleUser('doctor');
        $esp = Especialidad::firstOrCreate(['nombre' => 'Medicina General']);
        $doctor->especialidades()->syncWithoutDetaching([$esp->id]);

        Horario::create([
            'doctor_id' => $doctor->id,
            'fecha' => $date,
            'hora_inicio' => '08:00:00',
            'hora_fin' => '18:00:00',
            'intervalo_minutos' => 30,
        ]);

        return [$doctor, $esp];
    }

    /**
     * R1 — Web: Cita válida y reprogramable continúa permitiendo reprogramación legítima.
     */
    public function test_r1_web_legitimate_reschedule_succeeds(): void
    {
        Queue::fake();
        [$doctor, $esp] = $this->createDoctorWithSchedule('2026-08-05');
        $paciente = $this->createRoleUser('paciente');

        $cita = Cita::create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-05',
            'hora' => '09:00:00',
            'motivo_consulta' => 'Motivo inicial',
            'estado' => Cita::ESTADO_PENDIENTE,
            'activo' => true,
        ]);

        $response = $this->actingAs($paciente)->put(route('paciente.editar-cita.update', $cita->id), [
            'fecha' => '2026-08-05',
            'hora' => '11:00',
            'motivo_consulta' => 'Motivo actualizado legítimo',
        ]);

        $response->assertRedirect(route('paciente.citas'));
        $response->assertSessionHas('success', 'Cita reagendada.');

        $cita->refresh();
        $this->assertEquals('2026-08-05', Carbon::parse($cita->fecha)->format('Y-m-d'));
        $this->assertEquals('11:00:00', $cita->hora);
        $this->assertEquals('Motivo actualizado legítimo', $cita->motivo_consulta);
        $this->assertEquals(Cita::ESTADO_PENDIENTE, $cita->estado);
        $this->assertTrue((bool) $cita->activo);

        Queue::assertPushed(NotificarCambioEstadoCitaJob::class);
    }

    /**
     * R2 — Web: Cita cancelada no puede ser modificada ni reactivada vía PUT directo.
     */
    public function test_r2_web_cancelled_cita_cannot_be_rescheduled_or_reactivated(): void
    {
        Queue::fake();
        [$doctor, $esp] = $this->createDoctorWithSchedule('2026-08-05');
        $paciente = $this->createRoleUser('paciente');

        $originalFecha = '2026-08-05';
        $originalHora = '09:00:00';

        $cita = Cita::create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => $originalFecha,
            'hora' => $originalHora,
            'motivo_consulta' => 'Cita que fue cancelada',
            'estado' => Cita::ESTADO_CANCELADA,
            'activo' => false,
        ]);

        $response = $this->actingAs($paciente)->put(route('paciente.editar-cita.update', $cita->id), [
            'fecha' => '2026-08-05',
            'hora' => '14:00',
            'motivo_consulta' => 'Intento revivir cita',
        ]);

        $response->assertSessionHas('error', 'Esta cita no puede ser modificada.');

        $cita->refresh();
        $this->assertEquals(Cita::ESTADO_CANCELADA, $cita->estado, 'El estado cancelada debe permanecer cancelada');
        $this->assertFalse((bool) $cita->activo, 'El campo activo debe permanecer false');
        $this->assertEquals($originalFecha, Carbon::parse($cita->fecha)->format('Y-m-d'));
        $this->assertEquals($originalHora, $cita->hora);
        $this->assertEquals('Cita que fue cancelada', $cita->motivo_consulta);

        Queue::assertNotPushed(NotificarCambioEstadoCitaJob::class);
    }

    /**
     * R3 — Web: Estados terminales 'realizada' y 'no_se_presento' son rechazados y no se reabren.
     */
    public function test_r3_web_terminal_states_realizada_and_no_se_presento_are_rejected(): void
    {
        [$doctor, $esp] = $this->createDoctorWithSchedule('2026-08-05');
        $paciente = $this->createRoleUser('paciente');

        // Test realizada
        $citaRealizada = Cita::create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-05',
            'hora' => '09:00:00',
            'motivo_consulta' => 'Cita ya atendida',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => false,
        ]);

        $respRealizada = $this->actingAs($paciente)->put(route('paciente.editar-cita.update', $citaRealizada->id), [
            'fecha' => '2026-08-05',
            'hora' => '15:00',
            'motivo_consulta' => 'Intento modificar atendida',
        ]);
        $respRealizada->assertSessionHas('error', 'Esta cita no puede ser modificada.');
        $this->assertEquals(Cita::ESTADO_REALIZADA, $citaRealizada->refresh()->estado);
        $this->assertFalse((bool) $citaRealizada->activo);

        // Test no_se_presento
        $citaNoShow = Cita::create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-05',
            'hora' => '10:00:00',
            'motivo_consulta' => 'Cita no show',
            'estado' => Cita::ESTADO_NO_SE_PRESENTO,
            'activo' => false,
        ]);

        $respNoShow = $this->actingAs($paciente)->put(route('paciente.editar-cita.update', $citaNoShow->id), [
            'fecha' => '2026-08-05',
            'hora' => '16:00',
            'motivo_consulta' => 'Intento modificar no show',
        ]);
        $respNoShow->assertSessionHas('error', 'Esta cita no puede ser modificada.');
        $this->assertEquals(Cita::ESTADO_NO_SE_PRESENTO, $citaNoShow->refresh()->estado);
        $this->assertFalse((bool) $citaNoShow->activo);
    }

    /**
     * R4 — Chatbot: POST /chatbot/reagendar sobre cita cancelada es rechazado.
     */
    public function test_r4_chatbot_reschedule_on_cancelled_cita_is_rejected(): void
    {
        Queue::fake();
        [$doctor, $esp] = $this->createDoctorWithSchedule('2026-08-05');
        $paciente = $this->createRoleUser('paciente');

        $cita = Cita::create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-05',
            'hora' => '09:00:00',
            'motivo_consulta' => 'Cita cancelada antes de chatbot',
            'estado' => Cita::ESTADO_CANCELADA,
            'activo' => false,
        ]);

        $response = $this->withSession([
            ChatbotSessionKeys::SESSION_CHATBOT_USER_ID => $paciente->id,
            ChatbotSessionKeys::SESSION_CHATBOT_OTP_VERIFIED => true,
        ])->postJson(route('chatbot.reagendar'), [
            'cita_id' => $cita->id,
            'fecha' => '2026-08-05',
            'hora' => '12:00',
            'motivo_consulta' => 'Intento reagendar cancelada via chatbot',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'Esta cita ya no puede ser reprogramada.');

        $cita->refresh();
        $this->assertEquals(Cita::ESTADO_CANCELADA, $cita->estado);
        $this->assertFalse((bool) $cita->activo);
        $this->assertEquals('09:00:00', $cita->hora);

        Queue::assertNotPushed(NotificarCambioEstadoCitaJob::class);
    }

    /**
     * R5 — Carrera Web (TOCTOU):
     * Simula determinísticamente la condición de carrera:
     * Request A inicia reprogramación cuando la cita parece pendiente fuera de transacción;
     * Un Request concurrente B cancela la cita;
     * Request A entra al lock transaccional, lee el estado fresco, detecta cancelada y rechaza.
     */
    public function test_r5_web_toctou_race_condition_detected_under_lock_and_aborted(): void
    {
        Queue::fake();
        [$doctor, $esp] = $this->createDoctorWithSchedule('2026-08-05');
        $paciente = $this->createRoleUser('paciente');

        $cita = Cita::create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-05',
            'hora' => '09:00:00',
            'motivo_consulta' => 'Cita valida al inicio',
            'estado' => Cita::ESTADO_PENDIENTE,
            'activo' => true,
        ]);

        // Simular que justo después del chequeo inicial en controlador (pero antes de DB::transaction),
        // un doctor/admin ejecuta concurrentemente la cancelación en la BD:
        DB::beforeExecuting(function ($query, $bindings) use ($cita) {
            static $cancelled = false;
            // Se dispara durante la validación del profesional/horario, antes de entrar a DB::transaction
            if (! $cancelled && str_contains($query, 'especialidades')) {
                DB::table('citas_medicas')
                    ->where('id', $cita->id)
                    ->update([
                        'estado' => Cita::ESTADO_CANCELADA,
                        'activo' => false,
                    ]);
                $cancelled = true;
            }
        });

        $response = $this->actingAs($paciente)->put(route('paciente.editar-cita.update', $cita->id), [
            'fecha' => '2026-08-05',
            'hora' => '14:00',
            'motivo_consulta' => 'Reprogramacion que sufrio carrera',
        ]);

        $response->assertSessionHasErrors(['error' => 'Esta cita no puede ser modificada.']);

        $cita->refresh();
        $this->assertEquals(Cita::ESTADO_CANCELADA, $cita->estado, 'La cita cancelada concurrentemente NO fue sobreescrita a pendiente');
        $this->assertFalse((bool) $cita->activo, 'Activo sigue siendo false');
        $this->assertEquals('09:00:00', $cita->hora, 'La hora no fue cambiada por la reprogramación fallida');

        Queue::assertNotPushed(NotificarCambioEstadoCitaJob::class);
    }

    /**
     * R6 — Carrera Chatbot (TOCTOU):
     * Simula determinísticamente la cancelación concurrente antes del lock en /chatbot/reagendar.
     */
    public function test_r6_chatbot_toctou_race_condition_detected_under_lock_and_aborted(): void
    {
        Queue::fake();
        [$doctor, $esp] = $this->createDoctorWithSchedule('2026-08-05');
        $paciente = $this->createRoleUser('paciente');

        $cita = Cita::create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-05',
            'hora' => '09:00:00',
            'motivo_consulta' => 'Cita inicial chatbot',
            'estado' => Cita::ESTADO_PENDIENTE,
            'activo' => true,
        ]);

        DB::beforeExecuting(function ($query, $bindings) use ($cita) {
            static $cancelledChatbot = false;
            if (! $cancelledChatbot && (str_contains($query, 'doctor') || str_contains($query, 'horarios') || str_contains($query, 'users'))) {
                DB::table('citas_medicas')
                    ->where('id', $cita->id)
                    ->update([
                        'estado' => Cita::ESTADO_CANCELADA,
                        'activo' => false,
                    ]);
                $cancelledChatbot = true;
            }
        });

        $response = $this->withSession([
            ChatbotSessionKeys::SESSION_CHATBOT_USER_ID => $paciente->id,
            ChatbotSessionKeys::SESSION_CHATBOT_OTP_VERIFIED => true,
        ])->postJson(route('chatbot.reagendar'), [
            'cita_id' => $cita->id,
            'fecha' => '2026-08-05',
            'hora' => '15:00',
            'motivo_consulta' => 'Reprogramacion chatbot con carrera',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'Esta cita ya no puede ser reprogramada.');

        $cita->refresh();
        $this->assertEquals(Cita::ESTADO_CANCELADA, $cita->estado);
        $this->assertFalse((bool) $cita->activo);
        $this->assertEquals('09:00:00', $cita->hora);

        Queue::assertNotPushed(NotificarCambioEstadoCitaJob::class);
    }

    /**
     * R7 — No notificación contradictoria:
     * Si la reprogramación es rechazada por estado terminal, no se envían correos ni jobs de éxito.
     */
    public function test_r7_no_contradictory_notifications_on_rejected_reschedule(): void
    {
        Mail::fake();
        Queue::fake();

        [$doctor, $esp] = $this->createDoctorWithSchedule('2026-08-05');
        $paciente = $this->createRoleUser('paciente');

        $cita = Cita::create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-05',
            'hora' => '09:00:00',
            'motivo_consulta' => 'Cita cancelada',
            'estado' => Cita::ESTADO_CANCELADA,
            'activo' => false,
        ]);

        // Web attempt
        $this->actingAs($paciente)->put(route('paciente.editar-cita.update', $cita->id), [
            'fecha' => '2026-08-05',
            'hora' => '12:00',
            'motivo_consulta' => 'Intento web',
        ]);

        // Chatbot attempt
        $this->withSession([
            ChatbotSessionKeys::SESSION_CHATBOT_USER_ID => $paciente->id,
            ChatbotSessionKeys::SESSION_CHATBOT_OTP_VERIFIED => true,
        ])->postJson(route('chatbot.reagendar'), [
            'cita_id' => $cita->id,
            'fecha' => '2026-08-05',
            'hora' => '12:00',
            'motivo_consulta' => 'Intento chatbot',
        ]);

        Queue::assertNotPushed(NotificarCambioEstadoCitaJob::class);
        Mail::assertNothingQueued();
        Mail::assertNothingSent();
    }

    /**
     * R8 — Cita ajena:
     * Confirmar que no se puede reprogramar la cita de otro paciente ni por web ni por chatbot.
     */
    public function test_r8_reschedule_rejects_foreign_patient_cita_web_and_chatbot(): void
    {
        [$doctor, $esp] = $this->createDoctorWithSchedule('2026-08-05');
        $pacienteDueno = $this->createRoleUser('paciente');
        $pacienteAtacante = $this->createRoleUser('paciente');

        $cita = Cita::create([
            'paciente_id' => $pacienteDueno->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-05',
            'hora' => '09:00:00',
            'motivo_consulta' => 'Cita del dueño',
            'estado' => Cita::ESTADO_PENDIENTE,
            'activo' => true,
        ]);

        // Web attempt by atacante
        $webResp = $this->actingAs($pacienteAtacante)->put(route('paciente.editar-cita.update', $cita->id), [
            'fecha' => '2026-08-05',
            'hora' => '10:00',
            'motivo_consulta' => 'Hack web',
        ]);
        $webResp->assertSessionHas('error', 'No puedes modificar esta cita.');

        // Chatbot attempt by atacante
        $chatResp = $this->withSession([
            ChatbotSessionKeys::SESSION_CHATBOT_USER_ID => $pacienteAtacante->id,
            ChatbotSessionKeys::SESSION_CHATBOT_OTP_VERIFIED => true,
        ])->postJson(route('chatbot.reagendar'), [
            'cita_id' => $cita->id,
            'fecha' => '2026-08-05',
            'hora' => '10:00',
            'motivo_consulta' => 'Hack chatbot',
        ]);
        $chatResp->assertStatus(403)->assertJsonPath('ok', false);

        $cita->refresh();
        $this->assertEquals($pacienteDueno->id, $cita->paciente_id);
        $this->assertEquals('09:00:00', $cita->hora);
        $this->assertEquals('Cita del dueño', $cita->motivo_consulta);
    }
}
