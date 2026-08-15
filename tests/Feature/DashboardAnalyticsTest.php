<?php

namespace Tests\Feature;

use App\Models\CertificadoMedico;
use App\Models\Cita;
use App\Models\LabOrder;
use App\Models\NotaSoap;
use App\Models\NotaSoapDiagnostico;
use App\Models\PedidoLaboratorio;
use App\Models\Receta;
use App\Models\Role;
use App\Models\Especialidad;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardAnalyticsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-10 10:00:00', 'America/Guayaquil'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_superadmin_dashboard_uses_real_counts_and_drill_down_links(): void
    {
        $initialActive = User::onlyActive()->count();
        $initialPatients = User::whereHas('roles', fn($q) => $q->where('name', 'paciente'))->count();
        $initialDoctors = User::whereHas('roles', fn($q) => $q->where('name', 'doctor'))->count();
        $initialAdmins = User::whereHas('roles', fn($q) => $q->where('name', 'administrador'))->count();
        $initialLabs = User::whereHas('roles', fn($q) => $q->where('name', 'laboratorio'))->count();

        $superadmin = $this->userWithRole('superadmin', ['created_at' => Carbon::parse('2026-07-01 00:00:00', 'America/Guayaquil')]);
        $admin = $this->userWithRole('administrador');
        $doctorA = $this->userWithRole('doctor');
        $doctorB = $this->userWithRole('doctor');
        $patientA = $this->userWithRole('paciente');
        $patientB = $this->userWithRole('paciente');
        $this->userWithRole('laboratorio');
        $especialidad = Especialidad::factory()->create(['nombre' => 'Medicina General']);

        $citaPendiente = $this->createCita($patientA, $doctorA, $especialidad, '2026-07-10', '09:00:00', Cita::ESTADO_PENDIENTE);
        $this->createCita($patientB, $doctorA, $especialidad, '2026-07-10', '10:00:00', Cita::ESTADO_CONFIRMADA);
        $this->createCita($patientA, $doctorB, $especialidad, '2026-07-10', '11:00:00', Cita::ESTADO_REALIZADA);
        $this->createCita($patientB, $doctorB, $especialidad, '2026-07-09', '12:00:00', Cita::ESTADO_CANCELADA);

        $this->createRecipe($citaPendiente, $doctorA);
        $this->createCertificate($citaPendiente, $doctorA);
        $this->createLabOrder($citaPendiente, $doctorA, $patientA, ['glucosa', 'biometria_hematica'], 'pendiente_toma');

        $response = $this->actingAs($superadmin)->get(route('superadmin.dashboard.data', ['period' => '30d']));

        $response->assertOk();

        $payload = $response->json();

        $this->assertSame('superadmin', $payload['role']);
        $this->assertSame(7 + $initialActive, $payload['metrics']['users_active']['value']);
        $this->assertSame(2 + $initialPatients, $payload['metrics']['patients_total']['value']);
        $this->assertSame(2 + $initialDoctors, $payload['metrics']['doctors_total']['value']);
        $this->assertSame(1 + $initialAdmins, $payload['metrics']['admins_total']['value']);
        $this->assertSame(1 + $initialLabs, $payload['metrics']['laboratory_total']['value']);
        $this->assertSame(4, $payload['metrics']['appointments_period']['value']);
        $this->assertSame(3, $payload['metrics']['appointments_today']['value']);
        $this->assertSame(1, $payload['metrics']['lab_pending']['value']);
        $this->assertSame(3, $payload['metrics']['documents_total']['value']);

        $this->assertSame([1, 1, 1, 1, 0], $payload['charts']['citas_estado']['series']);
        $this->assertStringContainsString('estado=cancelada', $payload['charts']['citas_estado']['links'][2]);
        $this->assertStringContainsString('doctor_id='.$doctorA->id, $payload['charts']['citas_doctor']['links'][0]);
        $this->assertStringContainsString('role=paciente', $payload['charts']['usuarios_roles']['links'][0]);
        $this->assertStringContainsString('role=doctor', $payload['charts']['usuarios_roles']['links'][1]);
        $this->assertStringContainsString('role=administrador', $payload['charts']['usuarios_roles']['links'][2]);
        $this->assertStringContainsString('role=laboratorio', $payload['charts']['usuarios_roles']['links'][3]);

        $this->assertSame('Pacientes', $payload['charts']['usuarios_roles']['labels'][0]);
        $this->assertSame('Administradores', $payload['charts']['usuarios_roles']['labels'][2]);

        $this->actingAs($admin)->get(route('superadmin.dashboard.data'))->assertRedirect('/');
    }

    public function test_admin_dashboard_is_filterable_and_exposes_state_links(): void
    {
        $initialPatients = User::whereHas('roles', fn($q) => $q->where('name', 'paciente'))->count();
        $initialActiveDoctors = User::onlyActive()->whereHas('roles', fn($q) => $q->where('name', 'doctor'))->count();

        $admin = $this->userWithRole('administrador', ['created_at' => Carbon::parse('2026-07-01 00:00:00', 'America/Guayaquil')]);
        $doctor = $this->userWithRole('doctor');
        $patientA = $this->userWithRole('paciente');
        $patientB = $this->userWithRole('paciente');
        $this->userWithRole('laboratorio');
        $especialidad = Especialidad::factory()->create(['nombre' => 'Medicina General']);

        $pending = $this->createCita($patientA, $doctor, $especialidad, '2026-07-10', '11:00:00', Cita::ESTADO_PENDIENTE);
        $this->createCita($patientB, $doctor, $especialidad, '2026-07-10', '12:00:00', Cita::ESTADO_CONFIRMADA);
        $this->createCita($patientA, $doctor, $especialidad, '2026-07-10', '10:30:00', Cita::ESTADO_REALIZADA);
        $this->createCita($patientB, $doctor, $especialidad, '2026-07-09', '11:30:00', Cita::ESTADO_CANCELADA);
        $this->createLabOrder($pending, $doctor, $patientA, ['glucosa'], 'pendiente_toma');

        $response = $this->actingAs($admin)->get(route('admin.dashboard.data', ['period' => '30d']));

        $response->assertOk();

        $payload = $response->json();

        $this->assertSame('admin', $payload['role']);
        $this->assertSame(2 + $initialPatients, $payload['metrics']['patients_total']['value']);
        $this->assertSame(1 + $initialActiveDoctors, $payload['metrics']['doctors_active']['value']);
        $this->assertSame(4, $payload['metrics']['appointments_period']['value']);
        $this->assertSame(3, $payload['metrics']['appointments_today']['value']);
        $this->assertSame(1, $payload['metrics']['appointments_pending']['value']);
        $this->assertSame(1, $payload['metrics']['appointments_confirmed']['value']);
        $this->assertSame(1, $payload['metrics']['appointments_completed']['value']);
        $this->assertSame(1, $payload['metrics']['appointments_cancelled']['value']);
        $this->assertSame(1, $payload['metrics']['lab_pending']['value']);

        $this->assertSame([1, 1, 1, 1, 0], $payload['charts']['citas_estado']['series']);
        $this->assertStringContainsString('estado=pendiente', $payload['charts']['citas_estado']['links'][0]);
        $this->assertStringContainsString('doctor_id='.$doctor->id, $payload['charts']['citas_doctor']['links'][0]);

        $this->actingAs($doctor)->get(route('admin.dashboard.data'))->assertRedirect('/');
    }

    public function test_doctor_dashboard_is_scoped_to_authenticated_doctor_and_shows_follow_up_summary(): void
    {
        $doctor = $this->userWithRole('doctor', [
            'name' => 'Doctor QA',
            'email' => 'doctor.qa@clinic.test',
            'created_at' => Carbon::parse('2026-07-01 00:00:00', 'America/Guayaquil')
        ]);
        $otherDoctor = $this->userWithRole('doctor', ['name' => 'Doctor Secundario', 'email' => 'doctor.other@clinic.test']);
        $patientA = $this->userWithRole('paciente', ['name' => 'Paciente QA', 'email' => 'paciente.qa@clinic.test']);
        $patientB = $this->userWithRole('paciente', ['name' => 'Paciente Secundario', 'email' => 'paciente.other@clinic.test']);
        $especialidad = Especialidad::factory()->create(['nombre' => 'Medicina General']);

        $pending = $this->createCita($patientA, $doctor, $especialidad, '2026-07-10', '11:00:00', Cita::ESTADO_PENDIENTE);
        $this->createCita($patientA, $doctor, $especialidad, '2026-07-10', '12:00:00', Cita::ESTADO_CANCELADA);
        $completed = $this->createCita($patientB, $doctor, $especialidad, '2026-07-09', '11:00:00', Cita::ESTADO_REALIZADA);
        $nextAppointment = $this->createCita($patientA, $doctor, $especialidad, '2026-07-10', '13:00:00', Cita::ESTADO_CONFIRMADA);
        $this->createCita($patientB, $otherDoctor, $especialidad, '2026-07-10', '12:00:00', Cita::ESTADO_REALIZADA);

        $draftNote = NotaSoap::create([
            'cita_id' => $pending->id,
            'clinical_record_id' => null,
            'estado' => NotaSoap::ESTADO_BORRADOR,
            'subjetivo_motivo' => 'Control',
            'plan_general' => 'Seguimiento',
        ]);

        $signedNote = NotaSoap::create([
            'cita_id' => $completed->id,
            'clinical_record_id' => null,
            'estado' => NotaSoap::ESTADO_FIRMADA,
            'signed_at' => now(),
            'signed_by' => $doctor->id,
            'follow_up_date' => '2026-07-10',
            'follow_up_cita_id' => $nextAppointment->id,
            'subjetivo_motivo' => 'Seguimiento',
            'plan_general' => 'Control futuro',
        ]);

        NotaSoapDiagnostico::create([
            'nota_soap_id' => $signedNote->id,
            'tipo' => 'principal',
            'texto' => 'Asma cronica',
            'cie10' => 'J45.9',
        ]);

        $this->createRecipe($completed, $doctor, $signedNote);
        $this->createCertificate($completed, $doctor);
        $this->createLabOrder($completed, $doctor, $patientB, ['glucosa'], 'pendiente_toma');
        $this->createRecipe($this->createCita($patientB, $otherDoctor, $especialidad, '2026-07-09', '09:30:00', Cita::ESTADO_REALIZADA), $otherDoctor);

        $response = $this->actingAs($doctor)->get(route('doctor.dashboard.data', ['period' => '30d']));

        $response->assertOk();

        $payload = $response->json();

        $this->assertSame('doctor', $payload['role']);
        $this->assertSame(3, $payload['metrics']['appointments_today']['value']);
        $this->assertSame(1, $payload['metrics']['appointments_pending']['value']);
        $this->assertSame(1, $payload['metrics']['appointments_completed']['value']);
        $this->assertSame(1, $payload['metrics']['future_controls']['value']);
        $this->assertSame(1, $payload['metrics']['draft_notes']['value']);
        $this->assertSame(1, $payload['metrics']['lab_orders_related']['value']);
        $this->assertSame(1, $payload['metrics']['patients_attended']['value']);
        $this->assertSame(3, $payload['metrics']['documents_total']['value']);
        $this->assertStringContainsString('Paciente QA', $payload['metrics']['next_appointment']['subtitle']);

        $this->assertSame([1, 1, 1, 1, 0], $payload['charts']['appointments_status']['series']);
        $this->assertStringContainsString('estado=pendiente', $payload['charts']['appointments_status']['links'][0]);
        $this->assertSame(['Nuevos', 'Recurrentes'], $payload['charts']['patients_recurrent']['labels']);
        $this->assertNotEmpty($payload['lists']['next_appointments']);
        $this->assertStringContainsString('Paciente QA', $payload['lists']['next_appointments'][0]['label'] ?? '');
        $this->assertStringContainsString('Paciente QA', $payload['lists']['follow_up_controls'][0]['label'] ?? '');

        $this->actingAs($patientA)->get(route('doctor.dashboard.data'))->assertRedirect('/');
    }

    public function test_laboratorio_dashboard_aggregates_current_states_and_top_exams(): void
    {
        $lab = $this->userWithRole('laboratorio', ['name' => 'Laboratorio QA', 'email' => 'lab.qa@clinic.test']);
        $doctorA = $this->userWithRole('doctor', ['name' => 'Doctor Lab A', 'email' => 'doctor.lab-a@clinic.test']);
        $doctorB = $this->userWithRole('doctor', ['name' => 'Doctor Lab B', 'email' => 'doctor.lab-b@clinic.test']);
        $patientA = $this->userWithRole('paciente', ['name' => 'Paciente Lab A', 'email' => 'paciente.lab-a@clinic.test']);
        $patientB = $this->userWithRole('paciente', ['name' => 'Paciente Lab B', 'email' => 'paciente.lab-b@clinic.test']);
        $especialidad = Especialidad::factory()->create(['nombre' => 'Medicina General']);

        $this->createLabOrder($this->createCita($patientA, $doctorA, $especialidad, '2026-07-10', '08:00:00', Cita::ESTADO_REALIZADA), $doctorA, $patientA, ['glucosa', 'biometria_hematica'], 'pendiente_toma');
        $this->createLabOrder($this->createCita($patientA, $doctorB, $especialidad, '2026-07-10', '10:00:00', Cita::ESTADO_REALIZADA), $doctorB, $patientA, ['perfil_lipidico'], 'resultado_listo');
        $this->createLabOrder($this->createCita($patientB, $doctorB, $especialidad, '2026-07-10', '11:00:00', Cita::ESTADO_REALIZADA), $doctorB, $patientB, ['glucosa'], 'resultado_listo', now());
        $this->createLabOrder($this->createCita($patientB, $doctorA, $especialidad, '2026-07-10', '12:00:00', Cita::ESTADO_REALIZADA), $doctorA, $patientB, ['biometria_hematica'], 'cancelado');
        LabOrder::create([
            'patient_id' => $patientB->id,
            'doctor_id' => $doctorA->id,
            'source' => LabOrder::SOURCE_MEDICAL_ORDER,
            'priority' => 'normal',
            'status' => LabOrder::STATUS_MUESTRA_TOMADA,
            'scheduled_at' => now(),
        ]);

        $response = $this->actingAs($lab)->get(route('laboratorio.dashboard.data', ['period' => '30d']));

        $response->assertOk();

        $payload = $response->json();

        $this->assertSame('laboratorio', $payload['role']);
        $this->assertSame(5, $payload['metrics']['received_today']['value']);
        $this->assertSame(1, $payload['metrics']['pending']['value']);
        $this->assertSame(1, $payload['metrics']['in_process']['value']);
        $this->assertSame(2, $payload['metrics']['completed']['value']);
        $this->assertSame(1, $payload['metrics']['delivered']['value']);
        $this->assertSame(1, $payload['metrics']['cancelled']['value']);
        $this->assertSame(1, $payload['charts']['orders_status']['series'][0]);
        $this->assertSame(5, array_sum($payload['charts']['orders_timeline']['series'][0]['data']));
        $this->assertContains('Glucosa', $payload['charts']['top_exams']['labels']);
        $this->assertContains('Doctor Lab A', $payload['charts']['orders_by_doctor']['labels']);
        $this->assertContains('Doctor Lab B', $payload['charts']['orders_by_doctor']['labels']);

        $this->actingAs($doctorA)->get(route('laboratorio.dashboard.data'))->assertRedirect('/');
    }

    public function test_empty_period_returns_zero_series_without_fake_data(): void
    {
        $doctor = $this->userWithRole('doctor');

        $response = $this->actingAs($doctor)->get(route('doctor.dashboard.data', [
            'period' => 'custom',
            'from' => '2099-01-01',
            'to' => '2099-01-02',
        ]));

        $response->assertOk();

        $payload = $response->json();

        $this->assertSame(0, $payload['metrics']['appointments_today']['value']);
        $this->assertSame(0, array_sum($payload['charts']['appointments_status']['series']));
        $this->assertSame(0, array_sum($payload['charts']['patients_timeline']['series'][0]['data']));
        $this->assertSame(0, $payload['metrics']['next_appointment']['value']);
        $this->assertSame([], $payload['lists']['next_appointments']);
        $this->assertSame([], $payload['lists']['follow_up_controls']);
    }

    public function test_superadmin_dashboard_survives_missing_optional_lab_table(): void
    {
        $superadmin = $this->userWithRole('superadmin');

        $realSchema = Schema::getFacadeRoot();
        $mockSchema = \Mockery::mock($realSchema)->makePartial();
        $mockSchema->shouldReceive('hasTable')
            ->with('pedidos_laboratorio')
            ->andReturn(false);
        Schema::swap($mockSchema);

        $response = $this->actingAs($superadmin)->get(route('superadmin.dashboard'));

        $response->assertOk();
        $response->assertSee('data-dashboard-page', false);
        $response->assertSee('Superadmin', false);
    }

    public function test_dashboard_full_integration_and_future_appointment_scenarios(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-11 10:00:00', 'America/Guayaquil'));

        $admin = $this->userWithRole('administrador');
        $doctor = $this->userWithRole('doctor', ['name' => 'Doctor Asignado']);
        $otherDoctor = $this->userWithRole('doctor', ['name' => 'Otro Doctor']);
        $patient = $this->userWithRole('paciente');
        $especialidad = Especialidad::factory()->create(['nombre' => 'Cardiología']);

        // 1. Cita futura (2026-07-13) estado pendiente
        $cita = $this->createCita($patient, $doctor, $especialidad, '2026-07-13', '09:00:00', Cita::ESTADO_PENDIENTE);

        // --- Período "month" (Por defecto) ---
        // Admin
        $responseAdmin = $this->actingAs($admin)->get(route('admin.dashboard.data'));
        $responseAdmin->assertOk();
        $payloadAdmin = $responseAdmin->json();

        $this->assertSame(1, $payloadAdmin['charts']['citas_estado']['series'][0]); // Pendiente
        $this->assertSame('Doctor Asignado', $payloadAdmin['charts']['citas_doctor']['labels'][0]);
        // Series 0 of citas_doctor is Pendiente
        $this->assertSame(1, $payloadAdmin['charts']['citas_doctor']['series'][0]['data'][0]);
        
        $idxTimeline = array_search('13/07', $payloadAdmin['charts']['citas_por_dia_estado']['labels']);
        $this->assertNotFalse($idxTimeline);
        $this->assertSame(1, $payloadAdmin['charts']['citas_por_dia_estado']['series'][0]['data'][$idxTimeline]);

        // Doctor asignado
        $responseDoctor = $this->actingAs($doctor)->get(route('doctor.dashboard.data'));
        $responseDoctor->assertOk();
        $payloadDoctor = $responseDoctor->json();
        $this->assertSame(1, $payloadDoctor['charts']['appointments_status']['series'][0]); // Pendiente
        $idxDocTimeline = array_search('13/07', $payloadDoctor['charts']['appointments_status_timeline']['labels']);
        $this->assertNotFalse($idxDocTimeline);
        $this->assertSame(1, $payloadDoctor['charts']['appointments_status_timeline']['series'][0]['data'][$idxDocTimeline]);

        // Otro doctor no debe ver la cita
        $responseOtherDoctor = $this->actingAs($otherDoctor)->get(route('doctor.dashboard.data'));
        $this->assertSame(0, $responseOtherDoctor->json()['metrics']['appointments_pending']['value']);

        // --- Transición a Confirmada ---
        $cita->estado = Cita::ESTADO_CONFIRMADA;
        $cita->save();

        // Admin
        $responseAdmin = $this->actingAs($admin)->get(route('admin.dashboard.data'));
        $payloadAdmin = $responseAdmin->json();
        $this->assertSame(0, $payloadAdmin['charts']['citas_estado']['series'][0]); // Pendiente
        $this->assertSame(1, $payloadAdmin['charts']['citas_estado']['series'][1]); // Confirmada
        $this->assertSame(0, $payloadAdmin['charts']['citas_doctor']['series'][0]['data'][0]); // Pendiente doctor
        $this->assertSame(1, $payloadAdmin['charts']['citas_doctor']['series'][1]['data'][0]); // Confirmada doctor

        // Doctor
        $responseDoctor = $this->actingAs($doctor)->get(route('doctor.dashboard.data'));
        $payloadDoctor = $responseDoctor->json();
        $this->assertSame(0, $payloadDoctor['charts']['appointments_status']['series'][0]); // Pendiente
        $this->assertSame(1, $payloadDoctor['charts']['appointments_status']['series'][1]); // Confirmada

        // --- Filtro "30d" (No debe incluirla) ---
        $responseAdmin30d = $this->actingAs($admin)->get(route('admin.dashboard.data', ['period' => '30d']));
        $this->assertSame(0, $responseAdmin30d->json()['metrics']['appointments_period']['value']);

        // --- Filtro "next30d" (Sí debe incluirla) ---
        $responseAdminNext30d = $this->actingAs($admin)->get(route('admin.dashboard.data', ['period' => 'next30d']));
        $this->assertSame(1, $responseAdminNext30d->json()['metrics']['appointments_period']['value']);
    }

    public function test_doctor_dashboard_documentos_and_controles_scenarios(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-11 10:00:00', 'America/Guayaquil'));

        $doctor = $this->userWithRole('doctor', ['created_at' => Carbon::parse('2026-07-09 10:00:00', 'America/Guayaquil')]);
        $otherDoctor = $this->userWithRole('doctor');
        $patient = $this->userWithRole('paciente');
        $especialidad = Especialidad::factory()->create(['nombre' => 'General']);

        // 1. Cita original realizada
        $citaOriginal = $this->createCita($patient, $doctor, $especialidad, '2026-07-11', '08:00:00', Cita::ESTADO_REALIZADA);

        // 2. Control agendado en el futuro (pendiente)
        $citaControl = $this->createCita($patient, $doctor, $especialidad, '2026-07-13', '09:00:00', Cita::ESTADO_PENDIENTE);

        NotaSoap::create([
            'cita_id' => $citaOriginal->id,
            'estado' => NotaSoap::ESTADO_FIRMADA,
            'follow_up_cita_id' => $citaControl->id,
            'follow_up_date' => '2026-07-13',
            'signed_by' => $doctor->id,
            'subjetivo_motivo' => 'Control',
            'plan_general' => 'Plan',
        ]);

        // Cita normal de otro doctor (no control)
        $this->createCita($patient, $otherDoctor, $especialidad, '2026-07-13', '10:00:00', Cita::ESTADO_PENDIENTE);

        // Verificar control agrupado como Próximo
        $response = $this->actingAs($doctor)->get(route('doctor.dashboard.data'));
        $payload = $response->json();
        // Controles donut: [Próximos, Realizados, Cancelados, No presentados, Pendientes vencidos]
        $this->assertSame([1, 0, 0, 0, 0], $payload['charts']['controls_status']['series']);

        // Cambiar control a realizada
        $citaControl->estado = Cita::ESTADO_REALIZADA;
        $citaControl->save();

        $response = $this->actingAs($doctor)->get(route('doctor.dashboard.data'));
        $this->assertSame([0, 1, 0, 0, 0], $response->json()['charts']['controls_status']['series']);

        // Cambiar control a cancelada
        $citaControl->estado = Cita::ESTADO_CANCELADA;
        $citaControl->save();

        $response = $this->actingAs($doctor)->get(route('doctor.dashboard.data'));
        $this->assertSame([0, 0, 1, 0, 0], $response->json()['charts']['controls_status']['series']);

        // Crear control vencido (pendiente en el pasado, dentro del periodo de gracia de 30 mins)
        $citaOriginal2 = $this->createCita($patient, $doctor, $especialidad, '2026-07-11', '08:30:00', Cita::ESTADO_REALIZADA);
        $citaControlPasada = $this->createCita($patient, $doctor, $especialidad, '2026-07-11', '09:55:00', Cita::ESTADO_PENDIENTE);
        NotaSoap::create([
            'cita_id' => $citaOriginal2->id,
            'estado' => NotaSoap::ESTADO_FIRMADA,
            'follow_up_cita_id' => $citaControlPasada->id,
            'follow_up_date' => '2026-07-11',
            'signed_by' => $doctor->id,
            'subjetivo_motivo' => 'Control',
            'plan_general' => 'Plan',
        ]);

        $response = $this->actingAs($doctor)->get(route('doctor.dashboard.data'));
        $this->assertSame([0, 0, 1, 0, 1], $response->json()['charts']['controls_status']['series']);

        // --- Documentos ---
        // Receta
        $receta = $this->createRecipe($citaOriginal, $doctor);
        $receta->created_at = '2026-07-10 12:00:00';
        $receta->save();

        // Certificado
        $certificado = $this->createCertificate($citaOriginal, $doctor);
        $certificado->fecha_emision = '2026-07-10';
        $certificado->save();

        // Pedido laboratorio
        $pedido = $this->createLabOrder($citaOriginal, $doctor, $patient, ['glucosa'], 'pendiente_toma');
        $pedido->created_at = '2026-07-10 13:00:00';
        $pedido->save();

        // Documento de otro doctor (no debe contarse)
        $citaOtro = $this->createCita($patient, $otherDoctor, $especialidad, '2026-07-11', '10:00:00', Cita::ESTADO_REALIZADA);
        $this->createRecipe($citaOtro, $otherDoctor);

        $response = $this->actingAs($doctor)->get(route('doctor.dashboard.data'));
        $payloadDoc = $response->json();
        // Recetas, Certificados, Pedidos de lab = 1 cada uno
        $this->assertSame(3, $payloadDoc['metrics']['documents_total']['value']);
        $this->assertSame([1, 1, 1], $payloadDoc['charts']['documents_type']['series'][0]['data']);
    }

    private function userWithRole(string $roleName, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'status' => User::STATUS_ACTIVE,
            'active' => true,
        ], $attributes));

        $role = Role::firstOrCreate(['name' => $roleName]);
        $user->roles()->sync([$role->id]);

        return $user->fresh();
    }

    private function createCita(User $paciente, User $doctor, Especialidad $especialidad, string $fecha, string $hora, string $estado): Cita
    {
        return Cita::create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => $fecha,
            'hora' => $hora,
            'motivo_consulta' => 'Consulta QA',
            'estado' => $estado,
            'activo' => true,
        ]);
    }

    private function createRecipe(Cita $cita, User $doctor, ?NotaSoap $nota = null): Receta
    {
        return Receta::create([
            'cita_id' => $cita->id,
            'nota_soap_id' => $nota?->id,
            'clinical_record_id' => null,
            'diagnostico' => 'Diagnostico QA',
            'medicamentos' => 'Medicamento QA',
            'indicaciones' => 'Indicaciones QA',
        ]);
    }

    private function createCertificate(Cita $cita, User $doctor): CertificadoMedico
    {
        return CertificadoMedico::create([
            'codigo' => 'CERT-'.str_pad((string) $cita->id, 6, '0', STR_PAD_LEFT),
            'cita_id' => $cita->id,
            'paciente_id' => $cita->paciente_id,
            'doctor_id' => $doctor->id,
            'clinical_record_id' => null,
            'fecha_emision' => now(),
            'texto_constancia' => 'Certificado QA',
            'dias_reposo' => 1,
        ]);
    }

    private function createLabOrder(Cita $cita, User $doctor, User $patient, array $examenes, string $estado, ?Carbon $resultadoEnviadoEn = null): PedidoLaboratorio
    {
        return PedidoLaboratorio::create([
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'examenes' => $examenes,
            'estado' => $estado,
            'resultado_enviado_at' => $resultadoEnviadoEn,
        ]);
    }

    public function test_system_start_date_timezone_safe(): void
    {
        $tz = config('app.timezone', 'America/Guayaquil');
        $dateStr = '2025-05-15 14:30:25';
        $user = $this->userWithRole('superadmin', ['created_at' => Carbon::parse($dateStr, $tz)]);
        
        $service = app(\App\Services\DashboardAnalyticsService::class);
        $now = Carbon::parse('2026-07-10 10:00:00', $tz);
        
        $range = $service->resolveRange(['period' => 'all'], 'month', $now);
        
        $expectedStart = Carbon::parse('2025-05-15 00:00:00', $tz);
        $this->assertTrue($range['timeline_start']->equalTo($expectedStart), "Timeline start should be system start date");
    }

    public function test_system_start_date_empty_fallback(): void
    {
        \DB::table('role_user')->delete();
        \DB::table('users')->delete();
        
        $tz = config('app.timezone', 'America/Guayaquil');
        $now = Carbon::parse('2026-07-10 10:00:00', $tz);
        $service = app(\App\Services\DashboardAnalyticsService::class);
        
        $range = $service->resolveRange(['period' => 'all'], 'month', $now);
        
        $this->assertTrue($range['timeline_start']->equalTo($now->copy()->startOfDay()));
    }

    public function test_allowed_period_filters_only(): void
    {
        $user = $this->userWithRole('superadmin');
        $service = app(\App\Services\DashboardAnalyticsService::class);
        
        $rangeInvalid = $service->resolveRange(['period' => 'invalid']);
        $this->assertSame('month', $rangeInvalid['period']);
        
        $rangeToday = $service->resolveRange(['period' => 'today']);
        $this->assertSame('month', $rangeToday['period']);
        
        $rangeCustom = $service->resolveRange(['period' => 'custom']);
        $this->assertSame('month', $rangeCustom['period']);
    }

    public function test_inbound_period_validation(): void
    {
        $admin = $this->userWithRole('administrador');
        
        $response = $this->actingAs($admin)->get(route('admin.dashboard.data', ['period' => 'invalid']));
        $response->assertOk();
        $this->assertSame('month', $response->json()['filters']['period']);
    }

    public function test_last_7_days_exact_bounds(): void
    {
        $tz = config('app.timezone', 'America/Guayaquil');
        $now = Carbon::parse('2026-07-10 15:30:00', $tz);
        $this->userWithRole('superadmin', ['created_at' => Carbon::parse('2026-01-01 00:00:00', $tz)]);
        
        $service = app(\App\Services\DashboardAnalyticsService::class);
        $range = $service->resolveRange(['period' => '7d'], 'month', $now);
        
        $this->assertSame('2026-07-04', $range['start']->toDateString());
        $this->assertSame('00:00:00', $range['start']->toTimeString());
        
        $this->assertSame('2026-07-10', $range['end']->toDateString());
        $this->assertSame('23:59:59', $range['end']->toTimeString());
    }

    public function test_last_7_days_start_date_newer_than_installation(): void
    {
        $tz = config('app.timezone', 'America/Guayaquil');
        $now = Carbon::parse('2026-07-10 15:30:00', $tz);
        $this->userWithRole('superadmin', ['created_at' => Carbon::parse('2026-07-08 12:00:00', $tz)]);
        
        $service = app(\App\Services\DashboardAnalyticsService::class);
        $range = $service->resolveRange(['period' => '7d'], 'month', $now);
        
        $this->assertSame('2026-07-08', $range['start']->toDateString());
        $this->assertSame('2026-07-10', $range['end']->toDateString());
    }

    public function test_last_30_days_exact_bounds(): void
    {
        $tz = config('app.timezone', 'America/Guayaquil');
        $now = Carbon::parse('2026-07-10 12:00:00', $tz);
        $this->userWithRole('superadmin', ['created_at' => Carbon::parse('2026-01-01 00:00:00', $tz)]);
        
        $service = app(\App\Services\DashboardAnalyticsService::class);
        $range = $service->resolveRange(['period' => '30d'], 'month', $now);
        
        $this->assertSame('2026-06-11', $range['start']->toDateString());
        $this->assertSame('2026-07-10', $range['end']->toDateString());
    }

    public function test_this_month_bounds(): void
    {
        $tz = config('app.timezone', 'America/Guayaquil');
        $now = Carbon::parse('2026-07-10 12:00:00', $tz);
        $this->userWithRole('superadmin', ['created_at' => Carbon::parse('2026-01-01 00:00:00', $tz)]);
        
        $service = app(\App\Services\DashboardAnalyticsService::class);
        $range = $service->resolveRange(['period' => 'month'], 'month', $now);
        
        $this->assertSame('2026-07-01', $range['start']->toDateString());
        $this->assertSame('2026-07-31', $range['end']->toDateString());
    }

    public function test_this_year_bounds(): void
    {
        $tz = config('app.timezone', 'America/Guayaquil');
        $now = Carbon::parse('2026-07-10 12:00:00', $tz);
        $this->userWithRole('superadmin', ['created_at' => Carbon::parse('2026-01-01 00:00:00', $tz)]);
        
        $service = app(\App\Services\DashboardAnalyticsService::class);
        $range = $service->resolveRange(['period' => 'year'], 'month', $now);
        
        $this->assertSame('2026-01-01', $range['start']->toDateString());
        $this->assertSame('2026-12-31', $range['end']->toDateString());
    }

    public function test_all_period_no_constraints(): void
    {
        $tz = config('app.timezone', 'America/Guayaquil');
        $now = Carbon::parse('2026-07-10 12:00:00', $tz);
        $this->userWithRole('superadmin', ['created_at' => Carbon::parse('2026-01-01 00:00:00', $tz)]);
        
        $service = app(\App\Services\DashboardAnalyticsService::class);
        $range = $service->resolveRange(['period' => 'all'], 'month', $now);
        
        $this->assertNull($range['start']);
        $this->assertNull($range['end']);
    }

    public function test_all_timeline_bounds_start(): void
    {
        $tz = config('app.timezone', 'America/Guayaquil');
        $now = Carbon::parse('2026-07-10 12:00:00', $tz);
        $this->userWithRole('superadmin', ['created_at' => Carbon::parse('2025-03-15 10:00:00', $tz)]);
        
        $service = app(\App\Services\DashboardAnalyticsService::class);
        $range = $service->resolveRange(['period' => 'all'], 'month', $now);
        
        $this->assertSame('2025-03-15', $range['timeline_start']->toDateString());
    }

    public function test_all_timeline_bounds_end(): void
    {
        $tz = config('app.timezone', 'America/Guayaquil');
        $now = Carbon::parse('2026-07-10 12:00:00', $tz);
        $doctor = $this->userWithRole('doctor');
        $patient = $this->userWithRole('paciente', ['created_at' => Carbon::parse('2026-01-01 10:00:00', $tz)]);
        $especialidad = Especialidad::factory()->create();
        
        $this->createCita($patient, $doctor, $especialidad, '2026-09-25', '10:00:00', Cita::ESTADO_CONFIRMADA);
        
        $service = app(\App\Services\DashboardAnalyticsService::class);
        $range = $service->resolveRange(['period' => 'all'], 'month', $now);
        
        $this->assertSame('2026-09-25', $range['timeline_end']->toDateString());
    }

    public function test_all_grouping_less_than_2_years(): void
    {
        $tz = config('app.timezone', 'America/Guayaquil');
        $now = Carbon::parse('2026-07-10 12:00:00', $tz);
        $this->userWithRole('superadmin', ['created_at' => Carbon::parse('2025-01-01 10:00:00', $tz)]);
        
        $service = app(\App\Services\DashboardAnalyticsService::class);
        $range = $service->resolveRange(['period' => 'all'], 'month', $now);
        
        $this->assertSame('month', $range['grouping']);
    }

    public function test_all_grouping_more_than_2_years(): void
    {
        $tz = config('app.timezone', 'America/Guayaquil');
        $now = Carbon::parse('2026-07-10 12:00:00', $tz);
        $this->userWithRole('superadmin', ['created_at' => Carbon::parse('2023-01-01 10:00:00', $tz)]);
        
        $service = app(\App\Services\DashboardAnalyticsService::class);
        $range = $service->resolveRange(['period' => 'all'], 'month', $now);
        
        $this->assertSame('year', $range['grouping']);
    }

    public function test_daily_label_format_same_year(): void
    {
        $tz = config('app.timezone', 'America/Guayaquil');
        $now = Carbon::parse('2026-07-10 12:00:00', $tz);
        Carbon::setTestNow($now);
        $this->userWithRole('superadmin', ['created_at' => Carbon::parse('2026-01-01 00:00:00', $tz)]);
        
        $admin = $this->userWithRole('administrador');
        $response = $this->actingAs($admin)->get(route('admin.dashboard.data', ['period' => '7d']));
        
        $labels = $response->json()['charts']['pacientes_nuevos_atendidos']['labels'];
        $this->assertSame('04/07', $labels[0]);
    }

    public function test_daily_label_format_cross_year(): void
    {
        $tz = config('app.timezone', 'America/Guayaquil');
        $now = Carbon::parse('2026-01-03 12:00:00', $tz);
        Carbon::setTestNow($now);
        $this->userWithRole('superadmin', ['created_at' => Carbon::parse('2025-12-01 00:00:00', $tz)]);
        
        $admin = $this->userWithRole('administrador');
        $response = $this->actingAs($admin)->get(route('admin.dashboard.data', ['period' => '7d']));
        
        $labels = $response->json()['charts']['pacientes_nuevos_atendidos']['labels'];
        $this->assertSame('28/12/2025', $labels[0]);
    }

    public function test_monthly_label_format(): void
    {
        $tz = config('app.timezone', 'America/Guayaquil');
        $now = Carbon::parse('2026-07-10 12:00:00', $tz);
        Carbon::setTestNow($now);
        $this->userWithRole('superadmin', ['created_at' => Carbon::parse('2026-01-01 00:00:00', $tz)]);
        
        $admin = $this->userWithRole('administrador');
        $response = $this->actingAs($admin)->get(route('admin.dashboard.data', ['period' => 'year']));
        
        $labels = $response->json()['charts']['citas_por_dia_estado']['labels'];
        $this->assertSame('Ene 2026', $labels[0]);
    }

    public function test_yearly_label_format(): void
    {
        $tz = config('app.timezone', 'America/Guayaquil');
        $now = Carbon::parse('2026-07-10 12:00:00', $tz);
        Carbon::setTestNow($now);
        $this->userWithRole('superadmin', ['created_at' => Carbon::parse('2023-01-01 10:00:00', $tz)]);
        
        $admin = $this->userWithRole('administrador');
        $response = $this->actingAs($admin)->get(route('admin.dashboard.data', ['period' => 'all']));
        
        $labels = $response->json()['charts']['pacientes_nuevos_atendidos']['labels'];
        $this->assertSame('2023', $labels[0]);
    }

    public function test_date_vs_timestamp_filtering(): void
    {
        $tz = config('app.timezone', 'America/Guayaquil');
        $now = Carbon::parse('2026-07-10 12:00:00', $tz);
        Carbon::setTestNow($now);
        $doctor = $this->userWithRole('doctor');
        $patient = $this->userWithRole('paciente', ['created_at' => Carbon::parse('2026-07-01 10:00:00', $tz)]);
        $especialidad = Especialidad::factory()->create();
        
        $this->createCita($patient, $doctor, $especialidad, '2026-07-10', '10:00:00', Cita::ESTADO_REALIZADA);
        
        $recipe = $this->createRecipe(Cita::first(), $doctor);
        $recipe->created_at = Carbon::parse('2026-07-10 14:00:00', $tz);
        $recipe->save();
        
        $service = app(\App\Services\DashboardAnalyticsService::class);
        
        $docs = $service->buildSuperadminDashboard($doctor, ['period' => '7d']);
        
        $this->assertSame(1, $docs['metrics']['appointments_period']['value']);
        $this->assertSame(1, $docs['metrics']['documents_total']['value']);
    }
}
