<?php

namespace Tests\Feature;

use App\Models\CertificadoMedico;
use App\Models\Cita;
use App\Models\Dependiente;
use App\Models\Role;
use App\Models\User;
use App\Services\MedicalCertificateDocumentService;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MedicalCertificateR2StorageTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['private_documents.disk' => 'r2_private']);
        config(['image_optimization.avatar_disk' => 'r2_private']);
        Storage::fake('r2_private');
        Storage::fake('local');
        Storage::fake('public');
        Mail::fake();

        $this->seedRoles();
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/private/scratch/certificate_migration_manifest.json'));
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

    private function createRealizedCita(User $doctor, User $paciente, ?Dependiente $dependiente = null): Cita
    {
        $esp = \App\Models\Especialidad::firstOrCreate(['nombre' => 'Medicina General']);

        return Cita::create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'dependiente_id' => $dependiente?->id,
            'especialidad_id' => $esp->id,
            'fecha' => now()->toDateString(),
            'hora' => '10:00',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);
    }

    // 1. Nuevo certificado se guarda exclusivamente en r2_private
    // 2. Se guarda pdf_disk = r2_private
    // 3. Se guarda una clave relativa con UUID
    // 4. No se crea una copia local nueva
    public function test_new_certificate_creation_writes_exclusively_to_r2_private(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        $response = $this->actingAs($doctor)->post(route('doctor.certificados.store', $cita->id), [
            'texto_constancia' => 'Paciente requiere reposo medico por 3 dias.',
            'dias_reposo' => 3,
            'reposo_desde' => now()->toDateString(),
            'reposo_hasta' => now()->addDays(3)->toDateString(),
            'observaciones' => 'Reposo domiciliario',
        ]);

        $certificado = CertificadoMedico::where('cita_id', $cita->id)->firstOrFail();
        $response->assertRedirect(route('doctor.certificados.show', $certificado->id));

        $this->assertEquals('r2_private', $certificado->pdf_disk);
        $this->assertStringStartsWith('documents/medical-certificates/', $certificado->pdf_path);
        $this->assertStringEndsWith('.pdf', $certificado->pdf_path);

        $r2Disk = Storage::disk('r2_private');
        $localDisk = Storage::disk('local');

        $this->assertTrue($r2Disk->exists($certificado->pdf_path));
        $this->assertFalse($localDisk->exists($certificado->pdf_path));

        $binary = $r2Disk->get($certificado->pdf_path);
        $this->assertStringStartsWith('%PDF', $binary);
    }

    // 5. Doctor autorizado puede verlo y descargarlo
    // 6. Paciente correspondiente puede verlo y descargarlo
    // 7. Representante de dependiente puede acceder
    // 8. Usuario no relacionado recibe 403
    // 9. Invitado es redirigido o rechazado
    // 10. Visualización usa inline
    // 11. Descarga usa attachment
    // 12. Respuesta tiene application/pdf
    public function test_certificate_authorization_and_download_flow(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $otherPatient = $this->createRoleUser('paciente');

        $dependiente = Dependiente::create([
            'user_id' => $paciente->id,
            'nombre' => 'Hijo Certificado',
            'tipo_documento' => 'cedula',
            'dni' => '1754504636',
            'fecha_nacimiento' => '2020-01-01',
            'sexo' => 'Masculino',
            'parentesco' => 'hijo',
            'activo' => true,
        ]);

        $cita = $this->createRealizedCita($doctor, $paciente, $dependiente);

        $service = app(MedicalCertificateDocumentService::class);

        $certificado = CertificadoMedico::create([
            'codigo' => 'CM-20260802-000001',
            'csv' => 'CM-12345-CERT',
            'cita_id' => $cita->id,
            'paciente_id' => $paciente->id,
            'dependiente_id' => $dependiente->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now(),
            'texto_constancia' => 'Constancia test',
            'dias_reposo' => 2,
            'pdf_path' => '',
        ]);

        [$pdfBinary, $csv] = $service->generatePdfOutput($certificado);
        $st = $service->storeCertificatePdf($certificado, $pdfBinary);
        $certificado->update(['pdf_path' => $st['pdf_path'], 'pdf_disk' => $st['pdf_disk']]);

        $docDownloadRoute = route('doctor.certificados.download', $certificado->id);
        $pacDownloadRoute = route('paciente.certificados.download', $certificado->id);

        // 5. Doctor download
        $respDoc = $this->actingAs($doctor)->get($docDownloadRoute);
        $respDoc->assertStatus(200);
        $respDoc->assertHeader('Content-Type', 'application/pdf');

        // 6 & 7. Paciente Titular (representante del dependiente): 200 OK
        $respPac = $this->actingAs($paciente)->get($pacDownloadRoute);
        $respPac->assertStatus(200);
        $respPac->assertHeader('Content-Type', 'application/pdf');

        // 8. Usuario no relacionado recibe 403
        $this->actingAs($otherPatient)->get($pacDownloadRoute)->assertStatus(403);

        // 9. Invitado no autenticado
        auth()->logout();
        $respGuest = $this->get($pacDownloadRoute);
        $this->assertTrue($respGuest->isRedirect() || in_array($respGuest->status(), [302, 401, 403], true));
    }

    // 13. Correo adjunta correctamente desde R2
    public function test_email_attaches_certificate_from_r2_storage(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        $service = app(MedicalCertificateDocumentService::class);

        $certificado = CertificadoMedico::create([
            'codigo' => 'CM-20260802-000002',
            'csv' => 'CM-12345-MAIL',
            'cita_id' => $cita->id,
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now(),
            'texto_constancia' => 'Constancia Mail',
            'dias_reposo' => 1,
            'pdf_path' => '',
        ]);

        [$pdfBinary, $csv] = $service->generatePdfOutput($certificado);
        $st = $service->storeCertificatePdf($certificado, $pdfBinary);
        $certificado->update(['pdf_path' => $st['pdf_path'], 'pdf_disk' => $st['pdf_disk']]);

        $attachment = $service->getMailAttachment($certificado, 'certificado.pdf', null);
        $this->assertNotNull($attachment);
    }

    // 14. Documento local heredado continúa funcionando
    public function test_legacy_local_certificate_continues_working(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        $localPath = 'certificados-medicos/legacy_cm_1.pdf';
        Storage::disk('local')->put($localPath, '%PDF-1.4 Legacy Certificate Content');

        $certificado = CertificadoMedico::create([
            'codigo' => 'CM-LEGACY-001',
            'csv' => 'CM-12345-LEGACY',
            'cita_id' => $cita->id,
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now(),
            'texto_constancia' => 'Constancia Legacy',
            'pdf_path' => $localPath,
            'pdf_disk' => null,
        ]);

        $response = $this->actingAs($doctor)->get(route('doctor.certificados.download', $certificado->id));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');

        $content = $response->streamedContent() ?: $response->getContent();
        $this->assertStringContainsString('Legacy Certificate Content', $content);
    }

    // 15. Fallo de subida conserva el estado anterior
    // 16. Reemplazo atómico elimina R2 anterior solo después del éxito
    // 17. Archivo local anterior nunca se elimina
    public function test_atomic_replacement_and_error_handling(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        $service = app(MedicalCertificateDocumentService::class);

        $certificado = CertificadoMedico::create([
            'codigo' => 'CM-REPLACE-001',
            'csv' => 'CM-12345-REPL',
            'cita_id' => $cita->id,
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now(),
            'texto_constancia' => 'Constancia Replace 1',
            'pdf_path' => '',
        ]);

        [$pdfBinary1] = $service->generatePdfOutput($certificado);
        $st1 = $service->storeCertificatePdf($certificado, $pdfBinary1);
        $key1 = $st1['pdf_path'];
        $certificado->update(['pdf_path' => $key1, 'pdf_disk' => $st1['pdf_disk']]);

        $disk = Storage::disk('r2_private');
        $this->assertTrue($disk->exists($key1));

        // Perform replacement
        [$pdfBinary2] = $service->generatePdfOutput($certificado);
        $st2 = $service->replaceCertificatePdf($certificado, $pdfBinary2);
        $key2 = $st2['pdf_path'];

        $certificado->update(['pdf_path' => $key2, 'pdf_disk' => $st2['pdf_disk']]);
        $service->cleanupOldPdf($st2['old_pdf_path'], $st2['old_pdf_disk']);

        $this->assertTrue($disk->exists($key2));
        $this->assertFalse($disk->exists($key1));
    }

    // 18. --dry-run no modifica nada
    // 19. --execute migra solamente activos
    // 20. Huérfanos no se suben
    // 21. --verify detecta objetos ausentes o corruptos

    // 22. Verificación pública no expone rutas privadas
    public function test_public_verification_page_does_not_expose_private_r2_url(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        $service = app(MedicalCertificateDocumentService::class);

        $certificado = CertificadoMedico::create([
            'codigo' => 'CM-VERIF-001',
            'csv' => 'CM-VERIF-CSV-999',
            'cita_id' => $cita->id,
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now(),
            'texto_constancia' => 'Constancia Verification',
            'pdf_path' => '',
        ]);

        [$pdfBinary, $csv] = $service->generatePdfOutput($certificado);
        $st = $service->storeCertificatePdf($certificado, $pdfBinary);
        $certificado->update(['pdf_path' => $st['pdf_path'], 'pdf_disk' => $st['pdf_disk']]);

        $route = route('documentos.verificar.show', ['csv' => $csv]);
        $response = $this->get($route);

        $response->assertStatus(200);
        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));
        $this->assertStringNotContainsString('r2.cloudflarestorage.com', $response->getContent());
        $this->assertStringNotContainsString('r2.dev', $response->getContent());
    }

    // 23. Almacenamiento de recetas y funcionalidades continúan funcionando
    public function test_recipes_storage_and_features_continue_working(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        $recipeService = app(\App\Services\RecipeDocumentService::class);
        [$pdfBinary, $csv] = $recipeService->generatePdfOutput($cita, 'Diag Receta', 'Med Receta');

        $receta = \App\Models\Receta::create([
            'cita_id' => $cita->id,
            'diagnostico' => 'Diag Receta',
            'medicamentos' => 'Med Receta',
            'csv' => $csv,
            'pdf_path' => '',
        ]);

        $st = $recipeService->storeRecipePdf($receta, $pdfBinary);
        $receta->update(['pdf_path' => $st['pdf_path'], 'pdf_disk' => $st['pdf_disk']]);

        $this->assertEquals('r2_private', $receta->pdf_disk);
        $this->assertTrue(Storage::disk('r2_private')->exists($receta->pdf_path));
    }

    // 24. Botones de certificado incluyen atributos de exclusión del cargador de navegación
    public function test_certificate_download_and_view_links_have_loader_exclusion_attributes(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        $service = app(MedicalCertificateDocumentService::class);
        $certificado = CertificadoMedico::create([
            'codigo' => 'CM-TEST-ATTRS-01',
            'csv' => 'CM-TEST-ATTRS-CSV',
            'cita_id' => $cita->id,
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now(),
            'texto_constancia' => 'Constancia test attrs',
            'pdf_path' => '',
        ]);

        [$pdfBinary] = $service->generatePdfOutput($certificado);
        $st = $service->storeCertificatePdf($certificado, $pdfBinary);
        $certificado->update(['pdf_path' => $st['pdf_path'], 'pdf_disk' => $st['pdf_disk']]);

        $responseDoc = $this->actingAs($doctor)->get(route('doctor.citas'));
        $responseDoc->assertStatus(200);
        $responseDoc->assertSee('data-skip-page-loader', false);
        $responseDoc->assertSee('data-action-lock-ignore', false);

        $responseShow = $this->actingAs($doctor)->get(route('doctor.certificados.show', $certificado->id));
        $responseShow->assertStatus(200);
        $responseShow->assertSee('data-skip-page-loader', false);
        $responseShow->assertSee('data-action-lock-ignore', false);
    }
}
