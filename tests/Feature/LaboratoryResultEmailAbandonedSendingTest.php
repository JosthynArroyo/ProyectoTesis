<?php

namespace Tests\Feature;

use App\Jobs\EnviarResultadoPedidoLaboratorioJob;
use App\Mail\ResultadoPedidoLaboratorioMail;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\PedidoLaboratorio;
use App\Models\PedidoLaboratorioResultado;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LaboratoryResultEmailAbandonedSendingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('r2_private');
    }

    private function createBaseScenario(): array
    {
        $patientRole = Role::firstOrCreate(['name' => 'paciente']);
        $doctorRole = Role::firstOrCreate(['name' => 'doctor']);

        $patient = User::factory()->create([
            'email' => 'paciente.lab@example.com',
            'active' => true,
            'status' => User::STATUS_ACTIVE,
        ]);
        $patient->roles()->sync([$patientRole->id]);

        $doctor = User::factory()->create([
            'email' => 'doctor.lab@example.com',
            'active' => true,
            'status' => User::STATUS_ACTIVE,
        ]);
        $doctor->roles()->sync([$doctorRole->id]);

        $specialty = Especialidad::firstOrCreate(['nombre' => 'Medicina General'], ['activo' => true]);
        $doctor->especialidades()->attach($specialty->id);

        $cita = Cita::create([
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $specialty->id,
            'fecha' => now()->toDateString(),
            'hora' => '10:00:00',
            'motivo_consulta' => 'Examenes de rutina',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);

        $pedido = PedidoLaboratorio::create([
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'csv' => 'CSV-LR-001',
            'examenes' => ['glucosa', 'perfil_lipidico'],
            'estado' => PedidoLaboratorio::ESTADO_RESULTADO_LISTO,
        ]);

        $resultado = PedidoLaboratorioResultado::create([
            'pedido_laboratorio_id' => $pedido->id,
            'version' => 1,
            'pdf_path' => 'documents/laboratory-results/res-lr-001.pdf',
            'pdf_disk' => 'r2_private',
            'estado' => PedidoLaboratorioResultado::ESTADO_PUBLICADO,
            'envio_estado' => 'pending',
            'envio_intentos' => 0,
        ]);
        Storage::disk('r2_private')->put($resultado->pdf_path, '%PDF-1.4 test lab result');

        return [$patient, $doctor, $cita, $pedido, $resultado];
    }

    private function getSecondaryMysqlConnection(): \Illuminate\Database\Connection
    {
        $config = config('database.connections.mysql');
        config(['database.connections.mysql_lab_secondary' => $config]);

        return DB::connection('mysql_lab_secondary');
    }

    /**
     * LR1 — Envío normal: Resultado listo/no enviado -> un intento -> un email -> sent.
     */
    public function test_lr1_normal_send_succeeds_once(): void
    {
        Mail::fake();
        [$patient, , , , $resultado] = $this->createBaseScenario();

        $job = new EnviarResultadoPedidoLaboratorioJob($resultado->id);
        $job->handle();

        $resultado->refresh();
        $this->assertSame(1, $resultado->envio_intentos);
        $this->assertSame('sent', $resultado->envio_estado);
        $this->assertSame($patient->email, $resultado->enviado_a);
        $this->assertNotNull($resultado->enviado_en);
        Mail::assertSent(ResultadoPedidoLaboratorioMail::class, 1);
    }

    /**
     * LR2 — Worker concurrente activo:
     * Sesión A adquiere advisory lock mientras resultado está en sending.
     * Sesión B ejecuta el job -> GET_LOCK B = 0 -> 0 emails, +0 intentos, estado intacto.
     */
    public function test_lr2_active_concurrent_worker_is_blocked_by_advisory_lock(): void
    {
        Mail::fake();
        [$patient, , , , $resultado] = $this->createBaseScenario();

        $lockName = 'email_lab_res_' . $resultado->id;
        $secondaryConn = $this->getSecondaryMysqlConnection();

        // Sesión A adquiere advisory lock simulando un worker enviando activamente
        $resA = $secondaryConn->selectOne('SELECT GET_LOCK(?, 0) AS acq', [$lockName]);
        $this->assertSame(1, (int) $resA->acq);

        $resultado->update([
            'envio_estado' => 'sending',
            'envio_intentos' => 1,
            'enviado_a' => $patient->email,
        ]);

        // Worker B intenta ejecutar handle() concurrentemente
        $jobB = new EnviarResultadoPedidoLaboratorioJob($resultado->id);
        $jobB->handle();

        // Worker B no envía ni incrementa intentos
        Mail::assertNothingSent();
        $resultado->refresh();
        $this->assertSame(1, $resultado->envio_intentos);
        $this->assertSame('sending', $resultado->envio_estado);

        $secondaryConn->selectOne('SELECT RELEASE_LOCK(?)', [$lockName]);
        DB::disconnect('mysql_lab_secondary');
    }

    /**
     * LR3 — sending abandonado:
     * El registro quedó en 'sending' pero NO hay ningún advisory lock activo (el worker anterior murió).
     * El siguiente worker adquiere el lock, reclama el registro, incrementa intento y completa el envío.
     */
    public function test_lr3_abandoned_sending_is_safely_recovered_and_sent(): void
    {
        Mail::fake();
        [$patient, , , , $resultado] = $this->createBaseScenario();

        // Simular intento anterior muerto que dejó el registro en sending
        $resultado->update([
            'envio_estado' => 'sending',
            'envio_intentos' => 1,
            'enviado_a' => $patient->email,
        ]);

        // Siguiente ejecución del worker
        $job = new EnviarResultadoPedidoLaboratorioJob($resultado->id);
        $job->handle();

        $resultado->refresh();
        $this->assertSame(2, $resultado->envio_intentos, 'Debe haber incrementado el intento al recuperar el sending abandonado');
        $this->assertSame('sent', $resultado->envio_estado);
        $this->assertNotNull($resultado->enviado_en);
        Mail::assertSent(ResultadoPedidoLaboratorioMail::class, 1);
    }

    /**
     * LR4 — Muerte del owner:
     * Sesión A adquiere GET_LOCK. Sesión B no puede.
     * Al desconectar la Sesión A sin RELEASE_LOCK, Sesión B adquiere el lock de inmediato.
     */
    public function test_lr4_owner_death_automatically_releases_advisory_lock_without_orphan_mutex(): void
    {
        [, , , , $resultado] = $this->createBaseScenario();

        $lockName = 'email_lab_res_' . $resultado->id;
        $connA = $this->getSecondaryMysqlConnection();
        $connB = DB::connection();

        $resA = $connA->selectOne('SELECT GET_LOCK(?, 0) AS acq', [$lockName]);
        $this->assertSame(1, (int) $resA->acq);

        $resB1 = $connB->selectOne('SELECT GET_LOCK(?, 0) AS acq', [$lockName]);
        $this->assertSame(0, (int) $resB1->acq, 'Sesión B no debe poder adquirir mientras A vive');

        // Muerte de la sesión A
        DB::disconnect('mysql_lab_secondary');

        // Sesión B adquiere el lock inmediatamente
        $resB2 = $connB->selectOne('SELECT GET_LOCK(?, 0) AS acq', [$lockName]);
        $this->assertSame(1, (int) $resB2->acq);

        $connB->selectOne('SELECT RELEASE_LOCK(?)', [$lockName]);
    }

    /**
     * LR5 — sent: Un registro ya enviado no envía SMTP ni incrementa intentos.
     */
    public function test_lr5_already_sent_record_skips_smtp_and_attempts_increment(): void
    {
        Mail::fake();
        [$patient, , , , $resultado] = $this->createBaseScenario();

        $resultado->update([
            'envio_estado' => 'sent',
            'enviado_a' => $patient->email,
            'enviado_en' => now(),
            'envio_intentos' => 1,
        ]);

        $job = new EnviarResultadoPedidoLaboratorioJob($resultado->id);
        $job->handle();

        Mail::assertNothingSent();
        $this->assertSame(1, $resultado->refresh()->envio_intentos);
    }

    /**
     * LR6 — Fallo transitorio: SMTP falla -> failed/error, throw, advisory lock liberado; posterior retry tiene éxito.
     */
    public function test_lr6_transient_failure_marks_failed_state_releases_lock_and_allows_retry(): void
    {
        $attempts = 0;
        Mail::shouldReceive('to->send')
            ->twice()
            ->andReturnUsing(function () use (&$attempts) {
                $attempts++;
                if ($attempts === 1) {
                    throw new \RuntimeException('SMTP 421 Service Unavailable');
                }
                return true;
            });

        [, , , , $resultado] = $this->createBaseScenario();

        // 1. Intento 1 falla
        $job1 = new EnviarResultadoPedidoLaboratorioJob($resultado->id);
        $thrown = false;
        try {
            $job1->handle();
        } catch (\RuntimeException $e) {
            $thrown = true;
            $this->assertStringContainsString('SMTP 421', $e->getMessage());
        }

        $this->assertTrue($thrown);
        $resultado->refresh();
        $this->assertSame(1, $resultado->envio_intentos);
        $this->assertSame('failed', $resultado->envio_estado);
        $this->assertStringContainsString('SMTP 421', $resultado->envio_error);

        // 2. Intento 2 (reintento del worker) tiene éxito
        $job2 = new EnviarResultadoPedidoLaboratorioJob($resultado->id);
        $job2->handle();

        $resultado->refresh();
        $this->assertSame(2, $resultado->envio_intentos);
        $this->assertSame('sent', $resultado->envio_estado);
        $this->assertNull($resultado->envio_error);
    }

    /**
     * LR7 — failed(Throwable): Marca failed definitivo pero nunca degrada un registro que ya esté 'sent'.
     */
    public function test_lr7_failed_method_does_not_downgrade_already_sent_record(): void
    {
        [$patient, , , , $resultado] = $this->createBaseScenario();

        $resultado->update([
            'envio_estado' => 'sent',
            'enviado_a' => $patient->email,
            'enviado_en' => now(),
            'envio_intentos' => 1,
        ]);

        $job = new EnviarResultadoPedidoLaboratorioJob($resultado->id);
        $job->failed(new \RuntimeException('Max retries exceeded on failing worker'));

        $this->assertSame('sent', $resultado->refresh()->envio_estado, 'El registro sent no debe ser degradado a failed');
    }

    /**
     * LR8 — Intentos: Conteo riguroso en ganador, perdedor, recovery y failed.
     */
    public function test_lr8_envio_intentos_exact_count_semantics(): void
    {
        Mail::fake();
        [, , , , $resultado] = $this->createBaseScenario();

        // Worker ganador
        (new EnviarResultadoPedidoLaboratorioJob($resultado->id))->handle();
        $this->assertSame(1, $resultado->refresh()->envio_intentos);

        // Ejecución duplicada
        (new EnviarResultadoPedidoLaboratorioJob($resultado->id))->handle();
        $this->assertSame(1, $resultado->refresh()->envio_intentos, 'Ejecución duplicada sobre sent = +0');

        // Invocación a failed()
        (new EnviarResultadoPedidoLaboratorioJob($resultado->id))->failed(new \RuntimeException('err'));
        $this->assertSame(1, $resultado->refresh()->envio_intentos, 'failed() = +0');
    }

    /**
     * LR9 — Force: sent + force permite reenvío, pero mutex ocupado + force bloquea concurrencia.
     */
    public function test_lr9_force_resend_works_and_concurrent_force_is_serialized(): void
    {
        Mail::fake();
        [$patient, , , , $resultado] = $this->createBaseScenario();

        $resultado->update([
            'envio_estado' => 'sent',
            'enviado_a' => $patient->email,
            'enviado_en' => now(),
            'envio_intentos' => 1,
        ]);

        $lockName = 'email_lab_res_' . $resultado->id;
        $secondaryConn = $this->getSecondaryMysqlConnection();

        // Worker A adquiere el lock
        $secondaryConn->selectOne('SELECT GET_LOCK(?, 0)', [$lockName]);

        // Force Worker B intenta ejecutar simultáneamente
        (new EnviarResultadoPedidoLaboratorioJob($resultado->id, force: true))->handle();

        // Worker B no envía
        Mail::assertNothingSent();
        $this->assertSame(1, $resultado->refresh()->envio_intentos);

        // Worker A libera el lock
        $secondaryConn->selectOne('SELECT RELEASE_LOCK(?)', [$lockName]);
        DB::disconnect('mysql_lab_secondary');

        // Worker A ejecuta el force resend exitosamente
        (new EnviarResultadoPedidoLaboratorioJob($resultado->id, force: true))->handle();

        Mail::assertSent(ResultadoPedidoLaboratorioMail::class, 1);
        $resultado->refresh();
        $this->assertSame(2, $resultado->envio_intentos);
        $this->assertSame('sent', $resultado->envio_estado);
    }
}
