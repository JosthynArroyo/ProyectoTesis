<?php

namespace Tests\Feature;

use App\Events\CitaAgendada;
use App\Jobs\EnviarConfirmacionCitaJob;
use App\Jobs\NotificarCambioEstadoCitaJob;
use App\Mail\CambioEstadoCitaMail;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Horario;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProductionPatientAppointmentBookingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.mode' => 'production']);
    }

    /**
     * TEST 1: En modo production, el agendamiento crea la cita, PERSISTE en la base de datos y despacha confirmación.
     */
    public function test_production_appointment_booking_persists_and_dispatches_events_and_jobs(): void
    {
        Mail::fake();

        $doctorRole = Role::firstOrCreate(['name' => 'doctor'], ['label' => 'Doctor']);
        $pacienteRole = Role::firstOrCreate(['name' => 'paciente'], ['label' => 'Paciente']);

        $especialidad = Especialidad::firstOrCreate(
            ['nombre' => 'Medicina General'],
            ['descripcion' => 'Atención primaria', 'duracion_minutos' => 30, 'precio' => 30.00]
        );

        $doctor = User::create([
            'name' => 'Dr. Producción Test',
            'email' => 'doctor.prod@test-clinigest.test',
            'password' => Hash::make('ProdSecret123!'),
            'dni' => '0999999901',
            'telefono' => '0999999901',
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ]);
        $doctor->roles()->sync([$doctorRole->id]);
        $doctor->especialidades()->sync([$especialidad->id]);

        $paciente = User::create([
            'name' => 'Paciente Producción Test',
            'email' => 'paciente.prod@test-clinigest.test',
            'password' => Hash::make('ProdSecret123!'),
            'dni' => '0999999902',
            'telefono' => '0999999902',
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ]);
        $paciente->roles()->sync([$pacienteRole->id]);

        // Configurar horario para el doctor en una fecha futura
        $targetDate = now()->addDays(5);
        while (! $targetDate->isWeekday()) {
            $targetDate->addDay();
        }
        $targetDateStr = $targetDate->format('Y-m-d');

        Horario::create([
            'doctor_id' => $doctor->id,
            'fecha' => $targetDateStr,
            'hora_inicio' => '08:00:00',
            'hora_fin' => '13:00:00',
            'intervalo_minutos' => 30,
        ]);

        $motivoProd = 'Consulta médica productiva verificable '.uniqid();

        // Ejecutar POST /paciente/crear-cita como paciente en modo producción
        $response = $this->actingAs($paciente)->post(route('paciente.crear-cita.store'), [
            'especialidad_id' => $especialidad->id,
            'doctor_id' => $doctor->id,
            'fecha' => $targetDateStr,
            'hora' => '09:00',
            'motivo_consulta' => $motivoProd,
            'tipo_paciente' => 'titular',
        ]);

        // 1. Respuesta exitosa con redirección
        $response->assertRedirect(route('paciente.citas'));
        $response->assertSessionHas('success');

        // 2. En producción, la cita DEBE PERSISTIR en la base de datos
        $this->assertDatabaseHas('citas_medicas', [
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => $targetDateStr,
            'hora' => '09:00:00',
            'motivo_consulta' => $motivoProd,
            'estado' => Cita::ESTADO_PENDIENTE,
        ]);

        // 3. Los correos de confirmación en producción fueron encolados a paciente y doctor
        Mail::assertQueued(CambioEstadoCitaMail::class, 2);
    }

    /**
     * TEST 2: EnviarConfirmacionCitaJob ejecuta correctamente con modelo existente bajo contrato productivo original.
     */
    public function test_enviar_confirmacion_cita_job_contract_in_production(): void
    {
        Mail::fake();

        $doctorRole = Role::firstOrCreate(['name' => 'doctor'], ['label' => 'Doctor']);
        $pacienteRole = Role::firstOrCreate(['name' => 'paciente'], ['label' => 'Paciente']);

        $especialidad = Especialidad::firstOrCreate(
            ['nombre' => 'Pediatría Prod'],
            ['descripcion' => 'Pediatría', 'duracion_minutos' => 30, 'precio' => 35.00]
        );

        $doctor = User::create([
            'name' => 'Dra. Pediatra Prod',
            'email' => 'pediatra.prod@test-clinigest.test',
            'password' => Hash::make('ProdSecret123!'),
            'dni' => '0999999903',
            'telefono' => '0999999903',
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ]);
        $doctor->roles()->sync([$doctorRole->id]);

        $paciente = User::create([
            'name' => 'Madre Paciente Prod',
            'email' => 'madre.prod@test-clinigest.test',
            'password' => Hash::make('ProdSecret123!'),
            'dni' => '0999999904',
            'telefono' => '0999999904',
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ]);
        $paciente->roles()->sync([$pacienteRole->id]);

        $cita = Cita::create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => now()->addDays(2)->format('Y-m-d'),
            'hora' => '10:00:00',
            'motivo_consulta' => 'Control pediátrico',
            'estado' => Cita::ESTADO_CONFIRMADA,
            'folio_cita' => 'CIT-PROD-TEST-01',
            'activo' => true,
        ]);

        // Ejecución del Job con cita existente
        $job = new EnviarConfirmacionCitaJob($cita);
        $job->handle();

        Mail::assertQueued(CambioEstadoCitaMail::class, function ($mail) use ($paciente) {
            return $mail->hasTo($paciente->email);
        });
        Mail::assertQueued(CambioEstadoCitaMail::class, function ($mail) use ($doctor) {
            return $mail->hasTo($doctor->email);
        });
    }

    /**
     * TEST 3: NotificarCambioEstadoCitaJob ejecuta correctamente con modelo existente bajo contrato productivo original.
     */
    public function test_notificar_cambio_estado_cita_job_contract_in_production(): void
    {
        Mail::fake();

        $doctorRole = Role::firstOrCreate(['name' => 'doctor'], ['label' => 'Doctor']);
        $pacienteRole = Role::firstOrCreate(['name' => 'paciente'], ['label' => 'Paciente']);

        $especialidad = Especialidad::firstOrCreate(
            ['nombre' => 'Ginecología Prod'],
            ['descripcion' => 'Ginecología', 'duracion_minutos' => 30, 'precio' => 45.00]
        );

        $doctor = User::create([
            'name' => 'Dra. Gineco Prod',
            'email' => 'gineco.prod@test-clinigest.test',
            'password' => Hash::make('ProdSecret123!'),
            'dni' => '0999999905',
            'telefono' => '0999999905',
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ]);
        $doctor->roles()->sync([$doctorRole->id]);

        $paciente = User::create([
            'name' => 'Paciente Gineco Prod',
            'email' => 'paciente.gineco@test-clinigest.test',
            'password' => Hash::make('ProdSecret123!'),
            'dni' => '0999999906',
            'telefono' => '0999999906',
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ]);
        $paciente->roles()->sync([$pacienteRole->id]);

        $cita = Cita::create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => now()->addDays(3)->format('Y-m-d'),
            'hora' => '11:00:00',
            'motivo_consulta' => 'Control ginecológico',
            'estado' => Cita::ESTADO_CONFIRMADA,
            'folio_cita' => 'CIT-PROD-TEST-02',
            'activo' => true,
        ]);

        // Ejecución del Job para evento reagendada
        $job = new NotificarCambioEstadoCitaJob($cita, 'reagendada', 'paciente', '2026-10-01', '10:00:00');
        $job->handle();

        Mail::assertQueued(CambioEstadoCitaMail::class, function ($mail) use ($paciente) {
            return $mail->hasTo($paciente->email);
        });
    }

    /**
     * TEST 4: Intento de agendamiento fuera del horario configurado es rechazado.
     */
    public function test_out_of_schedule_booking_is_rejected_in_production(): void
    {
        $doctorRole = Role::firstOrCreate(['name' => 'doctor'], ['label' => 'Doctor']);
        $pacienteRole = Role::firstOrCreate(['name' => 'paciente'], ['label' => 'Paciente']);

        $especialidad = Especialidad::firstOrCreate(
            ['nombre' => 'Medicina General Prod Horario'],
            ['descripcion' => 'General', 'duracion_minutos' => 30, 'precio' => 30.00]
        );

        $doctor = User::create([
            'name' => 'Dr. Horario Test',
            'email' => 'doctor.horario@test-clinigest.test',
            'password' => Hash::make('ProdSecret123!'),
            'dni' => '0999999911',
            'telefono' => '0999999911',
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ]);
        $doctor->roles()->sync([$doctorRole->id]);
        $doctor->especialidades()->sync([$especialidad->id]);

        $paciente = User::create([
            'name' => 'Paciente Horario Test',
            'email' => 'paciente.horario@test-clinigest.test',
            'password' => Hash::make('ProdSecret123!'),
            'dni' => '0999999912',
            'telefono' => '0999999912',
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ]);
        $paciente->roles()->sync([$pacienteRole->id]);

        $targetDate = now()->addDays(5);
        while (! $targetDate->isWeekday()) {
            $targetDate->addDay();
        }
        $targetDateStr = $targetDate->format('Y-m-d');

        // Configurar jornada únicamente de 08:00 a 13:00
        Horario::create([
            'doctor_id' => $doctor->id,
            'fecha' => $targetDateStr,
            'hora_inicio' => '08:00:00',
            'hora_fin' => '13:00:00',
            'intervalo_minutos' => 30,
        ]);

        // Intento de agendar fuera de jornada: a las 23:00
        $response = $this->actingAs($paciente)->post(route('paciente.crear-cita.store'), [
            'especialidad_id' => $especialidad->id,
            'doctor_id' => $doctor->id,
            'fecha' => $targetDateStr,
            'hora' => '23:00',
            'motivo_consulta' => 'Intento fuera de horario nocturno',
            'tipo_paciente' => 'titular',
        ]);

        $response->assertSessionHasErrors('error');
        $this->assertDatabaseMissing('citas_medicas', [
            'doctor_id' => $doctor->id,
            'hora' => '23:00:00',
        ]);
    }

    /**
     * TEST 5: Intento de agendamiento con doctor que no pertenece a la especialidad es rechazado.
     */
    public function test_doctor_specialty_mismatch_booking_is_rejected_in_production(): void
    {
        $doctorRole = Role::firstOrCreate(['name' => 'doctor'], ['label' => 'Doctor']);
        $pacienteRole = Role::firstOrCreate(['name' => 'paciente'], ['label' => 'Paciente']);

        $medicinaGeneral = Especialidad::firstOrCreate(
            ['nombre' => 'Medicina General Mismatch'],
            ['descripcion' => 'General', 'duracion_minutos' => 30, 'precio' => 30.00]
        );

        $pediatria = Especialidad::firstOrCreate(
            ['nombre' => 'Pediatria Mismatch'],
            ['descripcion' => 'Pediatría', 'duracion_minutos' => 30, 'precio' => 35.00]
        );

        $doctor = User::create([
            'name' => 'Dr. Especialidad Test',
            'email' => 'doctor.esp@test-clinigest.test',
            'password' => Hash::make('ProdSecret123!'),
            'dni' => '0999999913',
            'telefono' => '0999999913',
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ]);
        $doctor->roles()->sync([$doctorRole->id]);
        // Solo pertenece a Medicina General
        $doctor->especialidades()->sync([$medicinaGeneral->id]);

        $paciente = User::create([
            'name' => 'Paciente Esp Test',
            'email' => 'paciente.esp@test-clinigest.test',
            'password' => Hash::make('ProdSecret123!'),
            'dni' => '0999999914',
            'telefono' => '0999999914',
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ]);
        $paciente->roles()->sync([$pacienteRole->id]);

        $targetDate = now()->addDays(5);
        while (! $targetDate->isWeekday()) {
            $targetDate->addDay();
        }
        $targetDateStr = $targetDate->format('Y-m-d');

        Horario::create([
            'doctor_id' => $doctor->id,
            'fecha' => $targetDateStr,
            'hora_inicio' => '08:00:00',
            'hora_fin' => '13:00:00',
            'intervalo_minutos' => 30,
        ]);

        // Intento de agendar con Pediatría, a la que el doctor NO pertenece
        $response = $this->actingAs($paciente)->post(route('paciente.crear-cita.store'), [
            'especialidad_id' => $pediatria->id,
            'doctor_id' => $doctor->id,
            'fecha' => $targetDateStr,
            'hora' => '09:00',
            'motivo_consulta' => 'Intento con especialidad cruzada',
            'tipo_paciente' => 'titular',
        ]);

        $response->assertSessionHasErrors('doctor_id');
        $this->assertDatabaseMissing('citas_medicas', [
            'doctor_id' => $doctor->id,
            'especialidad_id' => $pediatria->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}

