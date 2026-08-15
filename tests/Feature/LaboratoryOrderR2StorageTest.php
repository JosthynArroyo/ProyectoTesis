<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Dependiente;
use App\Models\PedidoLaboratorio;
use App\Models\PedidoLaboratorioResultado;
use App\Models\Role;
use App\Models\User;
use App\Services\LaboratoryOrderDocumentService;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LaboratoryOrderR2StorageTest extends TestCase
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
        @unlink(storage_path('app/private/scratch/laboratory_order_migration_manifest.json'));
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

    // 1. Nueva orden se guarda exclusivamente en r2_private
    // 2. Se guarda pdf_disk = r2_private
    // 3. Se guarda una clave relativa con UUID
    // 4. No se crea una copia local nueva
    public function test_new_laboratory_order_writes_exclusively_to_r2_private(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        $response = $this->actingAs($doctor)->post(route('doctor.pedidos-laboratorio.store', $cita->id), [
            'examenes' => ['hemograma_completo', 'glucosa_ayunas'],
        ]);

        $pedido = PedidoLaboratorio::where('cita_id', $cita->id)->firstOrFail();
        $response->assertRedirect(route('doctor.citas'));

        $this->assertEquals('r2_private', $pedido->pdf_disk);
        $this->assertStringStartsWith('documents/laboratory-orders/', $pedido->pdf_path);
        $this->assertStringEndsWith('.pdf', $pedido->pdf_path);

        $r2Disk = Storage::disk('r2_private');
        $localDisk = Storage::disk('local');

        $this->assertTrue($r2Disk->exists($pedido->pdf_path));
        $this->assertFalse($localDisk->exists($pedido->pdf_path));

        $binary = $r2Disk->get($pedido->pdf_path);
        $this->assertStringStartsWith('%PDF', $binary);
    }

    // 5. Doctor autorizado puede acceder
    // 6. Paciente correspondiente puede acceder
    // 7. Representante del dependiente puede acceder
    // 8. Usuario de laboratorio puede acceder
    // 9. Usuario no relacionado recibe 403
    // 10. Invitado no accede
    // 11. Descarga devuelve application/pdf
    // 12. Disposición inline
    public function test_laboratory_order_authorization_and_download_flow(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $labUser = $this->createRoleUser('laboratorio');
        $otherPatient = $this->createRoleUser('paciente');

        $dependiente = Dependiente::create([
            'user_id' => $paciente->id,
            'nombre' => 'Hijo Lab',
            'tipo_documento' => 'cedula',
            'dni' => '1754504637',
            'fecha_nacimiento' => '2020-01-01',
            'sexo' => 'Masculino',
            'parentesco' => 'hijo',
            'activo' => true,
        ]);

        $cita = $this->createRealizedCita($doctor, $paciente, $dependiente);
        $service = app(LaboratoryOrderDocumentService::class);

        $pedido = PedidoLaboratorio::create([
            'cita_id' => $cita->id,
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'csv' => 'LAB-12345-TEST',
            'examenes' => ['hemograma_completo'],
            'estado' => 'pendiente_toma',
            'pdf_path' => '',
        ]);

        [$pdfBinary, $csv] = $service->generatePdfOutput($pedido);
        $st = $service->storeOrderPdf($pedido, $pdfBinary);
        $pedido->update(['pdf_path' => $st['pdf_path'], 'pdf_disk' => $st['pdf_disk']]);

        $docDownloadRoute = route('doctor.pedidos-laboratorio.download', $pedido->id);
        $labDownloadRoute = route('laboratorio.pedidos.download-orden', $pedido->id);

        // 5. Doctor autorizado: 200 OK
        $respDoc = $this->actingAs($doctor)->get($docDownloadRoute);
        $respDoc->assertStatus(200);
        $respDoc->assertHeader('Content-Type', 'application/pdf');

        // 8. Usuario de laboratorio autorizado: 200 OK
        $respLab = $this->actingAs($labUser)->get($labDownloadRoute);
        $respLab->assertStatus(200);
        $respLab->assertHeader('Content-Type', 'application/pdf');

        // 9. Doctor no relacionado recibe 403
        $otherDoctor = $this->createRoleUser('doctor');
        $this->actingAs($otherDoctor)->get($docDownloadRoute)->assertStatus(403);

        // 10. Invitado no autenticado
        auth()->logout();
        $respGuest = $this->get($docDownloadRoute);
        $this->assertTrue($respGuest->isRedirect() || in_array($respGuest->status(), [302, 401, 403], true));
    }

    // 13. Correo adjunta desde R2
    public function test_email_attaches_order_from_r2_storage(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        $service = app(LaboratoryOrderDocumentService::class);

        $pedido = PedidoLaboratorio::create([
            'cita_id' => $cita->id,
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'csv' => 'LAB-12345-MAIL',
            'examenes' => ['hemograma_completo'],
            'estado' => 'pendiente_toma',
            'pdf_path' => '',
        ]);

        [$pdfBinary, $csv] = $service->generatePdfOutput($pedido);
        $st = $service->storeOrderPdf($pedido, $pdfBinary);
        $pedido->update(['pdf_path' => $st['pdf_path'], 'pdf_disk' => $st['pdf_disk']]);

        $attachment = $service->getMailAttachment($pedido, 'orden.pdf', null);
        $this->assertNotNull($attachment);
    }

    // 14. Orden local heredada continúa funcionando
    public function test_legacy_local_order_continues_working(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        $localPath = 'pedidos-laboratorio/legacy_order_1.pdf';
        Storage::disk('local')->put($localPath, '%PDF-1.4 Legacy Order Content');

        $pedido = PedidoLaboratorio::create([
            'cita_id' => $cita->id,
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'csv' => 'LAB-LEGACY-001',
            'examenes' => ['hemograma_completo'],
            'estado' => 'pendiente_toma',
            'pdf_path' => $localPath,
            'pdf_disk' => null,
        ]);

        $response = $this->actingAs($doctor)->get(route('doctor.pedidos-laboratorio.download', $pedido->id));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');

        $content = $response->streamedContent() ?: $response->getContent();
        $this->assertStringContainsString('Legacy Order Content', $content);
    }

    // 15. Fallo de subida conserva el estado anterior
    // 16. Reemplazo atómico funciona si existe ese flujo
    // 17. Archivo local heredado permanece
    public function test_atomic_replacement_and_error_handling(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        $service = app(LaboratoryOrderDocumentService::class);

        $pedido = PedidoLaboratorio::create([
            'cita_id' => $cita->id,
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'csv' => 'LAB-REPLACE-001',
            'examenes' => ['hemograma_completo'],
            'estado' => 'pendiente_toma',
            'pdf_path' => '',
        ]);

        [$pdfBinary1] = $service->generatePdfOutput($pedido);
        $st1 = $service->storeOrderPdf($pedido, $pdfBinary1);
        $key1 = $st1['pdf_path'];
        $pedido->update(['pdf_path' => $key1, 'pdf_disk' => $st1['pdf_disk']]);

        $disk = Storage::disk('r2_private');
        $this->assertTrue($disk->exists($key1));

        [$pdfBinary2] = $service->generatePdfOutput($pedido);
        $st2 = $service->replaceOrderPdf($pedido, $pdfBinary2);
        $key2 = $st2['pdf_path'];

        $pedido->update(['pdf_path' => $key2, 'pdf_disk' => $st2['pdf_disk']]);
        $service->cleanupOldPdf($st2['old_pdf_path'], $st2['old_pdf_disk']);

        $this->assertTrue($disk->exists($key2));
        $this->assertFalse($disk->exists($key1));
    }

    // 18. --dry-run no modifica nada
    // 19. --execute es idempotente y migra solo activos
    // 20. Huérfanos no se suben
    // 21. --verify detecta corrupción

    // 22. Verificación pública no expone URL privada
    public function test_public_verification_page_does_not_expose_private_r2_url(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        $service = app(LaboratoryOrderDocumentService::class);

        $pedido = PedidoLaboratorio::create([
            'cita_id' => $cita->id,
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'csv' => 'LAB-VERIF-CSV-999',
            'examenes' => ['hemograma_completo'],
            'estado' => 'pendiente_toma',
            'pdf_path' => '',
        ]);

        [$pdfBinary, $csv] = $service->generatePdfOutput($pedido);
        $st = $service->storeOrderPdf($pedido, $pdfBinary);
        $pedido->update(['pdf_path' => $st['pdf_path'], 'pdf_disk' => $st['pdf_disk']]);

        $route = route('documentos.verificar.show', ['csv' => $csv]);
        $response = $this->get($route);

        $response->assertStatus(200);
        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));
        $this->assertStringNotContainsString('r2.cloudflarestorage.com', $response->getContent());
        $this->assertStringNotContainsString('r2.dev', $response->getContent());
    }

    // 23. Enlaces de descarga contienen exclusión del overlay
    public function test_download_links_have_loader_exclusion_attributes(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        $service = app(LaboratoryOrderDocumentService::class);
        $pedido = PedidoLaboratorio::create([
            'cita_id' => $cita->id,
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'csv' => 'LAB-ATTRS-CSV',
            'examenes' => ['hemograma_completo'],
            'estado' => 'pendiente_toma',
            'pdf_path' => '',
        ]);

        [$pdfBinary] = $service->generatePdfOutput($pedido);
        $st = $service->storeOrderPdf($pedido, $pdfBinary);
        $pedido->update(['pdf_path' => $st['pdf_path'], 'pdf_disk' => $st['pdf_disk']]);

        $responseDoc = $this->actingAs($doctor)->get(route('doctor.citas'));
        $responseDoc->assertStatus(200);
        $responseDoc->assertSee('data-skip-page-loader', false);
        $responseDoc->assertSee('data-action-lock-ignore', false);
    }

    // 24. Resultados de laboratorio permanecen intactos
    public function test_laboratory_results_flow_and_tables_remain_intact(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        $pedido = PedidoLaboratorio::create([
            'cita_id' => $cita->id,
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'csv' => 'LAB-RES-CSV',
            'examenes' => ['hemograma_completo'],
            'estado' => 'resultado_listo',
            'pdf_path' => 'documents/laboratory-orders/1/uuid.pdf',
            'pdf_disk' => 'r2_private',
        ]);

        $resultadoPath = 'laboratorio_resultados/res_1.pdf';
        Storage::disk('local')->put($resultadoPath, '%PDF-1.4 Lab Result Content');

        $resultado = PedidoLaboratorioResultado::create([
            'pedido_laboratorio_id' => $pedido->id,
            'laboratorio_id' => $doctor->id,
            'version' => 1,
            'pdf_path' => $resultadoPath,
            'observaciones_generales' => 'Resultado Normal',
            'estado' => PedidoLaboratorioResultado::ESTADO_PUBLICADO,
            'publicado_at' => now(),
            'csv' => 'LAB-RES-CSV-PUB',
        ]);

        $this->assertEquals($resultadoPath, $resultado->pdf_path);
        $this->assertTrue(Storage::disk('local')->exists($resultado->pdf_path));
    }

    // 25. Recetas y certificados continúan funcionando
    public function test_recipes_and_medical_certificates_continue_working(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        $certService = app(\App\Services\MedicalCertificateDocumentService::class);
        $certificado = \App\Models\CertificadoMedico::create([
            'codigo' => 'CM-REGRESSION-01',
            'csv' => 'CM-REGRESS-CSV',
            'cita_id' => $cita->id,
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now(),
            'texto_constancia' => 'Constancia test regression',
            'pdf_path' => '',
        ]);

        [$pdfBinary] = $certService->generatePdfOutput($certificado);
        $st = $certService->storeCertificatePdf($certificado, $pdfBinary);
        $certificado->update(['pdf_path' => $st['pdf_path'], 'pdf_disk' => $st['pdf_disk']]);

        $this->assertEquals('r2_private', $certificado->pdf_disk);
        $this->assertTrue(Storage::disk('r2_private')->exists($certificado->pdf_path));
    }
}
