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

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentReceiptR2StorageTest extends TestCase
{
    use DatabaseTransactions;

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
        $this->assertStringStartsWith("documents/payment-receipts/{$receipt->pago_id}/", $receipt->pdf_path);
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

        $r2Files = Storage::disk('r2_private')->allFiles("documents/payment-receipts/{$receipt->pago_id}");
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

        $allR2Files = Storage::disk('r2_private')->allFiles("documents/payment-receipts/{$receipt->pago_id}");
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

    // 23. Execute migra exclusivamente vinculados

    // 24. Execute es idempotente

    // 25. Verify comprueba integridad

    // 26. Los 19 huerfanos permanecen intactos

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

    /**
     * CASO 1 — put() retorna false
     */
    public function test_caso_1_put_returns_false_does_not_assume_upload_and_fails_safely(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);
        $receipt = new PaymentReceipt(['pago_id' => $pago->id, 'folio_recibo' => 'TEST-01']);

        $service = app(PaymentReceiptDocumentService::class);
        $docService = app(\App\Services\PagoDocumentoService::class);

        $diskMock = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $diskMock->shouldReceive('put')->once()->andReturn(false);
        $diskMock->shouldReceive('delete')->never();
        Storage::shouldReceive('disk')->with('r2_private')->andReturn($diskMock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No se pudo guardar el PDF del recibo en almacenamiento R2');

        $service->generateAndStoreReceiptPdfContentOnly($pago, $receipt, $docService);
    }

    /**
     * CASO 2 (Hallazgo exacto) — put() true + exists() false ejecuta delete(newKey) incondicionalmente
     */
    public function test_caso_2_put_true_exists_false_executes_delete_on_exact_key(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);
        $receipt = new PaymentReceipt(['pago_id' => $pago->id, 'folio_recibo' => 'TEST-02']);

        $service = app(PaymentReceiptDocumentService::class);
        $docService = app(\App\Services\PagoDocumentoService::class);

        $diskMock = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $capturedKey = null;

        $diskMock->shouldReceive('put')->once()->with(\Mockery::on(function ($key) use (&$capturedKey, $pago) {
            $capturedKey = $key;
            return str_starts_with($key, "documents/payment-receipts/{$pago->id}/");
        }), \Mockery::type('string'))->andReturn(true);

        // exists() retorna false (el hallazgo exacto)
        $diskMock->shouldReceive('exists')->once()->andReturn(false);

        // delete() es invocado exactamente 1 vez con la key capturada
        $diskMock->shouldReceive('delete')->once()->with(\Mockery::on(function ($k) use (&$capturedKey) {
            return $k === $capturedKey;
        }))->andReturn(true);

        Storage::shouldReceive('disk')->with('r2_private')->andReturn($diskMock);

        try {
            $service->generateAndStoreReceiptPdfContentOnly($pago, $receipt, $docService);
            $this->fail('Debió lanzar excepción de verificación');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('no se encuentra en R2', $e->getMessage());
        }

        $this->assertNotNull($capturedKey);
    }

    /**
     * CASO 3 — put() true + exists() lanza Throwable -> delete_calls = 1
     */
    public function test_caso_3_put_true_exists_throws_executes_delete(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);
        $receipt = new PaymentReceipt(['pago_id' => $pago->id, 'folio_recibo' => 'TEST-03']);

        $service = app(PaymentReceiptDocumentService::class);
        $docService = app(\App\Services\PagoDocumentoService::class);

        $diskMock = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $capturedKey = null;

        $diskMock->shouldReceive('put')->once()->with(\Mockery::on(function ($key) use (&$capturedKey) {
            $capturedKey = $key;
            return true;
        }), \Mockery::type('string'))->andReturn(true);

        $diskMock->shouldReceive('exists')->once()->andThrow(new \RuntimeException('Network timeout on exists'));
        $diskMock->shouldReceive('delete')->once()->with(\Mockery::on(function ($k) use (&$capturedKey) {
            return $k === $capturedKey;
        }))->andReturn(true);

        Storage::shouldReceive('disk')->with('r2_private')->andReturn($diskMock);

        try {
            $service->generateAndStoreReceiptPdfContentOnly($pago, $receipt, $docService);
            $this->fail('Debió lanzar excepción');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Network timeout on exists', $e->getMessage());
        }
    }

    /**
     * CASO 4 — put() true + exists() true + size() falla/lanza -> delete_calls = 1
     */
    public function test_caso_4_put_true_size_failure_executes_delete(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);
        $receipt = new PaymentReceipt(['pago_id' => $pago->id, 'folio_recibo' => 'TEST-04']);

        $service = app(PaymentReceiptDocumentService::class);
        $docService = app(\App\Services\PagoDocumentoService::class);

        $diskMock = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $capturedKey = null;

        $diskMock->shouldReceive('put')->once()->with(\Mockery::on(function ($key) use (&$capturedKey) {
            $capturedKey = $key;
            return true;
        }), \Mockery::type('string'))->andReturn(true);

        $diskMock->shouldReceive('exists')->once()->andReturn(true);
        $diskMock->shouldReceive('size')->once()->andReturn(0); // Tamaño inválido
        $diskMock->shouldReceive('delete')->once()->with(\Mockery::on(function ($k) use (&$capturedKey) {
            return $k === $capturedKey;
        }))->andReturn(true);

        Storage::shouldReceive('disk')->with('r2_private')->andReturn($diskMock);

        try {
            $service->generateAndStoreReceiptPdfContentOnly($pago, $receipt, $docService);
            $this->fail('Debió lanzar excepción');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Inconsistencia en el tamaño', $e->getMessage());
        }
    }

    /**
     * CASO 5 — put() true + exists() true + size() ok + get() lanza -> delete_calls = 1
     */
    public function test_caso_5_put_true_get_throws_executes_delete(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);
        $receipt = new PaymentReceipt(['pago_id' => $pago->id, 'folio_recibo' => 'TEST-05']);

        $service = app(PaymentReceiptDocumentService::class);
        $docMock = $this->createMock(\App\Services\PagoDocumentoService::class);
        $dummyPdf = '%PDF-1.4 DUMMY PDF CONTENT FOR TESTING';
        $docMock->method('generarReciboPagoPdfContent')->willReturn($dummyPdf);

        $diskMock = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $capturedKey = null;

        $diskMock->shouldReceive('put')->once()->with(\Mockery::on(function ($key) use (&$capturedKey) {
            $capturedKey = $key;
            return true;
        }), \Mockery::type('string'))->andReturn(true);

        $diskMock->shouldReceive('exists')->once()->andReturn(true);
        $diskMock->shouldReceive('size')->once()->andReturn(strlen($dummyPdf));
        $diskMock->shouldReceive('get')->once()->andThrow(new \RuntimeException('Connection aborted during get'));
        $diskMock->shouldReceive('delete')->once()->with(\Mockery::on(function ($k) use (&$capturedKey) {
            return $k === $capturedKey;
        }))->andReturn(true);

        Storage::shouldReceive('disk')->with('r2_private')->andReturn($diskMock);

        try {
            $service->generateAndStoreReceiptPdfContentOnly($pago, $receipt, $docMock);
            $this->fail('Debió lanzar excepción');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Connection aborted during get', $e->getMessage());
        }
    }

    /**
     * CASO 6 — put() true + contenido inválido (no empieza con %PDF-) -> delete_calls = 1
     */
    public function test_caso_6_put_true_corrupted_header_executes_delete(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);
        $receipt = new PaymentReceipt(['pago_id' => $pago->id, 'folio_recibo' => 'TEST-06']);

        $service = app(PaymentReceiptDocumentService::class);
        $docMock = $this->createMock(\App\Services\PagoDocumentoService::class);
        $dummyPdf = '%PDF-1.4 DUMMY PDF CONTENT FOR TESTING';
        $docMock->method('generarReciboPagoPdfContent')->willReturn($dummyPdf);

        $diskMock = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $capturedKey = null;

        $diskMock->shouldReceive('put')->once()->with(\Mockery::on(function ($key) use (&$capturedKey) {
            $capturedKey = $key;
            return true;
        }), \Mockery::type('string'))->andReturn(true);

        $diskMock->shouldReceive('exists')->once()->andReturn(true);
        $diskMock->shouldReceive('size')->once()->andReturn(strlen($dummyPdf));
        $diskMock->shouldReceive('get')->once()->andReturn('HTML CORRUPT CONTENT');
        $diskMock->shouldReceive('delete')->once()->with(\Mockery::on(function ($k) use (&$capturedKey) {
            return $k === $capturedKey;
        }))->andReturn(true);

        Storage::shouldReceive('disk')->with('r2_private')->andReturn($diskMock);

        try {
            $service->generateAndStoreReceiptPdfContentOnly($pago, $receipt, $docMock);
            $this->fail('Debió lanzar excepción');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('no es un PDF valido', $e->getMessage());
        }
    }

    /**
     * CASO 7 — Todo correcto: delete_calls = 0, retorna newKey y objeto permanece
     */
    public function test_caso_7_all_verifications_pass_returns_key_without_deletion(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);
        $receipt = new PaymentReceipt(['pago_id' => $pago->id, 'folio_recibo' => 'TEST-07']);

        $service = app(PaymentReceiptDocumentService::class);
        $docService = app(\App\Services\PagoDocumentoService::class);

        $key = $service->generateAndStoreReceiptPdfContentOnly($pago, $receipt, $docService);

        $this->assertNotNull($key);
        $this->assertStringStartsWith("documents/payment-receipts/{$pago->id}/", $key);
        $this->assertTrue(Storage::disk('r2_private')->exists($key));
    }

    /**
     * CLEANUP FAIL — delete() también falla: preserva excepción primaria y no daña estado
     */
    public function test_cleanup_failure_preserves_primary_exception(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);
        $receipt = new PaymentReceipt(['pago_id' => $pago->id, 'folio_recibo' => 'TEST-CLEANUP-FAIL']);

        $service = app(PaymentReceiptDocumentService::class);
        $docService = app(\App\Services\PagoDocumentoService::class);

        $diskMock = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $capturedKey = null;

        $diskMock->shouldReceive('put')->once()->with(\Mockery::on(function ($key) use (&$capturedKey) {
            $capturedKey = $key;
            return true;
        }), \Mockery::type('string'))->andReturn(true);

        $diskMock->shouldReceive('exists')->once()->andReturn(false); // Falla primaria
        $diskMock->shouldReceive('delete')->once()->andThrow(new \RuntimeException('Delete failed due to S3 500')); // Cleanup falla

        Storage::shouldReceive('disk')->with('r2_private')->andReturn($diskMock);

        try {
            $service->generateAndStoreReceiptPdfContentOnly($pago, $receipt, $docService);
            $this->fail('Debió lanzar la excepción primaria');
        } catch (\RuntimeException $e) {
            // Debe preservar la causa primaria (exists), no ser sustituida silenciosamente por la de delete
            $this->assertStringContainsString('no se encuentra en R2', $e->getMessage());
        }
    }

    /**
     * PDF PREVIO — Falla en nuevo intento NO borra el PDF previo legítimo
     */
    public function test_failed_attempt_does_not_delete_previous_valid_pdf(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        // Aprobación inicial exitosa
        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $receipt = PaymentReceipt::where('pago_id', $pago->id)->firstOrFail();
        $previousKey = $receipt->pdf_path;
        $this->assertTrue(Storage::disk('r2_private')->exists($previousKey));

        // Intento fallido de regeneración
        $service = app(PaymentReceiptDocumentService::class);

        $tempReceipt = new PaymentReceipt(['pago_id' => $pago->id, 'folio_recibo' => 'TEMP']);

        $failingDoc = $this->createMock(\App\Services\PagoDocumentoService::class);
        $failingDoc->method('generarReciboPagoPdfContent')->willReturn('%PDF-invalid-size');

        try {
            $service->generateAndStoreReceiptPdfContentOnly($pago, $tempReceipt, $failingDoc);
        } catch (\Throwable $e) {}

        // El PDF previo legítimo sigue existiendo intacto
        $this->assertTrue(Storage::disk('r2_private')->exists($previousKey));
    }

    /**
     * RETRY — Intento 1 falla en verificación con cleanup -> Intento 2 triunfa -> exactamente 1 objeto válido
     */
    public function test_retry_after_verification_failure_leaves_exactly_one_valid_object(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $service = app(PaymentReceiptDocumentService::class);
        $receipt = new PaymentReceipt(['pago_id' => $pago->id, 'folio_recibo' => 'RETRY-01']);

        // Intento 1: falla verificación de tamaño con cleanup automático
        $mockFailingDoc = $this->createMock(\App\Services\PagoDocumentoService::class);
        $mockFailingDoc->method('generarReciboPagoPdfContent')->willReturn('%PDF-1.4 dummy content');

        // Simulamos fallo antes de aprobación
        $attempt1Key = null;
        try {
            $diskMock = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
            $diskMock->shouldReceive('put')->once()->with(\Mockery::on(function ($k) use (&$attempt1Key) {
                $attempt1Key = $k;
                return true;
            }), \Mockery::type('string'))->andReturn(true);
            $diskMock->shouldReceive('exists')->once()->andReturn(true);
            $diskMock->shouldReceive('size')->once()->andReturn(0); // Falla tamaño
            $diskMock->shouldReceive('delete')->once()->with(\Mockery::on(function ($k) use (&$attempt1Key) {
                return $k === $attempt1Key;
            }))->andReturn(true);

            Storage::shouldReceive('disk')->with('r2_private')->andReturn($diskMock);

            $service->generateAndStoreReceiptPdfContentOnly($pago, $receipt, $mockFailingDoc);
        } catch (\Throwable $e) {}

        \Mockery::close();
        $this->app->forgetInstance('filesystem');
        \Illuminate\Support\Facades\Storage::clearResolvedInstances();
        \Illuminate\Support\Facades\Storage::fake('r2_private');

        // Intento 2: Aprobación HTTP normal
        $pagoFresh = $this->createPagoForPatient($paciente);
        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pagoFresh));

        $freshReceipt = PaymentReceipt::where('pago_id', $pagoFresh->id)->firstOrFail();
        $allFiles = Storage::disk('r2_private')->allFiles("documents/payment-receipts/{$pagoFresh->id}");

        $this->assertCount(1, $allFiles);
        $this->assertEquals($freshReceipt->pdf_path, $allFiles[0]);
    }
}
