<?php

namespace Tests\Feature;

use App\Models\CertificadoMedico;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\NotaSoap;
use App\Models\Pago;
use App\Models\PedidoLaboratorio;
use App\Models\Receta;
use App\Models\Role;
use App\Models\User;
use App\Services\AppointmentConfirmationDocumentService;
use App\Services\DatabaseBackup\BackupStorageService;
use App\Services\ImageOptimizer;
use App\Services\LaboratoryOrderDocumentService;
use App\Services\MedicalCertificateDocumentService;
use App\Services\PaymentProofStorageService;
use App\Services\PaymentReceiptDocumentService;
use App\Services\ProfileAvatarService;
use App\Services\RecipeDocumentService;
use App\Support\ImageUrl;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageFallbackAndR2CompatibilityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Storage::fake('r2_public');
        Storage::fake('r2_private');
    }

    private function refreshImageServices(): ImageUrl
    {
        $this->app->forgetInstance(ImageOptimizer::class);
        $this->app->forgetInstance(ImageUrl::class);

        return $this->app->make(ImageUrl::class);
    }

    /**
     * Caso A — Instalación base sin credenciales R2:
     * La aplicación arranca con configuración segura local y public por defecto.
     */
    public function test_caso_a_base_installation_defaults_to_safe_local_and_public_disks(): void
    {
        $this->assertSame('local', config('filesystems.default'));
        $this->assertSame('public', config('image_optimization.disk'));
        $this->assertSame('local', config('image_optimization.avatar_disk'));
        $this->assertSame('local', config('private_documents.disk'));
        $this->assertSame('local', config('private_documents.recipe_disk'));

        $optimizer = app(ImageOptimizer::class);
        $this->assertSame('public', $optimizer->disk());

        $avatarService = app(ProfileAvatarService::class);
        $this->assertSame('local', $avatarService->disk());

        $certService = app(MedicalCertificateDocumentService::class);
        $this->assertSame('local', $certService->getCertificateDiskName());

        $recipeService = app(RecipeDocumentService::class);
        $this->assertSame('local', $recipeService->getRecipeDiskName());

        $orderService = app(LaboratoryOrderDocumentService::class);
        $this->assertSame('local', $orderService->getOrderDiskName());
    }

    /**
     * Caso B — Almacenamiento público en modo local:
     * Imágenes públicas usan el disco 'public' y resuelven rutas /storage/ accesibles.
     */
    public function test_caso_b_public_image_storage_resolves_and_stores_on_public_disk(): void
    {
        Config::set('image_optimization.disk', 'public');
        $optimizer = $this->app->make(ImageOptimizer::class);

        $file = UploadedFile::fake()->image('banner.jpg', 800, 600);
        $storedPath = $optimizer->optimizeAndStore($file, 'banners', baseName: 'test-local-banner');

        Storage::disk('public')->assertExists('images/banners/thumb/test-local-banner.webp');
        Storage::disk('public')->assertExists('images/banners/medium/test-local-banner.webp');
        Storage::disk('public')->assertExists('images/banners/large/test-local-banner.webp');

        Storage::disk('r2_public')->assertMissing('images/banners/thumb/test-local-banner.webp');

        $imageUrl = $this->refreshImageServices();
        $variants = $imageUrl->variants($storedPath, 'banners', 'banner');

        $this->assertStringContainsString('/storage/images/banners/', $variants['src']);
        $this->assertStringContainsString('/storage/images/banners/', $variants['thumb']);
        $this->assertStringContainsString('/storage/images/banners/', $variants['medium']);
    }

    /**
     * Caso C — Archivos clínicos privados en disco local protegido:
     * Certificados, recetas y pedidos de laboratorio se almacenan en 'local' (privado)
     * y NUNCA bajo storage/app/public ni accesibles directamente vía /storage.
     */
    public function test_caso_c_private_clinical_documents_stored_on_local_disk_and_never_in_public(): void
    {
        Config::set('private_documents.disk', 'local');
        Config::set('private_documents.recipe_disk', 'local');

        $doctor = $this->createUserWithRole('doctor');
        $patient = $this->createUserWithRole('paciente');
        $especialidad = Especialidad::firstOrCreate(['nombre' => 'Medicina General'], ['activo' => true]);

        $cita = Cita::create([
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => now()->toDateString(),
            'hora' => '10:00:00',
            'motivo_consulta' => 'Consulta de control',
            'estado' => Cita::ESTADO_CONFIRMADA,
            'activo' => true,
        ]);

        // 1. Certificado Médico
        $certService = app(MedicalCertificateDocumentService::class);
        $certificado = CertificadoMedico::create([
            'codigo' => 'CM-LOC-001',
            'cita_id' => $cita->id,
            'doctor_id' => $doctor->id,
            'paciente_id' => $patient->id,
            'texto_constancia' => 'Diagnostico y constancia medica confidencial',
            'dias_reposo' => 3,
            'fecha_emision' => now()->toDateString(),
            'activo' => true,
        ]);

        $storedCert = $certService->storeCertificatePdf($certificado, '%PDF-1.4 Mock Certificado Privado');
        $this->assertSame('local', $storedCert['pdf_disk']);
        Storage::disk('local')->assertExists($storedCert['pdf_path']);
        Storage::disk('public')->assertMissing($storedCert['pdf_path']);
        Storage::disk('r2_private')->assertMissing($storedCert['pdf_path']);

        // 2. Receta Médica
        $recipeService = app(RecipeDocumentService::class);
        $receta = Receta::create([
            'cita_id' => $cita->id,
            'diagnostico' => 'Diagnóstico Receta Local',
            'medicamentos' => 'Paracetamol 500mg',
            'indicaciones' => 'Cada 8 horas',
        ]);

        $storedRecipe = $recipeService->storeRecipePdf($receta, '%PDF-1.4 Mock Receta Privada');
        $this->assertSame('local', $storedRecipe['pdf_disk']);
        Storage::disk('local')->assertExists($storedRecipe['pdf_path']);
        Storage::disk('public')->assertMissing($storedRecipe['pdf_path']);

        // 3. Pedido de Laboratorio
        $orderService = app(LaboratoryOrderDocumentService::class);
        $pedido = PedidoLaboratorio::create([
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'estado' => PedidoLaboratorio::ESTADO_PENDIENTE_TOMA,
            'examenes' => ['Hemograma completo'],
        ]);

        $storedOrder = $orderService->storeOrderPdf($pedido, '%PDF-1.4 Mock Pedido Privado');
        $this->assertSame('local', $storedOrder['pdf_disk']);
        Storage::disk('local')->assertExists($storedOrder['pdf_path']);
        Storage::disk('public')->assertMissing($storedOrder['pdf_path']);
    }

    /**
     * Caso D — Avatares privados protegidos con control de acceso:
     * Los avatares se almacenan en disco local privado y requieren autenticación/autorización para streaming.
     */
    public function test_caso_d_private_avatars_stored_locally_and_enforce_authorized_access(): void
    {
        Config::set('image_optimization.avatar_disk', 'local');

        $user = $this->createUserWithRole('paciente');
        $otherUser = $this->createUserWithRole('paciente');
        $avatarService = app(ProfileAvatarService::class);

        $file = UploadedFile::fake()->image('my-avatar.png', 200, 200);
        $avatarPath = $avatarService->replace($user, $file);

        $this->assertTrue(Storage::disk('local')->exists($avatarPath));
        $this->assertFalse(Storage::disk('public')->exists($avatarPath));
        $this->assertFalse(Storage::disk('r2_private')->exists($avatarPath));

        // 1. Acceso no autenticado -> 302 / 401
        $guestResponse = $this->get(route('media.avatars.show', ['user' => $user, 'variant' => 'thumb']));
        $this->assertTrue($guestResponse->isRedirect());

        $guestJsonResponse = $this->getJson(route('media.avatars.show', ['user' => $user, 'variant' => 'thumb']));
        $guestJsonResponse->assertStatus(401);

        // 2. Acceso por usuario no autorizado -> 403
        $unauthResponse = $this->actingAs($otherUser)->get(route('media.avatars.show', ['user' => $user, 'variant' => 'thumb']));
        $unauthResponse->assertStatus(403);

        // 3. Acceso por el propietario -> 200
        $ownerResponse = $this->actingAs($user)->get(route('media.avatars.show', ['user' => $user, 'variant' => 'thumb']));
        $ownerResponse->assertStatus(200);
        $ownerResponse->assertHeader('Content-Type', 'image/webp');
    }

    /**
     * Caso E — Activación explícita de Cloudflare R2:
     * Al configurar las variables R2, el sistema opera completamente sobre r2_public y r2_private.
     */
    public function test_caso_e_explicit_r2_activation_operates_on_r2_disks(): void
    {
        Config::set('image_optimization.disk', 'r2_public');
        Config::set('image_optimization.avatar_disk', 'r2_private');
        Config::set('private_documents.disk', 'r2_private');
        Config::set('private_documents.recipe_disk', 'r2_private');
        Config::set('filesystems.disks.r2_public.url', 'https://pub-r2.example.com');
        Storage::fake('r2_public', ['url' => 'https://pub-r2.example.com']);
        Storage::fake('r2_private');

        $optimizer = $this->app->make(ImageOptimizer::class);
        $this->assertSame('r2_public', $optimizer->disk());

        $file = UploadedFile::fake()->image('r2-banner.jpg', 800, 600);
        $storedBanner = $optimizer->optimizeAndStore($file, 'banners', baseName: 'banner-r2');

        Storage::disk('r2_public')->assertExists('images/banners/thumb/banner-r2.webp');
        Storage::disk('public')->assertMissing('images/banners/thumb/banner-r2.webp');

        $imageUrl = $this->refreshImageServices();
        $variants = $imageUrl->variants($storedBanner, 'banners', 'banner');
        $this->assertStringStartsWith('https://pub-r2.example.com/images/banners/', $variants['src']);

        // Documento privado en R2
        $doctor = $this->createUserWithRole('doctor');
        $patient = $this->createUserWithRole('paciente');
        $especialidad = Especialidad::firstOrCreate(['nombre' => 'Medicina General'], ['activo' => true]);

        $cita = Cita::create([
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => now()->toDateString(),
            'hora' => '10:00:00',
            'motivo_consulta' => 'Consulta de control',
            'estado' => Cita::ESTADO_CONFIRMADA,
            'activo' => true,
        ]);

        $certService = app(MedicalCertificateDocumentService::class);
        $certificado = CertificadoMedico::create([
            'codigo' => 'CM-R2-001',
            'cita_id' => $cita->id,
            'doctor_id' => $doctor->id,
            'paciente_id' => $patient->id,
            'texto_constancia' => 'Diagnostico R2',
            'dias_reposo' => 1,
            'fecha_emision' => now()->toDateString(),
            'activo' => true,
        ]);

        $storedCert = $certService->storeCertificatePdf($certificado, '%PDF-1.4 Mock R2 Cert');
        $this->assertSame('r2_private', $storedCert['pdf_disk']);
        Storage::disk('r2_private')->assertExists($storedCert['pdf_path']);
        Storage::disk('local')->assertMissing($storedCert['pdf_path']);
    }

    /**
     * Caso F — Coexistencia y resolución dinámica de documentos locales y R2:
     * Si en la base de datos coexisten registros en 'local' y 'r2_private',
     * el sistema resuelve cada uno desde su disco correspondiente sin errores.
     */
    public function test_caso_f_coexistence_and_dynamic_disk_resolution_for_legacy_and_r2_docs(): void
    {
        $doctor = $this->createUserWithRole('doctor');
        $patient = $this->createUserWithRole('paciente');
        $especialidad = Especialidad::firstOrCreate(['nombre' => 'Medicina General'], ['activo' => true]);

        $cita = Cita::create([
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => now()->toDateString(),
            'hora' => '10:00:00',
            'motivo_consulta' => 'Consulta de control',
            'estado' => Cita::ESTADO_CONFIRMADA,
            'activo' => true,
        ]);

        $certService = app(MedicalCertificateDocumentService::class);

        // Documento A en disco local
        $certLocal = CertificadoMedico::create([
            'codigo' => 'CM-LOCAL-COEXIST',
            'cita_id' => $cita->id,
            'doctor_id' => $doctor->id,
            'paciente_id' => $patient->id,
            'texto_constancia' => 'Diagnostico Local',
            'dias_reposo' => 2,
            'fecha_emision' => now()->toDateString(),
            'pdf_path' => 'documents/medical-certificates/1/cert-local.pdf',
            'pdf_disk' => 'local',
            'activo' => true,
        ]);
        Storage::disk('local')->put('documents/medical-certificates/1/cert-local.pdf', '%PDF-1.4 Local Content');

        // Documento B en disco r2_private
        $certR2 = CertificadoMedico::create([
            'codigo' => 'CM-R2-COEXIST',
            'cita_id' => $cita->id,
            'doctor_id' => $doctor->id,
            'paciente_id' => $patient->id,
            'texto_constancia' => 'Diagnostico R2',
            'dias_reposo' => 5,
            'fecha_emision' => now()->toDateString(),
            'pdf_path' => 'documents/medical-certificates/2/cert-r2.pdf',
            'pdf_disk' => 'r2_private',
            'activo' => true,
        ]);
        Storage::disk('r2_private')->put('documents/medical-certificates/2/cert-r2.pdf', '%PDF-1.4 R2 Content');

        $this->actingAs($patient);

        // Stream inline local
        $responseLocal = $certService->streamInline($certLocal);
        $this->assertSame(200, $responseLocal->getStatusCode());

        // Stream inline R2
        $responseR2 = $certService->streamInline($certR2);
        $this->assertSame(200, $responseR2->getStatusCode());
        $this->assertSame('%PDF-1.4 R2 Content', $responseR2->getContent());
    }

    /**
     * Caso G — Cero fugas de información clínica en almacenamiento público:
     * Ningún documento privado ni avatar termina expuesto en storage/app/public.
     */
    public function test_caso_g_zero_clinical_data_leakage_in_public_storage(): void
    {
        Config::set('image_optimization.disk', 'public');
        Config::set('image_optimization.avatar_disk', 'local');
        Config::set('private_documents.disk', 'local');

        $patient = $this->createUserWithRole('paciente');
        $avatarService = app(ProfileAvatarService::class);
        $file = UploadedFile::fake()->image('patient-pic.jpg', 150, 150);
        $avatarPath = $avatarService->replace($patient, $file);

        $this->assertTrue(Storage::disk('local')->exists($avatarPath));
        $this->assertFalse(Storage::disk('public')->exists($avatarPath));

        $publicFiles = Storage::disk('public')->allFiles();
        foreach ($publicFiles as $publicFile) {
            $this->assertStringNotContainsString('documents/', $publicFile);
            $this->assertStringNotContainsString('medical-certificates', $publicFile);
            $this->assertStringNotContainsString('payment-proofs', $publicFile);
            $this->assertStringNotContainsString('payment-receipts', $publicFile);
            $this->assertStringNotContainsString('laboratory', $publicFile);
            $this->assertStringNotContainsString('avatars/' . $patient->id, $publicFile);
        }
    }

    /**
     * Caso H — Configuración de respaldos no bloquea bootstrap del sistema:
     * El servicio de backups retiene su configuración y la aplicación inicia limpiamente.
     */
    public function test_caso_h_backups_service_and_configuration_retains_r2_backups(): void
    {
        $this->assertSame('r2_backups', config('database_backups.disk'));

        $backupStorageService = app(BackupStorageService::class);
        $this->assertInstanceOf(BackupStorageService::class, $backupStorageService);
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        $user = User::factory()->create([
            'active' => true,
            'status' => User::STATUS_ACTIVE,
        ]);

        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }
}
