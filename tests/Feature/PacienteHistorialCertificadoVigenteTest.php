<?php

namespace Tests\Feature;

use App\Models\CertificadoMedico;
use App\Models\Cita;
use App\Models\Dependiente;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PacienteHistorialCertificadoVigenteTest extends TestCase
{
    use DatabaseTransactions;

    public function test_historial_shows_only_vigente_certificate_when_versioned(): void
    {
        [$patient, $doctor, $cita] = $this->createBaseContext();

        $v1 = CertificadoMedico::create([
            'codigo' => 'CM-20260810-000001',
            'estado_version' => CertificadoMedico::ESTADO_REEMPLAZADO,
            'version' => 1,
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now()->subDay(),
            'texto_constancia' => 'Constancia V1',
            'dias_reposo' => 3,
            'csv' => 'CSV-V1-1111',
            'pdf_path' => 'certificados/cm-v1.pdf',
            'pdf_disk' => 'r2_private',
        ]);

        $v2 = CertificadoMedico::create([
            'codigo' => 'CM-20260810-000001-2',
            'estado_version' => CertificadoMedico::ESTADO_VIGENTE,
            'version' => 2,
            'reemplaza_a_id' => $v1->id,
            'motivo_correccion' => 'Error en dias de reposo',
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now(),
            'texto_constancia' => 'Constancia V2 corregida',
            'dias_reposo' => 5,
            'csv' => 'CSV-V2-2222',
            'pdf_path' => 'certificados/cm-v2.pdf',
            'pdf_disk' => 'r2_private',
        ]);

        $v1->update(['reemplazado_por_id' => $v2->id]);

        $response = $this->actingAs($patient)->get(route('paciente.historial'));
        $response->assertOk();
        $response->assertSee('CM-20260810-000001-2');
        $response->assertDontSee('CM-20260810-000001<', false);
    }

    public function test_historial_shows_only_vigente_in_three_version_chain(): void
    {
        [$patient, $doctor, $cita] = $this->createBaseContext();

        $v1 = CertificadoMedico::create([
            'codigo' => 'CM-CHAIN-001',
            'estado_version' => CertificadoMedico::ESTADO_REEMPLAZADO,
            'version' => 1,
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now()->subDays(2),
            'texto_constancia' => 'Constancia V1',
            'dias_reposo' => 2,
            'csv' => 'CSV-V1',
        ]);

        $v2 = CertificadoMedico::create([
            'codigo' => 'CM-CHAIN-001-2',
            'estado_version' => CertificadoMedico::ESTADO_REEMPLAZADO,
            'version' => 2,
            'reemplaza_a_id' => $v1->id,
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now()->subDay(),
            'texto_constancia' => 'Constancia V2',
            'dias_reposo' => 4,
            'csv' => 'CSV-V2',
        ]);

        $v3 = CertificadoMedico::create([
            'codigo' => 'CM-CHAIN-001-3',
            'estado_version' => CertificadoMedico::ESTADO_VIGENTE,
            'version' => 3,
            'reemplaza_a_id' => $v2->id,
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now(),
            'texto_constancia' => 'Constancia V3',
            'dias_reposo' => 6,
            'csv' => 'CSV-V3',
        ]);

        $v1->update(['reemplazado_por_id' => $v2->id]);
        $v2->update(['reemplazado_por_id' => $v3->id]);

        $response = $this->actingAs($patient)->get(route('paciente.historial'));
        $response->assertOk();
        $response->assertSee('CM-CHAIN-001-3');
        $response->assertDontSee('CM-CHAIN-001<', false);
        $response->assertDontSee('CM-CHAIN-001-2<', false);
    }

    public function test_historial_shows_multiple_vigente_certificates_for_different_appointments(): void
    {
        [$patient, $doctor, $citaA] = $this->createBaseContext();

        $citaB = Cita::create([
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $citaA->especialidad_id,
            'fecha' => Carbon::now()->addDays(3)->toDateString(),
            'hora' => '11:00:00',
            'motivo_consulta' => 'Segunda consulta',
            'estado' => Cita::ESTADO_PENDIENTE,
        ]);

        // Cita A has V1 (reemplazado) + V2 (vigente)
        $certAV1 = CertificadoMedico::create([
            'codigo' => 'CM-CITA-A-1',
            'estado_version' => CertificadoMedico::ESTADO_REEMPLAZADO,
            'version' => 1,
            'cita_id' => $citaA->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now()->subDay(),
            'texto_constancia' => 'Cita A V1',
            'dias_reposo' => 1,
            'csv' => 'CSV-A1',
        ]);

        $certAV2 = CertificadoMedico::create([
            'codigo' => 'CM-CITA-A-2',
            'estado_version' => CertificadoMedico::ESTADO_VIGENTE,
            'version' => 2,
            'reemplaza_a_id' => $certAV1->id,
            'cita_id' => $citaA->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now(),
            'texto_constancia' => 'Cita A V2',
            'dias_reposo' => 3,
            'csv' => 'CSV-A2',
        ]);

        // Cita B has V1 (vigente)
        $certBV1 = CertificadoMedico::create([
            'codigo' => 'CM-CITA-B-1',
            'estado_version' => CertificadoMedico::ESTADO_VIGENTE,
            'version' => 1,
            'cita_id' => $citaB->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now(),
            'texto_constancia' => 'Cita B V1',
            'dias_reposo' => 2,
            'csv' => 'CSV-B1',
        ]);

        $response = $this->actingAs($patient)->get(route('paciente.historial'));
        $response->assertOk();
        $response->assertSee('CM-CITA-A-2');
        $response->assertSee('CM-CITA-B-1');
        $response->assertDontSee('CM-CITA-A-1');
    }

    public function test_ver_y_descargar_from_historial_use_vigente_certificate(): void
    {
        Storage::fake('r2_private');
        [$patient, $doctor, $cita] = $this->createBaseContext();

        Storage::disk('r2_private')->put('certificados/v1.pdf', 'PDF_V1_CONTENT');
        Storage::disk('r2_private')->put('certificados/v2.pdf', 'PDF_V2_CONTENT');

        $v1 = CertificadoMedico::create([
            'codigo' => 'CM-DOWNLOAD-1',
            'estado_version' => CertificadoMedico::ESTADO_REEMPLAZADO,
            'version' => 1,
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now()->subDay(),
            'texto_constancia' => 'Constancia V1',
            'dias_reposo' => 1,
            'csv' => 'CSV-DL1',
            'pdf_path' => 'certificados/v1.pdf',
            'pdf_disk' => 'r2_private',
        ]);

        $v2 = CertificadoMedico::create([
            'codigo' => 'CM-DOWNLOAD-2',
            'estado_version' => CertificadoMedico::ESTADO_VIGENTE,
            'version' => 2,
            'reemplaza_a_id' => $v1->id,
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now(),
            'texto_constancia' => 'Constancia V2',
            'dias_reposo' => 5,
            'csv' => 'CSV-DL2',
            'pdf_path' => 'certificados/v2.pdf',
            'pdf_disk' => 'r2_private',
        ]);

        // Ver certificate link points to V2
        $response = $this->actingAs($patient)->get(route('paciente.historial'));
        $response->assertOk();
        $response->assertSee(route('paciente.certificados.show', $v2));
        $response->assertSee(route('paciente.certificados.download', $v2));

        // Download streams V2 PDF
        $downloadResponse = $this->actingAs($patient)->get(route('paciente.certificados.download', $v2));
        $downloadResponse->assertOk();
        $this->assertStringContainsString('PDF_V2_CONTENT', $downloadResponse->getContent());
    }

    public function test_direct_access_to_replaced_certificate_shows_replacement_alert_and_link_to_vigente(): void
    {
        [$patient, $doctor, $cita] = $this->createBaseContext();

        $v1 = CertificadoMedico::create([
            'codigo' => 'CM-REPLACED-1',
            'estado_version' => CertificadoMedico::ESTADO_REEMPLAZADO,
            'version' => 1,
            'motivo_correccion' => 'Correccion de dias',
            'fecha_correccion' => now(),
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now()->subDay(),
            'texto_constancia' => 'Constancia V1',
            'dias_reposo' => 1,
            'csv' => 'CSV-REP1',
        ]);

        $v2 = CertificadoMedico::create([
            'codigo' => 'CM-REPLACED-2',
            'estado_version' => CertificadoMedico::ESTADO_VIGENTE,
            'version' => 2,
            'reemplaza_a_id' => $v1->id,
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now(),
            'texto_constancia' => 'Constancia V2',
            'dias_reposo' => 4,
            'csv' => 'CSV-REP2',
        ]);

        $v1->update(['reemplazado_por_id' => $v2->id]);

        $response = $this->actingAs($patient)->get(route('paciente.certificados.show', $v1));
        $response->assertOk();
        $response->assertSee('DOCUMENTO REEMPLAZADO');
        $response->assertSee('Correccion de dias');
        $response->assertSee('Ver certificado vigente');
        $response->assertSee(route('paciente.certificados.show', $v2));
    }

    public function test_historial_for_dependientes_shows_only_vigente_certificate(): void
    {
        [$patient, $doctor, $cita] = $this->createBaseContext();

        $dep = Dependiente::create([
            'user_id' => $patient->id,
            'nombre' => 'Anabel Arroyo',
            'parentesco' => 'Hija',
            'dni' => '1111111111',
            'fecha_nacimiento' => '2018-05-10',
            'activo' => true,
        ]);

        $cita->update(['dependiente_id' => $dep->id]);

        $v1 = CertificadoMedico::create([
            'codigo' => 'CM-DEP-1',
            'estado_version' => CertificadoMedico::ESTADO_REEMPLAZADO,
            'version' => 1,
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'dependiente_id' => $dep->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now()->subDay(),
            'texto_constancia' => 'Constancia V1 Anabel',
            'dias_reposo' => 2,
            'csv' => 'CSV-DEP1',
        ]);

        $v2 = CertificadoMedico::create([
            'codigo' => 'CM-DEP-2',
            'estado_version' => CertificadoMedico::ESTADO_VIGENTE,
            'version' => 2,
            'reemplaza_a_id' => $v1->id,
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'dependiente_id' => $dep->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now(),
            'texto_constancia' => 'Constancia V2 Anabel',
            'dias_reposo' => 5,
            'csv' => 'CSV-DEP2',
        ]);

        $response = $this->actingAs($patient)->get(route('paciente.historial', ['paciente' => $dep->id]));
        $response->assertOk();
        $response->assertSee('CM-DEP-2');
        $response->assertDontSee('CM-DEP-1');
    }

    private function createBaseContext(): array
    {
        $patientRole = Role::query()->firstOrCreate(['name' => 'paciente'], ['label' => 'Paciente']);
        $doctorRole = Role::query()->firstOrCreate(['name' => 'doctor'], ['label' => 'Doctor']);

        $patient = User::factory()->create(['name' => 'Josthyn Arroyo']);
        $patient->roles()->attach($patientRole);

        $doctor = User::factory()->create(['name' => 'Dr. Suarez']);
        $doctor->roles()->attach($doctorRole);
        $specialty = $doctor->especialidades()->getRelated()->newQuery()->create([
            'nombre' => 'Especialidad historial '.uniqid(),
            'activo' => true,
        ]);
        $doctor->especialidades()->attach($specialty);

        $cita = Cita::create([
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $specialty->id,
            'fecha' => Carbon::now()->addDays(2)->toDateString(),
            'hora' => '10:00:00',
            'motivo_consulta' => 'Consulta medica',
            'estado' => Cita::ESTADO_PENDIENTE,
        ]);

        return [$patient, $doctor, $cita];
    }
}
