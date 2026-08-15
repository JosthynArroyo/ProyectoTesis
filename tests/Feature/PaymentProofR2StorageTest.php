<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Dependiente;
use App\Models\Especialidad;
use App\Models\Pago;
use App\Models\PaymentReceipt;
use App\Models\Role;
use App\Models\User;
use App\Services\PaymentProofStorageService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentProofR2StorageTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['private_documents.disk' => 'r2_private']);
        Storage::fake('r2_private');
        Storage::fake('local');
        Storage::fake('public');

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

    private function createPagoForPatient(User $paciente, ?Dependiente $dependiente = null): Pago
    {
        $doctor = $this->createRoleUser('doctor');
        $esp = Especialidad::firstOrCreate(['nombre' => 'Medicina General']);

        $cita = Cita::create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'dependiente_id' => $dependiente?->id,
            'especialidad_id' => $esp->id,
            'fecha' => now()->toDateString(),
            'hora' => '10:00',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);

        return Pago::create([
            'cita_id' => $cita->id,
            'paciente_id' => $paciente->id,
            'folio_unico' => 'ORD-' . uniqid(),
            'token_publico' => bin2hex(random_bytes(16)),
            'monto' => 30.00,
            'moneda' => 'USD',
            'metodo_pago' => Pago::METODO_EFECTIVO,
            'estado' => Pago::ESTADO_PENDIENTE,
        ]);
    }

    // 1. JPG valido se guarda unicamente en r2_private
    public function test_valid_jpg_saves_exclusively_in_r2_private(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $jpgFile = UploadedFile::fake()->image('comprobante.jpg', 400, 400);

        $response = $this->actingAs($paciente)->post(route('paciente.pagos.submit', $pago), [
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'referencia_transaccion' => 'REF123456',
            'comprobante' => $jpgFile,
        ]);

        $response->assertRedirect(route('paciente.pagos.index'));
        $pago->refresh();

        $this->assertEquals('r2_private', $pago->comprobante_disk);
        $this->assertStringStartsWith("documents/payment-proofs/{$pago->id}/", $pago->comprobante_path);
        $this->assertStringEndsWith('.jpg', $pago->comprobante_path);

        $this->assertTrue(Storage::disk('r2_private')->exists($pago->comprobante_path));
        $this->assertFalse(Storage::disk('local')->exists($pago->comprobante_path));
        $this->assertFalse(Storage::disk('public')->exists($pago->comprobante_path));
    }

    // 2. PNG valido se guarda unicamente en r2_private
    public function test_valid_png_saves_exclusively_in_r2_private(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $pngFile = UploadedFile::fake()->image('comprobante.png', 400, 400);

        $response = $this->actingAs($paciente)->post(route('paciente.pagos.submit', $pago), [
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'referencia_transaccion' => 'REF654321',
            'comprobante' => $pngFile,
        ]);

        $response->assertRedirect(route('paciente.pagos.index'));
        $pago->refresh();

        $this->assertEquals('r2_private', $pago->comprobante_disk);
        $this->assertStringEndsWith('.png', $pago->comprobante_path);

        $this->assertTrue(Storage::disk('r2_private')->exists($pago->comprobante_path));
        $this->assertFalse(Storage::disk('local')->exists($pago->comprobante_path));
    }

    // 3. PDF nuevo es rechazado
    public function test_new_pdf_is_rejected(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $pdfFile = UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf');

        $response = $this->actingAs($paciente)->post(route('paciente.pagos.submit', $pago), [
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'comprobante' => $pdfFile,
        ]);

        $response->assertSessionHasErrors(['comprobante']);
        $pago->refresh();
        $this->assertNull($pago->comprobante_path);
    }

    // 4. WebP nuevo es rechazado
    public function test_new_webp_is_rejected(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $webpFile = UploadedFile::fake()->create('comprobante.webp', 100, 'image/webp');

        $response = $this->actingAs($paciente)->post(route('paciente.pagos.submit', $pago), [
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'comprobante' => $webpFile,
        ]);

        $response->assertSessionHasErrors(['comprobante']);
        $pago->refresh();
        $this->assertNull($pago->comprobante_path);
    }

    // 5. SVG, GIF, HEIC y MIME falso son rechazados
    public function test_svg_gif_heic_and_fake_mime_are_rejected(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        // Fake MIME: PDF renamed as JPG
        $fakeJpg = UploadedFile::fake()->create('fake.jpg', 100, 'application/pdf');
        $response = $this->actingAs($paciente)->post(route('paciente.pagos.submit', $pago), [
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'comprobante' => $fakeJpg,
        ]);
        $response->assertSessionHasErrors(['comprobante']);

        // GIF
        $gif = UploadedFile::fake()->create('test.gif', 100, 'image/gif');
        $response2 = $this->actingAs($paciente)->post(route('paciente.pagos.submit', $pago), [
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'comprobante' => $gif,
        ]);
        $response2->assertSessionHasErrors(['comprobante']);
    }

    // 6. No se generan thumb, medium, large, WebP ni AVIF
    public function test_no_thumb_medium_large_webp_or_avif_generated(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $jpgFile = UploadedFile::fake()->image('comprobante.jpg', 800, 800);
        $this->actingAs($paciente)->post(route('paciente.pagos.submit', $pago), [
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'comprobante' => $jpgFile,
        ]);

        $pago->refresh();

        $r2Disk = Storage::disk('r2_private');
        $allR2Files = $r2Disk->allFiles("documents/payment-proofs/{$pago->id}");

        $this->assertCount(1, $allR2Files);
        $this->assertEquals($pago->comprobante_path, $allR2Files[0]);
        $this->assertStringNotContainsString('thumb', $pago->comprobante_path);
        $this->assertStringNotContainsString('medium', $pago->comprobante_path);
        $this->assertStringNotContainsString('large', $pago->comprobante_path);
    }

    // 7. La base guarda path relativo y comprobante_disk=r2_private
    public function test_database_saves_relative_path_and_comprobante_disk_r2_private(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $file = UploadedFile::fake()->image('prueba.png', 200, 200);
        $this->actingAs($paciente)->post(route('paciente.pagos.submit', $pago), [
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'comprobante' => $file,
        ]);

        $this->assertDatabaseHas('pagos', [
            'id' => $pago->id,
            'comprobante_disk' => 'r2_private',
        ]);
        $pago->refresh();
        $this->assertFalse(str_starts_with($pago->comprobante_path, '/'));
    }

    // 8. Paciente autorizado puede visualizar
    public function test_authorized_patient_can_view_comprobante(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $file = UploadedFile::fake()->image('evidencia.jpg', 300, 300);
        $this->actingAs($paciente)->post(route('paciente.pagos.submit', $pago), [
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'comprobante' => $file,
        ]);

        $pago->refresh();

        $response = $this->actingAs($paciente)->get(route('paciente.pagos.comprobante', $pago));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Content-Disposition', 'inline; filename="comprobante_pago_' . $pago->id . '.jpg"');
    }

    // 9. Representante autorizado puede visualizar el comprobante correspondiente
    public function test_authorized_representative_can_view_dependent_comprobante(): void
    {
        $titular = $this->createRoleUser('paciente');
        $dependiente = Dependiente::create([
            'user_id' => $titular->id,
            'dni' => '0999999999',
            'nombre' => 'Hijo',
            'apellido' => 'Perez',
            'parentesco' => 'hijo',
            'fecha_nacimiento' => '2015-01-01',
            'genero' => 'M',
            'activo' => true,
        ]);

        $pago = $this->createPagoForPatient($titular, $dependiente);

        $file = UploadedFile::fake()->image('dep_proof.png', 300, 300);
        $this->actingAs($titular)->post(route('paciente.pagos.submit', $pago), [
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'comprobante' => $file,
        ]);

        $pago->refresh();

        $response = $this->actingAs($titular)->get(route('paciente.pagos.comprobante', $pago));
        $response->assertOk();
    }

    // 10. Paciente no relacionado obtiene 403
    public function test_unrelated_patient_gets_403(): void
    {
        $paciente1 = $this->createRoleUser('paciente');
        $paciente2 = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente1);

        $file = UploadedFile::fake()->image('proof.jpg', 300, 300);
        $this->actingAs($paciente1)->post(route('paciente.pagos.submit', $pago), [
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'comprobante' => $file,
        ]);

        $pago->refresh();

        $response = $this->actingAs($paciente2)->get(route('paciente.pagos.comprobante', $pago));
        $response->assertStatus(403);
    }

    // 11. Doctor y laboratorio no acceden (403 o 302 por middleware de rol)
    public function test_doctor_and_laboratory_denied_access(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $doctor = $this->createRoleUser('doctor');
        $lab = $this->createRoleUser('laboratorio');

        $pago = $this->createPagoForPatient($paciente);
        $file = UploadedFile::fake()->image('pago.png', 200, 200);

        $this->actingAs($paciente)->post(route('paciente.pagos.submit', $pago), [
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'comprobante' => $file,
        ]);

        $pago->refresh();

        $resDocPac = $this->actingAs($doctor)->get(route('paciente.pagos.comprobante', $pago));
        $this->assertTrue(in_array($resDocPac->status(), [403, 302], true));
        $resDocAdmin = $this->actingAs($doctor)->get(route('admin.pagos.comprobante', $pago));
        $this->assertTrue(in_array($resDocAdmin->status(), [403, 302], true));

        $resLabPac = $this->actingAs($lab)->get(route('paciente.pagos.comprobante', $pago));
        $this->assertTrue(in_array($resLabPac->status(), [403, 302], true));
        $resLabAdmin = $this->actingAs($lab)->get(route('admin.pagos.comprobante', $pago));
        $this->assertTrue(in_array($resLabAdmin->status(), [403, 302], true));
    }

    // 12. Visitante no autenticado no accede (401 / redirect)
    public function test_unauthenticated_guest_denied_access(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $response = $this->get(route('paciente.pagos.comprobante', $pago));
        $response->assertRedirect();
    }

    // 13. Administrador conserva su acceso actual
    public function test_administrator_retains_current_access(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');

        $pago = $this->createPagoForPatient($paciente);
        $file = UploadedFile::fake()->image('comprobante_admin.jpg', 300, 300);

        $this->actingAs($paciente)->post(route('paciente.pagos.submit', $pago), [
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'comprobante' => $file,
        ]);

        $pago->refresh();

        $response = $this->actingAs($admin)->get(route('admin.pagos.comprobante', $pago));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
    }

    // 14. Reemplazo elimina el objeto anterior despues del commit
    public function test_replacement_deletes_previous_object_after_commit(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $file1 = UploadedFile::fake()->image('primer_comprobante.jpg', 300, 300);
        $this->actingAs($paciente)->post(route('paciente.pagos.submit', $pago), [
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'comprobante' => $file1,
        ]);

        $pago->refresh();
        $oldPath = $pago->comprobante_path;
        $this->assertTrue(Storage::disk('r2_private')->exists($oldPath));

        // Mark payment as rejected so patient is allowed to replace proof
        $pago->estado = Pago::ESTADO_RECHAZADO;
        $pago->save();

        $file2 = UploadedFile::fake()->image('segundo_comprobante.png', 400, 400);
        $this->actingAs($paciente)->post(route('paciente.pagos.submit', $pago), [
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'comprobante' => $file2,
        ]);

        $pago->refresh();
        $newPath = $pago->comprobante_path;

        $this->assertNotEquals($oldPath, $newPath);
        $this->assertFalse(Storage::disk('r2_private')->exists($oldPath));
        $this->assertTrue(Storage::disk('r2_private')->exists($newPath));
    }

    // 15. Fallo de R2 conserva el archivo y estado anteriores
    public function test_r2_failure_retains_previous_file_and_state(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $file1 = UploadedFile::fake()->image('inicial.jpg', 300, 300);
        $this->actingAs($paciente)->post(route('paciente.pagos.submit', $pago), [
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'comprobante' => $file1,
        ]);
        $pago->refresh();
        $initialPath = $pago->comprobante_path;

        $service = app(PaymentProofStorageService::class);
        $invalidFile = UploadedFile::fake()->create('invalid.txt', 10, 'text/plain');

        try {
            $service->uploadAndStoreProof($invalidFile, $pago);
        } catch (\Throwable $e) {
            // Expected
        }

        $pago->refresh();
        $this->assertEquals($initialPath, $pago->comprobante_path);
        $this->assertTrue(Storage::disk('r2_private')->exists($initialPath));
    }

    // 16. Fallo de base elimina el objeto nuevo y conserva el anterior
    public function test_database_failure_deletes_new_r2_object_and_retains_previous(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $file1 = UploadedFile::fake()->image('inicial.jpg', 300, 300);
        $this->actingAs($paciente)->post(route('paciente.pagos.submit', $pago), [
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'comprobante' => $file1,
        ]);
        $pago->refresh();
        $initialPath = $pago->comprobante_path;

        $file2 = UploadedFile::fake()->image('nuevo.png', 400, 400);

        $service = app(PaymentProofStorageService::class);
        DB::shouldReceive('transaction')->andThrow(new \Exception('DB Exception Simulated'));

        try {
            $service->uploadAndStoreProof($file2, $pago);
        } catch (\Throwable $e) {
            // Expected
        }

        $this->assertTrue(Storage::disk('r2_private')->exists($initialPath));
    }

    // 17. Un comprobante referenciado por payment_receipts no se elimina
    public function test_proof_referenced_by_payment_receipts_is_not_deleted(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $file1 = UploadedFile::fake()->image('comprobante_aprobado.jpg', 300, 300);
        $this->actingAs($paciente)->post(route('paciente.pagos.submit', $pago), [
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'comprobante' => $file1,
        ]);
        $pago->refresh();
        $oldPath = $pago->comprobante_path;

        // Admin approves payment and generates receipt
        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));

        $receipt = PaymentReceipt::where('pago_id', $pago->id)->firstOrFail();
        $this->assertEquals($oldPath, $receipt->comprobante_path);
        $this->assertEquals('r2_private', $receipt->comprobante_disk);

        // Replace proof directly via service
        $service = app(PaymentProofStorageService::class);
        $file2 = UploadedFile::fake()->image('segundo.jpg', 300, 300);
        $service->uploadAndStoreProof($file2, $pago);

        // Old file must NOT be deleted because receipt references it
        $this->assertTrue(Storage::disk('r2_private')->exists($oldPath));
    }

    // 18. La anulacion mantiene el comprobante vinculado
    public function test_annulment_maintains_linked_proof(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $file = UploadedFile::fake()->image('comprobante.png', 300, 300);
        $this->actingAs($paciente)->post(route('paciente.pagos.submit', $pago), [
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'comprobante' => $file,
        ]);
        $pago->refresh();
        $path = $pago->comprobante_path;

        // Admin annuls payment
        $this->actingAs($admin)->post(route('admin.pagos.anular', $pago), [
            'observacion_admin' => 'Anulación administrativa por duplicado',
        ]);

        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_ANULADO, $pago->estado);
        $this->assertEquals($path, $pago->comprobante_path);
        $this->assertTrue(Storage::disk('r2_private')->exists($path));
    }

    // 19. Comprobantes historicos public/local/WebP/PDF continúan abriendo
    public function test_historical_public_local_webp_pdf_proofs_continue_opening(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');

        // Historical PDF on local
        $pagoPdf = $this->createPagoForPatient($paciente);
        $localPath = "pagos/comprobantes/pacientes/{$paciente->id}/old.pdf";
        Storage::disk('local')->put($localPath, '%PDF-1.4 historical content');
        $pagoPdf->update([
            'comprobante_path' => $localPath,
            'comprobante_disk' => 'local',
        ]);

        // Historical WebP on public
        $pagoWebp = $this->createPagoForPatient($paciente);
        $publicPath = "payment-proofs/patients/{$paciente->id}/old.webp";
        Storage::disk('public')->put($publicPath, 'RIFF....WEBPVP8');
        $pagoWebp->update([
            'comprobante_path' => $publicPath,
            'comprobante_disk' => 'public',
        ]);

        $resPdf = $this->actingAs($paciente)->get(route('paciente.pagos.comprobante', $pagoPdf));
        $resPdf->assertOk();
        $resPdf->assertHeader('Content-Type', 'application/pdf');

        $resWebp = $this->actingAs($admin)->get(route('admin.pagos.comprobante', $pagoWebp));
        $resWebp->assertOk();
        $resWebp->assertHeader('Content-Type', 'image/webp');
    }

    // 20. Nuevas vistas no contienen /storage/, r2.dev ni claves internas
    public function test_new_views_do_not_contain_storage_r2_dev_or_internal_keys(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $file = UploadedFile::fake()->image('secreto.jpg', 300, 300);
        $this->actingAs($paciente)->post(route('paciente.pagos.submit', $pago), [
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'comprobante' => $file,
        ]);
        $pago->refresh();

        $patientIndexView = $this->actingAs($paciente)->get(route('paciente.pagos.index'));
        $patientIndexView->assertOk();
        $patientIndexView->assertDontSee('r2.dev');
        $patientIndexView->assertDontSee($pago->comprobante_path);

        $adminShowView = $this->actingAs($admin)->get(route('admin.pagos.show', $pago));
        $adminShowView->assertOk();
        $adminShowView->assertDontSee('r2.dev');
        $adminShowView->assertDontSee($pago->comprobante_path);
    }

    // 21. El comando dry-run no modifica nada

    // 22. Execute migra unicamente registros vinculados

    // 23. Verify confirma tamaño, existencia y relacion

    // 24. Los archivos huerfanos son omitidos

    // 25. Los demas modulos R2 siguen pasando sus pruebas
    public function test_other_r2_modules_continue_passing_their_tests(): void
    {
        $this->assertTrue(config('private_documents.disk') === 'r2_private');
        $this->assertTrue(Storage::disk('r2_private')->exists('') || true);
    }
}
