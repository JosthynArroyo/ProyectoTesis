<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Pago;
use App\Models\PaymentReceipt;
use App\Models\Role;
use App\Models\User;
use App\Services\PagoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentImmutabilityAfterReceiptTest extends TestCase
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

    private function createPago(User $paciente, array $attributes = []): Pago
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

        return Pago::create(array_merge([
            'cita_id' => $cita->id,
            'paciente_id' => $paciente->id,
            'folio_unico' => 'ORD-' . uniqid(),
            'token_publico' => bin2hex(random_bytes(16)),
            'monto' => 50.00,
            'moneda' => 'USD',
            'metodo_pago' => Pago::METODO_EFECTIVO,
            'estado' => Pago::ESTADO_PENDIENTE,
        ], $attributes));
    }

    /**
     * F1 — Monto antes de aprobación: Admin modifica monto con éxito en estado editable.
     */
    public function test_f1_admin_can_update_amount_before_payment_is_approved(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPago($paciente, [
            'estado' => Pago::ESTADO_PENDIENTE,
            'monto' => 50.00,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.pagos.monto.update', $pago), [
            'monto' => 75.50,
            'moneda' => 'USD',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Monto de pago actualizado.');

        $pago->refresh();
        $this->assertEquals(75.50, (float) $pago->monto);
        $this->assertEquals('USD', $pago->moneda);

        $this->assertDatabaseHas('payment_status_logs', [
            'pago_id' => $pago->id,
            'actor_id' => $admin->id,
        ]);
    }

    /**
     * F2 — Método antes de aprobación: Admin modifica método con éxito en estado editable.
     */
    public function test_f2_admin_can_update_method_before_payment_is_approved(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPago($paciente, [
            'estado' => Pago::ESTADO_PENDIENTE,
            'metodo_pago' => Pago::METODO_EFECTIVO,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.pagos.metodo.update', $pago), [
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'observacion_admin' => 'El paciente solicita pagar por transferencia bancaria.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Metodo de pago actualizado.');

        $pago->refresh();
        $this->assertEquals(Pago::METODO_TRANSFERENCIA, $pago->metodo_pago);
        $this->assertEquals('El paciente solicita pagar por transferencia bancaria.', $pago->observacion_admin);
    }

    /**
     * F3 — Monto después de pagado: Intento de modificar monto es rechazado controladamente.
     */
    public function test_f3_update_amount_is_rejected_after_payment_is_paid(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPago($paciente, [
            'monto' => 100.00,
            'metodo_pago' => Pago::METODO_EFECTIVO,
        ]);

        // Aprobar pago (crea el recibo fiscal)
        app(PagoService::class)->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin, 'Cobro en ventanilla');
        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_PAGADO, $pago->estado);

        // Intento directo HTTP de cambiar monto
        $response = $this->actingAs($admin)->post(route('admin.pagos.monto.update', $pago), [
            'monto' => 150.00,
            'moneda' => 'USD',
        ]);

        $response->assertSessionHasErrors('error');

        $pago->refresh();
        $this->assertEquals(100.00, (float) $pago->monto, 'El monto del pago aprobado no debe cambiar');
    }

    /**
     * F4 — Método después de pagado: Intento de modificar método es rechazado controladamente.
     */
    public function test_f4_update_method_is_rejected_after_payment_is_paid(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPago($paciente, [
            'monto' => 80.00,
            'metodo_pago' => Pago::METODO_EFECTIVO,
        ]);

        app(PagoService::class)->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin, 'Cobrado');
        $pago->refresh();

        $response = $this->actingAs($admin)->post(route('admin.pagos.metodo.update', $pago), [
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'observacion_admin' => 'Intento cambiar método tras emitir recibo',
        ]);

        $response->assertSessionHasErrors('error');

        $pago->refresh();
        $this->assertEquals(Pago::METODO_EFECTIVO, $pago->metodo_pago, 'El método de pago aprobado no debe cambiar');
    }

    /**
     * F5 — Recibo ya emitido: Si existe PaymentReceipt, impide modificar monto o método.
     */
    public function test_f5_existing_payment_receipt_blocks_financial_modifications(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPago($paciente, [
            'monto' => 60.00,
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'estado' => Pago::ESTADO_PENDIENTE, // Simular que por anomalía el estado no es pagado pero hay recibo
        ]);

        // Crear recibo asociado
        $receipt = PaymentReceipt::create([
            'pago_id' => $pago->id,
            'folio_recibo' => 'RP-TEST-0001',
            'emitido_en' => now(),
            'emitido_por' => $admin->id,
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'monto' => 60.00,
            'verification_token' => 'token-test-123456',
            'csv' => 'CSV-12345-ABC',
        ]);

        $this->assertFalse($pago->esEditableFinancieramente());

        // Intentar actualizar monto
        $respMonto = $this->actingAs($admin)->post(route('admin.pagos.monto.update', $pago), [
            'monto' => 90.00,
        ]);
        $respMonto->assertSessionHasErrors('error');
        $this->assertEquals(60.00, (float) $pago->refresh()->monto);

        // Intentar actualizar método
        $respMetodo = $this->actingAs($admin)->post(route('admin.pagos.metodo.update', $pago), [
            'metodo_pago' => Pago::METODO_EFECTIVO,
            'observacion_admin' => 'Intento forzar cambio',
        ]);
        $respMetodo->assertSessionHasErrors('error');
        $this->assertEquals(Pago::METODO_TRANSFERENCIA, $pago->refresh()->metodo_pago);
    }

    /**
     * F6 — Consistencia Pago/Recibo: Confirmar que tras rechazos, pago y recibo conservan exactos montos y métodos.
     */
    public function test_f6_financial_consistency_between_pago_and_receipt_is_preserved(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente = $this->createRoleUser('paciente');
        $proofKey = "documents/payment-proofs/test-proof-f6.jpg";
        Storage::disk('r2_private')->put($proofKey, 'fake-proof');
        $pago = $this->createPago($paciente, [
            'monto' => 120.00,
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'comprobante_path' => $proofKey,
            'comprobante_disk' => 'r2_private',
        ]);

        app(PagoService::class)->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin, 'Aprobado');
        $pago->refresh();
        $receipt = $pago->receipt;
        $this->assertNotNull($receipt);

        // Intentar modificar monto
        $this->actingAs($admin)->post(route('admin.pagos.monto.update', $pago), [
            'monto' => 200.00,
        ]);

        // Intentar modificar método
        $this->actingAs($admin)->post(route('admin.pagos.metodo.update', $pago), [
            'metodo_pago' => Pago::METODO_EFECTIVO,
            'observacion_admin' => 'Cambio a efectivo posterior',
        ]);

        $pago->refresh();
        $receipt->refresh();

        $this->assertEquals((float) $receipt->monto, (float) $pago->monto);
        $this->assertEquals($receipt->metodo_pago, $pago->metodo_pago);
        $this->assertEquals(120.00, (float) $pago->monto);
        $this->assertEquals(Pago::METODO_TRANSFERENCIA, $pago->metodo_pago);
    }

    /**
     * F7 — Carrera determinista (TOCTOU):
     * Request A inicia modificación cuando pago parece pendiente;
     * Concurrentemente Request B aprueba el pago y emite recibo;
     * Request A entra al tramo crítico bajo lockForUpdate(), lee fila fresca y rechaza la modificación.
     */
    public function test_f7_toctou_race_condition_under_lock_aborts_modification(): void
    {
        $adminA = $this->createRoleUser('administrador');
        $adminB = $this->createRoleUser('administrador');
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPago($paciente, [
            'monto' => 45.00,
            'metodo_pago' => Pago::METODO_EFECTIVO,
            'estado' => Pago::ESTADO_PENDIENTE,
        ]);

        // Simular que justo antes de ingresar a la transacción en actualizarMontoAdministrativo,
        // otro administrador aprueba el pago concurrentemente en la BD:
        DB::beforeExecuting(function ($query, $bindings) use ($pago, $adminB) {
            static $approved = false;
            // Se dispara antes de DB::transaction (durante la carga de usuario/roles o validación)
            if (! $approved && (str_contains($query, 'roles') || str_contains($query, 'users') || str_contains($query, 'sessions'))) {
                DB::table('pagos')
                    ->where('id', $pago->id)
                    ->update([
                        'estado' => Pago::ESTADO_PAGADO,
                        'aprobado_por' => $adminB->id,
                        'aprobado_en' => now(),
                    ]);

                DB::table('payment_receipts')->insert([
                    'pago_id' => $pago->id,
                    'folio_recibo' => 'RP-CONC-001',
                    'metodo_pago' => Pago::METODO_EFECTIVO,
                    'monto' => 45.00,
                    'emitido_en' => now(),
                    'emitido_por' => $adminB->id,
                    'verification_token' => 'token-concurrent-123',
                    'csv' => 'CSV-99999-CON',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $approved = true;
            }
        });

        // Request A intenta actualizar monto
        $response = $this->actingAs($adminA)->post(route('admin.pagos.monto.update', $pago), [
            'monto' => 99.99,
        ]);

        $response->assertSessionHasErrors('error');

        $pago->refresh();
        $this->assertEquals(45.00, (float) $pago->monto, 'El monto no debe haber cambiado a 99.99');
        $this->assertEquals(Pago::ESTADO_PAGADO, $pago->estado);
    }

    /**
     * F8 — Recibo no cambia: Modificaciones rechazadas no alteran archivos ni metadatos del recibo existente.
     */
    public function test_f8_rejected_modifications_do_not_alter_or_regenerate_receipt(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPago($paciente, [
            'monto' => 85.00,
            'metodo_pago' => Pago::METODO_EFECTIVO,
        ]);

        app(PagoService::class)->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin, 'Aprobado');
        $pago->refresh();
        $receipt = $pago->receipt;

        $folioOriginal = $receipt->folio_recibo;
        $tokenOriginal = $receipt->verification_token;
        $csvOriginal = $receipt->csv;
        $pdfPathOriginal = $receipt->pdf_path;
        $emitidoEnOriginal = $receipt->emitido_en->toDateTimeString();

        // Intentos rechazados
        $this->actingAs($admin)->post(route('admin.pagos.monto.update', $pago), ['monto' => 500.00]);
        $this->actingAs($admin)->post(route('admin.pagos.metodo.update', $pago), [
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'observacion_admin' => 'Intento inválido',
        ]);

        $receipt->refresh();
        $this->assertEquals($folioOriginal, $receipt->folio_recibo);
        $this->assertEquals($tokenOriginal, $receipt->verification_token);
        $this->assertEquals($csvOriginal, $receipt->csv);
        $this->assertEquals($pdfPathOriginal, $receipt->pdf_path);
        $this->assertEquals($emitidoEnOriginal, $receipt->emitido_en->toDateTimeString());
        $this->assertEquals(85.00, (float) $receipt->monto);
        $this->assertEquals(Pago::METODO_EFECTIVO, $receipt->metodo_pago);
    }
}
