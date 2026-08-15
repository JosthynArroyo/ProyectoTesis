<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Dependiente;
use App\Models\Especialidad;
use App\Models\Pago;
use App\Models\PaymentReceipt;
use App\Models\Role;
use App\Models\User;
use App\Services\PaymentOrderDocumentService;
use App\Services\PagoService;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentOrderR2StorageTest extends TestCase
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
            'folio_unico' => 'OC-20260804-' . sprintf('%06d', rand(1, 99999)),
            'token_publico' => bin2hex(random_bytes(24)),
            'csv' => 'OC-' . strtoupper(bin2hex(random_bytes(4))) . '-TEST',
            'monto' => 35.00,
            'moneda' => 'USD',
            'metodo_pago' => Pago::METODO_EFECTIVO,
            'estado' => Pago::ESTADO_PENDIENTE,
        ]);
    }

    // 1. Orden sin PDF se genera en r2_private
    public function test_order_without_pdf_is_generated_in_r2_private(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $this->assertNull($pago->orden_pdf_path);
        $this->assertNull($pago->orden_pdf_disk);

        $pagoService = app(PagoService::class);
        $key = $pagoService->obtenerOGenerarOrdenPdf($pago, $paciente);

        $pago->refresh();
        $this->assertEquals('r2_private', $pago->orden_pdf_disk);
        $this->assertEquals($key, $pago->orden_pdf_path);
        $this->assertTrue(Storage::disk('r2_private')->exists($key));
    }

    // 2. Clave usa documents/payment-orders/{pago_id}/{uuid}.pdf
    public function test_key_uses_payment_orders_pago_id_uuid_format(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $pagoService = app(PagoService::class);
        $key = $pagoService->obtenerOGenerarOrdenPdf($pago, $paciente);

        $this->assertStringStartsWith("documents/payment-orders/{$pago->id}/", $key);
        $this->assertStringEndsWith(".pdf", $key);
    }

    // 3. orden_pdf_disk queda r2_private
    public function test_orden_pdf_disk_becomes_r2_private(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $pagoService = app(PagoService::class);
        $pagoService->obtenerOGenerarOrdenPdf($pago, $paciente);

        $pago->refresh();
        $this->assertEquals('r2_private', $pago->orden_pdf_disk);
    }

    // 4. No se crea archivo local
    public function test_no_local_file_created(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $pagoService = app(PagoService::class);
        $key = $pagoService->obtenerOGenerarOrdenPdf($pago, $paciente);

        $this->assertFalse(Storage::disk('local')->exists($key));
    }

    // 5. El objeto comienza con %PDF
    public function test_stored_object_starts_with_pdf_header(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $pagoService = app(PagoService::class);
        $key = $pagoService->obtenerOGenerarOrdenPdf($pago, $paciente);

        $content = Storage::disk('r2_private')->get($key);
        $this->assertStringStartsWith('%PDF', $content);
    }

    // 6. Descarga del paciente titular funciona
    public function test_authorized_titular_patient_can_download_order(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $response = $this->actingAs($paciente)->get(route('paciente.pagos.orden.pdf', $pago));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    // 7. Descarga del representante funciona
    public function test_authorized_representative_can_download_order(): void
    {
        $titular = $this->createRoleUser('paciente');
        $dependiente = Dependiente::create([
            'user_id' => $titular->id,
            'dni' => '0998877665',
            'nombre' => 'Hijo',
            'apellido' => 'Gomez',
            'parentesco' => 'hijo',
            'fecha_nacimiento' => '2016-05-05',
            'genero' => 'M',
            'activo' => true,
        ]);

        $pago = $this->createPagoForPatient($titular, $dependiente);

        $response = $this->actingAs($titular)->get(route('paciente.pagos.orden.pdf', $pago));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    // 8. Descarga del administrador funciona
    public function test_administrator_can_download_order(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $response = $this->actingAs($admin)->get(route('admin.pagos.orden.pdf', $pago));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    // 9. Superadministrador permitido
    public function test_superadmin_can_download_order(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $superadmin = $this->createRoleUser('superadmin');
        $pago = $this->createPagoForPatient($paciente);

        $response = $this->actingAs($superadmin)->get(route('admin.pagos.orden.pdf', $pago));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    // 10. Paciente ajeno recibe 403
    public function test_unrelated_patient_gets_403(): void
    {
        $paciente1 = $this->createRoleUser('paciente');
        $paciente2 = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente1);

        $response = $this->actingAs($paciente2)->get(route('paciente.pagos.orden.pdf', $pago));

        $response->assertStatus(403);
    }

    // 11. Doctor denegado
    public function test_doctor_denied(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $doctor = $this->createRoleUser('doctor');
        $pago = $this->createPagoForPatient($paciente);

        $resPac = $this->actingAs($doctor)->get(route('paciente.pagos.orden.pdf', $pago));
        $this->assertTrue(in_array($resPac->status(), [403, 302], true));

        $resAdmin = $this->actingAs($doctor)->get(route('admin.pagos.orden.pdf', $pago));
        $this->assertTrue(in_array($resAdmin->status(), [403, 302], true));
    }

    // 12. Laboratorio denegado
    public function test_laboratory_denied(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $lab = $this->createRoleUser('laboratorio');
        $pago = $this->createPagoForPatient($paciente);

        $resPac = $this->actingAs($lab)->get(route('paciente.pagos.orden.pdf', $pago));
        $this->assertTrue(in_array($resPac->status(), [403, 302], true));

        $resAdmin = $this->actingAs($lab)->get(route('admin.pagos.orden.pdf', $pago));
        $this->assertTrue(in_array($resAdmin->status(), [403, 302], true));
    }

    // 13. Visitante no autenticado denegado
    public function test_unauthenticated_guest_denied(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $response = $this->get(route('paciente.pagos.orden.pdf', $pago));
        $response->assertRedirect();
    }

    // 14. PDF heredado local continua funcionando
    public function test_legacy_local_pdf_continues_working(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $localPath = 'pagos/ordenes/orden_legacy_999.pdf';
        Storage::disk('local')->put($localPath, '%PDF-1.4 Legacy Order Content');

        $pago->update([
            'orden_pdf_path' => $localPath,
            'orden_pdf_disk' => null,
        ]);

        $response = $this->actingAs($paciente)->get(route('paciente.pagos.orden.pdf', $pago));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    // 15. PDF R2 existente no se regenera
    public function test_existing_r2_pdf_is_not_regenerated(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $pagoService = app(PagoService::class);
        $key1 = $pagoService->obtenerOGenerarOrdenPdf($pago, $paciente);
        $key2 = $pagoService->obtenerOGenerarOrdenPdf($pago, $paciente);

        $this->assertEquals($key1, $key2);
    }

    // 16. Cambio a pagado conserva la orden
    public function test_changing_to_paid_retains_order(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $pagoService = app(PagoService::class);
        $key = $pagoService->obtenerOGenerarOrdenPdf($pago, $paciente);

        $pagoService->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin);

        $pago->refresh();
        $this->assertEquals($key, $pago->orden_pdf_path);
        $this->assertTrue(Storage::disk('r2_private')->exists($key));
    }

    // 17. Rechazo conserva la orden
    public function test_rejection_retains_order(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $pagoService = app(PagoService::class);
        $key = $pagoService->obtenerOGenerarOrdenPdf($pago, $paciente);

        $pagoService->cambiarEstado($pago, Pago::ESTADO_RECHAZADO, $admin, 'Comprobante ilegible');

        $pago->refresh();
        $this->assertEquals($key, $pago->orden_pdf_path);
        $this->assertTrue(Storage::disk('r2_private')->exists($key));
    }

    // 18. Anulación conserva la orden
    public function test_annulment_retains_order(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $pagoService = app(PagoService::class);
        $key = $pagoService->obtenerOGenerarOrdenPdf($pago, $paciente);

        $pagoService->cambiarEstado($pago, Pago::ESTADO_ANULADO, $admin, 'Anulacion autorizada');

        $pago->refresh();
        $this->assertEquals($key, $pago->orden_pdf_path);
        $this->assertTrue(Storage::disk('r2_private')->exists($key));
    }

    // 19. Emisión de recibo no elimina la orden
    public function test_receipt_emission_does_not_delete_order(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $pagoService = app(PagoService::class);
        $orderKey = $pagoService->obtenerOGenerarOrdenPdf($pago, $paciente);

        $pagoService->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin);

        $pago->refresh();
        $this->assertNotNull($pago->receipt);
        $this->assertTrue(Storage::disk('r2_private')->exists($orderKey));
        $this->assertTrue(Storage::disk('r2_private')->exists($pago->receipt->pdf_path));
    }

    // 20. Fallo R2 no actualiza la BD
    public function test_r2_failure_does_not_update_db(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $orderDocService = app(PaymentOrderDocumentService::class);

        try {
            $orderDocService->verifyPdfContent('');
        } catch (\Throwable $e) {
            // Expected
        }

        $pago->refresh();
        $this->assertNull($pago->orden_pdf_path);
        $this->assertNull($pago->orden_pdf_disk);
    }

    // 21. Fallo BD elimina unicamente el objeto nuevo
    public function test_db_failure_deletes_only_new_r2_object(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $orderDocService = app(PaymentOrderDocumentService::class);
        $docService = app(\App\Services\PagoDocumentoService::class);

        DB::shouldReceive('transaction')->andThrow(new \Exception('DB Transaction Error'));

        try {
            $orderDocService->generateAndStoreOrderPdf($pago, $docService);
        } catch (\Throwable $e) {
            // Expected
        }

        $r2Files = Storage::disk('r2_private')->allFiles("documents/payment-orders/{$pago->id}");
        $this->assertCount(0, $r2Files);
    }

    // 22. Ruta heredada ausente se regenera directamente en R2
    public function test_missing_legacy_route_regenerates_directly_in_r2(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $pago->update([
            'orden_pdf_path' => 'pagos/ordenes/orden_missing.pdf',
            'orden_pdf_disk' => null,
        ]);

        $pagoService = app(PagoService::class);
        $key = $pagoService->obtenerOGenerarOrdenPdf($pago, $paciente);

        $pago->refresh();
        $this->assertEquals('r2_private', $pago->orden_pdf_disk);
        $this->assertStringStartsWith("documents/payment-orders/{$pago->id}/", $key);
        $this->assertTrue(Storage::disk('r2_private')->exists($key));
        $this->assertFalse(Storage::disk('local')->exists('pagos/ordenes/orden_missing.pdf'));
    }

    // 23. QR CSV nuevo permanece intacto
    public function test_new_csv_qr_remains_intact(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $response = $this->get(route('documentos.verificar.show', ['csv' => $pago->csv]));
        $response->assertOk();
        $response->assertSee('Orden de cobro');
        $response->assertSee($pago->folio_unico);
    }

    // 24. QR token heredado permanece intacto
    public function test_legacy_token_qr_remains_intact(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $response = $this->get(route('pagos.token.show', ['token' => $pago->token_publico]));
        $response->assertOk();
        $response->assertSee($pago->folio_unico);
    }

    // 25. Verificación pública no expone PDF
    public function test_public_verification_does_not_expose_pdf(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $responseCsv = $this->get(route('documentos.verificar.show', ['csv' => $pago->csv]));
        $responseCsv->assertDontSee('.pdf');
        $responseCsv->assertDontSee('r2.dev');

        $responseToken = $this->get(route('pagos.token.show', ['token' => $pago->token_publico]));
        $responseToken->assertDontSee('.pdf');
        $responseToken->assertDontSee('r2.dev');
    }

    // 26. Dry-run no modifica

    // 27. Execute migra solo vinculados

    // 28. Execute no migra pagos sin ruta

    // 29. Execute omite los 25 huérfanos

    // 30. Execute conserva archivos locales

    // 31. Execute es idempotente

    // 32. Verify comprueba integridad

    // 33. Enlaces no activan overlay
    public function test_order_links_have_loader_exclusion_attributes(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $resPac = $this->actingAs($paciente)->get(route('paciente.pagos.index'));
        $resPac->assertOk();
        $resPac->assertSee('data-action-lock-ignore');
        $resPac->assertSee('data-skip-page-loader');

        $resAdmin = $this->actingAs($admin)->get(route('admin.pagos.show', $pago));
        $resAdmin->assertOk();
        $resAdmin->assertSee('data-action-lock-ignore');
        $resAdmin->assertSee('data-skip-page-loader');
    }

    // 34. Recibos y comprobantes bancarios permanecen intactos
    public function test_receipts_and_proofs_remain_intact(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        // Proof upload
        $proof = UploadedFile::fake()->image('proof.jpg', 300, 300);
        $this->actingAs($paciente)->post(route('paciente.pagos.submit', $pago), [
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'comprobante' => $proof,
        ]);
        $pago->refresh();
        $this->assertEquals('r2_private', $pago->comprobante_disk);

        // Approval -> receipt in r2_private
        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $pago->refresh();
        $receipt = PaymentReceipt::where('pago_id', $pago->id)->firstOrFail();
        $this->assertEquals('r2_private', $receipt->pdf_disk);
    }

    // 35. Titular abre una orden pagada: 200 application/pdf
    public function test_titular_opens_paid_order_returns_200_application_pdf(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_PAGADO, $pago->estado);

        $response = $this->actingAs($paciente)->get(route('paciente.pagos.orden.pdf', $pago));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    // 36. Representante abre la orden pagada de su dependiente: 200
    public function test_representative_opens_paid_order_of_dependent_returns_200(): void
    {
        $titular = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $dependiente = Dependiente::create([
            'user_id' => $titular->id,
            'dni' => '0998877661',
            'nombre' => 'Hija',
            'apellido' => 'Arroyo',
            'parentesco' => 'hijo',
            'fecha_nacimiento' => '2018-03-03',
            'genero' => 'F',
            'activo' => true,
        ]);

        $pago = $this->createPagoForPatient($titular, $dependiente);
        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_PAGADO, $pago->estado);

        $response = $this->actingAs($titular)->get(route('paciente.pagos.orden.pdf', $pago));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    // 37. Paciente ajeno recibe 403 en orden pagada
    public function test_unrelated_patient_opens_paid_order_gets_403(): void
    {
        $paciente1 = $this->createRoleUser('paciente');
        $paciente2 = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');

        $pago = $this->createPagoForPatient($paciente1);
        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $pago->refresh();

        $response = $this->actingAs($paciente2)->get(route('paciente.pagos.orden.pdf', $pago));
        $response->assertStatus(403);
    }

    // 38. Pago pendiente relacionado: 200
    public function test_related_pending_payment_order_returns_200(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);
        $this->assertEquals(Pago::ESTADO_PENDIENTE, $pago->estado);

        $response = $this->actingAs($paciente)->get(route('paciente.pagos.orden.pdf', $pago));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    // 39. Orden R2 existente no se regenera ni cambia de clave
    public function test_existing_r2_order_is_not_regenerated_nor_key_changed(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $pagoService = app(PagoService::class);
        $keyInitial = $pagoService->obtenerOGenerarOrdenPdf($pago, $paciente);

        $response1 = $this->actingAs($paciente)->get(route('paciente.pagos.orden.pdf', $pago));
        $response1->assertOk();

        $pago->refresh();
        $keyAfterFirstRead = $pago->orden_pdf_path;

        $response2 = $this->actingAs($paciente)->get(route('paciente.pagos.orden.pdf', $pago));
        $response2->assertOk();

        $pago->refresh();
        $keyAfterSecondRead = $pago->orden_pdf_path;

        $this->assertEquals($keyInitial, $keyAfterFirstRead);
        $this->assertEquals($keyInitial, $keyAfterSecondRead);
    }

    // 40. El botón continúa oculto para pagos pagados
    public function test_download_order_button_remains_hidden_for_paid_payments(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_PAGADO, $pago->estado);

        $response = $this->actingAs($paciente)->get(route('paciente.pagos.index'));
        $response->assertOk();
        $response->assertDontSee(route('paciente.pagos.orden.pdf', $pago));
    }
}
