<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Dependiente;
use App\Models\Especialidad;
use App\Models\NotaSoap;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DependentClinicalHistoryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_clinical_history_index_and_search_separation(): void
    {
        $this->seed(DatabaseSeeder::class);

        $adminRole = Role::firstOrCreate(['name' => 'administrador']);
        $doctorRole = Role::firstOrCreate(['name' => 'doctor']);
        $patientRole = Role::firstOrCreate(['name' => 'paciente']);
        $especialidad = Especialidad::factory()->create();

        // 1. Crear Administrador
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);

        // 2. Crear Doctor
        $doctor = User::factory()->create(['name' => 'Dr. House']);
        $doctor->roles()->attach($doctorRole);

        // 3. Crear Paciente Titular y su Dependiente
        $titular = User::factory()->create([
            'name' => 'Josthyn Arroyo',
            'dni' => '0999999999',
            'email' => 'josthyn@example.com',
        ]);
        $titular->roles()->attach($patientRole);

        $dependiente = Dependiente::create([
            'user_id' => $titular->id,
            'nombre' => 'Anabel Arroyo',
            'dni' => '0988888888',
            'fecha_nacimiento' => '2015-08-20',
            'sexo' => 'Femenino',
            'parentesco' => 'hija',
            'activo' => true,
        ]);

        // 4. Crear Citas
        // Cita para el Titular
        $citaTitular = Cita::create([
            'paciente_id' => $titular->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => '2026-07-13',
            'hora' => '08:00:00',
            'motivo_consulta' => 'Control de rutina',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);

        // Cita para el Dependiente (Anabel)
        $citaDependiente = Cita::create([
            'paciente_id' => $titular->id,
            'dependiente_id' => $dependiente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => '2026-07-13',
            'hora' => '12:30:00',
            'motivo_consulta' => 'Fiebre alta',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);

        // 5. Crear Notas SOAP firmadas para cada una
        $notaTitular = NotaSoap::create([
            'cita_id' => $citaTitular->id,
            'estado' => NotaSoap::ESTADO_FIRMADA,
            'subjetivo_motivo' => 'Control',
            'signed_at' => now(),
            'doctor_id' => $doctor->id,
        ]);

        $notaDependiente = NotaSoap::create([
            'cita_id' => $citaDependiente->id,
            'estado' => NotaSoap::ESTADO_FIRMADA,
            'subjetivo_motivo' => 'Fiebre',
            'signed_at' => now(),
            'doctor_id' => $doctor->id,
        ]);

        $this->actingAs($admin);

        // Test 1: El listado completo debe ver ambos nombres reales
        $responseIndex = $this->get(route('admin.historial.index'));
        $responseIndex->assertOk();
        $responseIndex->assertSee('Josthyn Arroyo');
        $responseIndex->assertSee('Anabel Arroyo');

        // Test 2: Búsqueda por "Anabel" debe encontrar solo la nota del dependiente
        $responseSearch = $this->get(route('admin.historial.index', ['q' => 'Anabel']));
        $responseSearch->assertOk();
        $responseSearch->assertSee('Anabel Arroyo');
        $responseSearch->assertDontSee('Josthyn Arroyo');

        // Test 3: Búsqueda por "Josthyn" debe encontrar ambos ya que "Anabel" es dependiente de su cuenta
        // o si busca por DNI del titular encuentra ambos.
        // Pero para Notas firmadas:
        // Cargar paciente sin dependiente_id debe mostrar únicamente las notas del Titular
        $responseTitularNotas = $this->get(route('admin.historial.paciente', [
            'paciente' => $titular->id,
        ]));
        $responseTitularNotas->assertOk();
        $responseTitularNotas->assertSee($titular->name);
        $responseTitularNotas->assertDontSee('Anabel Arroyo'); // No debe mezclarse

        // Cargar paciente con dependiente_id debe mostrar únicamente las notas de Anabel
        $responseDepNotas = $this->get(route('admin.historial.paciente', [
            'paciente' => $titular->id,
            'dependiente_id' => $dependiente->id,
        ]));
        $responseDepNotas->assertOk();
        $responseDepNotas->assertSee('Anabel Arroyo');
        $responseDepNotas->assertDontSee('Josthyn Arroyo'); // No debe mezclarse
    }

    public function test_doctor_appointments_chart_robustness(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'administrador']);
        $admin->roles()->attach($adminRole);

        $patient = User::factory()->create();
        $patientRole = Role::firstOrCreate(['name' => 'paciente']);
        $patient->roles()->attach($patientRole);

        $especialidad = Especialidad::factory()->create();

        $blankDoctor = User::factory()->create([
            'name' => '',
        ]);
        $blankDoctor->roles()->attach(Role::firstOrCreate(['name' => 'doctor']));

        // Crear una cita con doctor que tiene nombre vacío
        Cita::create([
            'paciente_id' => $patient->id,
            'doctor_id' => $blankDoctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => now()->format('Y-m-d'),
            'hora' => '09:00:00',
            'motivo_consulta' => 'Consulta unassigned',
            'estado' => Cita::ESTADO_PENDIENTE,
            'activo' => true,
        ]);

        $this->actingAs($admin);
        $response = $this->get(route('admin.dashboard.data'));
        $response->assertOk();

        $citasDoctorData = $response->json()['charts']['citas_doctor'];
        $this->assertNotEmpty($citasDoctorData['labels']);
        
        // El label para doctor con nombre vacío debe ser "Doctor sin nombre"
        $this->assertContains('Doctor sin nombre', $citasDoctorData['labels']);

        // Todas las cantidades en las series deben ser enteros válidos
        foreach ($citasDoctorData['series'] as $serie) {
            $this->assertIsArray($serie['data']);
            foreach ($serie['data'] as $val) {
                $this->assertIsInt($val);
            }
        }
    }
}
