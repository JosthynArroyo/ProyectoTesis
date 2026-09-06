<?php

namespace Tests\Feature;

use App\Models\CertificadoMedico;
use App\Models\Cita;
use App\Models\ClinicalRecord;
use App\Models\Especialidad;
use App\Models\Role;
use App\Models\User;
use App\Mail\CertificadoMedicoMail;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MedicalCertificateFlowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-04-16 10:00:00', 'America/Guayaquil'));
        config(['private_documents.disk' => 'r2_private']);
        config(['image_optimization.avatar_disk' => 'r2_private']);
        Storage::fake('local');
        Storage::fake('public');
        Storage::fake('r2_private');
        Mail::fake();

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
        Storage::disk($certificado->pdf_disk ?? 'local')->assertExists($certificado->pdf_path);
        $this->assertSame('sent', $certificado->fresh()->envio_estado);
        $this->assertSame($patient->email, $certificado->fresh()->enviado_a);
        Mail::assertSent(CertificadoMedicoMail::class);

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

    public function test_issue_form_requires_both_rest_dates_in_ui_when_rest_days_are_positive(): void
    {
        [$doctor, , , $cita] = $this->clinicalScenario(Cita::ESTADO_REALIZADA);

        $initialResponse = $this->actingAs($doctor)
            ->get(route('doctor.certificados.create', $cita))
            ->assertOk()
            ->assertSee('Al indicar dias de reposo, las fechas "Reposo desde" y "Reposo hasta" son obligatorias.', false)
            ->assertSee('certificados-form', false);

        $initialHtml = $initialResponse->getContent();
        $requiredDesde = '/<input(?=[^>]*name="reposo_desde")(?=[^>]*\srequired(?:\s|>|=))[^>]*>/';
        $requiredHasta = '/<input(?=[^>]*name="reposo_hasta")(?=[^>]*\srequired(?:\s|>|=))[^>]*>/';

        $this->assertDoesNotMatchRegularExpression($requiredDesde, $initialHtml);
        $this->assertDoesNotMatchRegularExpression($requiredHasta, $initialHtml);

        $this->actingAs($doctor)
            ->from(route('doctor.certificados.create', $cita))
            ->post(route('doctor.certificados.store', $cita), [
                'texto_constancia' => 'Certificado con reposo sin rango.',
                'dias_reposo' => 2,
            ])
            ->assertRedirect(route('doctor.certificados.create', $cita))
            ->assertSessionHasErrors(['reposo_desde']);

        $errorResponse = $this->actingAs($doctor)
            ->get(route('doctor.certificados.create', $cita))
            ->assertOk()
            ->assertSee('Indica el rango de reposo cuando registras dias de reposo.')
            ->assertSee('Al indicar dias de reposo, las fechas "Reposo desde" y "Reposo hasta" son obligatorias.', false);

        $errorHtml = $errorResponse->getContent();
        $this->assertMatchesRegularExpression($requiredDesde, $errorHtml);
        $this->assertMatchesRegularExpression($requiredHasta, $errorHtml);
    }

    public function test_certificate_pdf_view_does_not_contain_visual_signature_block_and_preserves_meta(): void
    {
        [$doctor, $patient, , $cita] = $this->clinicalScenario(Cita::ESTADO_REALIZADA);

        $certificado = CertificadoMedico::create([
            'codigo' => 'CM-20260416-000099',
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinical_record_id' => ClinicalRecord::firstOrCreate(['patient_id' => $patient->id])->id,
            'fecha_emision' => now('America/Guayaquil'),
            'texto_constancia' => 'Certificado medico sin bloque visual de firma.',
            'dias_reposo' => 1,
        ]);

        $html = view('pdf.certificado-medico', [
            'certificado' => $certificado,
            'clinica' => 'Clinica Don Bosco',
            'pdfCss' => '',
            'csv' => 'TESTCSV123',
            'verificationUrl' => 'http://localhost/verificar/documento/TESTCSV123',
            'qrDataUri' => 'data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=',
        ])->render();

        $this->assertStringContainsString('<b>Doctor:</b>', $html);
        $this->assertStringContainsString('<b>Especialidad:</b>', $html);
        $this->assertStringContainsString('CSV: TESTCSV123', $html);
        $this->assertStringContainsString('Verificacion publica', $html);

        $this->assertStringNotContainsString('Validado en sistema', $html);
        $this->assertStringNotContainsString('Usuario #', $html);
        $this->assertStringNotContainsString('class="sign-row', $html);
        $this->assertStringNotContainsString('class="sign"', $html);
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

    public function test_concurrent_store_requests_produce_exactly_one_vigente_certificate_under_lock(): void
    {
        [$doctor, $patient, , $cita] = $this->clinicalScenario(Cita::ESTADO_REALIZADA);

        // 1. Simular concurrencia: Request A pasa la validación inicial antes de la transacción.
        // Mientras tanto, Request B entra a la transacción y crea el primer certificado vigente.
        $certB = CertificadoMedico::create([
            'codigo' => 'CM-20260416-000099',
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinical_record_id' => ClinicalRecord::firstOrCreate(['patient_id' => $patient->id])->id,
            'estado_version' => CertificadoMedico::ESTADO_VIGENTE,
            'version' => 1,
            'fecha_emision' => now('America/Guayaquil'),
            'texto_constancia' => 'Certificado creado concurrentemente por request B.',
            'dias_reposo' => 0,
        ]);

        // 2. Request A intenta ejecutar store() en la misma cita
        $response = $this->actingAs($doctor)
            ->post(route('doctor.certificados.store', $cita), [
                'texto_constancia' => 'Intento concurrente de request A.',
                'dias_reposo' => 0,
            ]);

        // 3. Debe ser redirigido de forma controlada a la vista del certificado vigente
        $response->assertRedirect(route('doctor.certificados.show', $certB));
        $response->assertSessionHas('info');

        // 4. Invariante: COUNT(vigente) debe ser exactamente 1
        $vigentes = CertificadoMedico::query()
            ->where('cita_id', $cita->id)
            ->vigente()
            ->get();

        $this->assertCount(1, $vigentes);
        $this->assertSame($certB->id, $vigentes->first()->id);
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
