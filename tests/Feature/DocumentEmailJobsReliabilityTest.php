<?php

namespace Tests\Feature;

use App\Jobs\EnviarCertificadoMedicoJob;
use App\Jobs\EnviarPedidoLaboratorioJob;
use App\Jobs\EnviarResultadoPedidoLaboratorioJob;
use App\Mail\CertificadoMedicoMail;
use App\Mail\PedidoLaboratorioMail;
use App\Mail\ResultadoPedidoLaboratorioMail;
use App\Models\CertificadoMedico;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\PedidoLaboratorio;
use App\Models\PedidoLaboratorioResultado;
use App\Models\Role;
use App\Models\User;
use App\Services\CertificadoMedicoPdfService;
use App\Services\DocumentoCsvService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentEmailJobsReliabilityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('r2_private');
    }

    /**
     * Caso A — Éxito:
     * Un job exitoso envía exactamente una vez, incrementa envio_intentos exactamente una vez y registra estado exitoso.
     */
    public function test_caso_a_successful_job_increments_attempts_once_and_marks_sent(): void
    {
        Mail::fake();
        [$patient, $doctor, $cita] = $this->createBaseMedicalScenario();

        $certificado = CertificadoMedico::create([
            'codigo' => 'CM-TEST-A1',
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now(),
            'texto_constancia' => 'Reposo médico.',
            'dias_reposo' => 1,
            'pdf_path' => 'certificados-medicos/cm-test-a1.pdf',
            'envio_estado' => 'pending',
            'envio_intentos' => 0,
        ]);
        Storage::disk('local')->put($certificado->pdf_path, '%PDF-1.4 test');

        $job = new EnviarCertificadoMedicoJob($certificado->id);
        $job->handle(app(CertificadoMedicoPdfService::class));

        $certificado->refresh();
        $this->assertSame(1, $certificado->envio_intentos, 'envio_intentos debe incrementarse exactamente una vez');
        $this->assertSame('sent', $certificado->envio_estado);
        $this->assertSame($patient->email, $certificado->enviado_a);
        $this->assertNotNull($certificado->enviado_en);
        Mail::assertSent(CertificadoMedicoMail::class, 1);
    }

    /**
     * Caso B — Fallo transitorio:
     * Simula excepción de transporte. Debe incrementar envio_intentos una sola vez,
     * registrar el error y propagar la excepción para que Laravel Queue pueda reintentar.
     */
    public function test_caso_b_transient_failure_increments_attempts_once_and_rethrows(): void
    {
        Mail::shouldReceive('to->send')
            ->once()
            ->andThrow(new \RuntimeException('Connection timed out on SMTP 587'));

        [$patient, $doctor, $cita] = $this->createBaseMedicalScenario();

        $certificado = CertificadoMedico::create([
            'codigo' => 'CM-TEST-B1',
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now(),
            'texto_constancia' => 'Reposo médico.',
            'dias_reposo' => 1,
            'pdf_path' => 'certificados-medicos/cm-test-b1.pdf',
            'envio_estado' => 'pending',
            'envio_intentos' => 0,
        ]);
        Storage::disk('local')->put($certificado->pdf_path, '%PDF-1.4 test');

        $job = new EnviarCertificadoMedicoJob($certificado->id);

        $caught = false;
        try {
            $job->handle(app(CertificadoMedicoPdfService::class));
        } catch (\RuntimeException $e) {
            $caught = true;
            $this->assertSame('Connection timed out on SMTP 587', $e->getMessage());
        }

        $this->assertTrue($caught, 'La excepción debe ser propagada para que Laravel Queue maneje el reintento.');

        $certificado->refresh();
        $this->assertSame(1, $certificado->envio_intentos, 'envio_intentos NO debe duplicarse en un solo intento fallido');
        $this->assertStringContainsString('Connection timed out on SMTP 587', $certificado->envio_error);
        $this->assertNotSame('sent', $certificado->envio_estado);
    }

    /**
     * Caso C — Reintento:
     * Demuestra que una ejecución posterior puede volver a intentar correctamente después de la falla previa.
     */
    public function test_caso_c_retry_after_transient_failure_succeeds_and_increments_attempts(): void
    {
        $sendAttempts = 0;
        Mail::shouldReceive('to->send')
            ->twice()
            ->andReturnUsing(function () use (&$sendAttempts) {
                $sendAttempts++;
                if ($sendAttempts === 1) {
                    throw new \RuntimeException('Temporary network error');
                }
                return true;
            });

        [$patient, $doctor, $cita] = $this->createBaseMedicalScenario();

        $certificado = CertificadoMedico::create([
            'codigo' => 'CM-TEST-C1',
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now(),
            'texto_constancia' => 'Reposo médico.',
            'dias_reposo' => 1,
            'pdf_path' => 'certificados-medicos/cm-test-c1.pdf',
            'envio_estado' => 'pending',
            'envio_intentos' => 0,
        ]);
        Storage::disk('local')->put($certificado->pdf_path, '%PDF-1.4 test');

        // 1. Primer intento falla
        try {
            (new EnviarCertificadoMedicoJob($certificado->id))->handle(app(CertificadoMedicoPdfService::class));
        } catch (\Throwable) {
            // Se propaga la excepción en el primer intento
        }

        $certificado->refresh();
        $this->assertSame(1, $certificado->envio_intentos);
        $this->assertNotSame('sent', $certificado->envio_estado);

        // 2. Segundo intento exitoso (simulación del worker reintentando)
        (new EnviarCertificadoMedicoJob($certificado->id))->handle(app(CertificadoMedicoPdfService::class));

        $certificado->refresh();
        $this->assertSame(2, $certificado->envio_intentos);
        $this->assertSame('sent', $certificado->envio_estado);
        $this->assertNull($certificado->envio_error);
    }

    /**
     * Caso D — Fallo definitivo:
     * Prueba el método failed(Throwable $exception) cuando se agotan los reintentos.
     */
    public function test_caso_d_failed_method_marks_final_failure(): void
    {
        [$patient, $doctor, $cita] = $this->createBaseMedicalScenario();

        $certificado = CertificadoMedico::create([
            'codigo' => 'CM-TEST-D1',
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now(),
            'texto_constancia' => 'Reposo médico.',
            'dias_reposo' => 1,
            'pdf_path' => 'certificados-medicos/cm-test-d1.pdf',
            'envio_estado' => 'sending',
            'envio_intentos' => 3,
        ]);

        $job = new EnviarCertificadoMedicoJob($certificado->id);
        $job->failed(new \RuntimeException('Max retries exceeded: SMTP permanently unavailable'));

        $certificado->refresh();
        $this->assertSame('failed', $certificado->envio_estado);
        $this->assertStringContainsString('Max retries exceeded', $certificado->envio_error);
    }

    /**
     * Caso E — Evitar doble envío:
     * Si el modelo ya consta como enviado, una ejecución repetida accidental no envía otro correo.
     */
    public function test_caso_e_already_sent_record_does_not_send_duplicate_mail(): void
    {
        Mail::fake();
        [$patient, $doctor, $cita] = $this->createBaseMedicalScenario();

        $certificado = CertificadoMedico::create([
            'codigo' => 'CM-TEST-E1',
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'fecha_emision' => now(),
            'texto_constancia' => 'Reposo médico.',
            'dias_reposo' => 1,
            'pdf_path' => 'certificados-medicos/cm-test-e1.pdf',
            'envio_estado' => 'sent',
            'enviado_a' => $patient->email,
            'enviado_en' => now(),
            'envio_intentos' => 1,
        ]);

        (new EnviarCertificadoMedicoJob($certificado->id))->handle(app(CertificadoMedicoPdfService::class));

        Mail::assertNothingSent();
        $certificado->refresh();
        $this->assertSame(1, $certificado->envio_intentos, 'No debe incrementar intentos si ya fue enviado');
    }

    /**
     * Caso F — EnviarCertificadoMedicoJob:
     * Cubre su política de reintentos, conteo único y reintento.
     */
    public function test_caso_f_enviar_certificado_medico_job_policy(): void
    {
        $job = new EnviarCertificadoMedicoJob(1);
        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 30, 60], $job->backoff);
    }

    /**
     * Caso G — EnviarPedidoLaboratorioJob:
     * Cubre su política de reintentos, conteo único y manejo de errores.
     */
    public function test_caso_g_enviar_pedido_laboratorio_job_flow(): void
    {
        Mail::fake();
        [$patient, $doctor, $cita] = $this->createBaseMedicalScenario();

        $pedido = PedidoLaboratorio::create([
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'csv' => 'CSV-TEST-G1',
            'examenes' => ['biometria_hematica'],
            'pdf_path' => 'pedidos-laboratorio/pl-test-g1.pdf',
            'estado' => 'pendiente_toma',
            'envio_estado' => 'pending',
            'envio_intentos' => 0,
        ]);
        Storage::disk('local')->put($pedido->pdf_path, '%PDF-1.4 test');

        $job = new EnviarPedidoLaboratorioJob($pedido->id);
        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 30, 60], $job->backoff);

        $job->handle(app(DocumentoCsvService::class));

        $pedido->refresh();
        $this->assertSame(1, $pedido->envio_intentos);
        $this->assertSame('sent', $pedido->envio_estado);
        $this->assertSame($patient->email, $pedido->enviado_a);
        Mail::assertSent(PedidoLaboratorioMail::class, 1);
    }

    /**
     * Caso H — EnviarResultadoPedidoLaboratorioJob:
     * Cubre el envío de resultados tanto para PedidoLaboratorioResultado como para PedidoLaboratorio.
     */
    public function test_caso_h_enviar_resultado_pedido_laboratorio_job_handles_both_target_types(): void
    {
        Mail::fake();
        [$patient, $doctor, $cita] = $this->createBaseMedicalScenario();

        // 1. Para PedidoLaboratorio legacy/MVP
        $pedidoLegacy = PedidoLaboratorio::create([
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'csv' => 'CSV-TEST-H1',
            'examenes' => ['glucosa'],
            'resultado_path' => 'legacy-medical-orders/res-h1.pdf',
            'resultado_resumen' => 'Normal',
            'estado' => 'resultado_listo',
            'envio_estado' => 'pending',
            'envio_intentos' => 0,
        ]);
        Storage::disk('r2_private')->put($pedidoLegacy->resultado_path, '%PDF-1.4 res');

        $jobLegacy = new EnviarResultadoPedidoLaboratorioJob(null, false, $pedidoLegacy->id);
        $this->assertSame(3, $jobLegacy->tries);
        $this->assertSame([10, 30, 60], $jobLegacy->backoff);

        $jobLegacy->handle();

        $pedidoLegacy->refresh();
        $this->assertSame(1, $pedidoLegacy->envio_intentos);
        $this->assertSame('sent', $pedidoLegacy->envio_estado);
        $this->assertSame($patient->email, $pedidoLegacy->enviado_a);

        // 2. Para PedidoLaboratorioResultado moderno
        $resultadoModerno = PedidoLaboratorioResultado::create([
            'pedido_laboratorio_id' => $pedidoLegacy->id,
            'version' => 1,
            'pdf_path' => 'documents/laboratory-results/res-moderno.pdf',
            'pdf_disk' => 'r2_private',
            'estado' => PedidoLaboratorioResultado::ESTADO_PUBLICADO,
            'envio_estado' => 'pending',
            'envio_intentos' => 0,
        ]);
        Storage::disk('r2_private')->put($resultadoModerno->pdf_path, '%PDF-1.4 res mod');

        $jobModerno = new EnviarResultadoPedidoLaboratorioJob($resultadoModerno->id);
        $jobModerno->handle();

        $resultadoModerno->refresh();
        $this->assertSame(1, $resultadoModerno->envio_intentos);
        $this->assertSame('sent', $resultadoModerno->envio_estado);
        $this->assertSame($patient->email, $resultadoModerno->enviado_a);

        Mail::assertSent(ResultadoPedidoLaboratorioMail::class, 2);
    }

    /**
     * Caso I — Integración MVP:
     * La publicación mediante /laboratorio/pedidos-mvp despacha el job oficial EnviarResultadoPedidoLaboratorioJob.
     */
    public function test_caso_i_mvp_publication_dispatches_official_job(): void
    {
        Queue::fake();

        $labRole = Role::firstOrCreate(['name' => 'laboratorio']);
        $labUser = User::factory()->create(['active' => true, 'status' => User::STATUS_ACTIVE]);
        $labUser->roles()->sync([$labRole->id]);

        [$patient, $doctor, $cita] = $this->createBaseMedicalScenario();

        $pedido = PedidoLaboratorio::create([
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'csv' => 'CSV-TEST-I1',
            'examenes' => ['glucosa', 'perfil_lipidico'],
            'estado' => PedidoLaboratorio::ESTADO_PENDIENTE_TOMA,
        ]);

        $pdf = UploadedFile::fake()->createWithContent('res_mvp.pdf', '%PDF-1.4 '.str_repeat('res ', 64));

        $response = $this->actingAs($labUser)->post(
            route('laboratorio.pedidos.resultado', $pedido),
            [
                'resultado_pdf' => $pdf,
                'resultado_resumen' => 'Resultados dentro de rango normal.',
            ]
        );

        $response->assertSessionHas('success');

        $pedido->refresh();
        $this->assertSame(PedidoLaboratorio::ESTADO_RESULTADO_LISTO, $pedido->estado);

        // Se verifica que se despachó el job oficial con el ID del pedido
        Queue::assertPushed(EnviarResultadoPedidoLaboratorioJob::class, function ($job) use ($pedido) {
            return $job->pedidoId === $pedido->id;
        });
    }

    /**
     * Caso J — Claim atómico bajo lock:
     * Si dos workers intentan procesar el mismo resultado simultáneamente,
     * el segundo detecta que el registro ya está en 'sending' o 'sent' bajo lock y no envía correo duplicado.
     */
    public function test_caso_j_atomic_claim_under_lock_prevents_duplicate_sending_by_concurrent_workers(): void
    {
        Mail::fake();
        [$patient, $doctor, $cita] = $this->createBaseMedicalScenario();

        $pedido = PedidoLaboratorio::create([
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'csv' => 'CSV-TEST-J1',
            'examenes' => ['glucosa'],
            'estado' => PedidoLaboratorio::ESTADO_RESULTADO_LISTO,
        ]);

        $resultado = PedidoLaboratorioResultado::create([
            'pedido_laboratorio_id' => $pedido->id,
            'version' => 1,
            'pdf_path' => 'documents/laboratory-results/res-j.pdf',
            'pdf_disk' => 'r2_private',
            'estado' => PedidoLaboratorioResultado::ESTADO_PUBLICADO,
            'envio_estado' => 'pending',
            'envio_intentos' => 0,
        ]);
        Storage::disk('r2_private')->put($resultado->pdf_path, '%PDF-1.4 res j');

        // Simular que el Worker A reclama el registro bajo advisory lock (estado pasa a 'sending')
        $resultado->update([
            'envio_estado' => 'sending',
            'envio_intentos' => 1,
            'enviado_a' => $patient->email,
        ]);

        $config = config('database.connections.mysql');
        config(['database.connections.mysql_worker_secondary' => $config]);
        $secondaryConn = DB::connection('mysql_worker_secondary');
        $secondaryConn->selectOne('SELECT GET_LOCK(?, 0)', ['email_lab_res_' . $resultado->id]);

        // Worker B intenta ejecutar handle() concurrentemente sobre el mismo resultado
        $jobB = new EnviarResultadoPedidoLaboratorioJob($resultado->id);
        $jobB->handle();

        // Worker B NO debe haber enviado correo porque detectó advisory lock ocupado
        Mail::assertNothingSent();
        $resultado->refresh();
        $this->assertSame(1, $resultado->envio_intentos, 'Worker B no debe incrementar intentos ni enviar');

        $secondaryConn->selectOne('SELECT RELEASE_LOCK(?)', ['email_lab_res_' . $resultado->id]);
        DB::disconnect('mysql_worker_secondary');
    }

    /**
     * Caso K — Fallo transitorio y reintento con claim atómico:
     * Ante un fallo temporal, el estado no queda en 'sending' permanente, sino que permite que el siguiente reintento reclame el envío.
     */
    public function test_caso_k_transient_failure_resets_sending_state_allowing_subsequent_retry_to_claim(): void
    {
        $sendAttempts = 0;
        Mail::shouldReceive('to->send')
            ->twice()
            ->andReturnUsing(function () use (&$sendAttempts) {
                $sendAttempts++;
                if ($sendAttempts === 1) {
                    throw new \RuntimeException('SMTP 421 Service not available');
                }
                return true;
            });

        [$patient, $doctor, $cita] = $this->createBaseMedicalScenario();

        $pedido = PedidoLaboratorio::create([
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'csv' => 'CSV-TEST-K1',
            'examenes' => ['glucosa'],
            'estado' => PedidoLaboratorio::ESTADO_RESULTADO_LISTO,
        ]);

        $resultado = PedidoLaboratorioResultado::create([
            'pedido_laboratorio_id' => $pedido->id,
            'version' => 1,
            'pdf_path' => 'documents/laboratory-results/res-k.pdf',
            'pdf_disk' => 'r2_private',
            'estado' => PedidoLaboratorioResultado::ESTADO_PUBLICADO,
            'envio_estado' => 'pending',
            'envio_intentos' => 0,
        ]);
        Storage::disk('r2_private')->put($resultado->pdf_path, '%PDF-1.4 res k');

        // 1. Intento 1: Falla
        try {
            (new EnviarResultadoPedidoLaboratorioJob($resultado->id))->handle();
        } catch (\Throwable $e) {
            $this->assertStringContainsString('SMTP 421', $e->getMessage());
        }

        $resultado->refresh();
        $this->assertSame(1, $resultado->envio_intentos);
        $this->assertSame('failed', $resultado->envio_estado, 'Debe quedar en failed y no en sending para permitir el retry');

        // 2. Intento 2 (reintento del worker): Tiene éxito
        (new EnviarResultadoPedidoLaboratorioJob($resultado->id))->handle();

        $resultado->refresh();
        $this->assertSame(2, $resultado->envio_intentos);
        $this->assertSame('sent', $resultado->envio_estado);
        $this->assertNull($resultado->envio_error);
    }

    private function createBaseMedicalScenario(): array
    {
        $patientRole = Role::firstOrCreate(['name' => 'paciente']);
        $doctorRole = Role::firstOrCreate(['name' => 'doctor']);

        $patient = User::factory()->create([
            'email' => 'paciente.jobs@example.com',
            'active' => true,
            'status' => User::STATUS_ACTIVE,
        ]);
        $patient->roles()->sync([$patientRole->id]);

        $doctor = User::factory()->create([
            'email' => 'doctor.jobs@example.com',
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
            'motivo_consulta' => 'Consulta medica',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);

        return [$patient, $doctor, $cita];
    }
}
