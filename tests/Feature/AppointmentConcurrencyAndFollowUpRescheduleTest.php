<?php

namespace Tests\Feature;

use App\Jobs\EnviarConfirmacionCitaJob;
use App\Jobs\NotificarCambioEstadoCitaJob;
use App\Mail\CambioEstadoCitaMail;
use App\Models\Cita;
use App\Models\CitaEvento;
use App\Models\ClinicalRecord;
use App\Models\Dependiente;
use App\Models\Especialidad;
use App\Models\Horario;
use App\Models\NotaSoap;
use App\Models\Role;
use App\Models\User;
use App\Services\ClinicalRecordService;
use App\Services\ProfessionalScheduleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AppointmentConcurrencyAndFollowUpRescheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seedRoles();
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

    private function createDoctorWithSchedule(string $date = '2026-08-03'): array
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

    /** 1. Mismo doctor, mismo día, 12:00 y 15:00: ambas citas se crean */
    public function test_same_doctor_same_day_different_times_12_and_15_are_allowed(): void
    {
        [$doctor, $esp] = $this->createDoctorWithSchedule('2026-08-03');
        $paciente1 = $this->createRoleUser('paciente');
        $paciente2 = $this->createRoleUser('paciente');

        // First appointment at 15:00
        $this->actingAs($paciente1)->post(route('paciente.crear-cita.store'), [
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-03',
            'hora' => '15:00',
            'motivo_consulta' => 'Consulta 15:00',
        ])->assertRedirect();

        // Second appointment at 12:00
        $response = $this->actingAs($paciente2)->post(route('paciente.crear-cita.store'), [
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-03',
            'hora' => '12:00',
            'motivo_consulta' => 'Consulta 12:00',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('citas_medicas', [
            'doctor_id' => $doctor->id,
            'fecha' => '2026-08-03 00:00:00',
            'hora' => '15:00:00',
        ]);
        $this->assertDatabaseHas('citas_medicas', [
            'doctor_id' => $doctor->id,
            'fecha' => '2026-08-03 00:00:00',
            'hora' => '12:00:00',
        ]);
    }

    /** 2. Mismo doctor y mismo slot: solo una se crea */
    public function test_same_doctor_same_slot_is_rejected(): void
    {
        [$doctor, $esp] = $this->createDoctorWithSchedule('2026-08-03');
        $paciente1 = $this->createRoleUser('paciente');
        $paciente2 = $this->createRoleUser('paciente');

        $this->actingAs($paciente1)->post(route('paciente.crear-cita.store'), [
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-03',
            'hora' => '15:00',
            'motivo_consulta' => 'Primera reserva 15:00',
        ])->assertRedirect();

        $resp2 = $this->actingAs($paciente2)->post(route('paciente.crear-cita.store'), [
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-03',
            'hora' => '15:00',
            'motivo_consulta' => 'Segunda reserva 15:00',
        ]);

        $resp2->assertSessionHasErrors();
        $this->assertEquals(1, Cita::where('doctor_id', $doctor->id)->whereDate('fecha', '2026-08-03')->where('hora', '15:00:00')->count());
    }

    /** 3. Solapamiento parcial: rechazado */
    public function test_partial_overlap_is_rejected(): void
    {
        [$doctor, $esp] = $this->createDoctorWithSchedule('2026-08-03');
        $svc = app(ProfessionalScheduleService::class);

        Cita::create([
            'paciente_id' => $this->createRoleUser('paciente')->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-03',
            'hora' => '15:00:00',
            'estado' => Cita::ESTADO_CONFIRMADA,
            'activo' => true,
        ]);

        $slot1515 = Carbon::createFromFormat('H:i', '15:15');
        $hasConflict = $svc->hasConflict($doctor->id, '2026-08-03', $slot1515, 30);
        $this->assertTrue($hasConflict);
    }

    /** 4. Slot adyacente sin buffer: permitido */
    public function test_adjacent_slot_without_buffer_is_allowed(): void
    {
        [$doctor, $esp] = $this->createDoctorWithSchedule('2026-08-03');
        $svc = app(ProfessionalScheduleService::class);

        Cita::create([
            'paciente_id' => $this->createRoleUser('paciente')->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-03',
            'hora' => '15:00:00',
            'estado' => Cita::ESTADO_CONFIRMADA,
            'activo' => true,
        ]);

        // 15:30 slot immediately adjacent
        $slot1530 = Carbon::createFromFormat('H:i', '15:30');
        $hasConflict = $svc->hasConflict($doctor->id, '2026-08-03', $slot1530, 30);
        $this->assertFalse($hasConflict);

        // 14:30 slot immediately adjacent before
        $slot1430 = Carbon::createFromFormat('H:i', '14:30');
        $hasConflictBefore = $svc->hasConflict($doctor->id, '2026-08-03', $slot1430, 30);
        $this->assertFalse($hasConflictBefore);
    }

    /** 5. Doctor diferente, misma hora: permitido */
    public function test_different_doctor_same_time_is_allowed(): void
    {
        [$doctor1, $esp1] = $this->createDoctorWithSchedule('2026-08-03');
        [$doctor2, $esp2] = $this->createDoctorWithSchedule('2026-08-03');
        $paciente1 = $this->createRoleUser('paciente');
        $paciente2 = $this->createRoleUser('paciente');

        $this->actingAs($paciente1)->post(route('paciente.crear-cita.store'), [
            'doctor_id' => $doctor1->id,
            'especialidad_id' => $esp1->id,
            'fecha' => '2026-08-03',
            'hora' => '10:00',
            'motivo_consulta' => 'Consulta Dr 1',
        ])->assertRedirect();

        $this->actingAs($paciente2)->post(route('paciente.crear-cita.store'), [
            'doctor_id' => $doctor2->id,
            'especialidad_id' => $esp2->id,
            'fecha' => '2026-08-03',
            'hora' => '10:00',
            'motivo_consulta' => 'Consulta Dr 2',
        ])->assertRedirect();

        $this->assertDatabaseHas('citas_medicas', ['doctor_id' => $doctor1->id, 'hora' => '10:00:00']);
        $this->assertDatabaseHas('citas_medicas', ['doctor_id' => $doctor2->id, 'hora' => '10:00:00']);
    }

    /** 6 & 7. Cita cancelada no bloquea, cita activa bloquea */
    public function test_cancelled_appointment_does_not_block_but_active_appointment_blocks(): void
    {
        [$doctor, $esp] = $this->createDoctorWithSchedule('2026-08-03');
        $svc = app(ProfessionalScheduleService::class);

        Cita::create([
            'paciente_id' => $this->createRoleUser('paciente')->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-03',
            'hora' => '11:00:00',
            'estado' => Cita::ESTADO_CANCELADA,
            'activo' => true,
        ]);

        $slot1100 = Carbon::createFromFormat('H:i', '11:00');
        $this->assertFalse($svc->hasConflict($doctor->id, '2026-08-03', $slot1100, 30));

        Cita::create([
            'paciente_id' => $this->createRoleUser('paciente')->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-03',
            'hora' => '14:00:00',
            'estado' => Cita::ESTADO_PENDIENTE,
            'activo' => true,
        ]);

        $slot1400 = Carbon::createFromFormat('H:i', '14:00');
        $this->assertTrue($svc->hasConflict($doctor->id, '2026-08-03', $slot1400, 30));
    }

    /** 8 & 9. Titular y dependiente respetan las mismas reglas de concurrencia */
    public function test_titular_and_dependent_follow_same_concurrency_rules(): void
    {
        [$doctor, $esp] = $this->createDoctorWithSchedule('2026-08-03');
        $titular = $this->createRoleUser('paciente');

        $dependiente = Dependiente::create([
            'user_id' => $titular->id,
            'nombre' => 'Hijo Cita',
            'tipo_documento' => 'cedula',
            'dni' => '1754504888',
            'fecha_nacimiento' => '2020-01-01',
            'sexo' => 'Masculino',
            'parentesco' => 'hijo',
            'activo' => true,
        ]);

        $this->actingAs($titular)->post(route('paciente.crear-cita.store'), [
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'dependiente_id' => $dependiente->id,
            'fecha' => '2026-08-03',
            'hora' => '09:00',
            'motivo_consulta' => 'Consulta dependiente',
        ])->assertRedirect();

        // Another patient trying same 09:00 slot fails
        $paciente2 = $this->createRoleUser('paciente');
        $resp = $this->actingAs($paciente2)->post(route('paciente.crear-cita.store'), [
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-03',
            'hora' => '09:00',
            'motivo_consulta' => 'Intento mismo slot',
        ]);

        $resp->assertSessionHasErrors();
    }

    /** 12, 13, 14, 15, 17. Reagendar control del día 5 al 7 persiste y actualiza la vista */
    public function test_reschedule_control_from_5th_to_7th_persists_in_database_and_updates_view(): void
    {
        [$doctor, $esp] = $this->createDoctorWithSchedule('2026-08-05');
        Horario::create([
            'doctor_id' => $doctor->id,
            'fecha' => '2026-08-07',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '18:00:00',
            'intervalo_minutos' => 30,
        ]);

        $paciente = $this->createRoleUser('paciente');
        $record = app(ClinicalRecordService::class)->ensureForPatient($paciente->id, $doctor->id);

        $citaOriginal = Cita::create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-04',
            'hora' => '11:30:00',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);

        $nota = NotaSoap::create([
            'cita_id' => $citaOriginal->id,
            'clinical_record_id' => $record->id,
            'estado' => NotaSoap::ESTADO_FIRMADA,
            'signed_at' => now(),
            'signed_by' => $doctor->id,
            'subjetivo_motivo' => 'Motivo consulta',
            'assessment' => 'Evolucion favorable',
            'plan_notas' => 'Continuar tratamiento',
            'follow_up_date' => '2026-08-05',
        ]);

        // Schedule initial control on the 5th
        $this->actingAs($doctor)->post(route('doctor.citas.proxima.planificada', $citaOriginal), [
            'fecha' => '2026-08-05',
            'hora' => '10:00',
        ])->assertOk();

        $nota->refresh();
        $controlCita = $nota->followUpCita;
        $this->assertNotNull($controlCita);
        $this->assertEquals('2026-08-05', Carbon::parse($controlCita->fecha)->format('Y-m-d'));

        // Reschedule control from 5th to 7th
        $reschedulingResponse = $this->actingAs($doctor)->post(route('doctor.citas.proxima.planificada', $citaOriginal), [
            'fecha' => '2026-08-07',
            'hora' => '10:00',
        ]);

        $reschedulingResponse->assertOk()->assertJson(['ok' => true]);

        // 12. Database contains 7th
        $controlCita->refresh();
        $nota->refresh();
        $this->assertEquals('2026-08-07', Carbon::parse($controlCita->fecha)->format('Y-m-d'));
        $this->assertEquals('2026-08-07', Carbon::parse($nota->follow_up_date)->format('Y-m-d'));

        // 14. Single control appointment updated, no duplicate created
        $this->assertEquals(1, Cita::where('source_nota_soap_id', $nota->id)->count());

        // 15. Original attended appointment remains unaltered on 4th
        $citaOriginal->refresh();
        $this->assertEquals('2026-08-04', Carbon::parse($citaOriginal->fecha)->format('Y-m-d'));
        $this->assertEquals(Cita::ESTADO_REALIZADA, $citaOriginal->estado);

        // 17. Audit event recorded
        $this->assertDatabaseHas('cita_eventos', [
            'cita_id' => $controlCita->id,
            'user_id' => $doctor->id,
            'tipo' => 'control_reagendado',
        ]);

        // 13. Reloading view shows 7th
        $viewResponse = $this->actingAs($doctor)->get(route('doctor.citas.soap', $citaOriginal));
        $viewResponse->assertOk();
        $viewResponse->assertSee('07/08/2026');
    }

    /** 16. Conflicto al reagendar conserva fecha anterior */
    public function test_conflict_when_rescheduling_control_preserves_previous_date(): void
    {
        [$doctor, $esp] = $this->createDoctorWithSchedule('2026-08-05');
        Horario::create([
            'doctor_id' => $doctor->id,
            'fecha' => '2026-08-07',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '18:00:00',
            'intervalo_minutos' => 30,
        ]);

        $paciente1 = $this->createRoleUser('paciente');
        $paciente2 = $this->createRoleUser('paciente');
        $record1 = app(ClinicalRecordService::class)->ensureForPatient($paciente1->id, $doctor->id);

        // Other patient occupies 2026-08-07 at 10:00
        Cita::create([
            'paciente_id' => $paciente2->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-07',
            'hora' => '10:00:00',
            'estado' => Cita::ESTADO_PENDIENTE,
            'activo' => true,
        ]);

        $citaOriginal = Cita::create([
            'paciente_id' => $paciente1->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-04',
            'hora' => '11:30:00',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);

        $nota = NotaSoap::create([
            'cita_id' => $citaOriginal->id,
            'clinical_record_id' => $record1->id,
            'estado' => NotaSoap::ESTADO_FIRMADA,
            'signed_at' => now(),
            'signed_by' => $doctor->id,
            'follow_up_date' => '2026-08-05',
        ]);

        // Schedule initial control on 5th
        $this->actingAs($doctor)->post(route('doctor.citas.proxima.planificada', $citaOriginal), [
            'fecha' => '2026-08-05',
            'hora' => '10:00',
        ])->assertOk();

        // Attempt reschedule to occupied slot on 7th
        $resp = $this->actingAs($doctor)->post(route('doctor.citas.proxima.planificada', $citaOriginal), [
            'fecha' => '2026-08-07',
            'hora' => '10:00',
        ]);

        $resp->assertStatus(422)->assertJson(['ok' => false]);

        $nota->refresh();
        $this->assertEquals('2026-08-05', Carbon::parse($nota->followUpCita->fecha)->format('Y-m-d'));
        $this->assertEquals('2026-08-05', Carbon::parse($nota->follow_up_date)->format('Y-m-d'));
    }

    /** 18 & 19. La petición no ejecuta SMTP directo pero encola notificación */
    public function test_reschedule_does_not_execute_direct_smtp_request(): void
    {
        [$doctor, $esp] = $this->createDoctorWithSchedule('2026-08-05');
        Horario::create([
            'doctor_id' => $doctor->id,
            'fecha' => '2026-08-07',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '18:00:00',
            'intervalo_minutos' => 30,
        ]);

        $paciente = $this->createRoleUser('paciente');
        $record = app(ClinicalRecordService::class)->ensureForPatient($paciente->id, $doctor->id);

        $citaOriginal = Cita::create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-04',
            'hora' => '11:30:00',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);

        $nota = NotaSoap::create([
            'cita_id' => $citaOriginal->id,
            'clinical_record_id' => $record->id,
            'estado' => NotaSoap::ESTADO_FIRMADA,
            'signed_at' => now(),
            'signed_by' => $doctor->id,
            'follow_up_date' => '2026-08-05',
        ]);

        $this->actingAs($doctor)->post(route('doctor.citas.proxima.planificada', $citaOriginal), [
            'fecha' => '2026-08-07',
            'hora' => '10:00',
        ])->assertOk();

        // Mailable queued/dispatched, SMTP not called in request
        Mail::assertQueued(CambioEstadoCitaMail::class);
    }

    /** 10. Con holds activos en 10:00 y 10:30 ambas citas deben crearse (escenario real fallido) */
    public function test_active_holds_at_10_and_1030_both_succeed_with_correct_tokens(): void
    {
        [$doctor, $esp] = $this->createDoctorWithSchedule('2026-08-07');
        $paciente1 = $this->createRoleUser('paciente');
        $paciente2 = $this->createRoleUser('paciente');
        $svc = app(ProfessionalScheduleService::class);

        // Insert active holds simulating both patients having selected their slots
        $tokenA = 'HOLD-TEST-1000-' . uniqid();
        $tokenB = 'HOLD-TEST-1030-' . uniqid();

        \Illuminate\Support\Facades\DB::table('appointment_slot_holds')->insert([
            [
                'doctor_id' => $doctor->id, 'paciente_id' => $paciente1->id,
                'fecha' => '2026-08-07', 'hora' => '10:00:00',
                'token' => $tokenA, 'status' => 'active',
                'expires_at' => now()->addMinutes(10),
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'doctor_id' => $doctor->id, 'paciente_id' => $paciente2->id,
                'fecha' => '2026-08-07', 'hora' => '10:30:00',
                'token' => $tokenB, 'status' => 'active',
                'expires_at' => now()->addMinutes(10),
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        // hasConflict with own token must NOT block
        $slot1000 = \Carbon\Carbon::createFromFormat('H:i', '10:00');
        $slot1030 = \Carbon\Carbon::createFromFormat('H:i', '10:30');

        $this->assertFalse($svc->hasConflict($doctor->id, '2026-08-07', $slot1000, 30, null, $tokenA),
            'Paciente A own hold at 10:00 must not block their own 10:00 booking');
        $this->assertFalse($svc->hasConflict($doctor->id, '2026-08-07', $slot1030, 30, null, $tokenB),
            'Paciente B own hold at 10:30 must not block their own 10:30 booking');

        // But a third party trying 10:00 sees A's hold and is blocked
        $tokenC = 'HOLD-TEST-THIRD-' . uniqid();
        $this->assertTrue($svc->hasConflict($doctor->id, '2026-08-07', $slot1000, 30, null, $tokenC),
            'A third patient with a different token should see hold A at 10:00 and be blocked');

        // Book via HTTP with the actual tokens (simulating store() after the fix)
        $r1 = $this->actingAs($paciente1)->post(route('paciente.crear-cita.store'), [
            'doctor_id'      => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha'          => '2026-08-07',
            'hora'           => '10:00',
            'motivo_consulta' => 'Consulta 10:00',
            'hold_token'     => $tokenA,
        ]);
        $r1->assertRedirect();

        $r2 = $this->actingAs($paciente2)->post(route('paciente.crear-cita.store'), [
            'doctor_id'      => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha'          => '2026-08-07',
            'hora'           => '10:30',
            'motivo_consulta' => 'Consulta 10:30',
            'hold_token'     => $tokenB,
        ]);
        $r2->assertRedirect();

        $this->assertDatabaseHas('citas_medicas', ['doctor_id' => $doctor->id, 'fecha' => '2026-08-07 00:00:00', 'hora' => '10:00:00']);
        $this->assertDatabaseHas('citas_medicas', ['doctor_id' => $doctor->id, 'fecha' => '2026-08-07 00:00:00', 'hora' => '10:30:00']);
        $this->assertEquals(2, \App\Models\Cita::where('doctor_id', $doctor->id)->whereDate('fecha', '2026-08-07')->count());
    }

    /** 11. Hold expirado no bloquea nuevo booking */
    public function test_expired_hold_does_not_block_booking(): void
    {
        [$doctor, $esp] = $this->createDoctorWithSchedule('2026-08-07');
        $paciente = $this->createRoleUser('paciente');
        $svc = app(ProfessionalScheduleService::class);

        // Insert an EXPIRED hold at 10:00 (null paciente_id is nullable in schema)
        \Illuminate\Support\Facades\DB::table('appointment_slot_holds')->insert([
            'doctor_id' => $doctor->id, 'paciente_id' => null,
            'fecha' => '2026-08-07', 'hora' => '10:00:00',
            'token' => 'EXPIRED-HOLD-' . uniqid(), 'status' => 'expired',
            'expires_at' => now()->subMinutes(5),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $slot1000 = \Carbon\Carbon::createFromFormat('H:i', '10:00');
        $this->assertFalse($svc->hasConflict($doctor->id, '2026-08-07', $slot1000, 30),
            'An expired hold must not block new bookings at that time');
    }

    /** 20. Zona horaria America/Guayaquil conserva fecha y hora */
    public function test_timezone_america_guayaquil_preserves_date_and_time(): void
    {
        [$doctor, $esp] = $this->createDoctorWithSchedule('2026-08-03');
        $paciente = $this->createRoleUser('paciente');

        $this->actingAs($paciente)->post(route('paciente.crear-cita.store'), [
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-03',
            'hora' => '12:00',
            'motivo_consulta' => 'Consulta timezone test',
        ])->assertRedirect();

        $cita = Cita::where('doctor_id', $doctor->id)->whereDate('fecha', '2026-08-03')->first();
        $this->assertNotNull($cita);
        $this->assertEquals('2026-08-03', Carbon::parse($cita->fecha)->format('Y-m-d'));
        $this->assertEquals('12:00:00', $cita->hora);
    }
}
