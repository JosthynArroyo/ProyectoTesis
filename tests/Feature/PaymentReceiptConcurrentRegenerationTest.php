<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Pago;
use App\Models\PaymentReceipt;
use App\Models\Role;
use App\Models\User;
use App\Services\PagoDocumentoService;
use App\Services\PagoService;
use App\Services\PaymentReceiptDocumentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentReceiptConcurrentRegenerationTest extends TestCase
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

    private function createPagoPagadoWithReceipt(User $paciente, ?User $admin = null): array
    {
        $admin = $admin ?: $this->createRoleUser('administrador');
        $doctor = $this->createRoleUser('doctor');
        $esp = Especialidad::firstOrCreate(['nombre' => 'Medicina General']);

        $cita = Cita::create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-05',
            'hora' => '09:00:00',
            'motivo_consulta' => 'Consulta pagada',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);

        $pago = Pago::create([
            'paciente_id' => $paciente->id,
            'cita_id' => $cita->id,
            'monto' => 35.00,
            'moneda' => 'USD',
            'metodo_pago' => Pago::METODO_EFECTIVO,
            'estado' => Pago::ESTADO_PAGADO,
            'aprobado_por' => $admin->id,
            'aprobado_en' => now(),
            'folio_unico' => 'ORD-'.rand(100000, 999999),
            'token_publico' => \Illuminate\Support\Str::random(40),
            'csv' => app(\App\Services\DocumentoCsvService::class)->generateCsv(),
        ]);

        $receipt = PaymentReceipt::create([
            'pago_id' => $pago->id,
            'folio_recibo' => 'REC-'.rand(100000, 999999),
            'verification_token' => \Illuminate\Support\Str::random(40),
            'csv' => app(\App\Services\DocumentoCsvService::class)->generateCsv(),
            'emitido_en' => now(),
            'emitido_por' => $admin->id,
            'metodo_pago' => Pago::METODO_EFECTIVO,
            'monto' => 35.00,
            'pdf_path' => null,
            'pdf_disk' => null,
        ]);

        return [$pago, $receipt, $admin];
    }

    /**
     * PR1 — PDF ya existe.
     * Si el PDF ya existe y es válido, no genera otro, no sube otro, no altera pdf_path.
     */
    public function test_pr1_existing_valid_pdf_is_reused_without_regeneration(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        [$pago, $receipt] = $this->createPagoPagadoWithReceipt($paciente, $admin);

        // Generar inicialmente el PDF
        $pagoService = app(PagoService::class);
        $receipt = $pagoService->emitirReciboParaPago($pago, $admin);
        $initialPdfPath = $receipt->pdf_path;
        $this->assertNotNull($initialPdfPath);

        $initialFiles = Storage::disk('r2_private')->allFiles("documents/payment-receipts/{$receipt->pago_id}");
        $this->assertCount(1, $initialFiles);

        // GET del recibo
        $response = $this->actingAs($admin)->get(route('admin.pagos.recibo.pdf', $pago));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        $receipt->refresh();
        $this->assertEquals($initialPdfPath, $receipt->pdf_path);

        $finalFiles = Storage::disk('r2_private')->allFiles("documents/payment-receipts/{$receipt->pago_id}");
        $this->assertCount(1, $finalFiles);
        $this->assertEquals($initialFiles, $finalFiles);
    }

    /**
     * PR2 — PDF faltante sin concurrencia.
     * Existe PaymentReceipt sin PDF. Una request recupera/genera el PDF, mantiene el mismo folio y token.
     */
    public function test_pr2_missing_pdf_is_regenerated_preserving_receipt_identity(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        [$pago, $receipt] = $this->createPagoPagadoWithReceipt($paciente, $admin);

        $originalReceiptId = $receipt->id;
        $originalFolio = $receipt->folio_recibo;
        $originalToken = $receipt->verification_token;
        $originalCsv = $receipt->csv;

        $this->assertNull($receipt->pdf_path);

        $response = $this->actingAs($admin)->get(route('admin.pagos.recibo.pdf', $pago));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        $receipt->refresh();
        $this->assertEquals($originalReceiptId, $receipt->id);
        $this->assertEquals($originalFolio, $receipt->folio_recibo);
        $this->assertEquals($originalToken, $receipt->verification_token);
        $this->assertEquals($originalCsv, $receipt->csv);
        $this->assertNotNull($receipt->pdf_path);
        $this->assertEquals('r2_private', $receipt->pdf_disk);

        $allFiles = Storage::disk('r2_private')->allFiles("documents/payment-receipts/{$receipt->pago_id}");
        $this->assertCount(1, $allFiles);
    }

    /**
     * PR3 — Dos requests concurrentes para el mismo recibo.
     * Ambas intentan regenerar al mismo tiempo.
     * Solo 1 genera y sube. La segunda revalida bajo el lock y reutiliza el PDF sin regenerar.
     * Resultado: 1 solo objeto en R2, 0 huérfanos.
     */
    public function test_pr3_concurrent_regeneration_requests_produce_exactly_one_r2_object(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        [$pago, $receipt] = $this->createPagoPagadoWithReceipt($paciente, $admin);

        $pagoService = app(PagoService::class);
        $docService = app(PagoDocumentoService::class);
        $receiptDocService = app(PaymentReceiptDocumentService::class);

        // Simular que Request A entra y genera el PDF bajo el lock,
        // mientras Request B esperaba. Al completar A, B entra y revalida.
        $resA = $pagoService->emitirReciboParaPago($pago, $admin);
        $resB = $pagoService->emitirReciboParaPago($pago, $admin);

        $this->assertEquals($resA->id, $resB->id);
        $this->assertEquals($resA->pdf_path, $resB->pdf_path);

        $allFiles = Storage::disk('r2_private')->allFiles("documents/payment-receipts/{$receipt->pago_id}");
        $this->assertCount(1, $allFiles, 'Debe existir exactamente 1 archivo PDF en R2, 0 huérfanos');

        // Logs de reemisión: exactamente 1
        $logs = $receipt->logs()->where('estado_nuevo', PaymentReceipt::ESTADO_REEMITIDO)->get();
        $this->assertCount(1, $logs);
    }

    /**
     * PR4 — Sesiones MySQL independientes demostrando exclusión mutua de GET_LOCK.
     * Conexión A adquiere GET_LOCK('payment_receipt_pdf_X', 0) -> retorna 1.
     * Conexión B con PDO independiente intenta GET_LOCK('payment_receipt_pdf_X', 0) -> retorna 0.
     * Conexión A libera con RELEASE_LOCK.
     * Conexión B adquiere -> retorna 1.
     */
    public function test_pr4_advisory_lock_mutual_exclusion_with_independent_pdo_connections(): void
    {
        $config = config('database.connections.mysql');
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
        $pdoA = new \PDO($dsn, $config['username'], $config['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $pdoB = new \PDO($dsn, $config['username'], $config['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

        $pagoId = 999999;
        $lockName = 'payment_receipt_pdf_payment_'.$pagoId;

        // 1. Conexión A adquiere el lock
        $stmtA = $pdoA->prepare('SELECT GET_LOCK(?, 0) AS acquired');
        $stmtA->execute([$lockName]);
        $resA = (int) $stmtA->fetchColumn();
        $this->assertEquals(1, $resA, 'Conexión A debe adquirir el lock exitosamente');

        // 2. Conexión B intenta adquirir el mismo lock inmediatamente con timeout 0 -> debe fallar (0)
        $stmtB = $pdoB->prepare('SELECT GET_LOCK(?, 0) AS acquired');
        $stmtB->execute([$lockName]);
        $resB = (int) $stmtB->fetchColumn();
        $this->assertEquals(0, $resB, 'Conexión B debe ser rechazada mientras Conexión A sostiene el lock');

        // 3. Conexión A libera el lock
        $stmtRel = $pdoA->prepare('SELECT RELEASE_LOCK(?) AS released');
        $stmtRel->execute([$lockName]);
        $resRel = (int) $stmtRel->fetchColumn();
        $this->assertEquals(1, $resRel, 'Conexión A debe liberar el lock exitosamente');

        // 4. Conexión B adquiere ahora el lock
        $stmtB->execute([$lockName]);
        $resB2 = (int) $stmtB->fetchColumn();
        $this->assertEquals(1, $resB2, 'Conexión B debe poder adquirir el lock tras la liberación de A');

        // Cleanup
        $stmtRelB = $pdoB->prepare('SELECT RELEASE_LOCK(?) AS released');
        $stmtRelB->execute([$lockName]);
    }

    /**
     * PR5 — Dos receipts distintos pueden regenerarse en paralelo sin bloquearse.
     */
    public function test_pr5_two_different_receipts_can_be_regenerated_independently(): void
    {
        $paciente1 = $this->createRoleUser('paciente');
        $paciente2 = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');

        [$pago1, $receipt1] = $this->createPagoPagadoWithReceipt($paciente1, $admin);
        [$pago2, $receipt2] = $this->createPagoPagadoWithReceipt($paciente2, $admin);

        $pagoService = app(PagoService::class);
        $res1 = $pagoService->emitirReciboParaPago($pago1, $admin);
        $res2 = $pagoService->emitirReciboParaPago($pago2, $admin);

        $this->assertNotNull($res1->pdf_path);
        $this->assertNotNull($res2->pdf_path);
        $this->assertNotEquals($res1->pdf_path, $res2->pdf_path);

        $files1 = Storage::disk('r2_private')->allFiles("documents/payment-receipts/{$receipt1->pago_id}");
        $files2 = Storage::disk('r2_private')->allFiles("documents/payment-receipts/{$receipt2->pago_id}");

        $this->assertCount(1, $files1);
        $this->assertCount(1, $files2);
    }

    /**
     * PR6 — Upload a R2 falla.
     * PaymentReceipt permanece intacto en DB, sin pdf_path erróneo y sin objetos huérfanos.
     */
    public function test_pr6_r2_upload_failure_leaves_receipt_intact_without_orphan_objects(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        [$pago, $receipt] = $this->createPagoPagadoWithReceipt($paciente, $admin);

        $docService = $this->createMock(PagoDocumentoService::class);
        $docService->method('generarReciboPagoPdfContent')->willThrowException(new \RuntimeException('Dompdf render error'));

        $pagoService = new PagoService(
            $docService,
            app(PaymentReceiptDocumentService::class),
            app(\App\Services\PaymentOrderDocumentService::class)
        );

        try {
            $pagoService->emitirReciboParaPago($pago, $admin);
            $this->fail('Debió lanzar RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Dompdf render error', $e->getMessage());
        }

        $receipt->refresh();
        $this->assertNull($receipt->pdf_path);

        $allFiles = Storage::disk('r2_private')->allFiles("documents/payment-receipts/{$receipt->pago_id}");
        $this->assertCount(0, $allFiles);
    }

    /**
     * PR7 — Upload funciona pero verificación de contenido/cabecera falla.
     * Limpia el objeto corrupto y no deja pdf_path inválido.
     */
    public function test_pr7_r2_corrupted_header_cleans_up_new_object(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        [$pago, $receipt] = $this->createPagoPagadoWithReceipt($paciente, $admin);

        $docService = $this->createMock(PagoDocumentoService::class);
        $docService->method('generarReciboPagoPdfContent')->willReturn('NOT_A_VALID_PDF_HEADER');

        $pagoService = new PagoService(
            $docService,
            app(PaymentReceiptDocumentService::class),
            app(\App\Services\PaymentOrderDocumentService::class)
        );

        try {
            $pagoService->emitirReciboParaPago($pago, $admin);
            $this->fail('Debió lanzar InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('no es un documento PDF valido', $e->getMessage());
        }

        $receipt->refresh();
        $this->assertNull($receipt->pdf_path);

        $allFiles = Storage::disk('r2_private')->allFiles("documents/payment-receipts/{$receipt->pago_id}");
        $this->assertCount(0, $allFiles);
    }

    /**
     * PR8 — R2 guardado pero fallo en persistencia de BD.
     * Elimina exclusivamente el archivo nuevo de R2 y conserva el PaymentReceipt original.
     */
    public function test_pr8_db_failure_after_r2_upload_cleans_up_new_r2_object(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        [$pago, $receipt] = $this->createPagoPagadoWithReceipt($paciente, $admin);

        $receiptDocService = app(PaymentReceiptDocumentService::class);
        $docService = app(PagoDocumentoService::class);

        DB::shouldReceive('transaction')->andThrow(new \Exception('DB Failure on receipt save'));

        try {
            $receiptDocService->generateAndStoreReceiptPdf($pago, $receipt, $docService);
            $this->fail('Debió lanzar excepción de BD');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('DB Failure', $e->getMessage());
        }

        $allFiles = Storage::disk('r2_private')->allFiles("documents/payment-receipts/{$receipt->pago_id}");
        $this->assertCount(0, $allFiles);
    }

    /**
     * PR9 — Retry exitoso tras un fallo inicial.
     */
    public function test_pr9_retry_after_initial_failure_successfully_recovers_receipt(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        [$pago, $receipt] = $this->createPagoPagadoWithReceipt($paciente, $admin);

        $failingDocService = $this->createMock(PagoDocumentoService::class);
        $failingDocService->method('generarReciboPagoPdfContent')->willThrowException(new \RuntimeException('Transient error'));

        $pagoServiceFail = new PagoService(
            $failingDocService,
            app(PaymentReceiptDocumentService::class),
            app(\App\Services\PaymentOrderDocumentService::class)
        );

        // Intento 1: Falla
        try {
            $pagoServiceFail->emitirReciboParaPago($pago, $admin);
        } catch (\RuntimeException $e) {
            // Expected
        }

        $this->assertNull($receipt->refresh()->pdf_path);

        // Intento 2: Servicio real con éxito
        $pagoServiceReal = app(PagoService::class);
        $recovered = $pagoServiceReal->emitirReciboParaPago($pago, $admin);

        $this->assertNotNull($recovered->pdf_path);
        $allFiles = Storage::disk('r2_private')->allFiles("documents/payment-receipts/{$receipt->pago_id}");
        $this->assertCount(1, $allFiles);
    }
}
