<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Dependiente;
use App\Models\Horario;
use App\Models\Especialidad;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AgendarCitaDependienteSelectorTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-03-09 09:00:00', 'America/Guayaquil'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_crear_cita_page_renders_dependientes_of_authenticated_patient(): void
    {
        [$patientA, $patientB] = $this->createPatients();

        $depA1 = Dependiente::create([
            'user_id' => $patientA->id,
            'nombre' => 'Anabel Arroyo',
            'fecha_nacimiento' => '2015-05-05',
            'parentesco' => 'Hija',
            'dni' => '1111111111',
            'activo' => true,
        ]);

        $depA2 = Dependiente::create([
            'user_id' => $patientA->id,
            'nombre' => 'Carlos Arroyo',
            'fecha_nacimiento' => '2015-05-05',
            'parentesco' => 'Hijo',
            'dni' => '2222222222',
            'activo' => true,
        ]);

        $depB = Dependiente::create([
            'user_id' => $patientB->id,
            'nombre' => 'Familiar Ajeno',
            'fecha_nacimiento' => '2015-05-05',
            'parentesco' => 'otro',
            'dni' => '3333333333',
            'activo' => true,
        ]);

        $response = $this->actingAs($patientA)->get(route('paciente.crear-cita'));
        $response->assertOk();
        $response->assertSee('Anabel Arroyo');
        $response->assertSee('Carlos Arroyo');
        $response->assertDontSee('Familiar Ajeno');
    }

    public function test_crear_cita_page_renders_empty_state_when_patient_has_no_dependientes(): void
    {
        [$patient] = $this->createPatients();

        $response = $this->actingAs($patient)->get(route('paciente.crear-cita'));
        $response->assertOk();
        $response->assertSee('No tienes familiares registrados en tu cuenta');
        $response->assertSee(route('paciente.dependientes.index'));
    }

    public function test_booking_appointment_for_dependient_saves_dependiente_id_and_patient_id(): void
    {
        [$patient, , $doctor, $especialidad, $fecha, $hora] = $this->bookingSetup();

        $dep = Dependiente::create([
            'user_id' => $patient->id,
            'nombre' => 'Anabel Arroyo',
            'fecha_nacimiento' => '2015-05-05',
            'parentesco' => 'Hija',
            'dni' => '1111111111',
            'activo' => true,
        ]);

        $response = $this->actingAs($patient)->post(route('paciente.crear-cita.store'), [
            'tipo_paciente' => 'dependiente',
            'dependiente_id' => $dep->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => $fecha,
            'hora' => $hora,
            'motivo_consulta' => 'Examen pediatrico de control',
        ]);

        $response->assertRedirect();
        $cita = Cita::query()->first();
        $this->assertNotNull($cita);
        $this->assertSame($patient->id, $cita->paciente_id);
        $this->assertSame($dep->id, $cita->dependiente_id);
        $this->assertSame('Anabel Arroyo', $cita->nombrePacienteReal());
    }

    public function test_booking_appointment_for_titular_clears_dependiente_id(): void
    {
        [$patient, , $doctor, $especialidad, $fecha, $hora] = $this->bookingSetup();

        $response = $this->actingAs($patient)->post(route('paciente.crear-cita.store'), [
            'tipo_paciente' => 'titular',
            'dependiente_id' => '',
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => $fecha,
            'hora' => $hora,
            'motivo_consulta' => 'Consulta medica propia',
        ]);

        $response->assertRedirect();
        $cita = Cita::query()->first();
        $this->assertNotNull($cita);
        $this->assertSame($patient->id, $cita->paciente_id);
        $this->assertNull($cita->dependiente_id);
        $this->assertSame($patient->name, $cita->nombrePacienteReal());
    }

    public function test_booking_appointment_with_missing_dependiente_id_fails_validation(): void
    {
        [$patient, , $doctor, $especialidad, $fecha, $hora] = $this->bookingSetup();

        $response = $this->actingAs($patient)->post(route('paciente.crear-cita.store'), [
            'tipo_paciente' => 'dependiente',
            'dependiente_id' => '',
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => $fecha,
            'hora' => $hora,
            'motivo_consulta' => 'Consulta familiar sin seleccionar',
        ]);

        $response->assertSessionHasErrors(['dependiente_id']);
        $this->assertSame(0, Cita::query()->count());
    }

    public function test_idor_prevented_patient_cannot_book_for_another_users_dependient(): void
    {
        [$patientA, $patientB, $doctor, $especialidad, $fecha, $hora] = $this->bookingSetup();

        $depB = Dependiente::create([
            'user_id' => $patientB->id,
            'nombre' => 'Dependiente de B',
            'fecha_nacimiento' => '2015-05-05',
            'parentesco' => 'Hijo',
            'dni' => '9999999999',
            'activo' => true,
        ]);

        $response = $this->actingAs($patientA)->post(route('paciente.crear-cita.store'), [
            'tipo_paciente' => 'dependiente',
            'dependiente_id' => $depB->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => $fecha,
            'hora' => $hora,
            'motivo_consulta' => 'Intento IDOR',
        ]);

        $response->assertSessionHasErrors(['dependiente_id']);
        $this->assertSame(0, Cita::query()->count());
    }

    public function test_booking_appointment_with_nonexistent_dependiente_id_fails_validation(): void
    {
        [$patient, , $doctor, $especialidad, $fecha, $hora] = $this->bookingSetup();

        $response = $this->actingAs($patient)->post(route('paciente.crear-cita.store'), [
            'tipo_paciente' => 'dependiente',
            'dependiente_id' => 99999,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => $fecha,
            'hora' => $hora,
            'motivo_consulta' => 'Dependiente inexistente',
        ]);

        $response->assertSessionHasErrors(['dependiente_id']);
        $this->assertSame(0, Cita::query()->count());
    }

    private function createPatients(): array
    {
        $role = Role::query()->firstOrCreate(['name' => 'paciente'], ['label' => 'Paciente']);
        $patientA = User::factory()->create(['name' => 'Josthyn Arroyo']);
        $patientA->roles()->attach($role);

        $patientB = User::factory()->create(['name' => 'Otro Paciente']);
        $patientB->roles()->attach($role);

        return [$patientA, $patientB];
    }

    private function bookingSetup(): array
    {
        [$patientA, $patientB] = $this->createPatients();

        $doctorRole = Role::query()->firstOrCreate(['name' => 'doctor'], ['label' => 'Doctor']);
        $doctor = User::factory()->create(['name' => 'Dr. Suarez']);
        $doctor->roles()->attach($doctorRole);

        $especialidad = Especialidad::query()->firstOrCreate(['id' => 10], ['nombre' => 'Pediatria', 'activo' => true]);
        $doctor->especialidades()->attach($especialidad->id);

        $fecha = Carbon::now('America/Guayaquil')->addDays(2)->toDateString();
        $dayNum = (int) Carbon::parse($fecha)->format('N');

        Horario::create([
            'doctor_id' => $doctor->id,
            'fecha' => $fecha,
            'dia_semana' => $dayNum,
            'hora_inicio' => '08:00:00',
            'hora_fin' => '17:00:00',
            'duracion_cita' => 30,
            'activo' => true,
        ]);

        return [$patientA, $patientB, $doctor, $especialidad, $fecha, '10:00'];
    }
}
