<?php

namespace Tests\Feature;

use App\Models\CertificadoMedico;
use App\Models\Cita;
use App\Models\ClinicalRecord;
use App\Models\Especialidad;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MedicalCertificateFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-04-16 10:00:00', 'America/Guayaquil'));
        Storage::fake('local');

        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_treating_doctor_can_issue_view_and_download_certificate_for_completed_appointment(): void
    {
        [$doctor, $patient, , $cita] = $this->clinicalScenario(Cita::ESTADO_REALIZADA);

        $this->actingAs($doctor)
            ->get(route('doctor.certificados.create', $cita))
            ->assertOk()
            ->assertSee('Certificado medico');

        $response = $this->actingAs($doctor)
            ->post(route('doctor.certificados.store', $cita), [
                'texto_constancia' => 'Se certifica que el paciente fue atendido y requiere reposo.',
                'dias_reposo' => 2,
                'reposo_desde' => '2026-04-16',
                'reposo_hasta' => '2026-04-17',
                'observaciones' => 'Reposo domiciliario y control segun evolucion.',
            ]);

        $certificado = CertificadoMedico::query()->firstOrFail();

        $response->assertRedirect(route('doctor.certificados.show', $certificado));

        $this->assertDatabaseHas('certificados_medicos', [
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'dias_reposo' => 2,
        ]);
        $this->assertDatabaseHas('cita_eventos', [
            'cita_id' => $cita->id,
            'user_id' => $doctor->id,
            'tipo' => 'certificado_emitido',
        ]);
        $this->assertNotNull($certificado->clinical_record_id);
        $this->assertNotEmpty($certificado->pdf_path);
        Storage::disk('local')->assertExists($certificado->pdf_path);

        $this->actingAs($doctor)
            ->get(route('doctor.certificados.show', $certificado))
            ->assertOk()
            ->assertSee($certificado->codigo);

        $this->actingAs($doctor)
            ->get(route('doctor.certificados.download', $certificado))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($patient)
            ->get(route('paciente.certificados.show', $certificado))
            ->assertOk()
            ->assertSee($certificado->codigo);

        $this->actingAs($patient)
            ->get(route('paciente.certificados.download', $certificado))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_other_doctor_cannot_issue_or_view_certificate_from_another_doctor(): void
    {
        [$doctor, , $otherDoctor, $cita] = $this->clinicalScenario(Cita::ESTADO_REALIZADA);

        $this->actingAs($otherDoctor)
            ->get(route('doctor.certificados.create', $cita))
            ->assertForbidden();

        $this->actingAs($otherDoctor)
            ->post(route('doctor.certificados.store', $cita), [
                'texto_constancia' => 'Documento no autorizado.',
                'dias_reposo' => 0,
            ])
            ->assertForbidden();

        $certificado = CertificadoMedico::create([
            'codigo' => 'CM-20260416-000001',
            'cita_id' => $cita->id,
            'paciente_id' => $cita->paciente_id,
            'doctor_id' => $doctor->id,
            'clinical_record_id' => ClinicalRecord::firstOrCreate(['patient_id' => $cita->paciente_id])->id,
            'fecha_emision' => now('America/Guayaquil'),
            'texto_constancia' => 'Certificado emitido por el doctor tratante.',
            'dias_reposo' => 0,
        ]);

        $this->actingAs($otherDoctor)
            ->get(route('doctor.certificados.show', $certificado))
            ->assertForbidden();
    }

    public function test_certificate_cannot_be_issued_for_non_completed_appointment(): void
    {
        [$doctor, , , $cita] = $this->clinicalScenario(Cita::ESTADO_CONFIRMADA);

        $this->actingAs($doctor)
            ->get(route('doctor.certificados.create', $cita))
            ->assertForbidden();

        $this->actingAs($doctor)
            ->post(route('doctor.certificados.store', $cita), [
                'texto_constancia' => 'No debe emitirse antes de finalizar la atencion.',
                'dias_reposo' => 0,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('certificados_medicos', 0);
    }

    public function test_patient_cannot_access_another_patient_certificate(): void
    {
        [$doctor, , , $cita] = $this->clinicalScenario(Cita::ESTADO_REALIZADA);
        $otherPatient = $this->userWithRole('paciente');

        $certificado = CertificadoMedico::create([
            'codigo' => 'CM-20260416-000002',
            'cita_id' => $cita->id,
            'paciente_id' => $cita->paciente_id,
            'doctor_id' => $doctor->id,
            'clinical_record_id' => ClinicalRecord::firstOrCreate(['patient_id' => $cita->paciente_id])->id,
            'fecha_emision' => now('America/Guayaquil'),
            'texto_constancia' => 'Certificado visible solo para su paciente.',
            'dias_reposo' => 0,
        ]);

        $this->actingAs($otherPatient)
            ->get(route('paciente.certificados.show', $certificado))
            ->assertForbidden();
    }

    private function clinicalScenario(string $estado): array
    {
        $specialty = Especialidad::query()->orderBy('id')->firstOrFail();
        $doctor = $this->userWithRole('doctor');
        $doctor->especialidades()->attach($specialty->id);
        $patient = $this->userWithRole('paciente');
        $otherDoctor = $this->userWithRole('doctor');
        $otherDoctor->especialidades()->attach($specialty->id);

        $cita = Cita::create([
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $specialty->id,
            'fecha' => '2026-04-15',
            'hora' => '09:00:00',
            'motivo_consulta' => 'Control medico',
            'estado' => $estado,
            'activo' => true,
        ]);

        return [$doctor, $patient, $otherDoctor, $cita];
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $user->roles()->attach($role);

        return $user;
    }
}
