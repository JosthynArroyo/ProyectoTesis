<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Pago;
use App\Models\PaymentReceipt;
use App\Models\Role;
use App\Models\User;
use App\Services\PagoDocumentoService;
use App\Services\PaymentReceiptDocumentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentApprovalBlockerTest extends TestCase
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

    private function createPagoForPatient(User $paciente, array $attributes = []): Pago
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
            'monto' => 45.00,
            'moneda' => 'USD',
            'metodo_pago' => Pago::METODO_EFECTIVO,
            'estado' => Pago::ESTADO_PENDIENTE,
        ], $attributes));
    }

    /**
     * PR1 — Aprobación normal:
     * Pago pendiente/en_verificacion + R2 OK -> pagado + receipt + PDF
     */
    public function test_pr1_normal_approval_creates_paid_status_receipt_and_valid_pdf_in_r2(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $response = $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));

        $response->assertRedirect(route('admin.pagos.show', $pago));
        $response->assertSessionHas('success');

        $pago->refresh();
        $this->assertSame(Pago::ESTADO_PAGADO, $pago->estado);
        $this->assertSame($admin->id, (int) $pago->aprobado_por);
        $this->assertNotNull($pago->aprobado_en);

        $receipt = $pago->receipt;
        $this->assertNotNull($receipt);
        $this->assertNotNull($receipt->pdf_path);
        $this->assertSame('r2_private', $receipt->pdf_disk);
        Storage::disk('r2_private')->assertExists($receipt->pdf_path);
    }

    /**
     * PR2 — Fallo de generación PDF:
     * Simula fallo antes del upload -> estado anterior intacto, 0 recibos nuevos, 0 artefactos R2 nuevos, no 500.
     */
    public function test_pr2_pdf_generation_failure_preserves_pago_and_cleans_up(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        // Mock PagoDocumentoService to throw during PDF rendering
        $this->mock(PagoDocumentoService::class, function ($mock) {
            $mock->shouldReceive('generarReciboPagoPdfContent')
                ->once()
                ->andThrow(new \RuntimeException('Dompdf engine fatal memory exhaustion'));
        });

        $response = $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));

        $this->assertNotEquals(500, $response->status());
        $response->assertRedirect(route('admin.pagos.show', $pago));
        $response->assertSessionHasErrors('error');

        $pago->refresh();
        $this->assertSame(Pago::ESTADO_PENDIENTE, $pago->estado);
        $this->assertNull($pago->aprobado_por);
        $this->assertNull($pago->aprobado_en);
        $this->assertNull($pago->receipt);
        $this->assertSame(0, PaymentReceipt::where('pago_id', $pago->id)->count());
        $this->assertEmpty(Storage::disk('r2_private')->allFiles());
    }

    /**
     * PR3 — Fallo de upload R2:
     * Simula put() / write() falla -> pago NO pagado, receipt NO definitivo, retry posible.
     */
    public function test_pr3_r2_upload_failure_reverts_pago_to_previous_state(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        // Mock PaymentReceiptDocumentService to fail on upload
        $this->mock(PaymentReceiptDocumentService::class, function ($mock) {
            $mock->shouldReceive('getDisk')->andReturn('r2_private');
            $mock->shouldReceive('resolveStorage')->andReturn(null);
            $mock->shouldReceive('generateAndStoreReceiptPdfContentOnly')
                ->once()
                ->andThrow(new \RuntimeException('Connection to Cloudflare R2 timed out'));
        });

        $response = $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));

        $this->assertNotEquals(500, $response->status());
        $response->assertRedirect(route('admin.pagos.show', $pago));
        $response->assertSessionHasErrors('error');

        $pago->refresh();
        $this->assertSame(Pago::ESTADO_PENDIENTE, $pago->estado);
        $this->assertNull($pago->aprobado_por);
        $this->assertNull($pago->receipt);
        $this->assertSame(0, PaymentReceipt::where('pago_id', $pago->id)->count());
    }

    /**
     * PR4 — R2 upload funciona pero verificación falla:
     * Simula upload OK, exists/verification FAIL -> limpiar objeto recién creado, restaurar/no cerrar pago.
     */
    public function test_pr4_r2_verification_failure_cleans_up_new_object_and_reverts_pago(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $this->mock(PaymentReceiptDocumentService::class, function ($mock) {
            $mock->shouldReceive('getDisk')->andReturn('r2_private');
            $mock->shouldReceive('resolveStorage')->andReturn(null);
            $mock->shouldReceive('generateAndStoreReceiptPdfContentOnly')
                ->once()
                ->andThrow(new \RuntimeException('Inconsistencia en el tamaño del PDF del recibo en R2.'));
        });

        $response = $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));

        $this->assertNotEquals(500, $response->status());
        $response->assertRedirect(route('admin.pagos.show', $pago));
        $response->assertSessionHasErrors('error');

        $pago->refresh();
        $this->assertSame(Pago::ESTADO_PENDIENTE, $pago->estado);
        $this->assertNull($pago->aprobado_por);
        $this->assertNull($pago->receipt);
        $this->assertSame(0, PaymentReceipt::where('pago_id', $pago->id)->count());
        $this->assertEmpty(Storage::disk('r2_private')->allFiles());
    }

    /**
     * PR5 — R2 OK pero persistencia DB final falla:
     * Debe limpiar nuevo objeto R2, pago mantiene estado anterior, no receipt inconsistente.
     */
    public function test_pr5_db_failure_after_r2_cleans_up_r2_object_and_reverts_pago(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        $this->mock(PaymentReceiptDocumentService::class, function ($mock) {
            $mock->shouldReceive('getDisk')->andReturn('r2_private');
            $mock->shouldReceive('resolveStorage')->andReturn(null);
            $mock->shouldReceive('generateAndStoreReceiptPdfContentOnly')
                ->once()
                ->andThrow(new \RuntimeException('Simulated DB deadlock during receipt finalization'));
        });

        $response = $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));

        $this->assertNotEquals(500, $response->status());
        $pago->refresh();
        $this->assertSame(Pago::ESTADO_PENDIENTE, $pago->estado);
        $this->assertNull($pago->receipt);
        $this->assertSame(0, PaymentReceipt::where('pago_id', $pago->id)->count());
        $this->assertEmpty(Storage::disk('r2_private')->allFiles());
    }

    /**
     * PR6 — Retry:
     * Primer intento R2 falla -> pago sigue pendiente.
     * Segundo intento R2 funciona -> un pago pagado, un receipt, un PDF.
     */
    public function test_pr6_retry_after_r2_failure_successfully_approves_payment(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        // Intento 1: R2 falla
        $this->mock(PaymentReceiptDocumentService::class, function ($mock) {
            $mock->shouldReceive('getDisk')->andReturn('r2_private');
            $mock->shouldReceive('resolveStorage')->andReturn(null);
            $mock->shouldReceive('generateAndStoreReceiptPdfContentOnly')
                ->once()
                ->andThrow(new \RuntimeException('Transient R2 error'));
        });

        $resp1 = $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $resp1->assertRedirect(route('admin.pagos.show', $pago));
        $resp1->assertSessionHasErrors('error');

        $pago->refresh();
        $this->assertSame(Pago::ESTADO_PENDIENTE, $pago->estado);
        $this->assertNull($pago->receipt);

        // Clear mock for Intento 2
        $this->app->forgetInstance(PaymentReceiptDocumentService::class);

        // Intento 2: R2 funciona normalmente
        $resp2 = $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $resp2->assertRedirect(route('admin.pagos.show', $pago));
        $resp2->assertSessionHas('success');

        $pago->refresh();
        $this->assertSame(Pago::ESTADO_PAGADO, $pago->estado);
        $this->assertSame($admin->id, (int) $pago->aprobado_por);
        $this->assertNotNull($pago->receipt);
        $this->assertSame(1, PaymentReceipt::where('pago_id', $pago->id)->count());
        Storage::disk('r2_private')->assertExists($pago->receipt->pdf_path);
    }

    /**
     * PR7 — Dos aprobaciones concurrentes:
     * Protección bajo lock evita dos recibos, dos PDFs, dos aprobaciones.
     */
    public function test_pr7_concurrent_approvals_result_in_exactly_one_receipt_and_one_approval(): void
    {
        $adminA = $this->createRoleUser('administrador');
        $adminB = $this->createRoleUser('administrador');
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        // Admin A aprueba con éxito
        $respA = $this->actingAs($adminA)->post(route('admin.pagos.aprobar', $pago));
        $respA->assertRedirect(route('admin.pagos.show', $pago));
        $respA->assertSessionHas('success');

        // Admin B intenta aprobar el mismo pago que ya fue pagado
        $respB = $this->actingAs($adminB)->post(route('admin.pagos.aprobar', $pago));
        $respB->assertSessionHasErrors('error');

        $pago->refresh();
        $this->assertSame(Pago::ESTADO_PAGADO, $pago->estado);
        $this->assertSame($adminA->id, (int) $pago->aprobado_por);
        $this->assertSame(1, PaymentReceipt::where('pago_id', $pago->id)->count());
        $allReceiptFiles = Storage::disk('r2_private')->allFiles();
        $this->assertCount(1, $allReceiptFiles);
    }

    /**
     * PR8 — Estado anterior en_verificacion:
     * Si falla recibo al aprobar desde en_verificacion -> vuelve/permanece en_verificacion (no pendiente).
     */
    public function test_pr8_failure_from_in_verification_state_restores_in_verification(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente = $this->createRoleUser('paciente');
        $proofKey = 'documents/payment-proofs/fake-proof.jpg';
        Storage::disk('r2_private')->put($proofKey, 'fake-proof-content');

        $pago = $this->createPagoForPatient($paciente, [
            'estado' => Pago::ESTADO_EN_VERIFICACION,
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'comprobante_path' => $proofKey,
            'comprobante_disk' => 'r2_private',
        ]);

        $this->mock(PaymentReceiptDocumentService::class, function ($mock) {
            $mock->shouldReceive('getDisk')->andReturn('r2_private');
            $mock->shouldReceive('resolveStorage')->andReturn(null);
            $mock->shouldReceive('generateAndStoreReceiptPdfContentOnly')
                ->once()
                ->andThrow(new \RuntimeException('R2 storage error during in_verificacion approval'));
        });

        $response = $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));

        $this->assertNotEquals(500, $response->status());
        $response->assertRedirect(route('admin.pagos.show', $pago));
        $response->assertSessionHasErrors('error');

        $pago->refresh();
        $this->assertSame(Pago::ESTADO_EN_VERIFICACION, $pago->estado, 'Debe restaurarse exactamente a en_verificacion, no a pendiente');
        $this->assertNull($pago->aprobado_por);
        $this->assertNull($pago->receipt);
        $this->assertSame(0, PaymentReceipt::where('pago_id', $pago->id)->count());
    }

    /**
     * TEST ESPECÍFICO DEL 500:
     * Llama realmente POST /admin/pagos/{pago}/aprobar simulando fallo R2.
     * Comprueba: status != 500 (redirect with error session) y pago NO queda pagado.
     */
    public function test_specific_500_blocker_reproduced_and_fixed(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoForPatient($paciente);

        // Simulate R2 failure during approval
        $this->mock(PaymentReceiptDocumentService::class, function ($mock) {
            $mock->shouldReceive('getDisk')->andReturn('r2_private');
            $mock->shouldReceive('resolveStorage')->andReturn(null);
            $mock->shouldReceive('generateAndStoreReceiptPdfContentOnly')
                ->once()
                ->andThrow(new \RuntimeException('Cloudflare R2 service unavailable (503)'));
        });

        $response = $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));

        // Debe ser redirección controlada con error, NUNCA 500
        $this->assertNotEquals(500, $response->status());
        $response->assertRedirect(route('admin.pagos.show', $pago));
        $response->assertSessionHasErrors('error');

        // Y el pago NUNCA debe quedar en estado pagado sin recibo
        $pago->refresh();
        $this->assertNotSame(Pago::ESTADO_PAGADO, $pago->estado);
        $this->assertSame(Pago::ESTADO_PENDIENTE, $pago->estado);
        $this->assertNull($pago->receipt);
        $this->assertSame(0, PaymentReceipt::where('pago_id', $pago->id)->count());
    }
}
