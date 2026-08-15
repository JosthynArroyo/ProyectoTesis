<?php

namespace Tests\Feature;

use App\Models\CertificadoMedico;
use App\Models\Cita;
use App\Models\Dependiente;
use App\Models\Especialidad;
use App\Models\PedidoLaboratorio;
use App\Models\Receta;
use App\Models\Role;
use App\Models\User;
use App\Services\LaboratoryOrderDocumentService;
use App\Services\MedicalCertificateDocumentService;
use App\Services\RecipeDocumentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentoVerificacionPrivacyTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['private_documents.disk' => 'r2_private']);
        config(['private_documents.recipe_disk' => 'r2_private']);
        config(['private_documents.certificate_disk' => 'r2_private']);

        Storage::fake('r2_private');
        Storage::fake('local');
        Storage::fake('public');
        Mail::fake();

        $this->seedRoles();
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
        $esp = Especialidad::firstOrCreate(['nombre' => 'Medicina General']);

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

    /**
     * Test public QR verification page privacy for Receta Medica
     */
    public function test_public_qr_verification_for_recipe_returns_html_and_protects_privacy(): void
    {
        $doctor = $this->createRoleUser('doctor', ['name' => 'Dr. Carlos Mendoza']);
        $paciente = $this->createRoleUser('paciente', ['name' => 'Josthyn Arroyo Mendez']);
        $cita = $this->createRealizedCita($doctor, $paciente);

        $recipeService = app(RecipeDocumentService::class);
        [$pdfBinary, $csv] = $recipeService->generatePdfOutput($cita, 'Diag Sensible Privado', 'Med Sensible Privado');

        $receta = Receta::create([
            'cita_id' => $cita->id,
            'diagnostico' => 'Diag Sensible Privado',
            'medicamentos' => 'Med Sensible Privado',
            'csv' => $csv,
            'pdf_path' => '',
        ]);
        $st = $recipeService->storeRecipePdf($receta, $pdfBinary);
        $receta->update(['pdf_path' => $st['pdf_path'], 'pdf_disk' => $st['pdf_disk']]);

        $response = $this->get(route('documentos.verificar.show', ['csv' => $csv]));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
        $this->assertNotEquals('application/pdf', $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $this->assertStringStartsNotWith('%PDF', $content);

        // Verification metadata
        $response->assertSee('Receta médica');
        $response->assertSee($csv);
        $response->assertSee('Dr. Carlos Mendoza');
        $response->assertSee('Josthyn A.'); // Protected patient name
        $response->assertSee('Verificado');

        // Check protected & forbidden text
        $this->assertStringNotContainsString('Diag Sensible Privado', $content);
        $this->assertStringNotContainsString('Med Sensible Privado', $content);

        $this->assertStringNotContainsString('r2.dev', $content);
        $this->assertStringNotContainsString('r2_private', $content);
        $this->assertStringNotContainsString('/storage/', $content);
        $this->assertStringNotContainsString('documents/recipes/', $content);
        $this->assertStringNotContainsString('.pdf', $content);
        $this->assertStringNotContainsString('download', strtolower($content));
    }

    /**
     * Test public QR verification page privacy for Certificado Medico
     */
    public function test_public_qr_verification_for_certificate_returns_html_and_protects_privacy(): void
    {
        $doctor = $this->createRoleUser('doctor', ['name' => 'Dra. Maria Elena']);
        $paciente = $this->createRoleUser('paciente', ['name' => 'Roberto Carlos Gomez']);
        $cita = $this->createRealizedCita($doctor, $paciente);

        $certService = app(MedicalCertificateDocumentService::class);
        $cert = CertificadoMedico::create([
            'codigo' => 'CM-PRIVACY-01',
            'csv' => 'CERT-PRIV-123',
            'cita_id' => $cita->id,
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now(),
            'texto_constancia' => 'Texto Constancia Privado Clinico',
            'dias_reposo' => 5,
            'pdf_path' => '',
        ]);

        [$pdfBinary, $csv] = $certService->generatePdfOutput($cert);
        $st = $certService->storeCertificatePdf($cert, $pdfBinary);
        $cert->update(['pdf_path' => $st['pdf_path'], 'pdf_disk' => $st['pdf_disk']]);

        $response = $this->get(route('documentos.verificar.show', ['csv' => $csv]));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $this->assertStringStartsNotWith('%PDF', $content);

        $response->assertSee('Certificado médico');
        $response->assertSee($csv);
        $response->assertSee('Dra. Maria Elena');
        $response->assertSee('Roberto C.');
        $response->assertSee('Verificado');

        $this->assertStringNotContainsString('Texto Constancia Privado Clinico', $content);

        $this->assertStringNotContainsString('r2.dev', $content);
        $this->assertStringNotContainsString('r2_private', $content);
        $this->assertStringNotContainsString('/storage/', $content);
        $this->assertStringNotContainsString('documents/medical-certificates/', $content);
        $this->assertStringNotContainsString('.pdf', $content);
    }

    /**
     * Test public QR verification page privacy for Pedido de Laboratorio
     */
    public function test_public_qr_verification_for_lab_order_returns_html_and_protects_privacy(): void
    {
        $doctor = $this->createRoleUser('doctor', ['name' => 'Dr. Fernando Perez']);
        $paciente = $this->createRoleUser('paciente', ['name' => 'Ana Lucia Torres']);
        $cita = $this->createRealizedCita($doctor, $paciente);

        $labService = app(LaboratoryOrderDocumentService::class);
        $pedido = PedidoLaboratorio::create([
            'cita_id' => $cita->id,
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'csv' => 'LAB-PRIV-456',
            'examenes' => ['hemograma_completo', 'perfil_lipidico'],
            'estado' => 'pendiente_toma',
            'pdf_path' => '',
        ]);

        [$pdfBinary, $csv] = $labService->generatePdfOutput($pedido);
        $st = $labService->storeOrderPdf($pedido, $pdfBinary);
        $pedido->update(['pdf_path' => $st['pdf_path'], 'pdf_disk' => $st['pdf_disk']]);

        $response = $this->get(route('documentos.verificar.show', ['csv' => $csv]));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $this->assertStringStartsNotWith('%PDF', $content);

        $response->assertSee('Pedido de laboratorio');
        $response->assertSee($csv);
        $response->assertSee('Dr. Fernando Perez');
        $response->assertSee('Ana L.');
        $response->assertSee('Verificado');

        $this->assertStringNotContainsString('r2.dev', $content);
        $this->assertStringNotContainsString('r2_private', $content);
        $this->assertStringNotContainsString('/storage/', $content);
        $this->assertStringNotContainsString('documents/laboratory-orders/', $content);
        $this->assertStringNotContainsString('.pdf', $content);
    }

    /**
     * Test invalid CSV returns 404
     */
    public function test_invalid_csv_returns_404(): void
    {
        $response = $this->get(route('documentos.verificar.show', ['csv' => 'INVALID-CSV-999']));
        $response->assertStatus(404);
    }

    /**
     * Test authorization for lab order viewing (inline) and downloading (attachment)
     */
    public function test_laboratory_order_authorization_inline_and_attachment_responses(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $labUser = $this->createRoleUser('laboratorio');
        $unrelatedPatient = $this->createRoleUser('paciente');

        $dependiente = Dependiente::create([
            'user_id' => $paciente->id,
            'nombre' => 'Hijo Representado',
            'tipo_documento' => 'cedula',
            'dni' => '1754504699',
            'fecha_nacimiento' => '2021-05-05',
            'sexo' => 'Masculino',
            'parentesco' => 'hijo',
            'activo' => true,
        ]);

        $citaDep = $this->createRealizedCita($doctor, $paciente, $dependiente);

        $labService = app(LaboratoryOrderDocumentService::class);
        $pedido = PedidoLaboratorio::create([
            'cita_id' => $citaDep->id,
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'csv' => 'LAB-AUTH-TEST-1',
            'examenes' => ['hemograma_completo'],
            'estado' => 'pendiente_toma',
            'pdf_path' => '',
        ]);

        [$pdfBinary, $csv] = $labService->generatePdfOutput($pedido);
        $st = $labService->storeOrderPdf($pedido, $pdfBinary);
        $pedido->update(['pdf_path' => $st['pdf_path'], 'pdf_disk' => $st['pdf_disk']]);

        // 1. Laboratorio - Ver (inline)
        $respLabView = $this->actingAs($labUser)->get(route('laboratorio.pedidos.download-orden', $pedido));
        $respLabView->assertStatus(200);
        $respLabView->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('inline', (string) $respLabView->headers->get('Content-Disposition'));

        // 2. Laboratorio - Descargar (attachment)
        $respLabDl = $this->actingAs($labUser)->get(route('laboratorio.pedidos.descargar-orden', $pedido));
        $respLabDl->assertStatus(200);
        $respLabDl->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment', (string) $respLabDl->headers->get('Content-Disposition'));

        // 3. Paciente titular/representante - Ver (inline)
        $respPacView = $this->actingAs($paciente)->get(route('paciente.laboratorio.pedido.orden.ver', $pedido));
        $respPacView->assertStatus(200);
        $respPacView->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('inline', (string) $respPacView->headers->get('Content-Disposition'));

        // 4. Paciente titular/representante - Descargar (attachment)
        $respPacDl = $this->actingAs($paciente)->get(route('paciente.laboratorio.pedido.orden.descargar', $pedido));
        $respPacDl->assertStatus(200);
        $respPacDl->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment', (string) $respPacDl->headers->get('Content-Disposition'));

        // 5. Unrelated patient receives 403
        $this->actingAs($unrelatedPatient)
            ->get(route('paciente.laboratorio.pedido.orden.ver', $pedido))
            ->assertStatus(403);

        $this->actingAs($unrelatedPatient)
            ->get(route('paciente.laboratorio.pedido.orden.descargar', $pedido))
            ->assertStatus(403);

        // 6. Guest receives redirect / 401
        auth()->logout();
        $respGuest = $this->get(route('paciente.laboratorio.pedido.orden.ver', $pedido));
        $this->assertTrue($respGuest->isRedirect() || in_array($respGuest->status(), [302, 401, 403], true));
    }

    /**
     * Test UI views contain both buttons (Ver and Descargar) and overlay attributes
     */
    public function test_ui_views_contain_both_buttons_and_overlay_attributes(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $labUser = $this->createRoleUser('laboratorio');

        $cita = $this->createRealizedCita($doctor, $paciente);
        $labService = app(LaboratoryOrderDocumentService::class);
        $pedido = PedidoLaboratorio::create([
            'cita_id' => $cita->id,
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'csv' => 'LAB-UI-BTN-TEST',
            'examenes' => ['glucosa'],
            'estado' => 'pendiente_toma',
            'pdf_path' => '',
        ]);

        [$pdfBinary] = $labService->generatePdfOutput($pedido);
        $st = $labService->storeOrderPdf($pedido, $pdfBinary);
        $pedido->update(['pdf_path' => $st['pdf_path'], 'pdf_disk' => $st['pdf_disk']]);

        // Laboratorio index view check
        $respLabView = $this->actingAs($labUser)->get(route('laboratorio.pedidos.index'));
        $respLabView->assertStatus(200);
        $respLabView->assertSee('Ver orden médica');
        $respLabView->assertSee('Descargar orden médica');
        $respLabView->assertSee('data-skip-page-loader', false);
        $respLabView->assertSee('data-action-lock-ignore', false);
        $respLabView->assertSee('download', false);

        // Paciente laboratorio index view check
        $respPacView = $this->actingAs($paciente)->get(route('paciente.laboratorio.index'));
        $respPacView->assertStatus(200);
        $respPacView->assertSee('Ver orden médica');
        $respPacView->assertSee('Descargar orden médica');
        $respPacView->assertSee('data-skip-page-loader', false);
        $respPacView->assertSee('data-action-lock-ignore', false);
        $respPacView->assertSee('download', false);
    }

    /**
     * Test document verification form page extends the public navbar layout
     */
    public function test_public_document_verification_form_extends_public_navbar_header(): void
    {
        $response = $this->get(route('documentos.verificar.form'));

        $response->assertStatus(200);
        $response->assertSee('id="cnav-header"', false);
        $response->assertSee('id="cnav-menu"', false);
        $response->assertSee('Comprueba un documento con su CSV.');
        $response->assertSee('Verificar documento');
        $response->assertSee('href="'.url('/').'"', false);
    }

    /**
     * Test document verification show page extends the public navbar layout
     */
    public function test_public_document_verification_show_extends_public_navbar_header(): void
    {
        $doctor = $this->createRoleUser('doctor', ['name' => 'Dr. Layout Test']);
        $paciente = $this->createRoleUser('paciente', ['name' => 'Paciente Layout Test']);
        $cita = $this->createRealizedCita($doctor, $paciente);

        $recipeService = app(RecipeDocumentService::class);
        [$pdfBinary, $csv] = $recipeService->generatePdfOutput($cita, 'Diag Test', 'Med Test');

        $receta = Receta::create([
            'cita_id' => $cita->id,
            'diagnostico' => 'Diag Test',
            'medicamentos' => 'Med Test',
            'csv' => $csv,
            'pdf_path' => '',
        ]);
        $st = $recipeService->storeRecipePdf($receta, $pdfBinary);
        $receta->update(['pdf_path' => $st['pdf_path'], 'pdf_disk' => $st['pdf_disk']]);

        $response = $this->get(route('documentos.verificar.show', ['csv' => $csv]));

        $response->assertStatus(200);
        $response->assertSee('id="cnav-header"', false);
        $response->assertSee('id="cnav-menu"', false);
        $response->assertSee('Receta médica verificado.');
    }
}
