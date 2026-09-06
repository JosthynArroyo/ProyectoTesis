<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\ClinicalRecord;
use App\Models\Especialidad;
use App\Models\NotaSoap;
use App\Models\NotaSoapDiagnostico;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ClinicalRecordWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_doctor_can_view_patient_centric_record_and_update_master_clinical_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $doctorRole = Role::query()->where('name', 'doctor')->firstOrFail();
        $patientRole = Role::query()->where('name', 'paciente')->firstOrFail();
        $specialty = Especialidad::query()->orderBy('id')->firstOrFail();

        $patient = User::factory()->create([
            'fecha_nacimiento' => '1990-01-10',
            'sexo' => 'F',
        ]);
        $patient->roles()->attach($patientRole);

        $doctorA = User::factory()->create();
        $doctorA->roles()->attach($doctorRole);

        $doctorB = User::factory()->create();
        $doctorB->roles()->attach($doctorRole);

        $record = ClinicalRecord::create([
            'patient_id' => $patient->id,
        ]);

        Cita::create([
            'paciente_id' => $patient->id,
            'doctor_id' => $doctorA->id,
            'especialidad_id' => $specialty->id,
            'fecha' => '2026-04-01',
            'hora' => '09:00:00',
            'motivo_consulta' => 'Control general',
            'estado' => Cita::ESTADO_CONFIRMADA,
            'activo' => true,
        ]);

        $pastCita = Cita::create([
            'paciente_id' => $patient->id,
            'doctor_id' => $doctorB->id,
            'especialidad_id' => $specialty->id,
            'fecha' => '2026-03-25',
            'hora' => '10:00:00',
            'motivo_consulta' => 'Seguimiento respiratorio',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);

        $nota = NotaSoap::create([
            'cita_id' => $pastCita->id,
            'clinical_record_id' => $record->id,
            'estado' => NotaSoap::ESTADO_FIRMADA,
            'signed_at' => now(),
            'signed_by' => $doctorB->id,
            'subjetivo_motivo' => 'Disnea leve',
            'subjetivo_hpi' => 'Antecedente respiratorio conocido',
            'subjetivo_ros' => ['alergias' => ['no_conocidas' => true]],
            'signos_vitales' => ['ta' => '110/70', 'fc' => 78],
            'assessment' => 'Paciente estable',
            'plan_general' => 'Continuar vigilancia',
            'plan_notas' => 'Control ambulatorio',
        ]);

        NotaSoapDiagnostico::create([
            'nota_soap_id' => $nota->id,
            'tipo' => 'principal',
            'texto' => 'Asma cronica',
            'cie10' => 'J45.9',
        ]);

        $this->actingAs($doctorA)
            ->get(route('doctor.pacientes.historial', $patient))
            ->assertOk()
            ->assertSee('Expediente')
            ->assertSee('Asma cronica');

        $payload = [
            'allergies_status' => 'documented',
            'clinical_summary' => 'Paciente con seguimiento longitudinal activo.',
            'allergies' => [
                ['allergen' => 'Penicilina', 'reaction' => 'Rash', 'severity' => 'moderate', 'status' => 'active'],
            ],
            'personal_histories' => [
                ['title' => 'Hipotiroidismo'],
            ],
            'family_histories' => [
                ['title' => 'Diabetes mellitus', 'relation_label' => 'Madre'],
            ],
            'surgeries' => [
                ['title' => 'Apendicectomia', 'occurred_on' => '2018-02-01'],
            ],
            'hospitalizations' => [
                ['title' => 'Neumonia', 'occurred_on' => '2022-09-15'],
            ],
            'immunizations' => [
                ['title' => 'Influenza anual', 'occurred_on' => '2025-11-01'],
            ],
            'problems' => [
                ['name' => 'Hipertension arterial', 'status' => 'active', 'is_chronic' => '1', 'started_at' => '2024-01-10'],
            ],
            'medications' => [
                ['name' => 'Losartan', 'dosage' => '50 mg', 'frequency' => 'Cada 24 horas', 'route' => 'Oral', 'status' => 'active'],
            ],
            'alerts' => [
                ['title' => 'Riesgo de reaccion alergica', 'description' => 'Evitar betalactamicos', 'severity' => 'high', 'type' => 'clinical', 'is_active' => '1'],
            ],
        ];

        $this->actingAs($doctorA)
            ->put(route('doctor.pacientes.historial.update', $patient), $payload)
            ->assertRedirect(route('doctor.pacientes.historial', $patient));

        $this->assertDatabaseHas('clinical_records', [
            'patient_id' => $patient->id,
            'allergies_status' => 'documented',
        ]);
        $this->assertDatabaseHas('clinical_record_allergies', [
            'clinical_record_id' => $record->id,
            'allergen' => 'Penicilina',
        ]);
        $this->assertDatabaseHas('clinical_record_problems', [
            'clinical_record_id' => $record->id,
            'name' => 'Hipertension arterial',
        ]);
        $this->assertDatabaseHas('clinical_record_medications', [
            'clinical_record_id' => $record->id,
            'name' => 'Losartan',
        ]);
        $this->assertDatabaseHas('clinical_record_alerts', [
            'clinical_record_id' => $record->id,
            'title' => 'Riesgo de reaccion alergica',
        ]);

        $this->actingAs($doctorA)
            ->get(route('doctor.pacientes.historial', $patient))
            ->assertOk()
            ->assertSee('css/medical-record.css', false)
            ->assertSee('css/medical-record-v2.css', false)
            ->assertSee('mt-3', false)
            ->assertDontSee('style="cursor:default"', false)
            ->assertDontSee('style="margin-top:.75rem"', false)
            ->assertDontSee('style="opacity:.7"', false);
    }

    public function test_soap_encoding_does_not_have_regression(): void
    {
        $this->seed(DatabaseSeeder::class);

        $doctorRole = Role::query()->where('name', 'doctor')->firstOrFail();
        $patientRole = Role::query()->where('name', 'paciente')->firstOrFail();
        $specialty = Especialidad::query()->orderBy('id')->firstOrFail();

        $patient = User::factory()->create();
        $patient->roles()->attach($patientRole);

        $doctor = User::factory()->create();
        $doctor->roles()->attach($doctorRole);

        $cita = Cita::create([
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $specialty->id,
            'fecha' => '2026-07-13',
            'hora' => '08:00:00',
            'motivo_consulta' => 'Dolor de garganta',
            'estado' => Cita::ESTADO_CONFIRMADA,
            'activo' => true,
        ]);

        $response = $this->actingAs($doctor)
            ->get(route('doctor.citas.soap', $cita));

        $response->assertOk();

        // Must see correct UTF-8 Spanish texts
        $response->assertSee('Presión arterial', false);
        $response->assertSee('Respiración', false);
        $response->assertSee('Temperatura', false);
        $response->assertSee('°C', false);
        $response->assertSee('centímetros', false);
        $response->assertSee('guardará', false);

        // Must not see corrupted encodings
        $response->assertDontSee('PresiÃ³n', false);
        $response->assertDontSee('RespiraciÃ³n', false);
        $response->assertDontSee('Â°C', false);
        $response->assertDontSee('centÃ', false);
        $response->assertDontSee('guardarÃ', false);
        $response->assertDontSee("\u{FFFD}", false);
    }
}
