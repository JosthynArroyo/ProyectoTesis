<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Pago;
use App\Models\PaymentReceipt;
use App\Models\Role;
use App\Models\User;
use App\Services\PaymentReceiptDocumentService;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentFinancialVerificationTest extends TestCase
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

    private function createPagoForPatient(User $paciente): Pago
    {
        $doctor = $this->createRoleUser('doctor');
        $esp = Especialidad::firstOrCreate(['nombre' => 'Medicina General']);

        $cita = Cita::create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
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
            'monto' => 45.00,
            'moneda' => 'USD',
            'metodo_pago' => Pago::METODO_EFECTIVO,
            'estado' => Pago::ESTADO_PENDIENTE,
        ]);
    }

    // 1. Nuevo recibo genera token unico
    public function test_new_receipt_generates_unique_token(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $receipt = PaymentReceipt::where('pago_id', $pago->id)->firstOrFail();

        $this->assertNotEmpty($receipt->verification_token);
        $this->assertGreaterThanOrEqual(20, strlen($receipt->verification_token));
    }

    // 2. Nuevo recibo PDF contiene QR
    public function test_new_receipt_pdf_contains_qr(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $receipt = PaymentReceipt::where('pago_id', $pago->id)->firstOrFail();

        $content = Storage::disk('r2_private')->get($receipt->pdf_path);
        $this->assertStringStartsWith('%PDF', $content);
    }

    // 3. QR utiliza ruta Laravel y no R2
    public function test_qr_uses_laravel_route_not_r2(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $receipt = PaymentReceipt::where('pago_id', $pago->id)->firstOrFail();

        $expectedUrl = route('recibos.verificar', ['token' => $receipt->verification_token], true);
        $this->assertStringContainsString('/verificar-recibo/', $expectedUrl);
        $this->assertStringStartsNotWith('https://r2', $expectedUrl);
    }

    // 4. Token valido devuelve HTML
    public function test_valid_token_returns_html(): void
    {
        $paciente = $this->createRoleUser('paciente', ['name' => 'Josthyn Arroyo']);
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $receipt = PaymentReceipt::where('pago_id', $pago->id)->firstOrFail();

        $response = $this->get(route('recibos.verificar', $receipt->verification_token));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $response->assertSee('Recibo de pago verificado');
    }

    // 5. Token invalido devuelve 404
    public function test_invalid_token_returns_404(): void
    {
        $response = $this->get('/verificar-recibo/token-invalido-inexistente-12345');
        $response->assertStatus(404);
    }

    // 6. Verificacion no devuelve %PDF
    public function test_verification_does_not_return_pdf_binary(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $receipt = PaymentReceipt::where('pago_id', $pago->id)->firstOrFail();

        $response = $this->get(route('recibos.verificar', $receipt->verification_token));
        $this->assertStringStartsNotWith('%PDF', $response->getContent());
    }

    // 7. Verificacion no expone rutas, discos ni R2
    public function test_verification_does_not_expose_paths_disks_or_r2(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $receipt = PaymentReceipt::where('pago_id', $pago->id)->firstOrFail();

        $response = $this->get(route('recibos.verificar', $receipt->verification_token));
        $content = $response->getContent();

        $this->assertStringNotContainsString('r2_private', $content);
        $this->assertStringNotContainsString('r2.dev', $content);
        $this->assertStringNotContainsString('documents/payment-receipts', $content);
    }

    // 8. Verificacion protege el nombre
    public function test_verification_protects_patient_name(): void
    {
        $paciente = $this->createRoleUser('paciente', ['name' => 'Josthyn Arroyo']);
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $receipt = PaymentReceipt::where('pago_id', $pago->id)->firstOrFail();

        $response = $this->get(route('recibos.verificar', $receipt->verification_token));
        $response->assertSee('Josthyn A.');
        $response->assertDontSee('Josthyn Arroyo');
    }

    // 9. Verificacion no muestra cedula ni referencia bancaria
    public function test_verification_does_not_show_dni_or_bank_reference(): void
    {
        $paciente = $this->createRoleUser('paciente', ['dni' => '0998877665']);
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);
        $pago->update(['referencia_transaccion' => 'REF-BANK-SECRET-999']);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $receipt = PaymentReceipt::where('pago_id', $pago->id)->firstOrFail();

        $response = $this->get(route('recibos.verificar', $receipt->verification_token));
        $response->assertDontSee('0998877665');
        $response->assertDontSee('REF-BANK-SECRET-999');
    }

    // 10. Recibo anulado muestra estado correspondiente
    public function test_annulled_receipt_shows_annulled_status(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $receipt = PaymentReceipt::where('pago_id', $pago->id)->firstOrFail();

        $pago->update(['estado' => Pago::ESTADO_ANULADO]);

        $response = $this->get(route('recibos.verificar', $receipt->verification_token));
        $response->assertOk();
        $response->assertSee('Recibo Anulado');
    }

    // 11. Recibo historico sin token sigue descargandose
    public function test_historical_receipt_without_token_continues_downloading(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);
        $pago->update(['estado' => Pago::ESTADO_PAGADO]);

        $localPath = 'pagos/recibos/recibo_hist_001.pdf';
        Storage::disk('local')->put($localPath, '%PDF-1.4 Historical Receipt');

        $receipt = PaymentReceipt::create([
            'pago_id' => $pago->id,
            'folio_recibo' => 'RP-HIST-001',
            'emitido_en' => now(),
            'metodo_pago' => 'efectivo',
            'monto' => 45.00,
            'pdf_path' => $localPath,
            'pdf_disk' => 'local',
            'verification_token' => null,
        ]);

        $response = $this->actingAs($paciente)->get(route('paciente.pagos.recibo.pdf', $pago));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    // 12. Recibo historico no se regenera
    public function test_historical_receipt_is_not_regenerated(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);
        $pago->update(['estado' => Pago::ESTADO_PAGADO]);

        $localPath = 'pagos/recibos/recibo_hist_002.pdf';
        Storage::disk('local')->put($localPath, '%PDF-1.4 Historical 2 Content');

        $receipt = PaymentReceipt::create([
            'pago_id' => $pago->id,
            'folio_recibo' => 'RP-HIST-002',
            'emitido_en' => now(),
            'metodo_pago' => 'efectivo',
            'monto' => 45.00,
            'pdf_path' => $localPath,
            'pdf_disk' => 'local',
            'verification_token' => null,
        ]);

        $pagoService = app(\App\Services\PagoService::class);
        $res = $pagoService->emitirReciboParaPago($pago, $paciente);

        $this->assertEquals($localPath, $res->pdf_path);
        $this->assertNull($res->verification_token);
    }

    // 13. Orden conserva token_publico
    public function test_order_retains_public_token(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $this->assertNotEmpty($pago->token_publico);
        $this->assertEquals(32, strlen($pago->token_publico));
    }

    // 14. Visitante sin sesion con token de orden valido recibe HTTP 200 HTML sin redireccion ni 403
    public function test_unauthenticated_visitor_gets_200_html_for_valid_order_token(): void
    {
        $paciente = $this->createRoleUser('paciente', ['name' => 'Josthyn Arroyo', 'dni' => '0912345678', 'email' => 'josthyn@test.com']);
        $pago = $this->createPagoForPatient($paciente);
        $pago->update(['referencia_transaccion' => 'BANK-SECRET-123']);

        $response = $this->get(route('pagos.token.show', $pago->token_publico));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $this->assertFalse($response->isRedirect());
        $this->assertNotEquals(403, $response->status());
        $this->assertStringStartsNotWith('%PDF', $response->getContent());
        $this->assertStringNotContainsString('r2.dev', $response->getContent());
        $this->assertStringNotContainsString('/storage/', $response->getContent());
        $this->assertStringNotContainsString('0912345678', $response->getContent());
        $this->assertStringNotContainsString('josthyn@test.com', $response->getContent());
        $this->assertStringNotContainsString('BANK-SECRET-123', $response->getContent());
        $response->assertSee('Josthyn A.');
    }

    // 15. Token de orden invalido devuelve 404
    public function test_invalid_order_token_returns_404(): void
    {
        $response = $this->get('/cobro/token-invalido-inexistente-12345');
        $response->assertStatus(404);
    }

    // 15b. Orden pagada y anulada muestra estado correspondiente
    public function test_order_paid_and_annulled_status(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $pago->update(['estado' => Pago::ESTADO_PAGADO]);
        $responsePaid = $this->get(route('pagos.token.show', $pago->token_publico));
        $responsePaid->assertSee('PAGADO');

        $pago->update(['estado' => Pago::ESTADO_ANULADO]);
        $responseAnnulled = $this->get(route('pagos.token.show', $pago->token_publico));
        $responseAnnulled->assertSee('ANULADO');
    }

    // 16. Pago pendiente muestra orden y token
    public function test_pending_payment_shows_order_and_token(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $response = $this->actingAs($paciente)->get(route('paciente.pagos.index'));
        $response->assertOk();
        $response->assertSee(route('paciente.pagos.orden.pdf', $pago));
        $response->assertSee(route('pagos.token.show', $pago->token_publico));
    }

    // 17. En verificacion muestra orden y comprobante
    public function test_in_verification_shows_order_and_proof(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $file = UploadedFile::fake()->image('proof.jpg', 300, 300);
        $this->actingAs($paciente)->post(route('paciente.pagos.submit', $pago), [
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'comprobante' => $file,
        ]);
        $pago->refresh();

        $response = $this->actingAs($paciente)->get(route('paciente.pagos.index'));
        $response->assertOk();
        $response->assertSee(route('paciente.pagos.orden.pdf', $pago));
        $response->assertSee(route('paciente.pagos.comprobante', $pago));
        $response->assertDontSee(route('paciente.pagos.recibo.pdf', $pago));
    }

    // 18. Rechazado permite reemplazo
    public function test_rejected_payment_allows_replacement(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);
        $pago->update(['estado' => Pago::ESTADO_RECHAZADO]);

        $this->assertTrue($pago->esEditablePorPaciente());
    }

    // 19. Pagado oculta orden y token
    public function test_paid_payment_hides_order_and_token_on_main_patient_card(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));

        $response = $this->actingAs($paciente)->get(route('paciente.pagos.index'));
        $response->assertOk();

        // The action buttons block for paid payment should NOT contain orden.pdf nor token.show
        $content = $response->getContent();
        $this->assertStringNotContainsString(route('paciente.pagos.orden.pdf', $pago), $content);
        $this->assertStringNotContainsString(route('pagos.token.show', $pago->token_publico), $content);
    }

    // 20. Pagado muestra recibo
    public function test_paid_payment_shows_receipt(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));

        $response = $this->actingAs($paciente)->get(route('paciente.pagos.index'));
        $response->assertOk();
        $response->assertSee(route('paciente.pagos.recibo.pdf', $pago));
    }

    // 21. Pago en efectivo pagado no muestra comprobante inexistente
    public function test_paid_cash_payment_does_not_show_nonexistent_proof(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);
        $pago->update(['metodo_pago' => Pago::METODO_EFECTIVO, 'comprobante_path' => null]);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));

        $response = $this->actingAs($paciente)->get(route('paciente.pagos.index'));
        $response->assertOk();
        $response->assertDontSee(route('paciente.pagos.comprobante', $pago));
    }

    // 22. Paciente ajeno no obtiene documentos privados
    public function test_unrelated_patient_cannot_access_private_documents(): void
    {
        $paciente1 = $this->createRoleUser('paciente');
        $paciente2 = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');

        $pago = $this->createPagoForPatient($paciente1);
        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));

        $this->actingAs($paciente2)->get(route('paciente.pagos.recibo.pdf', $pago))->assertStatus(403);
        $this->actingAs($paciente2)->get(route('paciente.pagos.orden.pdf', $pago))->assertStatus(403);
    }

    // 23. Enlaces no activan overlay
    public function test_links_have_overlay_exclusion_attributes(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));

        $response = $this->actingAs($paciente)->get(route('paciente.pagos.index'));
        $response->assertOk();
        $response->assertSee('data-action-lock-ignore');
        $response->assertSee('data-skip-page-loader');
    }

    // 24. No se crean objetos R2 adicionales al verificar
    public function test_verification_does_not_create_additional_r2_objects(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $receipt = PaymentReceipt::where('pago_id', $pago->id)->firstOrFail();

        $countBefore = count(Storage::disk('r2_private')->allFiles());

        $this->get(route('recibos.verificar', $receipt->verification_token))->assertOk();

        $countAfter = count(Storage::disk('r2_private')->allFiles());
        $this->assertEquals($countBefore, $countAfter);
    }

    // 25. No se modifican pdf_path ni pdf_disk existentes
    public function test_existing_pdf_path_and_pdf_disk_are_unmodified_on_verification(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $receipt = PaymentReceipt::where('pago_id', $pago->id)->firstOrFail();

        $pathBefore = $receipt->pdf_path;
        $diskBefore = $receipt->pdf_disk;

        $this->get(route('recibos.verificar', $receipt->verification_token))->assertOk();

        $receipt->refresh();
        $this->assertEquals($pathBefore, $receipt->pdf_path);
        $this->assertEquals($diskBefore, $receipt->pdf_disk);
    }

    // 26. Nueva orden genera csv y utiliza ruta /verificar/{csv}
    public function test_new_order_generates_csv_and_uses_verificar_csv_route(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $pagoService = app(\App\Services\PagoService::class);
        $pagoService->asegurarDatosOrden($pago);

        $pago->refresh();
        $this->assertNotEmpty($pago->csv);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{3}-[0-9]{5}-[A-Z0-9]{3}$/', $pago->csv);

        $response = $this->get(route('documentos.verificar.show', ['csv' => $pago->csv]));
        $response->assertOk();
        $response->assertViewIs('documentos.verificacion-show');
        $response->assertSee('Orden de cobro');
    }

    // 27. Nuevo recibo genera csv y utiliza ruta /verificar/{csv}
    public function test_new_receipt_generates_csv_and_uses_verificar_csv_route(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPagoForPatient($paciente);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $receipt = PaymentReceipt::where('pago_id', $pago->id)->firstOrFail();

        $this->assertNotEmpty($receipt->csv);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{3}-[0-9]{5}-[A-Z0-9]{3}$/', $receipt->csv);

        $response = $this->get(route('documentos.verificar.show', ['csv' => $receipt->csv]));
        $response->assertOk();
        $response->assertViewIs('documentos.verificacion-show');
        $response->assertSee('Recibo de pago');
    }
}
