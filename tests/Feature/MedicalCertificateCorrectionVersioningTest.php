<?php

namespace Tests\Feature;

use App\Jobs\EnviarCertificadoMedicoJob;
use App\Models\CertificadoMedico;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MedicalCertificateCorrectionVersioningTest extends TestCase
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
        Queue::fake();

        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_correcting_certificate_creates_new_version_preserves_old_version_and_updates_status(): void
    {
        [$doctor, $patient, , $cita] = $this->clinicalScenario(Cita::ESTADO_REALIZADA);

        // 1. Emit Initial Certificate (A)
        $this->actingAs($doctor)->post(route('doctor.certificados.store', $cita), [
            'texto_constancia' => 'Texto original del certificado A.',
            'dias_reposo' => 2,
            'reposo_desde' => '2026-04-16',
            'reposo_hasta' => '2026-04-17',
            'observaciones' => 'Observacion original.',
        ]);

        $certA = CertificadoMedico::where('cita_id', $cita->id)->firstOrFail();
        $this->assertSame(CertificadoMedico::ESTADO_VIGENTE, $certA->estado_version ?: CertificadoMedico::ESTADO_VIGENTE);
        $this->assertSame(1, $certA->version ?: 1);
        $pathA = $certA->pdf_path;
        $csvA = $certA->csv;
        $codigoA = $certA->codigo;
        Storage::disk('r2_private')->assertExists($pathA);

        // 2. Doctor access correction form for A
        $this->actingAs($doctor)
            ->get(route('doctor.certificados.corregir', $certA))
            ->assertOk()
            ->assertSee('Corregir certificado medico')
            ->assertSee('Texto original del certificado A.')
            ->assertSee('Aviso de correccion de certificado');

        // 3. Doctor submits correction (A -> B)
        $response = $this->actingAs($doctor)->post(route('doctor.certificados.store-corregido', $certA), [
            'motivo_correccion' => 'Error en dias de reposo, el paciente requiere 5 dias.',
            'texto_constancia' => 'Texto corregido del certificado B.',
            'dias_reposo' => 5,
            'reposo_desde' => '2026-04-16',
            'reposo_hasta' => '2026-04-20',
            'observaciones' => 'Observacion actualizada.',
        ]);

        $certB = CertificadoMedico::where('cita_id', $cita->id)->where('version', 2)->firstOrFail();
        $response->assertRedirect(route('doctor.certificados.show', $certB));

        // 4. Assert Immutability of Certificate A
        $certA->refresh();
        $this->assertSame(CertificadoMedico::ESTADO_REEMPLAZADO, $certA->estado_version);
        $this->assertSame($certB->id, $certA->reemplazado_por_id);
        $this->assertSame('Error en dias de reposo, el paciente requiere 5 dias.', $certA->motivo_correccion);
        $this->assertSame('Texto original del certificado A.', $certA->texto_constancia);
        $this->assertSame(2, $certA->dias_reposo);
        $this->assertSame($pathA, $certA->pdf_path);
        $this->assertSame($csvA, $certA->csv);
        $this->assertSame($codigoA, $certA->codigo);
        Storage::disk('r2_private')->assertExists($pathA);

        // 5. Assert Properties of New Certificate B
        $this->assertSame(CertificadoMedico::ESTADO_VIGENTE, $certB->estado_version);
        $this->assertSame(2, $certB->version);
        $this->assertSame($certA->id, $certB->reemplaza_a_id);
        $this->assertSame('Texto corregido del certificado B.', $certB->texto_constancia);
        $this->assertSame(5, $certB->dias_reposo);
        $this->assertNotEquals($pathA, $certB->pdf_path);
        $this->assertNotEquals($csvA, $certB->csv);
        $this->assertNotEquals($codigoA, $certB->codigo);
        Storage::disk('r2_private')->assertExists($certB->pdf_path);

        // 6. Relationship Cita::certificadoMedico resolves active certificate B
        $this->assertSame($certB->id, $cita->fresh()->certificadoMedico->id);
    }

    public function test_correction_form_requires_both_rest_dates_in_ui_when_rest_days_are_positive(): void
    {
        [$doctor, , , $cita] = $this->clinicalScenario(Cita::ESTADO_REALIZADA);

        $this->actingAs($doctor)->post(route('doctor.certificados.store', $cita), [
            'texto_constancia' => 'Certificado original con reposo.',
            'dias_reposo' => 2,
            'reposo_desde' => '2026-04-16',
            'reposo_hasta' => '2026-04-17',
        ]);

        $certificado = CertificadoMedico::where('cita_id', $cita->id)->firstOrFail();

        $this->actingAs($doctor)
            ->from(route('doctor.certificados.corregir', $certificado))
            ->post(route('doctor.certificados.store-corregido', $certificado), [
                'motivo_correccion' => 'Ajuste del periodo de reposo',
                'texto_constancia' => 'Certificado corregido con reposo.',
                'dias_reposo' => 3,
            ])
            ->assertRedirect(route('doctor.certificados.corregir', $certificado))
            ->assertSessionHasErrors(['reposo_desde']);

        $response = $this->actingAs($doctor)
            ->get(route('doctor.certificados.corregir', $certificado))
            ->assertOk()
            ->assertSee('Indica el rango de reposo cuando registras dias de reposo.')
            ->assertSee('Al indicar dias de reposo, las fechas "Reposo desde" y "Reposo hasta" son obligatorias.', false)
            ->assertSee('data-certificado-reposo-form', false)
            ->assertSee('certificados-form', false);

        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/<input(?=[^>]*name="reposo_desde")(?=[^>]*\srequired(?:\s|>|=))[^>]*>/', $html);
        $this->assertMatchesRegularExpression('/<input(?=[^>]*name="reposo_hasta")(?=[^>]*\srequired(?:\s|>|=))[^>]*>/', $html);
        $this->assertDatabaseCount('certificados_medicos', 1);

        $validResponse = $this->actingAs($doctor)
            ->post(route('doctor.certificados.store-corregido', $certificado), [
                'motivo_correccion' => 'Ajuste del periodo de reposo',
                'texto_constancia' => 'Certificado corregido con reposo.',
                'dias_reposo' => 3,
                'reposo_desde' => '2026-04-16',
                'reposo_hasta' => '2026-04-18',
            ]);

        $corregido = CertificadoMedico::where('cita_id', $cita->id)->where('version', 2)->firstOrFail();
        $validResponse->assertRedirect(route('doctor.certificados.show', $corregido));
        $this->assertSame(3, $corregido->dias_reposo);
        $this->assertDatabaseCount('certificados_medicos', 2);
    }

    public function test_multiple_corrections_chain_a_to_b_to_c(): void
    {
        [$doctor, , , $cita] = $this->clinicalScenario(Cita::ESTADO_REALIZADA);

        $this->actingAs($doctor)->post(route('doctor.certificados.store', $cita), [
            'texto_constancia' => 'Certificado V1',
            'dias_reposo' => 1,
            'reposo_desde' => '2026-04-16',
            'reposo_hasta' => '2026-04-16',
        ]);
        $certA = CertificadoMedico::where('cita_id', $cita->id)->firstOrFail();

        $this->actingAs($doctor)->post(route('doctor.certificados.store-corregido', $certA), [
            'motivo_correccion' => 'Primera correccion',
            'texto_constancia' => 'Certificado V2',
            'dias_reposo' => 2,
            'reposo_desde' => '2026-04-16',
            'reposo_hasta' => '2026-04-17',
        ]);
        $certB = CertificadoMedico::where('cita_id', $cita->id)->where('version', 2)->firstOrFail();

        $this->actingAs($doctor)->post(route('doctor.certificados.store-corregido', $certB), [
            'motivo_correccion' => 'Segunda correccion',
            'texto_constancia' => 'Certificado V3',
            'dias_reposo' => 3,
            'reposo_desde' => '2026-04-16',
            'reposo_hasta' => '2026-04-18',
        ]);
        $certC = CertificadoMedico::where('cita_id', $cita->id)->where('version', 3)->firstOrFail();

        $certA->refresh();
        $certB->refresh();

        $this->assertSame(CertificadoMedico::ESTADO_REEMPLAZADO, $certA->estado_version);
        $this->assertSame(CertificadoMedico::ESTADO_REEMPLAZADO, $certB->estado_version);
        $this->assertSame(CertificadoMedico::ESTADO_VIGENTE, $certC->estado_version);

        $this->assertSame($certC->id, $cita->fresh()->certificadoMedico->id);
        $this->assertCount(3, $cita->fresh()->certificadosMedicos);
    }

    public function test_cannot_correct_already_replaced_certificate(): void
    {
        [$doctor, , , $cita] = $this->clinicalScenario(Cita::ESTADO_REALIZADA);

        $this->actingAs($doctor)->post(route('doctor.certificados.store', $cita), [
            'texto_constancia' => 'Version 1',
            'dias_reposo' => 1,
            'reposo_desde' => '2026-04-16',
            'reposo_hasta' => '2026-04-16',
        ]);
        $certA = CertificadoMedico::where('cita_id', $cita->id)->firstOrFail();

        $this->actingAs($doctor)->post(route('doctor.certificados.store-corregido', $certA), [
            'motivo_correccion' => 'Cambio 1',
            'texto_constancia' => 'Version 2',
            'dias_reposo' => 2,
            'reposo_desde' => '2026-04-16',
            'reposo_hasta' => '2026-04-17',
        ]);
        $certB = CertificadoMedico::where('cita_id', $cita->id)->where('version', 2)->firstOrFail();

        // Attempting to correct A again when B is already active
        $response = $this->actingAs($doctor)->post(route('doctor.certificados.store-corregido', $certA), [
            'motivo_correccion' => 'Intento invalido sobre A',
            'texto_constancia' => 'Version 3 invalida',
            'dias_reposo' => 3,
            'reposo_desde' => '2026-04-16',
            'reposo_hasta' => '2026-04-18',
        ]);

        $response->assertRedirect(route('doctor.certificados.show', $certB));
        $this->assertDatabaseCount('certificados_medicos', 2);
    }

    public function test_unauthorized_doctor_or_patient_cannot_correct_certificate(): void
    {
        [$doctor, $patient, $otherDoctor, $cita] = $this->clinicalScenario(Cita::ESTADO_REALIZADA);

        $this->actingAs($doctor)->post(route('doctor.certificados.store', $cita), [
            'texto_constancia' => 'Version 1',
            'dias_reposo' => 1,
            'reposo_desde' => '2026-04-16',
            'reposo_hasta' => '2026-04-16',
        ]);
        $cert = CertificadoMedico::where('cita_id', $cita->id)->firstOrFail();

        $this->actingAs($otherDoctor)
            ->get(route('doctor.certificados.corregir', $cert))
            ->assertForbidden();

        $this->actingAs($otherDoctor)
            ->post(route('doctor.certificados.store-corregido', $cert), [
                'motivo_correccion' => 'Intento por doctor ajeno',
                'texto_constancia' => 'Hacked',
            ])
            ->assertForbidden();

        $this->actingAs($patient)
            ->get(route('doctor.certificados.corregir', $cert))
            ->assertRedirect('/')
            ->assertSessionHas('error', 'Acceso denegado');
    }

    public function test_public_verification_distinguishes_between_active_and_replaced_csv(): void
    {
        [$doctor, , , $cita] = $this->clinicalScenario(Cita::ESTADO_REALIZADA);

        $this->actingAs($doctor)->post(route('doctor.certificados.store', $cita), [
            'texto_constancia' => 'Version 1',
            'dias_reposo' => 1,
            'reposo_desde' => '2026-04-16',
            'reposo_hasta' => '2026-04-16',
        ]);
        $certA = CertificadoMedico::where('cita_id', $cita->id)->firstOrFail();

        $this->actingAs($doctor)->post(route('doctor.certificados.store-corregido', $certA), [
            'motivo_correccion' => 'Correccion de dias',
            'texto_constancia' => 'Version 2',
            'dias_reposo' => 3,
            'reposo_desde' => '2026-04-16',
            'reposo_hasta' => '2026-04-18',
        ]);
        $certB = CertificadoMedico::where('cita_id', $cita->id)->where('version', 2)->firstOrFail();

        // Check CSV A (Replaced)
        $this->get(route('documentos.verificar.show', $certA->csv))
            ->assertOk()
            ->assertSee('Reemplazado')
            ->assertSee('DOCUMENTO REEMPLAZADO');

        // Check CSV B (Active)
        $this->get(route('documentos.verificar.show', $certB->csv))
            ->assertOk()
            ->assertSee('Verificado')
            ->assertDontSee('DOCUMENTO REEMPLAZADO');
    }

    public function test_citas_dropdown_menu_shows_ver_and_corregir_and_no_descargar(): void
    {
        [$doctor, , , $cita] = $this->clinicalScenario(Cita::ESTADO_REALIZADA);

        // Menu BEFORE certificate emission
        $responseBefore = $this->actingAs($doctor)->get(route('doctor.citas', ['estado' => 'realizada', 'estado_filtro' => 'realizada']));
        $responseBefore->assertOk();
        $responseBefore->assertSee('Emitir certificado');

        // Emit certificate
        $this->actingAs($doctor)->post(route('doctor.certificados.store', $cita), [
            'texto_constancia' => 'Version 1',
            'dias_reposo' => 1,
            'reposo_desde' => '2026-04-16',
            'reposo_hasta' => '2026-04-16',
        ]);

        // Menu AFTER certificate emission
        $responseAfter = $this->actingAs($doctor)->get(route('doctor.citas', ['estado' => 'realizada', 'estado_filtro' => 'realizada']));
        $responseAfter->assertOk();
        $responseAfter->assertSee('Ver certificado');
        $responseAfter->assertSee('Corregir certificado');
        $responseAfter->assertDontSee('Descargar certificado');
    }

    public function test_medical_certificate_for_dependient_uses_dependient_name_in_text_pdf_view_and_public_verification(): void
    {
        [$doctor, $holder, , $citaHolder] = $this->clinicalScenario(Cita::ESTADO_REALIZADA);

        $dependiente = \App\Models\Dependiente::create([
            'user_id' => $holder->id,
            'nombre' => 'Anabel',
            'parentesco' => 'Hija',
            'dni' => '1234567890',
            'fecha_nacimiento' => '2018-05-10',
        ]);

        $citaDependiente = Cita::create([
            'paciente_id' => $holder->id,
            'dependiente_id' => $dependiente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $citaHolder->especialidad_id,
            'fecha' => '2026-04-16',
            'hora' => '10:00:00',
            'motivo_consulta' => 'Control pediatrico',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);

        // 1. Suggested text uses dependiente name "Anabel", not holder "Josthyn"
        $createResponse = $this->actingAs($doctor)->get(route('doctor.certificados.create', $citaDependiente));
        $createResponse->assertOk();
        $createResponse->assertSee('Anabel');

        // 2. Submit certificate
        $this->actingAs($doctor)->post(route('doctor.certificados.store', $citaDependiente), [
            'texto_constancia' => 'Se certifica que la paciente Anabel fue atendida.',
            'dias_reposo' => 2,
            'reposo_desde' => '2026-04-16',
            'reposo_hasta' => '2026-04-17',
        ]);

        $cert = CertificadoMedico::where('cita_id', $citaDependiente->id)->firstOrFail();
        $this->assertSame('Anabel', $cert->nombrePacienteReal());
        $this->assertSame($dependiente->id, $cert->dependiente_id);

        // 3. Show view displays dependiente name
        $showResponse = $this->actingAs($doctor)->get(route('doctor.certificados.show', $cert));
        $showResponse->assertOk();
        $showResponse->assertSee('Anabel');

        // 4. Public verification displays protected name for Anabel
        $this->get(route('documentos.verificar.show', $cert->csv))
            ->assertOk()
            ->assertSee('Anabel');
    }

    public function test_correcting_dependient_certificate_preserves_dependient_identity_in_v2(): void
    {
        [$doctor, $holder, , $citaHolder] = $this->clinicalScenario(Cita::ESTADO_REALIZADA);

        $dependiente = \App\Models\Dependiente::create([
            'user_id' => $holder->id,
            'nombre' => 'Anabel',
            'parentesco' => 'Hija',
            'dni' => '1234567890',
            'fecha_nacimiento' => '2018-05-10',
        ]);

        $citaDependiente = Cita::create([
            'paciente_id' => $holder->id,
            'dependiente_id' => $dependiente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $citaHolder->especialidad_id,
            'fecha' => '2026-04-16',
            'hora' => '10:00:00',
            'motivo_consulta' => 'Control pediatrico',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);

        $this->actingAs($doctor)->post(route('doctor.certificados.store', $citaDependiente), [
            'texto_constancia' => 'Se certifica que la paciente Anabel fue atendida.',
            'dias_reposo' => 1,
            'reposo_desde' => '2026-04-16',
            'reposo_hasta' => '2026-04-16',
        ]);
        $certV1 = CertificadoMedico::where('cita_id', $citaDependiente->id)->firstOrFail();

        $this->actingAs($doctor)->post(route('doctor.certificados.store-corregido', $certV1), [
            'motivo_correccion' => 'Ajuste de reposo',
            'texto_constancia' => 'Se certifica que la paciente Anabel requiere 3 dias.',
            'dias_reposo' => 3,
            'reposo_desde' => '2026-04-16',
            'reposo_hasta' => '2026-04-18',
        ]);

        $certV2 = CertificadoMedico::where('cita_id', $citaDependiente->id)->where('version', 2)->firstOrFail();
        $this->assertSame('Anabel', $certV2->nombrePacienteReal());
        $this->assertSame($dependiente->id, $certV2->dependiente_id);
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
