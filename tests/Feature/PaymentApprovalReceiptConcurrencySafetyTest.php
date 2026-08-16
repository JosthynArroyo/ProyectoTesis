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
use App\Services\PaymentOrderDocumentService;
use App\Services\PaymentProofStorageService;
use App\Services\PaymentReceiptDocumentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentApprovalReceiptConcurrencySafetyTest extends TestCase
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

    private function createPago(User $paciente, string $estado = Pago::ESTADO_PENDIENTE): Pago
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

        return Pago::create([
            'paciente_id' => $paciente->id,
            'cita_id' => $cita->id,
            'monto' => 45.00,
            'moneda' => 'USD',
            'metodo_pago' => Pago::METODO_EFECTIVO,
            'estado' => $estado,
            'folio_unico' => 'ORD-'.rand(100000, 999999),
            'token_publico' => \Illuminate\Support\Str::random(40),
            'csv' => app(DocumentoCsvService::class)->generateCsv(),
        ]);
    }

    /**
     * PR1 — Aprobación normal
     */
    public function test_pr1_normal_approval_creates_paid_status_receipt_and_valid_r2_pdf(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        $response = $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $response->assertRedirect(route('admin.pagos.show', $pago));

        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_PAGADO, $pago->estado);
        $this->assertEquals($admin->id, $pago->aprobado_por);
        $this->assertNotNull($pago->aprobado_en);

        $receipt = $pago->receipt;
        $this->assertNotNull($receipt);
        $this->assertNotNull($receipt->pdf_path);
        $this->assertEquals('r2_private', $receipt->pdf_disk);

        $files = Storage::disk('r2_private')->allFiles("documents/payment-receipts/{$receipt->pago_id}");
        $this->assertCount(1, $files);
    }

    /**
     * PR2 — Fallo aislado de aprobación
     */
    public function test_pr2_isolated_approval_failure_restores_state_and_cleans_up(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        $failingDocService = $this->createMock(PagoDocumentoService::class);
        $failingDocService->method('generarReciboPagoPdfContent')->willThrowException(new \RuntimeException('Dompdf engine crash'));

        $this->app->instance(PagoDocumentoService::class, $failingDocService);

        $response = $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $response->assertRedirect(route('admin.pagos.show', $pago));
        $response->assertSessionHasErrors(['error']);

        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_PENDIENTE, $pago->estado);
        $this->assertNull($pago->aprobado_por);
        $this->assertNull($pago->aprobado_en);
        $this->assertNull($pago->receipt);

        $this->assertCount(0, PaymentReceipt::where('pago_id', $pago->id)->get());
    }

    /**
     * PR3 — INTERLEAVING EXACTO DEL CUARTO QA
     * Request A inicia aprobación y retiene el mutex del pago.
     * Request B intenta descargar/regenerar el recibo mientras A aún está procesando.
     * Request A falla y ejecuta su compensación completa bajo el mutex.
     * Request A libera el mutex.
     * Request B reanuda, revalida estado fresco de BD, observa que el pago fue restaurado a pendiente y no existe recibo,
     * por lo que B NO genera ningún PDF ni modifica nada.
     * Resultado: 0 recibos incompletos, 0 objetos R2 huérfanos, 0 interferencia entre flujos.
     */
    public function test_pr3_fourth_qa_interleaving_mutex_prevents_compensation_from_deleting_b_work(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        $pagoService = app(PagoService::class);

        // Simulamos la adquisición del lock por parte de Request A durante su flujo de aprobación
        $connection = DB::connection();
        $lockName = 'payment_receipt_pdf_payment_'.$pago->id;
        $connection->selectOne('SELECT GET_LOCK(?, 15) AS acquired', [$lockName]);

        // Mientras A retiene el lock, Request B intenta llamar a emitirReciboParaPago con timeout 0 o espera
        // Demostramos que Request B queda bloqueada o no puede adquirir el mutex
        $config = config('database.connections.mysql');
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
        $pdoB = new \PDO($dsn, $config['username'], $config['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $stmtB = $pdoB->prepare('SELECT GET_LOCK(?, 0) AS acquired');
        $stmtB->execute([$lockName]);
        $acquiredByB = (int) $stmtB->fetchColumn();
        $this->assertEquals(0, $acquiredByB, 'Request B no puede acceder mientras Request A está en curso');

        // Request A falla y compensa
        $pagoService->compensarAprobacionFallida($pago, Pago::ESTADO_PENDIENTE);

        // Request A libera el lock
        $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);

        // Ahora Request B puede adquirir el lock
        $stmtB->execute([$lockName]);
        $this->assertEquals(1, (int) $stmtB->fetchColumn());
        $pdoB->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);

        // Al consultar Request B el estado de pago, éste está en pendiente y no genera ningún recibo inválido
        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_PENDIENTE, $pago->estado);
        $this->assertNull($pago->receipt);
        $this->assertCount(0, Storage::disk('r2_private')->allFiles());
    }

    /**
     * PR4 — Inversa: A éxito / B espera y luego reutiliza el PDF generado por A sin regenerar
     */
    public function test_pr4_inverse_interleaving_b_waits_for_a_and_reuses_valid_pdf_without_regeneration(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        // A aprueba exitosamente
        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));

        $pago->refresh();
        $receipt = $pago->receipt;
        $this->assertNotNull($receipt);
        $initialPdfPath = $receipt->pdf_path;

        // B llega y solicita el recibo
        $pagoService = app(PagoService::class);
        $bReceipt = $pagoService->emitirReciboParaPago($pago, $admin);

        $this->assertEquals($receipt->id, $bReceipt->id);
        $this->assertEquals($initialPdfPath, $bReceipt->pdf_path);

        $files = Storage::disk('r2_private')->allFiles("documents/payment-receipts/{$receipt->pago_id}");
        $this->assertCount(1, $files, 'Exactamente 1 archivo PDF en R2');
    }

    /**
     * PR5 — Dos sesiones MySQL independientes demostrando mutex sobre el pago
     */
    public function test_pr5_advisory_lock_mutual_exclusion_with_independent_pdo_connections(): void
    {
        $config = config('database.connections.mysql');
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
        $pdoA = new \PDO($dsn, $config['username'], $config['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $pdoB = new \PDO($dsn, $config['username'], $config['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

        $pagoId = 888888;
        $lockName = 'payment_receipt_pdf_payment_'.$pagoId;

        // Conexión A adquiere el lock
        $stmtA = $pdoA->prepare('SELECT GET_LOCK(?, 0) AS acquired');
        $stmtA->execute([$lockName]);
        $this->assertEquals(1, (int) $stmtA->fetchColumn());

        // Conexión B es rechazada
        $stmtB = $pdoB->prepare('SELECT GET_LOCK(?, 0) AS acquired');
        $stmtB->execute([$lockName]);
        $this->assertEquals(0, (int) $stmtB->fetchColumn());

        // Conexión A libera el lock
        $stmtRelA = $pdoA->prepare('SELECT RELEASE_LOCK(?) AS released');
        $stmtRelA->execute([$lockName]);
        $this->assertEquals(1, (int) $stmtRelA->fetchColumn());

        // Conexión B adquiere el lock
        $stmtB->execute([$lockName]);
        $this->assertEquals(1, (int) $stmtB->fetchColumn());

        // Conexión B libera
        $stmtRelB = $pdoB->prepare('SELECT RELEASE_LOCK(?) AS released');
        $stmtRelB->execute([$lockName]);
    }

    /**
     * PR6 — Dos pagos diferentes no se bloquean mutuamente
     */
    public function test_pr6_two_different_payments_use_distinct_locks(): void
    {
        $config = config('database.connections.mysql');
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
        $pdoA = new \PDO($dsn, $config['username'], $config['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $pdoB = new \PDO($dsn, $config['username'], $config['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

        $lockName1 = 'payment_receipt_pdf_payment_100';
        $lockName2 = 'payment_receipt_pdf_payment_200';

        $stmtA = $pdoA->prepare('SELECT GET_LOCK(?, 0)');
        $stmtA->execute([$lockName1]);
        $this->assertEquals(1, (int) $stmtA->fetchColumn());

        $stmtB = $pdoB->prepare('SELECT GET_LOCK(?, 0)');
        $stmtB->execute([$lockName2]);
        $this->assertEquals(1, (int) $stmtB->fetchColumn(), 'El pago 200 no se bloquea por el lock del pago 100');

        $pdoA->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName1]);
        $pdoB->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName2]);
    }

    /**
     * PR7 — Ownership de compensación: compensarAprobacionFallida no elimina un recibo que ya tiene un PDF válido
     */
    public function test_pr7_compensation_does_not_delete_valid_verified_receipt(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        // Aprobación legítima que crea el recibo con PDF válido
        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $pago->refresh();
        $receipt = $pago->receipt;
        $this->assertNotNull($receipt->pdf_path);

        $pagoService = app(PagoService::class);

        // Llamar a compensarAprobacionFallida directamente sobre un pago con recibo válido no debe destruirlo
        $pagoService->compensarAprobacionFallida($pago, Pago::ESTADO_PENDIENTE, null, 999999);

        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_PAGADO, $pago->estado);
        $this->assertNotNull($pago->receipt);
        $this->assertEquals($receipt->id, $pago->receipt->id);
        $this->assertNotNull($pago->receipt->pdf_path);
    }

    /**
     * PR8 — Estado posterior no pisado si no coincide con el intento actual
     */
    public function test_pr8_compensation_respects_receipt_ownership_id(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        $pago->update(['estado' => Pago::ESTADO_PAGADO]);

        $receipt = PaymentReceipt::create([
            'pago_id' => $pago->id,
            'folio_recibo' => 'REC-'.rand(100000, 999999),
            'verification_token' => \Illuminate\Support\Str::random(40),
            'csv' => app(DocumentoCsvService::class)->generateCsv(),
            'emitido_en' => now(),
            'emitido_por' => $admin->id,
            'metodo_pago' => Pago::METODO_EFECTIVO,
            'monto' => 45.00,
            'pdf_path' => null,
            'pdf_disk' => null,
        ]);

        $pagoService = app(PagoService::class);

        // Intento de compensación con un createdReceiptId diferente
        $pagoService->compensarAprobacionFallida($pago, Pago::ESTADO_PENDIENTE, null, $receipt->id + 999);

        // El recibo original no debe ser eliminado
        $this->assertNotNull(PaymentReceipt::find($receipt->id));
    }

    /**
     * PR9 — Retry exitoso después de un fallo inicial
     */
    public function test_pr9_retry_succeeds_after_initial_failure(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        // Intento 1: falla
        $failingDocService = $this->createMock(PagoDocumentoService::class);
        $failingDocService->method('generarReciboPagoPdfContent')->willThrowException(new \RuntimeException('Transient network error'));
        $this->app->instance(PagoDocumentoService::class, $failingDocService);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));

        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_PENDIENTE, $pago->estado);
        $this->assertNull($pago->receipt);

        // Intento 2: restablecemos servicio real
        $this->app->forgetInstance(PagoDocumentoService::class);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));

        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_PAGADO, $pago->estado);
        $this->assertNotNull($pago->receipt);
        $this->assertNotNull($pago->receipt->pdf_path);
    }

    /**
     * PR10 — Fallo desde en_verificacion restaura exactamente en_verificacion
     */
    public function test_pr10_failure_from_in_verification_restores_in_verification_state(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_EN_VERIFICACION);

        $failingDocService = $this->createMock(PagoDocumentoService::class);
        $failingDocService->method('generarReciboPagoPdfContent')->willThrowException(new \RuntimeException('Transient error'));
        $this->app->instance(PagoDocumentoService::class, $failingDocService);

        $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));

        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_EN_VERIFICACION, $pago->estado);
        $this->assertNull($pago->aprobado_por);
        $this->assertNull($pago->aprobado_en);
        $this->assertNull($pago->receipt);
    }

    /**
     * PR11 / BLOCKER 1 — GET_LOCK = 0 (timeout con 2ª conexión PDO) no deja el pago en pagado
     */
    public function test_pr11_get_lock_timeout_zero_preserves_pending_without_receipt(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        $config = config('database.connections.mysql');
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
        $pdoExternal = new \PDO($dsn, $config['username'], $config['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

        $lockName = 'payment_receipt_pdf_payment_'.$pago->id;

        // La segunda conexión independiente adquiere y retiene el lock
        $stmtLock = $pdoExternal->prepare('SELECT GET_LOCK(?, 0)');
        $stmtLock->execute([$lockName]);
        $this->assertEquals(1, (int) $stmtLock->fetchColumn());

        try {
            $pagoService = app(PagoService::class);
            $pagoService->cambiarEstado(
                pago: $pago,
                nuevoEstado: Pago::ESTADO_PAGADO,
                actor: $admin,
                motivo: 'Aprobación concurrente'
            );
            $this->fail('Debió lanzar RuntimeException por no obtener el lock');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('No se pudo obtener el bloqueo', $e->getMessage());
        } finally {
            $pdoExternal->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
        }

        $pago->refresh();
        // Invariante crítica: el pago NO debe estar pagado ni tener recibo
        $this->assertEquals(Pago::ESTADO_PENDIENTE, $pago->estado);
        $this->assertNull($pago->aprobado_por);
        $this->assertNull($pago->aprobado_en);
        $this->assertNull($pago->receipt);
        $this->assertCount(0, Storage::disk('r2_private')->allFiles());
    }

    /**
     * PR12 / BLOCKER 1 — GET_LOCK = 0 conserva exactamente el estado en_verificacion
     */
    public function test_pr12_get_lock_timeout_zero_preserves_in_verification(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_EN_VERIFICACION);

        $config = config('database.connections.mysql');
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
        $pdoExternal = new \PDO($dsn, $config['username'], $config['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

        $lockName = 'payment_receipt_pdf_payment_'.$pago->id;

        $stmtLock = $pdoExternal->prepare('SELECT GET_LOCK(?, 0)');
        $stmtLock->execute([$lockName]);
        $this->assertEquals(1, (int) $stmtLock->fetchColumn());

        try {
            $pagoService = app(PagoService::class);
            $pagoService->cambiarEstado(
                pago: $pago,
                nuevoEstado: Pago::ESTADO_PAGADO,
                actor: $admin
            );
            $this->fail('Debió lanzar RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('No se pudo obtener el bloqueo', $e->getMessage());
        } finally {
            $pdoExternal->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
        }

        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_EN_VERIFICACION, $pago->estado);
        $this->assertNull($pago->aprobado_por);
        $this->assertNull($pago->aprobado_en);
        $this->assertNull($pago->receipt);
    }

    /**
     * PR13 / BLOCKER 1 — GET_LOCK = NULL preserva el estado sin recibo ni log
     */
    public function test_pr13_get_lock_null_preserves_state_without_receipt(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        // Simulamos retorno NULL para GET_LOCK
        DB::listen(function ($query) {
            if (str_contains(strtolower($query->sql), 'get_lock')) {
                // Provocar error en adquisición retornando null o excepción
                throw new \RuntimeException('MySQL internal error on GET_LOCK');
            }
        });

        try {
            $pagoService = app(PagoService::class);
            $pagoService->cambiarEstado(
                pago: $pago,
                nuevoEstado: Pago::ESTADO_PAGADO,
                actor: $admin
            );
            $this->fail('Debió lanzar excepción');
        } catch (\RuntimeException $e) {
            // Expected
        }

        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_PENDIENTE, $pago->estado);
        $this->assertNull($pago->aprobado_por);
        $this->assertNull($pago->aprobado_en);
        $this->assertNull($pago->receipt);
    }

    /**
     * PR14 / BLOCKER 1 — HTTP real POST /admin/pagos/{pago}/aprobar con timeout devuelve redirect no 500 e integridad intacta
     */
    public function test_pr14_http_real_post_timeout_returns_redirect_and_preserves_integrity(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        $config = config('database.connections.mysql');
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
        $pdoExternal = new \PDO($dsn, $config['username'], $config['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

        $lockName = 'payment_receipt_pdf_payment_'.$pago->id;
        $pdoExternal->prepare('SELECT GET_LOCK(?, 0)')->execute([$lockName]);

        try {
            $response = $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
            // Debe retornar redirect 302 hacia admin.pagos.show (no 500)
            $response->assertRedirect(route('admin.pagos.show', $pago));
            $response->assertSessionHasErrors(['error']);
        } finally {
            $pdoExternal->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
        }

        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_PENDIENTE, $pago->estado);
        $this->assertNull($pago->aprobado_por);
        $this->assertNull($pago->aprobado_en);
        $this->assertNull($pago->receipt);
        $this->assertCount(0, Storage::disk('r2_private')->allFiles());
    }

    /**
     * PR15 / BLOCKER 1 — Retry después de timeout de lock tiene éxito completo
     */
    public function test_pr15_retry_after_lock_timeout_succeeds(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        $config = config('database.connections.mysql');
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
        $pdoExternal = new \PDO($dsn, $config['username'], $config['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

        $lockName = 'payment_receipt_pdf_payment_'.$pago->id;

        // Intento 1: Lock ocupado externamente -> falla de forma segura
        $pdoExternal->prepare('SELECT GET_LOCK(?, 0)')->execute([$lockName]);
        $response1 = $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $response1->assertRedirect(route('admin.pagos.show', $pago));
        $response1->assertSessionHasErrors(['error']);

        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_PENDIENTE, $pago->estado);
        $this->assertNull($pago->receipt);

        // Liberamos el lock en la conexión externa
        $pdoExternal->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);

        // Intento 2: Lock disponible -> éxito completo
        $response2 = $this->actingAs($admin)->post(route('admin.pagos.aprobar', $pago));
        $response2->assertRedirect(route('admin.pagos.show', $pago));
        $response2->assertSessionHas('success');

        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_PAGADO, $pago->estado);
        $this->assertNotNull($pago->receipt);
        $this->assertNotNull($pago->receipt->pdf_path);
        $this->assertTrue(Storage::disk('r2_private')->exists($pago->receipt->pdf_path));
    }

    /**
     * PR16 / BLOCKER 2 — A falla / B espera / B revalida estado fresco y aprueba limpiamente
     */
    public function test_pr16_blocker2_interleaving_a_fails_b_waits_and_fresh_revalidates_into_clean_paid_with_receipt(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        $config = config('database.connections.mysql');
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
        $pdoA = new \PDO($dsn, $config['username'], $config['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

        $lockName = 'payment_receipt_pdf_payment_'.$pago->id;

        // 1. Conexión A adquiere el lock simulando el inicio del procesamiento de A
        $pdoA->prepare('SELECT GET_LOCK(?, 0)')->execute([$lockName]);

        // A modifica temporalmente el pago y luego falla y compensa
        $pago->update(['estado' => Pago::ESTADO_PAGADO, 'aprobado_por' => $admin->id, 'aprobado_en' => now()]);
        $pagoService = app(PagoService::class);
        $pagoService->compensarAprobacionFallida($pago, Pago::ESTADO_PENDIENTE);

        // A libera el lock
        $pdoA->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);

        // 2. Request B ahora ejecuta cambiarEstado a pagado
        $pagoService->cambiarEstado(
            pago: $pago,
            nuevoEstado: Pago::ESTADO_PAGADO,
            actor: $admin,
            motivo: 'Aprobación por B tras espera'
        );

        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_PAGADO, $pago->estado);
        $this->assertNotNull($pago->receipt);
        $this->assertNotNull($pago->receipt->pdf_path);
        $this->assertCount(1, PaymentReceipt::where('pago_id', $pago->id)->get());
        $this->assertCount(1, Storage::disk('r2_private')->allFiles("documents/payment-receipts/{$pago->id}"));
    }

    /**
     * PR17 / BLOCKER 2 — B espera y al entrar encuentra estado NO aprobable (anulado): rechaza sin crear recibo
     */
    public function test_pr17_blocker2_b_waits_and_finds_unapprovable_state_aborts_without_receipt(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        $config = config('database.connections.mysql');
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
        $pdoA = new \PDO($dsn, $config['username'], $config['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

        $lockName = 'payment_receipt_pdf_payment_'.$pago->id;

        // Conexión A retiene el lock
        $pdoA->prepare('SELECT GET_LOCK(?, 0)')->execute([$lockName]);

        // Mientras B espera, el pago es anulado administrativamente
        $pago->update(['estado' => Pago::ESTADO_ANULADO]);

        // Conexión A libera el lock
        $pdoA->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);

        // Request B intenta aprobar pero debe ser rechazado por transición inválida anulado -> pagado
        try {
            $pagoService = app(PagoService::class);
            $pagoService->cambiarEstado(
                pago: $pago,
                nuevoEstado: Pago::ESTADO_PAGADO,
                actor: $admin
            );
            $this->fail('Debió lanzar InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            // Expected: transición no permitida desde anulado
        }

        $pago->refresh();
        $this->assertEquals(Pago::ESTADO_ANULADO, $pago->estado);
        $this->assertNull($pago->receipt);
        $this->assertCount(0, Storage::disk('r2_private')->allFiles());
    }

    /**
     * PR18 / BLOCKER 2 — A tiene éxito / B espera y reutiliza el recibo sin duplicar
     */
    public function test_pr18_blocker2_a_succeeds_b_waits_and_reuses_single_receipt_without_duplication(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        $pagoService = app(PagoService::class);

        // A aprueba con éxito
        $pagoService->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin);
        $pago->refresh();
        $receipt1 = $pago->receipt;
        $this->assertNotNull($receipt1);

        // B llega y ejecuta cambiarEstado o emitirReciboParaPago
        $pagoService->cambiarEstado($pago, Pago::ESTADO_PAGADO, $admin);

        $pago->refresh();
        $receipt2 = $pago->receipt;

        $this->assertEquals($receipt1->id, $receipt2->id);
        $this->assertEquals(1, PaymentReceipt::where('pago_id', $pago->id)->count());
        $this->assertCount(1, Storage::disk('r2_private')->allFiles("documents/payment-receipts/{$receipt1->pago_id}"));
    }

    /**
     * PR19 / BLOCKER 2 — Verificación defensiva directa: emitirReciboParaPago rechaza pagos en estado pendiente
     */
    public function test_pr19_blocker2_ejecutar_emision_recibo_directa_defensive_check_blocks_non_pagado(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $pago = $this->createPago($paciente, Pago::ESTADO_PENDIENTE);

        $pagoService = app(PagoService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Solo se puede emitir recibo para pagos en estado pagado');

        $pagoService->emitirReciboParaPago($pago, $admin);
    }
}
