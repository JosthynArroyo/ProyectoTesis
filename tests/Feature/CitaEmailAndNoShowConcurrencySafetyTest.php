<?php

namespace Tests\Feature;

use App\Jobs\NotificarCambioEstadoCitaJob;
use App\Mail\CambioEstadoCitaMail;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Horario;
use App\Models\Role;
use App\Models\User;
use App\Services\CitaNoShowService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CitaEmailAndNoShowConcurrencySafetyTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-01 08:00:00');
        \Illuminate\Support\Facades\Storage::fake('r2_private');
        Mail::fake();
        Queue::fake();
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

    private function createCita(User $paciente, User $doctor, array $attributes = []): Cita
    {
        $esp = Especialidad::firstOrCreate(['nombre' => 'Medicina General']);
        $doctor->especialidades()->syncWithoutDetaching([$esp->id]);

        return Cita::create(array_merge([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => '2026-08-05',
            'hora' => '09:00:00',
            'motivo_consulta' => 'Consulta general',
            'estado' => Cita::ESTADO_PENDIENTE,
            'activo' => true,
            'folio_cita' => 'CIT-'.rand(100000, 999999),
            'token_validacion' => \Illuminate\Support\Str::random(40),
            'csv' => app(\App\Services\DocumentoCsvService::class)->generateCsv(),
        ], $attributes));
    }

    /**
     * PR1 — Aceptar por email normal: pendiente -> confirmada con activo=true
     */
    public function test_pr1_normal_email_accept_transitions_to_confirmada_and_active(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $doctor = $this->createRoleUser('doctor');
        $cita = $this->createCita($paciente, $doctor, ['estado' => Cita::ESTADO_PENDIENTE, 'activo' => true]);

        $signedUrl = URL::signedRoute('email.cita.action', [
            'cita' => $cita->id,
            'rol' => 'doctor',
            'accion' => 'aceptar',
        ]);

        $response = $this->get($signedUrl);
        $response->assertRedirect(route('doctor.citas'));
        $response->assertSessionHas('success');

        $cita->refresh();
        $this->assertEquals(Cita::ESTADO_CONFIRMADA, $cita->estado);
        $this->assertTrue((bool) $cita->activo);
    }

    /**
     * PR2 — Cancelar por email normal: confirmada -> cancelada con activo=false
     */
    public function test_pr2_normal_email_cancel_transitions_to_cancelada_and_inactive(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $doctor = $this->createRoleUser('doctor');
        $cita = $this->createCita($paciente, $doctor, ['estado' => Cita::ESTADO_CONFIRMADA, 'activo' => true]);

        $signedUrl = URL::signedRoute('email.cita.action', [
            'cita' => $cita->id,
            'rol' => 'paciente',
            'accion' => 'cancelar',
        ]);

        $response = $this->get($signedUrl);
        $response->assertRedirect(route('paciente.citas'));
        $response->assertSessionHas('success');

        $cita->refresh();
        $this->assertEquals(Cita::ESTADO_CANCELADA, $cita->estado);
        $this->assertFalse((bool) $cita->activo);
    }

    /**
     * PR3 — Aceptar email vs cancelación concurrente (reproduce defecto exacto de Codex):
     * La cancelación concurrente gana, y la acción tardía de email no puede sobrescribir estado a confirmada.
     */
    public function test_pr3_email_accept_versus_concurrent_cancellation_preserves_cancelada(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $doctor = $this->createRoleUser('doctor');
        $cita = $this->createCita($paciente, $doctor, ['estado' => Cita::ESTADO_PENDIENTE, 'activo' => true]);

        $signedUrl = URL::signedRoute('email.cita.action', [
            'cita' => $cita->id,
            'rol' => 'doctor',
            'accion' => 'aceptar',
        ]);

        // Simulamos intercepción: justo cuando se inicia el lock de la cita, otro proceso ya la canceló
        DB::listen(function ($query) use ($cita) {
            static $cancelled = false;
            if (! $cancelled && str_contains(strtolower($query->sql), 'for update')) {
                $cancelled = true;
                // Modificación concurrente antes de que el controlador proceda
                DB::table('citas_medicas')->where('id', $cita->id)->update([
                    'estado' => Cita::ESTADO_CANCELADA,
                    'activo' => false,
                ]);
            }
        });

        $response = $this->get($signedUrl);
        $response->assertRedirect('/');
        $response->assertSessionHas('error');

        $cita->refresh();
        // Invariante: debe seguir cancelada con activo=false
        $this->assertEquals(Cita::ESTADO_CANCELADA, $cita->estado);
        $this->assertFalse((bool) $cita->activo);
    }

    /**
     * PR4 — No-show vs cancelación:
     * Cita seleccionada como candidata, pero cancelada antes del lock de marcarSiVencio.
     * Resultado: cancelada preservada.
     */
    public function test_pr4_no_show_versus_cancellation_preserves_cancelada(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $doctor = $this->createRoleUser('doctor');
        // Cita en el pasado (vencida)
        $cita = $this->createCita($paciente, $doctor, [
            'fecha' => '2026-07-30',
            'hora' => '08:00:00',
            'estado' => Cita::ESTADO_PENDIENTE,
            'activo' => true,
        ]);

        // Cancelamos la cita concurrentemente
        $cita->estado = Cita::ESTADO_CANCELADA;
        $cita->activo = false;
        $cita->save();

        $service = app(CitaNoShowService::class);
        $result = $service->marcarSiVencio($cita);

        $this->assertFalse($result, 'No debe marcar no-show si ya está cancelada');

        $cita->refresh();
        $this->assertEquals(Cita::ESTADO_CANCELADA, $cita->estado);
        $this->assertFalse((bool) $cita->activo);
    }

    /**
     * PR5 — No-show vs realizada:
     * Cita marcada como realizada no puede ser convertida a no_se_presento.
     */
    public function test_pr5_no_show_versus_realizada_preserves_realizada(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $doctor = $this->createRoleUser('doctor');
        $cita = $this->createCita($paciente, $doctor, [
            'fecha' => '2026-07-30',
            'hora' => '08:00:00',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => false,
        ]);

        $service = app(CitaNoShowService::class);
        $result = $service->marcarSiVencio($cita);

        $this->assertFalse($result, 'No debe marcar no-show si ya fue realizada');

        $cita->refresh();
        $this->assertEquals(Cita::ESTADO_REALIZADA, $cita->estado);
    }

    /**
     * PR6 — Doble no-show: dos llamadas consecutivas o concurrentes
     * Una sola transición efectiva sin duplicar dispatch de notificaciones.
     */
    public function test_pr6_double_no_show_performs_single_transition(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $doctor = $this->createRoleUser('doctor');
        $cita = $this->createCita($paciente, $doctor, [
            'fecha' => '2026-07-30',
            'hora' => '08:00:00',
            'estado' => Cita::ESTADO_PENDIENTE,
            'activo' => true,
        ]);

        $service = app(CitaNoShowService::class);

        $res1 = $service->marcarSiVencio($cita);
        $res2 = $service->marcarSiVencio($cita);

        $this->assertTrue($res1);
        $this->assertFalse($res2);

        $cita->refresh();
        $this->assertEquals(Cita::ESTADO_NO_SE_PRESENTO, $cita->estado);
        $this->assertFalse((bool) $cita->activo);

        Queue::assertPushed(NotificarCambioEstadoCitaJob::class, 1);
    }

    /**
     * PR7 — marcarVencidas por lotes no sobrescribe citas modificadas durante el procesamiento
     */
    public function test_pr7_marcar_vencidas_batch_does_not_overwrite_modified_citas(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $doctor = $this->createRoleUser('doctor');

        // Cita 1: vencida legítima
        $cita1 = $this->createCita($paciente, $doctor, [
            'fecha' => '2026-07-30',
            'hora' => '08:00:00',
            'estado' => Cita::ESTADO_PENDIENTE,
            'activo' => true,
        ]);

        // Cita 2: vencida pero cancelada (hora distinta para evitar índice único)
        $cita2 = $this->createCita($paciente, $doctor, [
            'fecha' => '2026-07-30',
            'hora' => '09:00:00',
            'estado' => Cita::ESTADO_CANCELADA,
            'activo' => false,
        ]);

        $service = app(CitaNoShowService::class);
        $marcadas = $service->marcarVencidas('America/Guayaquil', 30);

        $this->assertTrue($marcadas->contains('id', $cita1->id));
        $this->assertFalse($marcadas->contains('id', $cita2->id));

        $cita1->refresh();
        $this->assertEquals(Cita::ESTADO_NO_SE_PRESENTO, $cita1->estado);

        $cita2->refresh();
        $this->assertEquals(Cita::ESTADO_CANCELADA, $cita2->estado);
    }

    /**
     * PR8 — Doble uso del enlace firmado: no duplica efectos ni transiciones
     */
    public function test_pr8_signed_link_reuse_does_not_duplicate_effects(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $doctor = $this->createRoleUser('doctor');
        $cita = $this->createCita($paciente, $doctor, ['estado' => Cita::ESTADO_PENDIENTE, 'activo' => true]);

        $signedUrl = URL::signedRoute('email.cita.action', [
            'cita' => $cita->id,
            'rol' => 'doctor',
            'accion' => 'aceptar',
        ]);

        // Uso 1: éxito
        $resp1 = $this->get($signedUrl);
        $resp1->assertRedirect(route('doctor.citas'));
        $resp1->assertSessionHas('success');

        // Uso 2: rechazado porque ya no es pendiente
        $resp2 = $this->get($signedUrl);
        $resp2->assertRedirect('/');
        $resp2->assertSessionHas('error');

        $cita->refresh();
        $this->assertEquals(Cita::ESTADO_CONFIRMADA, $cita->estado);
    }

    /**
     * PR9 — Enlace firmado inválido: rechazado con 403 por middleware signed
     */
    public function test_pr9_invalid_signed_link_is_rejected(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $doctor = $this->createRoleUser('doctor');
        $cita = $this->createCita($paciente, $doctor);

        $invalidUrl = route('email.cita.action', [
            'cita' => $cita->id,
            'rol' => 'doctor',
            'accion' => 'aceptar',
            'signature' => 'invalid-signature-12345',
        ]);

        $response = $this->get($invalidUrl);
        $response->assertForbidden();
    }

    /**
     * PR10 — Enlace expirado: rechazado con 403 por middleware signed
     */
    public function test_pr10_expired_signed_link_is_rejected(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $doctor = $this->createRoleUser('doctor');
        $cita = $this->createCita($paciente, $doctor);

        $expiredUrl = URL::temporarySignedRoute('email.cita.action', now()->subMinute(), [
            'cita' => $cita->id,
            'rol' => 'doctor',
            'accion' => 'aceptar',
        ]);

        $response = $this->get($expiredUrl);
        $response->assertForbidden();
    }

    /**
     * PR11 — Side effects: la operación perdedora produce 0 jobs/eventos de éxito
     */
    public function test_pr11_losing_operation_produces_zero_side_effects(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $doctor = $this->createRoleUser('doctor');
        $cita = $this->createCita($paciente, $doctor, [
            'fecha' => '2026-07-30',
            'hora' => '08:00:00',
            'estado' => Cita::ESTADO_CANCELADA,
            'activo' => false,
        ]);

        $service = app(CitaNoShowService::class);
        $service->marcarSiVencio($cita);

        Queue::assertNothingPushed();
    }

    /**
     * PR12 — HTTP: la carrera no produce error 500
     */
    public function test_pr12_concurrency_race_does_not_return_http_500(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $doctor = $this->createRoleUser('doctor');
        $cita = $this->createCita($paciente, $doctor, ['estado' => Cita::ESTADO_CANCELADA, 'activo' => false]);

        $signedUrl = URL::signedRoute('email.cita.action', [
            'cita' => $cita->id,
            'rol' => 'doctor',
            'accion' => 'aceptar',
        ]);

        $response = $this->get($signedUrl);
        $this->assertNotEquals(500, $response->getStatusCode());
        $response->assertRedirect('/');
    }

    /**
     * PR13 — Row lock real: dos conexiones PDO independientes demuestran exclusión mutua
     */
    public function test_pr13_pessimistic_row_lock_mutual_exclusion_with_independent_pdo_connections(): void
    {
        $config = config('database.connections.mysql');
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
        $pdoA = new \PDO($dsn, $config['username'], $config['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $pdoB = new \PDO($dsn, $config['username'], $config['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

        $pdoA->exec('SET innodb_lock_wait_timeout = 1');
        $pdoB->exec('SET innodb_lock_wait_timeout = 1');

        $emailA = 'test_lock_p_'.\Illuminate\Support\Str::random(8).'@example.com';
        $emailD = 'test_lock_d_'.\Illuminate\Support\Str::random(8).'@example.com';
        $dniP = (string) rand(10000000, 99999999);
        $dniD = (string) rand(10000000, 99999999);

        $pacienteId = null;
        $doctorId = null;
        $createdEspId = null;
        $rowId = null;

        try {
            $pdoA->exec("INSERT INTO users (name, email, password, dni, status, created_at, updated_at) VALUES ('P Lock', '{$emailA}', 'secret', '{$dniP}', 'active', NOW(), NOW())");
            $pacienteId = (int) $pdoA->lastInsertId();

            $pdoA->exec("INSERT INTO users (name, email, password, dni, status, created_at, updated_at) VALUES ('D Lock', '{$emailD}', 'secret', '{$dniD}', 'active', NOW(), NOW())");
            $doctorId = (int) $pdoA->lastInsertId();

            $espStmt = $pdoA->query('SELECT id FROM especialidades LIMIT 1');
            $espId = (int) $espStmt->fetchColumn();
            if (! $espId) {
                $pdoA->exec("INSERT INTO especialidades (nombre, created_at, updated_at) VALUES ('Especialidad Lock', NOW(), NOW())");
                $createdEspId = (int) $pdoA->lastInsertId();
                $espId = $createdEspId;
            }

            $tokenVal = \Illuminate\Support\Str::random(40);
            $csvCode = app(\App\Services\DocumentoCsvService::class)->generateCsv();

            $pdoA->exec("INSERT INTO citas_medicas (paciente_id, doctor_id, especialidad_id, fecha, hora, motivo_consulta, estado, activo, folio_cita, token_validacion, csv, created_at, updated_at) VALUES ({$pacienteId}, {$doctorId}, {$espId}, '2026-08-05', '17:00:00', 'Test', 'pendiente', 1, 'CIT-LOCK-1', '{$tokenVal}', '{$csvCode}', NOW(), NOW())");
            $rowId = (int) $pdoA->lastInsertId();

            // Conexión A inicia transacción y adquiere row lock
            $pdoA->beginTransaction();
            $stmtA = $pdoA->prepare('SELECT id, estado FROM citas_medicas WHERE id = ? FOR UPDATE');
            $stmtA->execute([$rowId]);
            $rowA = $stmtA->fetch(\PDO::FETCH_ASSOC);
            $this->assertNotEmpty($rowA);

            // Conexión B intenta adquirir el mismo row lock
            $pdoB->beginTransaction();
            $lockTimedOut = false;
            try {
                $stmtB = $pdoB->prepare('SELECT id, estado FROM citas_medicas WHERE id = ? FOR UPDATE');
                $stmtB->execute([$rowId]);
            } catch (\PDOException $e) {
                $lockTimedOut = true;
            }

            $this->assertTrue($lockTimedOut, 'Conexión B debe expirar por timeout al intentar adquirir el row lock retenido por Conexión A');
        } finally {
            if ($pdoA->inTransaction()) {
                try {
                    $pdoA->rollBack();
                } catch (\Throwable $e) {}
            }
            if ($pdoB->inTransaction()) {
                try {
                    $pdoB->rollBack();
                } catch (\Throwable $e) {}
            }

            try {
                $pdoA->exec("DELETE FROM citas_medicas WHERE folio_cita = 'CIT-LOCK-1'");
            } catch (\Throwable $e) {}

            try {
                $pdoA->exec("DELETE FROM users WHERE email LIKE 'test_lock_%'");
            } catch (\Throwable $e) {}

            try {
                $pdoA->exec("DELETE FROM especialidades WHERE nombre = 'Especialidad Lock'");
            } catch (\Throwable $e) {}
        }
    }

    /**
     * PR14 — Citas distintas: dos citas distintas se procesan independientemente sin lock global
     */
    public function test_pr14_two_different_citas_can_be_processed_independently(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $doctor = $this->createRoleUser('doctor');

        $cita1 = $this->createCita($paciente, $doctor, ['hora' => '09:00:00', 'estado' => Cita::ESTADO_PENDIENTE, 'activo' => true]);
        $cita2 = $this->createCita($paciente, $doctor, ['hora' => '09:30:00', 'estado' => Cita::ESTADO_PENDIENTE, 'activo' => true]);

        $url1 = URL::signedRoute('email.cita.action', ['cita' => $cita1->id, 'rol' => 'doctor', 'accion' => 'aceptar']);
        $url2 = URL::signedRoute('email.cita.action', ['cita' => $cita2->id, 'rol' => 'doctor', 'accion' => 'aceptar']);

        $this->get($url1)->assertRedirect(route('doctor.citas'));
        $this->get($url2)->assertRedirect(route('doctor.citas'));

        $cita1->refresh();
        $cita2->refresh();

        $this->assertEquals(Cita::ESTADO_CONFIRMADA, $cita1->estado);
        $this->assertEquals(Cita::ESTADO_CONFIRMADA, $cita2->estado);
    }
}
