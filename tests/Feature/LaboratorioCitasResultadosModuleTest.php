<?php

namespace Tests\Feature;

use App\Mail\ResultadoLaboratorioMail;
use App\Models\Cita;
use App\Models\Dependiente;
use App\Models\Especialidad;
use App\Models\LabOrder;
use App\Models\LaboratorioOrden;
use App\Models\PedidoLaboratorio;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LaboratorioCitasResultadosModuleTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('r2_private');
        Mail::fake();
    }

    public function test_laboratorio_user_can_view_worklist_with_all_three_order_sources(): void
    {
        [$doctor, $patient, $labUser, $specialty] = $this->createScenarioUsers();

        $cita1 = Cita::create([
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $specialty->id,
            'fecha' => '2026-08-15',
            'hora' => '09:00:00',
            'motivo_consulta' => 'Examen de laboratorio',
            'estado' => Cita::ESTADO_PENDIENTE,
            'activo' => true,
        ]);

        $legacyOrder = LaboratorioOrden::create([
            'cita_id' => $cita1->id,
            'solicitante_id' => $patient->id,
            'origen' => 'paciente',
            'prioridad' => 'normal',
            'tipo_examen' => 'Hemograma Completo',
            'estado' => LaboratorioOrden::ESTADO_CITA_PROGRAMADA,
        ]);

        $cita2 = Cita::create([
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $specialty->id,
            'fecha' => '2026-08-16',
            'hora' => '10:00:00',
            'motivo_consulta' => 'Consulta medica',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);

        $pedido = PedidoLaboratorio::create([
            'cita_id' => $cita2->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'csv' => 'CSV-TEST-100',
            'examenes' => ['perfil_hepatico', 'glucosa'],
            'estado' => PedidoLaboratorio::ESTADO_PENDIENTE_TOMA,
            'envio_estado' => 'queued',
        ]);

        $labOrder = LabOrder::create([
            'patient_id' => $patient->id,
            'source' => LabOrder::SOURCE_ROUTINE,
            'doctor_id' => $doctor->id,
            'laboratorio_id' => null,
            'priority' => 'normal',
            'status' => LabOrder::STATUS_PENDIENTE_TOMA,
            'scheduled_at' => now(),
            'doctor_notes' => 'Perfil lipidico',
        ]);

        $response = $this->actingAs($labUser)->get(route('laboratorio.ordenes.index'));
        $response->assertOk();
        $response->assertSee('Hemograma Completo');
        $response->assertSee('Perfil Hepatico, Glucosa');
        $response->assertSee('Examen de laboratorio');
    }

    public function test_orders_in_muestra_tomada_visible_in_todos_and_muestra_tomada_filter(): void
    {
        [$doctor, $patient, $labUser, $specialty] = $this->createScenarioUsers();

        $cita = Cita::create([
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $specialty->id,
            'fecha' => '2026-08-15',
            'hora' => '09:00:00',
            'motivo_consulta' => 'Control anual',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);

        $pedido = PedidoLaboratorio::create([
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'csv' => 'CSV-TEST-200',
            'examenes' => ['tgo_tgp'],
            'estado' => PedidoLaboratorio::ESTADO_MUESTRA_TOMADA,
            'sample_collected_at' => now(),
            'sample_collected_by' => $labUser->id,
        ]);

        // 1. Filter = all
        $responseAll = $this->actingAs($labUser)->get(route('laboratorio.ordenes.index', ['estado' => 'all']));
        $responseAll->assertOk();
        $responseAll->assertSee('Tgo Tgp');
        $responseAll->assertSee('Muestra tomada / análisis');

        // 2. Filter = muestra_tomada
        $responseFilter = $this->actingAs($labUser)->get(route('laboratorio.ordenes.index', ['estado' => 'muestra_tomada']));
        $responseFilter->assertOk();
        $responseFilter->assertSee('Tgo Tgp');
        $responseFilter->assertSee('Muestra tomada / análisis');
    }

    public function test_laboratorio_user_authorized_for_legacy_orden_actions_with_different_doctor_id(): void
    {
        [$doctor, $patient, $labUser, $specialty] = $this->createScenarioUsers();

        $cita = Cita::create([
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $specialty->id,
            'fecha' => '2026-08-15',
            'hora' => '09:00:00',
            'motivo_consulta' => 'Lab directo',
            'estado' => Cita::ESTADO_PENDIENTE,
            'activo' => true,
        ]);

        $orden = LaboratorioOrden::create([
            'cita_id' => $cita->id,
            'solicitante_id' => $patient->id,
            'origen' => 'paciente',
            'prioridad' => 'normal',
            'tipo_examen' => 'Urocultivo',
            'estado' => LaboratorioOrden::ESTADO_CITA_PROGRAMADA,
        ]);

        // 1. Mark sample as Laboratorio user
        $sampleResp = $this->actingAs($labUser)->post(route('laboratorio.ordenes.muestra', $orden));
        $sampleResp->assertRedirect();
        $orden->refresh();
        $this->assertSame(LaboratorioOrden::ESTADO_MUESTRA_TOMADA, $orden->estado);

        // 2. Upload result PDF as Laboratorio user
        $uploadResp = $this->actingAs($labUser)->post(route('laboratorio.ordenes.resultado', $orden), [
            'resultado_resumen' => 'Cultivo negativo a las 48 horas.',
            'resultado_pdf' => UploadedFile::fake()
                ->createWithContent('urocultivo_resultado.pdf', '%PDF-1.4 resultado urocultivo')
                ->mimeType('application/pdf'),
        ]);
        $uploadResp->assertRedirect();
        $orden->refresh();
        $this->assertSame(LaboratorioOrden::ESTADO_RESULTADO_DISPONIBLE, $orden->estado);
        $this->assertStringStartsWith('documents/laboratory-results/legacy-orders/', $orden->resultado_path);
        Storage::disk('r2_private')->assertExists($orden->resultado_path);
        Storage::disk('local')->assertMissing($orden->resultado_path);

        // 3. Download result as Laboratorio user
        $downloadResp = $this->actingAs($labUser)->get(route('laboratorio.ordenes.download', $orden));
        $downloadResp->assertOk();

        $this->actingAs($patient)
            ->get(route('paciente.laboratorio.download', $orden))
            ->assertOk();

        Mail::assertSent(ResultadoLaboratorioMail::class, function (ResultadoLaboratorioMail $mail) use ($patient): bool {
            return $mail->hasTo($patient->email)
                && count($mail->build()->rawAttachments) === 1;
        });
    }

    public function test_self_service_result_is_stored_and_consumed_from_r2(): void
    {
        [$doctor, $patient, $labUser] = $this->createScenarioUsers();

        $order = LabOrder::create([
            'patient_id' => $patient->id,
            'source' => LabOrder::SOURCE_ROUTINE,
            'doctor_id' => $doctor->id,
            'laboratorio_id' => $labUser->id,
            'priority' => 'normal',
            'status' => LabOrder::STATUS_MUESTRA_TOMADA,
            'scheduled_at' => now(),
            'doctor_notes' => 'Perfil de control',
        ]);

        $this->actingAs($labUser)->post(route('laboratorio.lab-orders.resultado', $order), [
            'resultado_resumen' => 'Resultado sin novedades.',
            'resultado_pdf' => UploadedFile::fake()
                ->createWithContent('resultado.pdf', '%PDF-1.4 resultado de control')
                ->mimeType('application/pdf'),
        ])->assertRedirect();

        $order->refresh();
        $this->assertStringStartsWith('documents/laboratory-results/self-service-orders/', $order->resultado_path);
        Storage::disk('r2_private')->assertExists($order->resultado_path);
        Storage::disk('local')->assertMissing($order->resultado_path);

        $this->actingAs($labUser)
            ->get(route('laboratorio.lab-orders.download', $order))
            ->assertOk();
        $this->actingAs($patient)
            ->get(route('paciente.lab-orders.download', $order))
            ->assertOk();

        Mail::assertSent(ResultadoLaboratorioMail::class, function (ResultadoLaboratorioMail $mail) use ($patient): bool {
            return $mail->hasTo($patient->email)
                && count($mail->build()->rawAttachments) === 1;
        });
    }

    public function test_historical_local_result_remains_read_only_fallback(): void
    {
        [$doctor, $patient, $labUser, $specialty] = $this->createScenarioUsers();

        $cita = Cita::create([
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $specialty->id,
            'fecha' => '2026-08-15',
            'hora' => '13:00:00',
            'motivo_consulta' => 'Resultado histórico',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);
        $path = 'laboratorio_resultados/historico.pdf';
        $orden = LaboratorioOrden::create([
            'cita_id' => $cita->id,
            'solicitante_id' => $patient->id,
            'origen' => 'paciente',
            'prioridad' => 'normal',
            'tipo_examen' => 'Histórico',
            'estado' => LaboratorioOrden::ESTADO_RESULTADO_DISPONIBLE,
            'resultado_path' => $path,
            'resultado_publicado_at' => now(),
        ]);
        Storage::disk('local')->put($path, '%PDF-1.4 histórico');

        $this->actingAs($labUser)
            ->get(route('laboratorio.ordenes.download', $orden))
            ->assertOk();
        $this->actingAs($patient)
            ->get(route('paciente.laboratorio.download', $orden))
            ->assertOk();

        Storage::disk('local')->assertExists($path);
        Storage::disk('r2_private')->assertMissing($path);
    }

    public function test_another_laboratorio_user_blocked_from_assigned_self_service_order(): void
    {
        [$doctor, $patient, $labUserA] = $this->createScenarioUsers();
        $labUserB = $this->userWithRole('laboratorio');

        $assignedOrder = LabOrder::create([
            'patient_id' => $patient->id,
            'source' => LabOrder::SOURCE_ROUTINE,
            'doctor_id' => $doctor->id,
            'laboratorio_id' => $labUserA->id,
            'priority' => 'normal',
            'status' => LabOrder::STATUS_PENDIENTE_TOMA,
            'scheduled_at' => now(),
            'doctor_notes' => 'Examen privado Lab A',
        ]);

        $forbiddenSample = $this->actingAs($labUserB)->post(route('laboratorio.lab-orders.muestra', $assignedOrder));
        $forbiddenSample->assertStatus(403);

        $forbiddenUpload = $this->actingAs($labUserB)->post(route('laboratorio.lab-orders.resultado', $assignedOrder), [
            'resultado_resumen' => 'Forbidden result',
            'resultado_pdf' => UploadedFile::fake()->create('forbidden.pdf', 100, 'application/pdf'),
        ]);
        $forbiddenUpload->assertStatus(403);
    }

    public function test_dependient_appointment_displays_dependient_name_in_worklist(): void
    {
        [$doctor, $holder, $labUser, $specialty] = $this->createScenarioUsers();

        $dependiente = Dependiente::create([
            'user_id' => $holder->id,
            'nombre' => 'Anabel',
            'parentesco' => 'Hija',
            'dni' => '1234567890',
            'fecha_nacimiento' => '2018-05-10',
        ]);

        $citaDependiente = Cita::create([
            'paciente_id' => $holder->id,
            'dependiente_id' => $dependiente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $specialty->id,
            'fecha' => '2026-08-15',
            'hora' => '11:00:00',
            'motivo_consulta' => 'Examen pediatrico',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);

        $pedido = PedidoLaboratorio::create([
            'cita_id' => $citaDependiente->id,
            'paciente_id' => $holder->id,
            'doctor_id' => $doctor->id,
            'csv' => 'CSV-DEP-999',
            'examenes' => ['hemograma'],
            'estado' => PedidoLaboratorio::ESTADO_PENDIENTE_TOMA,
        ]);

        $response = $this->actingAs($labUser)->get(route('laboratorio.ordenes.index'));
        $response->assertOk();
        $response->assertSee('Anabel');
    }

    public function test_no_duplicate_rows_when_appointment_has_both_legacy_and_pedido(): void
    {
        [$doctor, $patient, $labUser, $specialty] = $this->createScenarioUsers();

        $cita = Cita::create([
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $specialty->id,
            'fecha' => '2026-08-15',
            'hora' => '12:00:00',
            'motivo_consulta' => 'Consulta doble',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);

        LaboratorioOrden::create([
            'cita_id' => $cita->id,
            'solicitante_id' => $patient->id,
            'origen' => 'paciente',
            'prioridad' => 'normal',
            'tipo_examen' => 'Examen Duplicado',
            'estado' => LaboratorioOrden::ESTADO_CITA_PROGRAMADA,
        ]);

        PedidoLaboratorio::create([
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'csv' => 'CSV-DUP-111',
            'examenes' => ['perfil_lipidico'],
            'estado' => PedidoLaboratorio::ESTADO_PENDIENTE_TOMA,
        ]);

        $response = $this->actingAs($labUser)->get(route('laboratorio.ordenes.index'));
        $response->assertOk();
        // The list should show the PedidoLaboratorio ("Perfil Lipidico") and not duplicate the legacy order card
        $response->assertSee('Perfil Lipidico');
        $response->assertDontSee('Examen Duplicado');
    }

    private function createScenarioUsers(): array
    {
        $specialty = Especialidad::factory()->create(['nombre' => 'General', 'activo' => true]);
        $doctor = $this->userWithRole('doctor');
        $patient = $this->userWithRole('paciente');
        $labUser = $this->userWithRole('laboratorio');

        return [$doctor, $patient, $labUser, $specialty];
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::query()->firstOrCreate(['name' => $roleName], ['label' => ucfirst($roleName)]);
        $user->roles()->attach($role);

        return $user;
    }
}
