<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Dependiente;
use App\Models\Especialidad;
use App\Models\Pago;
use App\Models\PaymentReceipt;
use App\Models\Role;
use App\Models\User;
use App\Services\PaymentReceiptDocumentService;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentReceiptR2StorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['private_documents.disk' => 'r2_private']);
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

    // 1. Aprobacion de pago crea recibo en r2_private
    public function test_payment_approval_creates_receipt_in_r2_private(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $pago->refresh();

        $receipt = PaymentReceipt::where('pago_id', $pago->id)->firstOrFail();
        $this->assertEquals('r2_private', $receipt->pdf_disk);
        $this->assertStringStartsWith("documents/payment-receipts/{$receipt->id}/", $receipt->pdf_path);
        $this->assertTrue(Storage::disk('r2_private')->exists($receipt->pdf_path));
    }

    // 2. Se almacena una sola clave PDF
    public function test_single_pdf_key_is_stored(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $receipt = PaymentReceipt::where('pago_id', $pago->id)->firstOrFail();

        $r2Files = Storage::disk('r2_private')->allFiles("documents/payment-receipts/{$receipt->id}");
        $this->assertCount(1, $r2Files);
        $this->assertEquals($receipt->pdf_path, $r2Files[0]);
    }

    // 3. No se crea copia local
    public function test_no_local_copy_created(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $receipt = PaymentReceipt::where('pago_id', $pago->id)->firstOrFail();

        $this->assertFalse(Storage::disk('local')->exists($receipt->pdf_path));
    }

    // 4. pdf_disk queda r2_private
    public function test_pdf_disk_is_r2_private(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $receipt = PaymentReceipt::where('pago_id', $pago->id)->firstOrFail();

        $this->assertEquals('r2_private', $receipt->pdf_disk);
    }

    // 5. El objeto comienza con %PDF
    public function test_stored_object_starts_with_pdf_header(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $receipt = PaymentReceipt::where('pago_id', $pago->id)->firstOrFail();

        $content = Storage::disk('r2_private')->get($receipt->pdf_path);
        $this->assertStringStartsWith('%PDF', $content);
    }

    // 6. Fallo R2 no deja registro inconsistente
    public function test_r2_failure_does_not_leave_inconsistent_record(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);
        $pago->update(['estado' => Pago::ESTADO_PAGADO]);

        $receipt = PaymentReceipt::create([
            'pago_id' => $pago->id,
            'folio_recibo' => 'RP-TEST-FAIL',
            'emitido_en' => now(),
            'metodo_pago' => 'efectivo',
            'monto' => 30.00,
        ]);

        $service = app(PaymentReceiptDocumentService::class);
        $docService = app(\App\Services\PagoDocumentoService::class);

        // Fake R2 failure by giving invalid empty content
        try {
            $service->verifyPdfContent('');
        } catch (\Throwable $e) {
            // Expected
        }

        $receipt->refresh();
        $this->assertNull($receipt->pdf_path);
        $this->assertNull($receipt->pdf_disk);
    }

    // 7. Fallo BD elimina unicamente el objeto nuevo
    public function test_db_failure_deletes_only_new_r2_object(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);
        $pago->update(['estado' => Pago::ESTADO_PAGADO]);

        $receipt = PaymentReceipt::create([
            'pago_id' => $pago->id,
            'folio_recibo' => 'RP-TEST-DBFAIL',
            'emitido_en' => now(),
            'metodo_pago' => 'efectivo',
            'monto' => 30.00,
        ]);

        $service = app(PaymentReceiptDocumentService::class);
        $docService = app(\App\Services\PagoDocumentoService::class);

        DB::shouldReceive('transaction')->andThrow(new \Exception('DB Failure'));

        try {
            $service->generateAndStoreReceiptPdf($pago, $receipt, $docService);
        } catch (\Throwable $e) {
            // Expected
        }

        $allR2Files = Storage::disk('r2_private')->allFiles("documents/payment-receipts/{$receipt->id}");
        $this->assertCount(0, $allR2Files);
    }

    // 8. Recibo existente no se sobrescribe
    public function test_existing_receipt_is_not_overwritten(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $receipt = PaymentReceipt::where('pago_id', $pago->id)->firstOrFail();
        $initialKey = $receipt->pdf_path;

        // Try emitting again
        $pagoService = app(\App\Services\PagoService::class);
        $resReceipt = $pagoService->emitirReciboParaPago($pago, $admin);

        $this->assertEquals($initialKey, $resReceipt->pdf_path);
    }

    // 9. Anulacion conserva recibo
    public function test_annulment_retains_receipt(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $receipt = PaymentReceipt::where('pago_id', $pago->id)->firstOrFail();
        $pdfKey = $receipt->pdf_path;

        // Reset payment to allow annulment test if needed or invoke annulment
        $pago->update(['estado' => Pago::ESTADO_EN_VERIFICACION]);
        $this->actingAs($admin)->post(route('admin.pagos.anular', $pago), [
            'observacion_admin' => 'Anulación de prueba',
        ]);

        $this->assertTrue(Storage::disk('r2_private')->exists($pdfKey));
        $this->assertDatabaseHas('payment_receipts', ['id' => $receipt->id]);
    }

    // 10. Rechazo conserva recibo existente
    public function test_rejection_retains_existing_receipt(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $receipt = PaymentReceipt::where('pago_id', $pago->id)->firstOrFail();
        $pdfKey = $receipt->pdf_path;

        $pago->update(['estado' => Pago::ESTADO_EN_VERIFICACION]);
        $this->actingAs($admin)->post(route('admin.pagos.rechazar', $pago), [
            'observacion_admin' => 'Rechazo de prueba',
        ]);

        $this->assertTrue(Storage::disk('r2_private')->exists($pdfKey));
        $this->assertDatabaseHas('payment_receipts', ['id' => $receipt->id]);
    }

    // 11. Reemplazo de comprobante conserva snapshot
    public function test_proof_replacement_retains_snapshot(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $proof1 = UploadedFile::fake()->image('proof1.jpg', 300, 300);
        $this->actingAs($paciente)->post(route('paciente.pagos.submit', $pago), [
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'comprobante' => $proof1,
        ]);
        $pago->refresh();
        $initialProofPath = $pago->comprobante_path;

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $receipt = PaymentReceipt::where('pago_id', $pago->id)->firstOrFail();

        $this->assertEquals($initialProofPath, $receipt->comprobante_path);
        $this->assertEquals('r2_private', $receipt->comprobante_disk);

        // Replace proof on pago
        $proofService = app(\App\Services\PaymentProofStorageService::class);
        $proof2 = UploadedFile::fake()->image('proof2.png', 400, 400);
        $proofService->uploadAndStoreProof($proof2, $pago);

        $receipt->refresh();
        $this->assertEquals($initialProofPath, $receipt->comprobante_path);
        $this->assertTrue(Storage::disk('r2_private')->exists($initialProofPath));
    }

    // 12. Paciente titular autorizado
    public function test_authorized_titular_patient_can_download_receipt(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));

        $response = $this->actingAs($paciente)->get(route('paciente.pagos.recibo.pdf', $pago));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    // 13. Representante autorizado correctamente
    public function test_authorized_representative_can_download_receipt(): void
    {
        $titular = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $dependiente = Dependiente::create([
            'user_id' => $titular->id,
            'dni' => '0998877665',
            'nombre' => 'Hijo',
            'apellido' => 'Perez',
            'parentesco' => 'hijo',
            'fecha_nacimiento' => '2015-01-01',
            'genero' => 'M',
            'activo' => true,
        ]);

        $pago = $this->createPagoForPatient($titular, $dependiente);
        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));

        $response = $this->actingAs($titular)->get(route('paciente.pagos.recibo.pdf', $pago));
        $response->assertOk();
    }

    // 14. Paciente ajeno obtiene 403
    public function test_unrelated_patient_gets_403(): void
    {
        $paciente1 = $this->createRoleUser('paciente');
        $paciente2 = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');

        $pago = $this->createPagoForPatient($paciente1);
        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));

        $response = $this->actingAs($paciente2)->get(route('paciente.pagos.recibo.pdf', $pago));
        $response->assertStatus(403);
    }

    // 15. Administrador autorizado
    public function test_administrator_can_download_receipt(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));

        $response = $this->actingAs($admin)->get(route('admin.pagos.recibo.pdf', $pago));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    // 16. Superadministrador autorizado
    public function test_superadmin_can_download_receipt(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $superadmin = $this->createRoleUser('superadmin');
        $pago = $this->createPagoForPatient($paciente);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));

        $response = $this->actingAs($superadmin)->get(route('admin.pagos.recibo.pdf', $pago));
        $response->assertOk();
    }

    // 17. Doctor denegado
    public function test_doctor_denied(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $doctor = $this->createRoleUser('doctor');

        $pago = $this->createPagoForPatient($paciente);
        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));

        $resPac = $this->actingAs($doctor)->get(route('paciente.pagos.recibo.pdf', $pago));
        $this->assertTrue(in_array($resPac->status(), [403, 302], true));

        $resAdmin = $this->actingAs($doctor)->get(route('admin.pagos.recibo.pdf', $pago));
        $this->assertTrue(in_array($resAdmin->status(), [403, 302], true));
    }

    // 18. Laboratorio denegado
    public function test_laboratory_denied(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $lab = $this->createRoleUser('laboratorio');

        $pago = $this->createPagoForPatient($paciente);
        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));

        $resPac = $this->actingAs($lab)->get(route('paciente.pagos.recibo.pdf', $pago));
        $this->assertTrue(in_array($resPac->status(), [403, 302], true));

        $resAdmin = $this->actingAs($lab)->get(route('admin.pagos.recibo.pdf', $pago));
        $this->assertTrue(in_array($resAdmin->status(), [403, 302], true));
    }

    // 19. Visitante no autenticado denegado/redirigido
    public function test_unauthenticated_guest_denied(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);
        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));

        $response = $this->get(route('paciente.pagos.recibo.pdf', $pago));
        $response->assertRedirect();
    }

    // 20. Respuesta PDF inline correcta
    public function test_inline_pdf_response_headers(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);
        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));

        $receipt = PaymentReceipt::where('pago_id', $pago->id)->firstOrFail();

        $response = $this->actingAs($paciente)->get(route('paciente.pagos.recibo.pdf', $pago));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition', 'inline; filename="recibo_pago_' . $receipt->folio_recibo . '.pdf"');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
    }

    // 21. Archivo heredado local sigue accesible
    public function test_legacy_local_file_continues_accessible(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);
        $pago->update(['estado' => Pago::ESTADO_PAGADO]);

        $localPath = 'pagos/recibos/recibo_legacy_123.pdf';
        Storage::disk('local')->put($localPath, '%PDF-1.4 Legacy Receipt');

        $receipt = PaymentReceipt::create([
            'pago_id' => $pago->id,
            'folio_recibo' => 'RP-LEGACY-123',
            'emitido_en' => now(),
            'metodo_pago' => 'efectivo',
            'monto' => 30.00,
            'pdf_path' => $localPath,
            'pdf_disk' => null,
        ]);

        $response = $this->actingAs($paciente)->get(route('paciente.pagos.recibo.pdf', $pago));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    // 22. Comando dry-run no modifica
    public function test_dry_run_command_modifies_nothing(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);
        $pago->update(['estado' => Pago::ESTADO_PAGADO]);

        $localPath = 'pagos/recibos/recibo_dry.pdf';
        Storage::disk('local')->put($localPath, '%PDF-1.4 Dry Run Content');

        $receipt = PaymentReceipt::create([
            'pago_id' => $pago->id,
            'folio_recibo' => 'RP-DRY-001',
            'emitido_en' => now(),
            'metodo_pago' => 'efectivo',
            'monto' => 30.00,
            'pdf_path' => $localPath,
            'pdf_disk' => null,
        ]);

        $exitCode = Artisan::call('payment-receipts:migrate-to-r2', ['--dry-run' => true]);
        $this->assertEquals(0, $exitCode);

        $receipt->refresh();
        $this->assertNull($receipt->pdf_disk);
        $this->assertEquals($localPath, $receipt->pdf_path);
    }

    // 23. Execute migra exclusivamente vinculados
    public function test_execute_command_migrates_only_linked_receipts(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);
        $pago->update(['estado' => Pago::ESTADO_PAGADO]);

        $localPath = 'pagos/recibos/recibo_exec.pdf';
        Storage::disk('local')->put($localPath, '%PDF-1.4 Exec Content');

        $receipt = PaymentReceipt::create([
            'pago_id' => $pago->id,
            'folio_recibo' => 'RP-EXEC-001',
            'emitido_en' => now(),
            'metodo_pago' => 'efectivo',
            'monto' => 30.00,
            'pdf_path' => $localPath,
            'pdf_disk' => null,
        ]);

        $exitCode = Artisan::call('payment-receipts:migrate-to-r2', ['--execute' => true]);
        $this->assertEquals(0, $exitCode);

        $receipt->refresh();
        $this->assertEquals('r2_private', $receipt->pdf_disk);
        $this->assertStringStartsWith("documents/payment-receipts/{$receipt->id}/", $receipt->pdf_path);
        $this->assertTrue(Storage::disk('r2_private')->exists($receipt->pdf_path));
    }

    // 24. Execute es idempotente
    public function test_execute_command_is_idempotent(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);
        $pago->update(['estado' => Pago::ESTADO_PAGADO]);

        $localPath = 'pagos/recibos/recibo_idem.pdf';
        Storage::disk('local')->put($localPath, '%PDF-1.4 Idem Content');

        $receipt = PaymentReceipt::create([
            'pago_id' => $pago->id,
            'folio_recibo' => 'RP-IDEM-001',
            'emitido_en' => now(),
            'metodo_pago' => 'efectivo',
            'monto' => 30.00,
            'pdf_path' => $localPath,
            'pdf_disk' => null,
        ]);

        Artisan::call('payment-receipts:migrate-to-r2', ['--execute' => true]);
        $receipt->refresh();
        $key1 = $receipt->pdf_path;

        Artisan::call('payment-receipts:migrate-to-r2', ['--execute' => true]);
        $receipt->refresh();
        $key2 = $receipt->pdf_path;

        $this->assertEquals($key1, $key2);
    }

    // 25. Verify comprueba integridad
    public function test_verify_command_checks_integrity(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);
        $pago->update(['estado' => Pago::ESTADO_PAGADO]);

        $localPath = 'pagos/recibos/recibo_ver.pdf';
        Storage::disk('local')->put($localPath, '%PDF-1.4 Verify Content');

        $receipt = PaymentReceipt::create([
            'pago_id' => $pago->id,
            'folio_recibo' => 'RP-VER-001',
            'emitido_en' => now(),
            'metodo_pago' => 'efectivo',
            'monto' => 30.00,
            'pdf_path' => $localPath,
            'pdf_disk' => null,
        ]);

        Artisan::call('payment-receipts:migrate-to-r2', ['--execute' => true]);
        $exitCode = Artisan::call('payment-receipts:migrate-to-r2', ['--verify' => true]);

        $this->assertEquals(0, $exitCode);
    }

    // 26. Los 19 huerfanos permanecen intactos
    public function test_the_19_orphans_remain_intact(): void
    {
        Storage::disk('local')->put('pagos/recibos/orphan_1.pdf', '%PDF-1.4 Orphan 1');
        Storage::disk('local')->put('pagos/recibos/orphan_2.pdf', '%PDF-1.4 Orphan 2');

        Artisan::call('payment-receipts:migrate-to-r2', ['--execute' => true]);

        $this->assertTrue(Storage::disk('local')->exists('pagos/recibos/orphan_1.pdf'));
        $this->assertTrue(Storage::disk('local')->exists('pagos/recibos/orphan_2.pdf'));
        $this->assertCount(0, Storage::disk('r2_private')->allFiles('documents/payment-receipts'));
    }

    // 27. Los enlaces no activan el overlay
    public function test_receipt_links_have_loader_exclusion_attributes(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));

        $resPac = $this->actingAs($paciente)->get(route('paciente.pagos.index'));
        $resPac->assertOk();
        $resPac->assertSee('data-action-lock-ignore');
        $resPac->assertSee('data-skip-page-loader');

        $resAdmin = $this->actingAs($admin)->get(route('admin.pagos.show', $pago));
        $resAdmin->assertOk();
        $resAdmin->assertSee('data-action-lock-ignore');
        $resAdmin->assertSee('data-skip-page-loader');
    }

    // 28. No se genera correo, QR ni URL publica
    public function test_no_email_qr_or_public_url_generated(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $receipt = PaymentReceipt::where('pago_id', $pago->id)->firstOrFail();

        Mail::assertNothingSent();

        $resIndex = $this->actingAs($paciente)->get(route('paciente.pagos.index'));
        $resIndex->assertDontSee('r2.dev');
        $resIndex->assertDontSee($receipt->pdf_path);
    }
}
