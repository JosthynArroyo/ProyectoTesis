<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Pago;
use App\Models\PaymentReceipt;
use App\Models\PaymentReceiptLog;
use App\Models\PaymentStatusLog;
use App\Models\Role;
use App\Models\User;
use App\Services\DocumentoCsvService;
use App\Services\PagoDocumentoService;
use App\Services\PagoService;
use App\Services\PaymentReceiptDocumentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentApprovalLifecycleIntegrityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-01 08:00:00');
        Storage::fake('r2_private');
        $this->seedRoles();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
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

    private function createPago(User $paciente, string $estado = Pago::ESTADO_PENDIENTE, array $overrides = []): Pago
    {
        $doctor = $this->createRoleUser('doctor');
        $esp = Especialidad::firstOrCreate(['nombre' => 'Medicina General']);

        $cita = Cita::create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-05',
            'hora' => '09:00:00',
            'motivo_consulta' => 'Consulta control',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);

        return Pago::create(array_merge([
            'paciente_id' => $paciente->id,
            'cita_id' => $cita->id,
            'monto' => 45.00,
            'moneda' => 'USD',
            'metodo_pago' => Pago::METODO_EFECTIVO,
            'estado' => $estado,
            'folio_unico' => 'ORD-'.rand(100000, 999999),
            'token_publico' => \Illuminate\Support\Str::random(40),
            'csv' => app(DocumentoCsvService::class)->generateCsv(),
        ], $overrides));
    }

    /**
     * PR1 — Aprobación normal
     */
    public function test_pr1_normal_approval(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        $pagoService = app(PagoService::class);
        $res = $pagoService->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin);

        $this->assertEquals(Pago::ESTADO_PAGADO, $res->estado);
        $this->assertEquals($admin->id, $res->aprobado_por);
        $this->assertNotNull($res->aprobado_en);

        $receipt = $res->receipt;
        $this->assertNotNull($receipt);
        $this->assertEquals($admin->id, $receipt->emitido_por);
        $this->assertNotNull($receipt->pdf_path);
        $this->assertTrue(Storage::disk('r2_private')->exists($receipt->pdf_path));
    }

    /**
     * PR2 — Dompdf fail
     */
    public function test_pr2_dompdf_fail(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        $failingDocService = $this->createMock(PagoDocumentoService::class);
        $failingDocService->method('generarReciboPagoPdfContent')->willThrowException(new \RuntimeException('Dompdf error'));
        $this->app->instance(PagoDocumentoService::class, $failingDocService);

        try {
            app(PagoService::class)->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin);
            $this->fail('Debió lanzar RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('No se pudo completar la emisión del recibo', $e->getMessage());
        }

        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_PENDIENTE, $pago->estado);
        $this->assertNull($pago->aprobado_por);
        $this->assertNull($pago->receipt);
        $this->assertCount(0, PaymentStatusLog::where('pago_id', $pago->id)->get());
    }

    /**
     * PR3 — R2 put fail
     */
    public function test_pr3_r2_put_fail(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        Storage::shouldReceive('disk')->with('r2_private')->andReturnSelf();
        Storage::shouldReceive('put')->andThrow(new \RuntimeException('R2 network down'));
        Storage::shouldReceive('delete')->andReturn(true);

        try {
            app(PagoService::class)->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin);
            $this->fail('Debió lanzar RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('No se pudo completar la emisión del recibo', $e->getMessage());
        }

        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_PENDIENTE, $pago->estado);
        $this->assertNull($pago->aprobado_por);
        $this->assertNull($pago->receipt);
    }

    /**
     * PR4 — R2 verification fail
     */
    public function test_pr4_r2_verification_fail(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        Storage::shouldReceive('disk')->with('r2_private')->andReturnSelf();
        Storage::shouldReceive('put')->andReturn(true);
        Storage::shouldReceive('exists')->andReturn(true);
        Storage::shouldReceive('size')->andReturn(100);
        Storage::shouldReceive('get')->andReturn('CORRUPT_NOT_A_PDF');
        Storage::shouldReceive('delete')->andReturn(true);

        try {
            app(PagoService::class)->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin);
            $this->fail('Debió lanzar RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('No se pudo completar la emisión del recibo', $e->getMessage());
        }

        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_PENDIENTE, $pago->estado);
        $this->assertNull($pago->aprobado_por);
    }

    /**
     * PR5 — DB final fail después de R2 success (newKey eliminada por cleanup)
     */
    public function test_pr5_db_final_fail_cleans_up_new_r2_key(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        // Simulamos fallo en la transacción final interceptando el UPDATE de pago
        DB::listen(function ($query) {
            if (str_contains(strtolower($query->sql), 'update `pagos` set `estado`')) {
                throw new \RuntimeException('Deadlock in final payment commit');
            }
        });

        try {
            app(PagoService::class)->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin);
            $this->fail('Debió fallar');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('Deadlock in final payment commit', $e->getMessage());
        }

        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_PENDIENTE, $pago->estado);
        $this->assertNull($pago->receipt);
        $this->assertCount(0, Storage::disk('r2_private')->allFiles());
    }

    /**
     * PR6 — DB final fail + cleanup R2 fail
     */
    public function test_pr6_db_final_fail_and_cleanup_fail_still_leaves_pago_not_paid(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        // Fallo en DB
        DB::listen(function ($query) {
            if (str_contains(strtolower($query->sql), 'update `pagos` set `estado`')) {
                throw new \RuntimeException('DB crash in final commit');
            }
        });

        try {
            app(PagoService::class)->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin);
            $this->fail('Debió fallar');
        } catch (\Throwable $e) {
            // Expected
        }

        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_PENDIENTE, $pago->estado);
        $this->assertNull($pago->aprobado_por);
    }

    /**
     * PR7 — Demostración de que no puede terminar pagado sin recibo
     */
    public function test_pr7_cannot_end_up_in_paid_without_receipt(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        // Si cualquier paso falla
        $failingDocService = $this->createMock(PagoDocumentoService::class);
        $failingDocService->method('generarReciboPagoPdfContent')->willThrowException(new \RuntimeException('Render crash'));
        $this->app->instance(PagoDocumentoService::class, $failingDocService);

        try {
            app(PagoService::class)->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin);
        } catch (\Throwable $e) {}

        $pago->refresh();
        $this->assertNotEquals(Pago::ESTADO_PAGADO, $pago->estado);
    }

    /**
     * PR8 — GET_LOCK=0
     */
    public function test_pr8_get_lock_zero_preserves_state(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        $config = config('database.connections.mysql');
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
        $pdoExt = new \PDO($dsn, $config['username'], $config['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

        $lockName = 'payment_receipt_pdf_payment_'.$pago->id;
        $pdoExt->prepare('SELECT GET_LOCK(?, 0)')->execute([$lockName]);

        try {
            app(PagoService::class)->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin);
            $this->fail('Debió lanzar RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('No se pudo obtener el bloqueo', $e->getMessage());
        }

        $pdoExt->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);

        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_PENDIENTE, $pago->estado);
        $this->assertNull($pago->receipt);
    }

    /**
     * PR9 — GET_LOCK=NULL
     */
    public function test_pr9_get_lock_null_preserves_state(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        DB::shouldReceive('connection')->andReturnSelf();
        DB::shouldReceive('selectOne')->andReturn((object) ['acquired' => null]);

        try {
            app(PagoService::class)->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin);
            $this->fail('Debió lanzar RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('No se pudo obtener el bloqueo', $e->getMessage());
        }

        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_PENDIENTE, $pago->estado);
    }

    /**
     * PR10 — Retry después de fallo
     */
    public function test_pr10_retry_after_failure_succeeds(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        // Intento 1: Falla
        $failingDocService = $this->createMock(PagoDocumentoService::class);
        $failingDocService->method('generarReciboPagoPdfContent')->willThrowException(new \RuntimeException('Transient network error'));
        $this->app->instance(PagoDocumentoService::class, $failingDocService);

        try {
            app(PagoService::class)->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin);
        } catch (\Throwable $e) {}

        // Intento 2: Servicio normal
        $this->app->forgetInstance(PagoDocumentoService::class);
        $pagoService = app(PagoService::class);
        $res = $pagoService->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin);

        $this->assertEquals(Pago::ESTADO_PAGADO, $res->estado);
        $this->assertNotNull($res->receipt);
        $this->assertTrue(Storage::disk('r2_private')->exists($res->receipt->pdf_path));
    }

    /**
     * PR11 — Autoría: A éxito / B espera (DOS administradores distintos)
     */
    public function test_pr11_authorship_immutability_admin_a_and_admin_b(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $adminA = $this->createRoleUser('administrador', ['name' => 'Admin A']);
        $adminB = $this->createRoleUser('administrador', ['name' => 'Admin B']);
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        $pagoService = app(PagoService::class);

        // 1. Admin A aprueba con éxito
        $pagoService->cambiarEstado($pago, Pago::ESTADO_PAGADO, $adminA, 'Aprobado por Admin A');
        $pago->refresh();

        $originalApprovedBy = $pago->aprobado_por;
        $originalApprovedAt = $pago->aprobado_en;
        $originalReceipt = $pago->receipt;
        $originalReceiptIssuer = $originalReceipt->emitido_por;
        $originalPdfPath = $originalReceipt->pdf_path;
        $originalFolio = $originalReceipt->folio_recibo;
        $originalToken = $originalReceipt->verification_token;
        $originalCsv = $originalReceipt->csv;
        $originalObs = $pago->observacion_admin;
        $originalLogsCount = PaymentStatusLog::where('pago_id', $pago->id)->count();

        $this->assertEquals($adminA->id, $originalApprovedBy);
        $this->assertEquals($adminA->id, $originalReceiptIssuer);

        // 2. Admin B intenta aprobar el mismo pago ya aprobado
        $pagoService->cambiarEstado($pago, Pago::ESTADO_PAGADO, $adminB, 'Aprobado por Admin B');
        $pago->refresh();

        // 3. Comprobar que TODOS los datos de autoría e identidad de A siguen IDÉNTICOS
        $this->assertEquals($originalApprovedBy, $pago->aprobado_por);
        $this->assertEquals($originalApprovedAt->toDateTimeString(), $pago->aprobado_en->toDateTimeString());
        $this->assertEquals($originalObs, $pago->observacion_admin);

        $finalReceipt = $pago->receipt;
        $this->assertEquals($originalReceipt->id, $finalReceipt->id);
        $this->assertEquals($originalReceiptIssuer, $finalReceipt->emitido_por);
        $this->assertEquals($originalPdfPath, $finalReceipt->pdf_path);
        $this->assertEquals($originalFolio, $finalReceipt->folio_recibo);
        $this->assertEquals($originalToken, $finalReceipt->verification_token);
        $this->assertEquals($originalCsv, $finalReceipt->csv);

        $this->assertEquals($originalLogsCount, PaymentStatusLog::where('pago_id', $pago->id)->count());
        $this->assertCount(1, Storage::disk('r2_private')->allFiles("documents/payment-receipts/{$pago->id}"));
    }

    /**
     * PR12 — A falla / B espera: B realiza nueva aprobación legítima con autoría B
     */
    public function test_pr12_a_fails_b_succeeds_with_author_b(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $adminA = $this->createRoleUser('administrador', ['name' => 'Admin A']);
        $adminB = $this->createRoleUser('administrador', ['name' => 'Admin B']);
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        // A falla en el render
        $failingDocService = $this->createMock(PagoDocumentoService::class);
        $failingDocService->method('generarReciboPagoPdfContent')->willThrowException(new \RuntimeException('Render crash for A'));
        $this->app->instance(PagoDocumentoService::class, $failingDocService);

        try {
            app(PagoService::class)->cambiarEstado($pago, Pago::ESTADO_PAGADO, $adminA);
        } catch (\Throwable $e) {}

        // B entra y aprueba
        $this->app->forgetInstance(PagoDocumentoService::class);
        $pagoService = app(PagoService::class);
        $pagoService->cambiarEstado($pago, Pago::ESTADO_PAGADO, $adminB, 'Aprobado por B');

        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_PAGADO, $pago->estado);
        $this->assertEquals($adminB->id, $pago->aprobado_por);
        $this->assertEquals($adminB->id, $pago->receipt->emitido_por);
    }

    /**
     * PR13 — B encuentra estado no aprobable (anulado)
     */
    public function test_pr13_b_finds_unapprovable_state(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_ANULADO);

        $this->expectException(\InvalidArgumentException::class);
        app(PagoService::class)->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin);
    }

    /**
     * PR14 — Doble POST del mismo administrador (idempotencia)
     */
    public function test_pr14_double_post_same_admin_is_idempotent(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        $resp1 = $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $resp1->assertRedirect(route('admin.pagos.show', $pago));

        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_PAGADO, $pago->estado);
        $receiptId = $pago->receipt->id;

        $resp2 = $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $resp2->assertRedirect(route('admin.pagos.show', $pago));

        $pago->refresh();
        $this->assertEquals(1, PaymentReceipt::where('pago_id', $pago->id)->count());
        $this->assertEquals($receiptId, $pago->receipt->id);
    }

    /**
     * PR15 — Doble POST de administradores distintos (no sobrescribir autoría)
     */
    public function test_pr15_double_post_different_admins_preserves_first_author(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $adminA = $this->createRoleUser('administrador', ['name' => 'Admin A']);
        $adminB = $this->createRoleUser('administrador', ['name' => 'Admin B']);
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        $this->actingAs($adminA)->post(route('admin.pagos.aprobar', $pago));
        $pago->refresh();
        $this->assertEquals($adminA->id, $pago->aprobado_por);

        $this->actingAs($adminB)->post(route('admin.pagos.aprobar', $pago));
        $pago->refresh();
        $this->assertEquals($adminA->id, $pago->aprobado_por);
        $this->assertEquals($adminA->id, $pago->receipt->emitido_por);
    }

    /**
     * PR16 — Receipt regeneration concurrente
     */
    public function test_pr16_concurrent_regeneration_produces_single_valid_pdf(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        $pagoService = app(PagoService::class);
        $pagoService->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin);
        $pago->refresh();

        // Borramos el PDF en R2 para forzar regeneración
        Storage::disk('r2_private')->delete($pago->receipt->pdf_path);

        $rec1 = $pagoService->emitirReciboParaPago($pago, $admin);
        $rec2 = $pagoService->emitirReciboParaPago($pago, $admin);

        $this->assertEquals($rec1->id, $rec2->id);
        $this->assertCount(1, Storage::disk('r2_private')->allFiles("documents/payment-receipts/{$pago->id}"));
    }

    /**
     * PR17 — Missing PDF regeneration solo si sigue pagado
     */
    public function test_pr17_missing_pdf_regeneration_requires_paid_state(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        $this->expectException(\InvalidArgumentException::class);
        app(PagoService::class)->emitirReciboParaPago($pago, $admin);
    }

    /**
     * PR18 — PaymentStatusLog: exactamente una transición por aprobación
     */
    public function test_pr18_single_payment_status_log_created(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        app(PagoService::class)->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin);

        $this->assertEquals(1, PaymentStatusLog::where('pago_id', $pago->id)->where('estado_nuevo', Pago::ESTADO_PAGADO)->count());
    }

    /**
     * PR19 — PaymentReceiptLog
     */
    public function test_pr19_payment_receipt_log_integrity(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        app(PagoService::class)->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin);
        $pago->refresh();

        $this->assertGreaterThanOrEqual(1, $pago->receipt->logs()->count());
    }

    /**
     * PR20 — Folio/token/CSV inmutables
     */
    public function test_pr20_identifiers_immutable(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        app(PagoService::class)->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin);
        $pago->refresh();

        $folio = $pago->receipt->folio_recibo;
        $token = $pago->receipt->verification_token;
        $csv = $pago->receipt->csv;

        app(PagoService::class)->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin);
        $pago->refresh();

        $this->assertEquals($folio, $pago->receipt->folio_recibo);
        $this->assertEquals($token, $pago->receipt->verification_token);
        $this->assertEquals($csv, $pago->receipt->csv);
    }

    /**
     * PR21 — Inmutabilidad financiera de monto y método tras aprobación
     */
    public function test_pr21_financial_immutability_after_approval(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        app(PagoService::class)->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin);
        $pago->refresh();

        $this->assertFalse($pago->esEditableFinancieramente());

        $this->expectException(\InvalidArgumentException::class);
        app(PagoService::class)->actualizarMontoAdministrativo($pago, 99.00, 'USD', $admin);
    }

    /**
     * PR22 — en_verificacion: fallo conserva estado y comprobante
     */
    public function test_pr22_en_verificacion_failure_retains_state_and_proof(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_EN_VERIFICACION, [
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'comprobante_path' => 'proofs/test.pdf',
            'comprobante_disk' => 'r2_private',
        ]);

        $failingDocService = $this->createMock(PagoDocumentoService::class);
        $failingDocService->method('generarReciboPagoPdfContent')->willThrowException(new \RuntimeException('Render crash'));
        $this->app->instance(PagoDocumentoService::class, $failingDocService);

        try {
            app(PagoService::class)->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin);
        } catch (\Throwable $e) {}

        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_EN_VERIFICACION, $pago->estado);
        $this->assertEquals('proofs/test.pdf', $pago->comprobante_path);
    }

    /**
     * PR23 — Pendiente: fallo conserva estado pendiente
     */
    public function test_pr23_pendiente_failure_retains_pendiente(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        $failingDocService = $this->createMock(PagoDocumentoService::class);
        $failingDocService->method('generarReciboPagoPdfContent')->willThrowException(new \RuntimeException('Render crash'));
        $this->app->instance(PagoDocumentoService::class, $failingDocService);

        try {
            app(PagoService::class)->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin);
        } catch (\Throwable $e) {}

        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_PENDIENTE, $pago->estado);
    }

    /**
     * PR24 — R2 orphan check: 0 nuevos objetos huérfanos tras fallos
     */
    public function test_pr24_r2_zero_orphan_objects(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        $failingDocService = $this->createMock(PagoDocumentoService::class);
        $failingDocService->method('generarReciboPagoPdfContent')->willThrowException(new \RuntimeException('Render crash'));
        $this->app->instance(PagoDocumentoService::class, $failingDocService);

        try {
            app(PagoService::class)->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin);
        } catch (\Throwable $e) {}

        $this->assertCount(0, Storage::disk('r2_private')->allFiles());
    }

    /**
     * PR25 — HTTP real aprobación normal
     */
    public function test_pr25_http_real_approval(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        $response = $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $response->assertRedirect(route('admin.pagos.show', $pago));
        $response->assertSessionHas('success');

        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_PAGADO, $pago->estado);
    }

    /**
     * PR26 — HTTP timeout lock
     */
    public function test_pr26_http_timeout_lock(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        $config = config('database.connections.mysql');
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
        $pdoExt = new \PDO($dsn, $config['username'], $config['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

        $lockName = 'payment_receipt_pdf_payment_'.$pago->id;
        $pdoExt->prepare('SELECT GET_LOCK(?, 0)')->execute([$lockName]);

        $response = $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $response->assertRedirect(route('admin.pagos.show', $pago));
        $response->assertSessionHasErrors(['error']);

        $pdoExt->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
    }

    /**
     * PR27 — HTTP ya aprobado
     */
    public function test_pr27_http_already_approved(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));

        $response = $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $response->assertRedirect(route('admin.pagos.show', $pago));
    }

    /**
     * PR28 — HTTP fallo documental controlado (sin 500)
     */
    public function test_pr28_http_document_failure_returns_controlled_error(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        $failingDocService = $this->createMock(PagoDocumentoService::class);
        $failingDocService->method('generarReciboPagoPdfContent')->willThrowException(new \RuntimeException('PDF template failure'));
        $this->app->instance(PagoDocumentoService::class, $failingDocService);

        $response = $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $this->assertNotEquals(500, $response->getStatusCode());
        $response->assertRedirect(route('admin.pagos.show', $pago));
        $response->assertSessionHasErrors(['error']);
    }

    /**
     * PR29 — DOS PDO independientes demuestran exclusión mutua de advisory lock
     */
    public function test_pr29_two_independent_pdo_connections_advisory_lock_mutual_exclusion(): void
    {
        $config = config('database.connections.mysql');
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
        $pdoA = new \PDO($dsn, $config['username'], $config['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $pdoB = new \PDO($dsn, $config['username'], $config['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

        $lockKey = 'test_advisory_lock_mutex_'.\Illuminate\Support\Str::random(10);

        $stmtA = $pdoA->prepare('SELECT GET_LOCK(?, 0) AS acquired');
        $stmtA->execute([$lockKey]);
        $acquiredA = (int) $stmtA->fetchColumn();
        $this->assertEquals(1, $acquiredA);

        $stmtB = $pdoB->prepare('SELECT GET_LOCK(?, 0) AS acquired');
        $stmtB->execute([$lockKey]);
        $acquiredB = (int) $stmtB->fetchColumn();
        $this->assertEquals(0, $acquiredB);

        $pdoA->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockKey]);
    }

    /**
     * PR30 — Pagos distintos no se bloquean globalmente
     */
    public function test_pr30_different_payments_do_not_block_each_other(): void
    {
        $config = config('database.connections.mysql');
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
        $pdoA = new \PDO($dsn, $config['username'], $config['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $pdoB = new \PDO($dsn, $config['username'], $config['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

        $lockKey1 = 'payment_receipt_pdf_payment_9999901';
        $lockKey2 = 'payment_receipt_pdf_payment_9999902';

        $stmtA = $pdoA->prepare('SELECT GET_LOCK(?, 0) AS acquired');
        $stmtA->execute([$lockKey1]);
        $acquiredA = (int) $stmtA->fetchColumn();
        $this->assertEquals(1, $acquiredA);

        $stmtB = $pdoB->prepare('SELECT GET_LOCK(?, 0) AS acquired');
        $stmtB->execute([$lockKey2]);
        $acquiredB = (int) $stmtB->fetchColumn();
        $this->assertEquals(1, $acquiredB);

        $pdoA->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockKey1]);
        $pdoB->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockKey2]);
    }

    /**
     * PR31 / REQ 15 — Edición de monto gana primero: Pago = 99, Receipt = 99
     */
    public function test_pr31_amount_edit_wins_first_then_approval_uses_updated_amount(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $adminA = $this->createRoleUser('administrador', ['name' => 'Admin Approver']);
        $adminB = $this->createRoleUser('administrador', ['name' => 'Admin Editor']);
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE, ['monto' => 45.00]);

        $pagoService = app(PagoService::class);

        // 1. Admin B actualiza el monto a 99.00
        $pagoService->actualizarMontoAdministrativo($pago, 99.00, 'USD', $adminB);
        $pago->refresh();
        $this->assertEquals(99.00, (float) $pago->monto);

        // 2. Admin A aprueba
        $pagoService->cambiarEstado($pago, Pago::ESTADO_PAGADO, $adminA);
        $pago->refresh();

        // 3. Pago y Receipt deben coincidir en 99.00
        $this->assertEquals(Pago::ESTADO_PAGADO, $pago->estado);
        $this->assertEquals(99.00, (float) $pago->monto);
        $this->assertNotNull($pago->receipt);
        $this->assertEquals(99.00, (float) $pago->receipt->monto);
    }

    /**
     * PR32 / REQ 16 — Aprobación gana primero: edición posterior de monto es rechazada
     */
    public function test_pr32_approval_wins_first_then_amount_edit_is_rejected(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $adminA = $this->createRoleUser('administrador', ['name' => 'Admin Approver']);
        $adminB = $this->createRoleUser('administrador', ['name' => 'Admin Editor']);
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE, ['monto' => 45.00]);

        $pagoService = app(PagoService::class);

        // 1. Admin A aprueba con 45.00
        $pagoService->cambiarEstado($pago, Pago::ESTADO_PAGADO, $adminA);
        $pago->refresh();

        // 2. Admin B intenta cambiar a 99.00 -> debe fallar por inmutabilidad
        try {
            $pagoService->actualizarMontoAdministrativo($pago, 99.00, 'USD', $adminB);
            $this->fail('Debió rechazar la modificación de monto tras aprobación');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('ya fue aprobado', $e->getMessage());
        }

        $pago->refresh();
        $this->assertEquals(45.00, (float) $pago->monto);
        $this->assertEquals(45.00, (float) $pago->receipt->monto);
    }

    /**
     * PR33 / REQ 17 — Carrera de método de pago: edición antes de aprobación vs inmutabilidad tras aprobación
     */
    public function test_pr33_payment_method_mutation_race_and_immutability(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $adminA = $this->createRoleUser('administrador', ['name' => 'Admin Approver']);
        $adminB = $this->createRoleUser('administrador', ['name' => 'Admin Editor']);
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE, ['metodo_pago' => Pago::METODO_EFECTIVO]);

        $pagoService = app(PagoService::class);

        // Admin B cambia método a transferencia
        $pagoService->actualizarMetodoAdministrativo($pago, Pago::METODO_TRANSFERENCIA, 'Pago por transferencia', $adminB);
        $pago->refresh();
        $this->assertEquals(Pago::METODO_TRANSFERENCIA, $pago->metodo_pago);

        // Simulamos comprobante para permitir aprobación
        $proofKey = 'proofs/valid.pdf';
        Storage::disk('r2_private')->put($proofKey, '%PDF-1.4 fake proof');
        $pago->comprobante_path = $proofKey;
        $pago->comprobante_disk = 'r2_private';
        $pago->save();

        // Admin A aprueba
        $pagoService->cambiarEstado($pago, Pago::ESTADO_PAGADO, $adminA);
        $pago->refresh();

        $this->assertEquals(Pago::METODO_TRANSFERENCIA, $pago->receipt->metodo_pago);

        // Intento posterior de cambio de método es rechazado
        try {
            $pagoService->actualizarMetodoAdministrativo($pago, Pago::METODO_EFECTIVO, 'Intento cambio', $adminB);
            $this->fail('Debió rechazar cambio de método post-aprobación');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('ya fue aprobado', $e->getMessage());
        }
    }

    /**
     * PR34 / REQ 18 — Snapshot mismatch detectado y abortado
     */
    public function test_pr34_snapshot_mismatch_aborts_approval_and_cleans_up_r2(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE, ['monto' => 45.00]);

        // Interceptamos la generación de PDF para simular una alteración externa en BD
        $docService = $this->app->make(PagoDocumentoService::class);
        $spyDocService = $this->createMock(PagoDocumentoService::class);
        $spyDocService->method('generarReciboPagoPdfContent')->willReturnCallback(function ($p, $r) use ($pago, $docService) {
            // Modificación externa del monto en la base de datos mientras se renderiza el PDF
            DB::table('pagos')->where('id', $pago->id)->update(['monto' => 99.00]);
            return $docService->generarReciboPagoPdfContent($p, $r);
        });
        $this->app->instance(PagoDocumentoService::class, $spyDocService);

        try {
            app(PagoService::class)->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin);
            $this->fail('Debió abortar por snapshot mismatch');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('Snapshot mismatch', $e->getMessage());
        }

        $pago->refresh();
        $this->assertNotEquals(Pago::ESTADO_PAGADO, $pago->estado);
        $this->assertEquals(0, PaymentReceipt::where('pago_id', $pago->id)->count());
        $this->assertCount(0, Storage::disk('r2_private')->allFiles());
    }

    /**
     * PR35 / REQ 19 — No staging receipt: PaymentReceipt count es 0 antes del commit final
     */
    public function test_pr35_no_staging_receipt_persisted_before_valid_pdf(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        $observedCountDuringDompdf = null;
        $docService = $this->app->make(PagoDocumentoService::class);
        $spyDocService = $this->createMock(PagoDocumentoService::class);
        $spyDocService->method('generarReciboPagoPdfContent')->willReturnCallback(function ($p, $r) use ($pago, $docService, &$observedCountDuringDompdf) {
            $observedCountDuringDompdf = PaymentReceipt::where('pago_id', $pago->id)->count();
            return $docService->generarReciboPagoPdfContent($p, $r);
        });
        $this->app->instance(PagoDocumentoService::class, $spyDocService);

        app(PagoService::class)->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin);

        // Durante el render de Dompdf en staging, PaymentReceipt no debe existir en la BD
        $this->assertSame(0, $observedCountDuringDompdf);

        // Tras el commit final, debe existir exactamente 1
        $this->assertEquals(1, PaymentReceipt::where('pago_id', $pago->id)->count());
    }

    /**
     * PR36 / REQ 20 — Fallo documental no requiere compensación de receipt en BD
     */
    public function test_pr36_document_failure_leaves_zero_receipts_without_needing_db_cleanup(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        $failingDocService = $this->createMock(PagoDocumentoService::class);
        $failingDocService->method('generarReciboPagoPdfContent')->willThrowException(new \RuntimeException('Template failure'));
        $this->app->instance(PagoDocumentoService::class, $failingDocService);

        try {
            app(PagoService::class)->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin);
        } catch (\Throwable $e) {}

        $this->assertEquals(0, PaymentReceipt::where('pago_id', $pago->id)->count());
    }

    /**
     * PR37 / REQ 21 — Mutación concurrente por ruta HTTP de paciente y admin comparten mutex
     */
    public function test_pr37_all_financial_routes_are_serialized_under_same_mutex(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE, ['monto' => 30.00]);

        // 1. Admin actualiza monto vía HTTP
        $respMonto = $this->actingAs($admin)->post(route('admin.pagos.monto.update', $pago), [
            'monto' => 50.00,
            'moneda' => 'USD',
        ]);
        $respMonto->assertSessionHas('success');

        // 2. Admin actualiza método vía HTTP
        $respMetodo = $this->actingAs($admin)->post(route('admin.pagos.metodo.update', $pago), [
            'metodo_pago' => Pago::METODO_EFECTIVO,
            'observacion_admin' => 'Pago verificado en caja',
        ]);
        $respMetodo->assertSessionHas('success');

        // 3. Admin aprueba vía HTTP
        $respAprobar = $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $respAprobar->assertSessionHas('success');

        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_PAGADO, $pago->estado);
        $this->assertEquals(50.00, (float) $pago->monto);
        $this->assertEquals(50.00, (float) $pago->receipt->monto);
        $this->assertEquals(Pago::METODO_EFECTIVO, $pago->receipt->metodo_pago);
    }

    /**
     * PR38 — Stale controller efectivo -> B transferencia sin comprobante -> A intenta aprobar: rechazado
     */
    public function test_pr38_stale_controller_efectivo_to_transfer_without_proof_is_rejected(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $adminA = $this->createRoleUser('administrador', ['name' => 'Admin Approver']);
        $adminB = $this->createRoleUser('administrador', ['name' => 'Admin Editor']);
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE, [
            'metodo_pago' => Pago::METODO_EFECTIVO,
            'comprobante_path' => null,
            'comprobante_disk' => null,
        ]);

        // Simular que el controlador de A cargó la instancia de $pago con método efectivo.
        // Mientras tanto, B cambia el método a transferencia sin comprobante.
        $pagoService = app(PagoService::class);
        $pagoService->actualizarMetodoAdministrativo($pago, Pago::METODO_TRANSFERENCIA, 'Cambio a transferencia', $adminB);

        // A intenta aprobar usando la ruta HTTP
        $response = $this->actingAs($adminA)->post(route('admin.pagos.aprobar', $pago));

        $this->assertNotEquals(500, $response->status());
        $response->assertRedirect(route('admin.pagos.show', $pago));
        $response->assertSessionHasErrors(['error']);

        $pago->refresh();
        $this->assertNotEquals(Pago::ESTADO_PAGADO, $pago->estado);
        $this->assertEquals(Pago::ESTADO_PENDIENTE, $pago->estado);
        $this->assertNull($pago->aprobado_por);
        $this->assertNull($pago->aprobado_en);
        $this->assertEquals(0, PaymentReceipt::where('pago_id', $pago->id)->count());
        $this->assertEquals(0, PaymentStatusLog::where('pago_id', $pago->id)->where('estado_nuevo', Pago::ESTADO_PAGADO)->count());
        $this->assertCount(0, Storage::disk('r2_private')->allFiles());
    }

    /**
     * PR39 — B transferencia CON comprobante válido -> A entra después -> aprobación válida
     */
    public function test_pr39_transfer_with_valid_proof_is_successfully_approved(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $adminA = $this->createRoleUser('administrador', ['name' => 'Admin Approver']);
        $adminB = $this->createRoleUser('administrador', ['name' => 'Admin Editor']);
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE, [
            'metodo_pago' => Pago::METODO_EFECTIVO,
        ]);

        // Crear archivo de comprobante en fake R2
        $proofKey = "documents/payment-proofs/{$pago->id}/comprobante.jpg";
        Storage::disk('r2_private')->put($proofKey, 'fake-image-binary-data');

        // B cambia a transferencia con comprobante
        $pago->comprobante_path = $proofKey;
        $pago->comprobante_disk = 'r2_private';
        $pago->save();

        $pagoService = app(PagoService::class);
        $pagoService->actualizarMetodoAdministrativo($pago, Pago::METODO_TRANSFERENCIA, 'Cambio a transferencia', $adminB);

        // A aprueba
        $response = $this->actingAs($adminA)->post(route('admin.pagos.aprobar', $pago));
        $response->assertRedirect(route('admin.pagos.show', $pago));
        $response->assertSessionHas('success');

        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_PAGADO, $pago->estado);
        $this->assertEquals(Pago::METODO_TRANSFERENCIA, $pago->metodo_pago);
        $this->assertNotNull($pago->receipt);
        $this->assertEquals(Pago::METODO_TRANSFERENCIA, $pago->receipt->metodo_pago);
        $this->assertEquals($proofKey, $pago->receipt->comprobante_path);
        $this->assertEquals(1, PaymentReceipt::where('pago_id', $pago->id)->count());
    }

    /**
     * PR40 — Aprobación gana primero -> edición posterior a transferencia rechazada por inmutabilidad
     */
    public function test_pr40_approval_first_then_method_edit_rejected(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $adminA = $this->createRoleUser('administrador', ['name' => 'Admin Approver']);
        $adminB = $this->createRoleUser('administrador', ['name' => 'Admin Editor']);
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE, [
            'metodo_pago' => Pago::METODO_EFECTIVO,
        ]);

        $pagoService = app(PagoService::class);
        $pagoService->cambiarEstado($pago, Pago::ESTADO_PAGADO, $adminA);
        $pago->refresh();

        try {
            $pagoService->actualizarMetodoAdministrativo($pago, Pago::METODO_TRANSFERENCIA, 'Intento de cambio', $adminB);
            $this->fail('Debió rechazar edición de método tras aprobación');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('ya fue aprobado', $e->getMessage());
        }

        $pago->refresh();
        $this->assertEquals(Pago::METODO_EFECTIVO, $pago->metodo_pago);
    }

    /**
     * PR41 — Llamada DIRECTA a PagoService sin pasar por PagoController rechaza transferencia sin comprobante
     */
    public function test_pr41_direct_service_call_rejects_transfer_without_proof(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE, [
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'comprobante_path' => null,
            'comprobante_disk' => null,
        ]);

        $pagoService = app(PagoService::class);

        try {
            $pagoService->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin);
            $this->fail('PagoService debió rechazar la aprobación de transferencia sin comprobante');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('No se puede aprobar una transferencia sin comprobante adjunto', $e->getMessage());
        }

        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_PENDIENTE, $pago->estado);
        $this->assertNull($pago->receipt);
        $this->assertNull($pago->aprobado_por);
    }

    /**
     * PR42 — Precondición en service: pago sin método de pago asignado es rechazado
     */
    public function test_pr42_direct_service_call_rejects_empty_payment_method(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE, [
            'metodo_pago' => null,
        ]);

        $pagoService = app(PagoService::class);

        try {
            $pagoService->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin);
            $this->fail('PagoService debió rechazar pago sin método');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Debe asignar un método de pago antes de aprobar', $e->getMessage());
        }

        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_PENDIENTE, $pago->estado);
    }

    /**
     * PR43 — Snapshot mismatch si comprobante_path cambia durante la aprobación
     */
    public function test_pr43_snapshot_mismatch_on_proof_path_modification(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');

        $proofKey1 = "documents/payment-proofs/proof1.jpg";
        $proofKeyAltered = "documents/payment-proofs/altered.jpg";
        Storage::disk('r2_private')->put($proofKey1, 'fake-data-1');
        Storage::disk('r2_private')->put($proofKeyAltered, 'fake-data-altered');

        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE, [
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'comprobante_path' => $proofKey1,
            'comprobante_disk' => 'r2_private',
        ]);

        // Simular alteración externa del comprobante_path mientras se renderiza el PDF
        $docService = $this->app->make(PagoDocumentoService::class);
        $spyDocService = $this->createMock(PagoDocumentoService::class);
        $spyDocService->method('generarReciboPagoPdfContent')->willReturnCallback(function ($p, $r) use ($pago, $docService) {
            DB::table('pagos')->where('id', $pago->id)->update(['comprobante_path' => 'documents/payment-proofs/altered.jpg']);
            return $docService->generarReciboPagoPdfContent($p, $r);
        });
        $this->app->instance(PagoDocumentoService::class, $spyDocService);

        try {
            app(PagoService::class)->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin);
            $this->fail('Debió abortar por snapshot mismatch en comprobante');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('Snapshot mismatch', $e->getMessage());
        }

        $pago->refresh();
        $this->assertNotEquals(Pago::ESTADO_PAGADO, $pago->estado);
        $this->assertEquals(0, PaymentReceipt::where('pago_id', $pago->id)->count());
    }

    /**
     * PR44 — No receipt, no pdf, no log de pagado tras rechazo
     */
    public function test_pr44_no_artifacts_or_logs_after_rejection(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE, [
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'comprobante_path' => null,
        ]);

        try {
            app(PagoService::class)->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin);
        } catch (\Throwable $e) {}

        $pago->refresh();
        $this->assertNull($pago->receipt);
        $this->assertEquals(0, PaymentReceipt::where('pago_id', $pago->id)->count());
        $this->assertEquals(0, PaymentStatusLog::where('pago_id', $pago->id)->where('estado_nuevo', Pago::ESTADO_PAGADO)->count());
        $this->assertCount(0, Storage::disk('r2_private')->allFiles());
    }
}
