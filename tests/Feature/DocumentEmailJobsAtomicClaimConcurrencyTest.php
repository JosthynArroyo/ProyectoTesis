<?php

namespace Tests\Feature;

use App\Jobs\EnviarCertificadoMedicoJob;
use App\Jobs\EnviarPedidoLaboratorioJob;
use App\Mail\CertificadoMedicoMail;
use App\Mail\PedidoLaboratorioMail;
use App\Models\CertificadoMedico;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\PedidoLaboratorio;
use App\Models\Role;
use App\Models\User;
use App\Services\CertificadoMedicoPdfService;
use App\Services\DocumentoCsvService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentEmailJobsAtomicClaimConcurrencyTest extends TestCase
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
            'email' => 'paciente.claim@example.com',
            'active' => true,
            'status' => User::STATUS_ACTIVE,
        ]);
        $patient->roles()->sync([$patientRole->id]);

        $doctor = User::factory()->create([
            'email' => 'doctor.claim@example.com',
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
            'motivo_consulta' => 'Consulta general',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);

        return [$patient, $doctor, $cita];
    }

    private function getSecondaryMysqlConnection(): \Illuminate\Database\Connection
    {
        $config = config('database.connections.mysql');
        config(['database.connections.mysql_worker_secondary' => $config]);

        return DB::connection('mysql_worker_secondary');
    }

    /**
     * J1 — Certificado: Envío normal.
     */
    public function test_j1_certificado_normal_send_succeeds_once(): void
    {
        Mail::fake();
        [$patient, $doctor, $cita] = $this->createBaseScenario();

        $certificado = CertificadoMedico::create([
            'codigo' => 'CM-J1-001',
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now(),
            'texto_constancia' => 'Reposo por 24 horas.',
            'dias_reposo' => 1,
            'pdf_path' => 'certificados-medicos/cm-j1-001.pdf',
            'envio_estado' => 'pending',
            'envio_intentos' => 0,
        ]);
        Storage::disk('local')->put($certificado->pdf_path, '%PDF-1.4 test');

        $job = new EnviarCertificadoMedicoJob($certificado->id);
        $job->handle(app(CertificadoMedicoPdfService::class));

        $certificado->refresh();
        $this->assertSame(1, $certificado->envio_intentos);
        $this->assertSame('sent', $certificado->envio_estado);
        $this->assertSame($patient->email, $certificado->enviado_a);
        $this->assertNotNull($certificado->enviado_en);
        Mail::assertSent(CertificadoMedicoMail::class, 1);
    }

    /**
     * J2 — Certificado: Dos workers concurrentes con dos conexiones MySQL distintas.
     * Worker A adquiere GET_LOCK en sesión secundaria.
     * Worker B ejecuta en sesión principal -> GET_LOCK devuelve 0 -> B sale sin enviar.
     * Exactamente un email es enviado.
     */
    public function test_j2_certificado_two_concurrent_workers_results_in_single_effective_send(): void
    {
        Mail::fake();
        [$patient, $doctor, $cita] = $this->createBaseScenario();

        $certificado = CertificadoMedico::create([
            'codigo' => 'CM-J2-001',
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now(),
            'texto_constancia' => 'Reposo.',
            'dias_reposo' => 1,
            'pdf_path' => 'certificados-medicos/cm-j2-001.pdf',
            'envio_estado' => 'pending',
            'envio_intentos' => 0,
        ]);
        Storage::disk('local')->put($certificado->pdf_path, '%PDF-1.4 test');

        $lockName = 'email_cert_' . $certificado->id;
        $secondaryConn = $this->getSecondaryMysqlConnection();

        // Worker A adquiere el lock en la sesión secundaria
        $resA = $secondaryConn->selectOne('SELECT GET_LOCK(?, 0) AS acq', [$lockName]);
        $this->assertSame(1, (int) $resA->acq, 'Worker A debe adquirir el lock en la sesión MySQL secundaria');

        // Worker B intenta ejecutar handle() concurrentemente en la sesión principal
        $jobB = new EnviarCertificadoMedicoJob($certificado->id);
        $jobB->handle(app(CertificadoMedicoPdfService::class));

        // Worker B detecta lock ocupado y sale inmediatamente
        Mail::assertNothingSent();
        $certificado->refresh();
        $this->assertSame(0, $certificado->envio_intentos, 'Worker B perdedor no debe incrementar intentos');
        $this->assertSame('pending', $certificado->envio_estado);

        // Worker A libera el lock
        $secondaryConn->selectOne('SELECT RELEASE_LOCK(?) AS rel', [$lockName]);
        DB::disconnect('mysql_worker_secondary');

        // Siguiente ejecución puede procesar el envío
        $jobA = new EnviarCertificadoMedicoJob($certificado->id);
        $jobA->handle(app(CertificadoMedicoPdfService::class));

        Mail::assertSent(CertificadoMedicoMail::class, 1);
        $certificado->refresh();
        $this->assertSame(1, $certificado->envio_intentos);
        $this->assertSame('sent', $certificado->envio_estado);
    }

    /**
     * J3 — Pedido Laboratorio: Dos workers concurrentes con dos conexiones MySQL.
     * Exactamente un envío efectivo.
     */
    public function test_j3_pedido_laboratorio_two_concurrent_workers_results_in_single_effective_send(): void
    {
        Mail::fake();
        [$patient, $doctor, $cita] = $this->createBaseScenario();

        $pedido = PedidoLaboratorio::create([
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'csv' => 'CSV-J3-001',
            'examenes' => ['biometria_hematica'],
            'pdf_path' => 'pedidos-laboratorio/pl-j3-001.pdf',
            'estado' => 'pendiente_toma',
            'envio_estado' => 'pending',
            'envio_intentos' => 0,
        ]);
        Storage::disk('local')->put($pedido->pdf_path, '%PDF-1.4 test');

        $lockName = 'email_lab_' . $pedido->id;
        $secondaryConn = $this->getSecondaryMysqlConnection();

        // Worker A adquiere el lock en la sesión secundaria
        $resA = $secondaryConn->selectOne('SELECT GET_LOCK(?, 0) AS acq', [$lockName]);
        $this->assertSame(1, (int) $resA->acq, 'Worker A debe adquirir el lock en la sesión MySQL secundaria');

        // Worker B intenta ejecutar handle() en la sesión principal
        $jobB = new EnviarPedidoLaboratorioJob($pedido->id);
        $jobB->handle(app(DocumentoCsvService::class));

        Mail::assertNothingSent();
        $pedido->refresh();
        $this->assertSame(0, $pedido->envio_intentos);
        $this->assertSame('pending', $pedido->envio_estado);

        // Worker A libera el lock
        $secondaryConn->selectOne('SELECT RELEASE_LOCK(?) AS rel', [$lockName]);
        DB::disconnect('mysql_worker_secondary');

        // Worker ejecuta el envío exitosamente
        $jobA = new EnviarPedidoLaboratorioJob($pedido->id);
        $jobA->handle(app(DocumentoCsvService::class));

        Mail::assertSent(PedidoLaboratorioMail::class, 1);
        $pedido->refresh();
        $this->assertSame(1, $pedido->envio_intentos);
        $this->assertSame('sent', $pedido->envio_estado);
    }

    /**
     * J4 — Worker perdedor: No envía, no incrementa intentos, no altera estado.
     */
    public function test_j4_losing_worker_does_not_send_or_increment_attempts_on_both_jobs(): void
    {
        Mail::fake();
        [$patient, $doctor, $cita] = $this->createBaseScenario();

        $certificado = CertificadoMedico::create([
            'codigo' => 'CM-J4-001',
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now(),
            'texto_constancia' => 'Reposo.',
            'dias_reposo' => 1,
            'pdf_path' => 'certificados-medicos/cm-j4-001.pdf',
            'envio_estado' => 'pending',
            'envio_intentos' => 0,
        ]);
        Storage::disk('local')->put($certificado->pdf_path, '%PDF-1.4 test');

        $pedido = PedidoLaboratorio::create([
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'csv' => 'CSV-J4-001',
            'examenes' => ['glucosa'],
            'pdf_path' => 'pedidos-laboratorio/pl-j4-001.pdf',
            'estado' => 'pendiente_toma',
            'envio_estado' => 'pending',
            'envio_intentos' => 0,
        ]);
        Storage::disk('local')->put($pedido->pdf_path, '%PDF-1.4 test');

        $secondaryConn = $this->getSecondaryMysqlConnection();
        $secondaryConn->selectOne('SELECT GET_LOCK(?, 0)', ['email_cert_' . $certificado->id]);
        $secondaryConn->selectOne('SELECT GET_LOCK(?, 0)', ['email_lab_' . $pedido->id]);

        (new EnviarCertificadoMedicoJob($certificado->id))->handle(app(CertificadoMedicoPdfService::class));
        (new EnviarPedidoLaboratorioJob($pedido->id))->handle(app(DocumentoCsvService::class));

        Mail::assertNothingSent();

        $certificado->refresh();
        $this->assertSame(0, $certificado->envio_intentos);
        $this->assertSame('pending', $certificado->envio_estado);

        $pedido->refresh();
        $this->assertSame(0, $pedido->envio_intentos);
        $this->assertSame('pending', $pedido->envio_estado);

        $secondaryConn->selectOne('SELECT RELEASE_LOCK(?)', ['email_cert_' . $certificado->id]);
        $secondaryConn->selectOne('SELECT RELEASE_LOCK(?)', ['email_lab_' . $pedido->id]);
        DB::disconnect('mysql_worker_secondary');
    }

    /**
     * J5 — Ya sent: Una ejecución normal posterior no envía correo ni incrementa intentos.
     */
    public function test_j5_already_sent_record_skips_smtp_and_counter_increment(): void
    {
        Mail::fake();
        [$patient, $doctor, $cita] = $this->createBaseScenario();

        $certificado = CertificadoMedico::create([
            'codigo' => 'CM-J5-001',
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now(),
            'texto_constancia' => 'Reposo.',
            'dias_reposo' => 1,
            'pdf_path' => 'certificados-medicos/cm-j5-001.pdf',
            'envio_estado' => 'sent',
            'enviado_a' => $patient->email,
            'enviado_en' => now(),
            'envio_intentos' => 1,
        ]);
        Storage::disk('local')->put($certificado->pdf_path, '%PDF-1.4 test');

        $pedido = PedidoLaboratorio::create([
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'csv' => 'CSV-J5-001',
            'examenes' => ['glucosa'],
            'pdf_path' => 'pedidos-laboratorio/pl-j5-001.pdf',
            'estado' => 'pendiente_toma',
            'envio_estado' => 'sent',
            'enviado_a' => $patient->email,
            'enviado_en' => now(),
            'envio_intentos' => 1,
        ]);
        Storage::disk('local')->put($pedido->pdf_path, '%PDF-1.4 test');

        (new EnviarCertificadoMedicoJob($certificado->id))->handle(app(CertificadoMedicoPdfService::class));
        (new EnviarPedidoLaboratorioJob($pedido->id))->handle(app(DocumentoCsvService::class));

        Mail::assertNothingSent();
        $this->assertSame(1, $certificado->refresh()->envio_intentos);
        $this->assertSame(1, $pedido->refresh()->envio_intentos);
    }

    /**
     * J6 — Fallo transitorio: Registra error, libera lock, deja el registro recuperable y relanza la excepción.
     */
    public function test_j6_transient_failure_marks_failed_state_releases_lock_and_rethrows(): void
    {
        Mail::shouldReceive('to->send')
            ->once()
            ->andThrow(new \RuntimeException('SMTP temporary handshake failure'));

        [$patient, $doctor, $cita] = $this->createBaseScenario();

        $certificado = CertificadoMedico::create([
            'codigo' => 'CM-J6-001',
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now(),
            'texto_constancia' => 'Reposo.',
            'dias_reposo' => 1,
            'pdf_path' => 'certificados-medicos/cm-j6-001.pdf',
            'envio_estado' => 'pending',
            'envio_intentos' => 0,
        ]);
        Storage::disk('local')->put($certificado->pdf_path, '%PDF-1.4 test');

        $job = new EnviarCertificadoMedicoJob($certificado->id);

        $rethrow = false;
        try {
            $job->handle(app(CertificadoMedicoPdfService::class));
        } catch (\RuntimeException $e) {
            $rethrow = true;
            $this->assertStringContainsString('SMTP temporary handshake failure', $e->getMessage());
        }

        $this->assertTrue($rethrow, 'La excepción debe ser relanzada');

        $certificado->refresh();
        $this->assertSame(1, $certificado->envio_intentos);
        $this->assertSame('failed', $certificado->envio_estado, 'Debe quedar en failed y no en sending');
        $this->assertStringContainsString('SMTP temporary handshake failure', $certificado->envio_error);

        // Confirmar que el lock MySQL fue liberado y otra sesión puede adquirirlo
        $secondaryConn = $this->getSecondaryMysqlConnection();
        $res = $secondaryConn->selectOne('SELECT GET_LOCK(?, 0) AS acq', ['email_cert_' . $certificado->id]);
        $this->assertSame(1, (int) $res->acq, 'El advisory lock quedó libre en finally tras la excepción');
        $secondaryConn->selectOne('SELECT RELEASE_LOCK(?)', ['email_cert_' . $certificado->id]);
        DB::disconnect('mysql_worker_secondary');
    }

    /**
     * J7 — Worker muere / claim abandonado:
     * Un registro que haya quedado en 'sending' o cuyo worker haya caído se recupera limpiamente
     * en el siguiente intento una vez que el nuevo worker adquiere el advisory lock.
     */
    public function test_j7_abandoned_claim_recovers_cleanly_on_subsequent_execution(): void
    {
        Mail::fake();
        [$patient, $doctor, $cita] = $this->createBaseScenario();

        // Simular que el proceso anterior murió abruptamente dejando envio_estado = 'sending'
        $certificado = CertificadoMedico::create([
            'codigo' => 'CM-J7-001',
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now(),
            'texto_constancia' => 'Reposo.',
            'dias_reposo' => 1,
            'pdf_path' => 'certificados-medicos/cm-j7-001.pdf',
            'envio_estado' => 'sending',
            'envio_intentos' => 1,
        ]);
        Storage::disk('local')->put($certificado->pdf_path, '%PDF-1.4 test');

        $pedido = PedidoLaboratorio::create([
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'csv' => 'CSV-J7-001',
            'examenes' => ['glucosa'],
            'pdf_path' => 'pedidos-laboratorio/pl-j7-001.pdf',
            'estado' => 'pendiente_toma',
            'envio_estado' => 'sending',
            'envio_intentos' => 1,
        ]);
        Storage::disk('local')->put($pedido->pdf_path, '%PDF-1.4 test');

        // La siguiente ejecución del worker reclama el documento y completa el envío sin quedar atascada
        (new EnviarCertificadoMedicoJob($certificado->id))->handle(app(CertificadoMedicoPdfService::class));
        (new EnviarPedidoLaboratorioJob($pedido->id))->handle(app(DocumentoCsvService::class));

        $certificado->refresh();
        $this->assertSame(2, $certificado->envio_intentos);
        $this->assertSame('sent', $certificado->envio_estado);

        $pedido->refresh();
        $this->assertSame(2, $pedido->envio_intentos);
        $this->assertSame('sent', $pedido->envio_estado);

        Mail::assertSent(CertificadoMedicoMail::class, 1);
        Mail::assertSent(PedidoLaboratorioMail::class, 1);
    }

    /**
     * J8 — failed(Throwable): Marca failed definitivo pero nunca degrada un registro que ya esté 'sent'.
     */
    public function test_j8_failed_method_does_not_downgrade_already_sent_record(): void
    {
        [$patient, $doctor, $cita] = $this->createBaseScenario();

        $certificado = CertificadoMedico::create([
            'codigo' => 'CM-J8-001',
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now(),
            'texto_constancia' => 'Reposo.',
            'dias_reposo' => 1,
            'pdf_path' => 'certificados-medicos/cm-j8-001.pdf',
            'envio_estado' => 'sent',
            'enviado_a' => $patient->email,
            'enviado_en' => now(),
            'envio_intentos' => 1,
        ]);

        $pedido = PedidoLaboratorio::create([
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'csv' => 'CSV-J8-001',
            'examenes' => ['glucosa'],
            'pdf_path' => 'pedidos-laboratorio/pl-j8-001.pdf',
            'estado' => 'pendiente_toma',
            'envio_estado' => 'sent',
            'enviado_a' => $patient->email,
            'enviado_en' => now(),
            'envio_intentos' => 1,
        ]);

        $jobCert = new EnviarCertificadoMedicoJob($certificado->id);
        $jobCert->failed(new \RuntimeException('Max retries reached on failing worker'));

        $jobPed = new EnviarPedidoLaboratorioJob($pedido->id);
        $jobPed->failed(new \RuntimeException('Max retries reached on failing worker'));

        $this->assertSame('sent', $certificado->refresh()->envio_estado, 'Certificado sent no debe ser degradado a failed');
        $this->assertSame('sent', $pedido->refresh()->envio_estado, 'Pedido sent no debe ser degradado a failed');
    }

    /**
     * J9 — envio_intentos: Conteo exacto en ganador, perdedor, retry y failed.
     */
    public function test_j9_envio_intentos_exact_count_semantics(): void
    {
        Mail::fake();
        [$patient, $doctor, $cita] = $this->createBaseScenario();

        $certificado = CertificadoMedico::create([
            'codigo' => 'CM-J9-001',
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now(),
            'texto_constancia' => 'Reposo.',
            'dias_reposo' => 1,
            'pdf_path' => 'certificados-medicos/cm-j9-001.pdf',
            'envio_estado' => 'pending',
            'envio_intentos' => 0,
        ]);
        Storage::disk('local')->put($certificado->pdf_path, '%PDF-1.4 test');

        // Worker A (ganador) ejecuta
        (new EnviarCertificadoMedicoJob($certificado->id))->handle(app(CertificadoMedicoPdfService::class));
        $this->assertSame(1, $certificado->refresh()->envio_intentos);

        // Worker perdedor / duplicado ejecuta posterior
        (new EnviarCertificadoMedicoJob($certificado->id))->handle(app(CertificadoMedicoPdfService::class));
        $this->assertSame(1, $certificado->refresh()->envio_intentos, 'Ejecución duplicada posterior = +0');

        // Invocación a failed()
        (new EnviarCertificadoMedicoJob($certificado->id))->failed(new \RuntimeException('err'));
        $this->assertSame(1, $certificado->refresh()->envio_intentos, 'failed() = +0');
    }

    /**
     * J10 — force / forceResend: Reenvío deliberado funciona y dos force concurrentes se serializan bajo mutex.
     */
    public function test_j10_force_resend_works_and_concurrent_force_resends_are_serialized(): void
    {
        Mail::fake();
        [$patient, $doctor, $cita] = $this->createBaseScenario();

        $certificado = CertificadoMedico::create([
            'codigo' => 'CM-J10-001',
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now(),
            'texto_constancia' => 'Reposo.',
            'dias_reposo' => 1,
            'pdf_path' => 'certificados-medicos/cm-j10-001.pdf',
            'envio_estado' => 'sent',
            'enviado_a' => $patient->email,
            'enviado_en' => now(),
            'envio_intentos' => 1,
        ]);
        Storage::disk('local')->put($certificado->pdf_path, '%PDF-1.4 test');

        $lockName = 'email_cert_' . $certificado->id;
        $secondaryConn = $this->getSecondaryMysqlConnection();

        // Worker A adquiere el lock en la sesión secundaria
        $secondaryConn->selectOne('SELECT GET_LOCK(?, 0)', [$lockName]);

        // Force Worker B intenta ejecutar mientras Worker A tiene el lock
        (new EnviarCertificadoMedicoJob($certificado->id, forceResend: true))->handle(app(CertificadoMedicoPdfService::class));

        // Worker B no pudo enviar porque Worker A tiene el lock
        Mail::assertNothingSent();
        $this->assertSame(1, $certificado->refresh()->envio_intentos);

        // Worker A libera el lock
        $secondaryConn->selectOne('SELECT RELEASE_LOCK(?)', [$lockName]);
        DB::disconnect('mysql_worker_secondary');

        // Worker A completa el reenvío deliberado
        (new EnviarCertificadoMedicoJob($certificado->id, forceResend: true))->handle(app(CertificadoMedicoPdfService::class));

        Mail::assertSent(CertificadoMedicoMail::class, 1);
        $certificado->refresh();
        $this->assertSame(2, $certificado->envio_intentos);
        $this->assertSame('sent', $certificado->envio_estado);
    }

    /**
     * J11 — Muerte abrupta del worker: Desconexión de sesión MySQL libera automáticamente el advisory lock.
     */
    public function test_j11_owner_crash_automatically_releases_advisory_lock_on_mysql_session_disconnect(): void
    {
        [$patient, $doctor, $cita] = $this->createBaseScenario();

        $certificado = CertificadoMedico::create([
            'codigo' => 'CM-J11-001',
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now(),
            'texto_constancia' => 'Reposo.',
            'dias_reposo' => 1,
            'pdf_path' => 'certificados-medicos/cm-j11-001.pdf',
            'envio_estado' => 'pending',
            'envio_intentos' => 0,
        ]);

        $lockName = 'email_cert_' . $certificado->id;

        // 1. Sesión A adquiere el advisory lock
        $connA = $this->getSecondaryMysqlConnection();
        $resA = $connA->selectOne('SELECT GET_LOCK(?, 0) AS acq', [$lockName]);
        $this->assertSame(1, (int) $resA->acq);

        // 2. Sesión B intenta adquirir y falla (0)
        $connB = DB::connection();
        $resB1 = $connB->selectOne('SELECT GET_LOCK(?, 0) AS acq', [$lockName]);
        $this->assertSame(0, (int) $resB1->acq, 'Sesión B no debe poder adquirir mientras A está viva');

        // 3. Simular caída abrupta / muerte del Worker A (se desconecta la conexión A sin llamar RELEASE_LOCK)
        DB::disconnect('mysql_worker_secondary');

        // 4. Sesión B vuelve a intentar inmediatamente: MySQL ya liberó el lock al morir la sesión A
        $resB2 = $connB->selectOne('SELECT GET_LOCK(?, 0) AS acq', [$lockName]);
        $this->assertSame(1, (int) $resB2->acq, 'Sesión B adquiere el lock inmediatamente tras la muerte de la sesión A');

        $connB->selectOne('SELECT RELEASE_LOCK(?)', [$lockName]);
    }
}
