<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\CitaRecordatorio;
use App\Models\Especialidad;
use App\Models\Role;
use App\Models\User;
use App\Services\CitaComprobanteService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRecordatoriosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-04-08 12:30:00', 'America/Guayaquil'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_y_listado_muestran_recordatorios_pendientes_visibles_y_habilitan_solo_los_que_ya_estan_en_ventana(): void
    {
        $admin = $this->createUserWithRole('administrador');
        $doctor = $this->createUserWithRole('doctor', ['name' => 'Andrea Ortega']);
        $especialidad = Especialidad::factory()->create(['nombre' => 'Cardiologia']);

        $pacienteConWhatsapp = $this->createUserWithRole('paciente', [
            'name' => 'Maria Perez',
            'telefono' => '0991234567',
        ]);

        $pacienteSinWhatsapp = $this->createUserWithRole('paciente', [
            'name' => 'Luis Gomez',
            'telefono' => '123',
        ]);

        $citaHabilitada = $this->createReminderAppointment(
            $pacienteConWhatsapp,
            $doctor,
            $especialidad,
            '14:00:00',
            '2026-04-09',
            '2026-04-07 08:00:00'
        );

        $citaFutura = $this->createReminderAppointment(
            $pacienteSinWhatsapp,
            $doctor,
            $especialidad,
            '13:00:00',
            '2026-04-10',
            '2026-04-07 08:00:00',
            Cita::ESTADO_PENDIENTE
        );

        $dashboard = $this->actingAs($admin)->get(route('admin.dashboard'));

        $dashboard->assertOk();
        $dashboard->assertSee('Tienes recordatorios por enviar');
        $dashboard->assertSee(route('admin.recordatorios.index'), false);

        $response = $this->actingAs($admin)->get(route('admin.recordatorios.index'));

        $response->assertOk();
        $response->assertSee('Maria Perez');
        $response->assertSee('Luis Gomez');
        $response->assertSee('Cardiologia');
        $response->assertSee('Pendientes por enviar');
        $response->assertSee('Enviar por WhatsApp');
        $response->assertSee('Proximamente');
        $response->assertSee('Sin telefono valido');
        $response->assertSee('https://wa.me/593991234567', false);

        $this->assertDatabaseHas('cita_recordatorios', [
            'cita_id' => $citaHabilitada->id,
            'estado' => CitaRecordatorio::ESTADO_PENDIENTE,
        ]);

        $this->assertDatabaseHas('cita_recordatorios', [
            'cita_id' => $citaFutura->id,
            'estado' => CitaRecordatorio::ESTADO_PENDIENTE,
        ]);
    }

    public function test_admin_puede_marcar_recordatorio_como_enviado_cuando_ya_entro_en_ventana(): void
    {
        $admin = $this->createUserWithRole('administrador');
        $doctor = $this->createUserWithRole('doctor', ['name' => 'Sofia Cedeno']);
        $paciente = $this->createUserWithRole('paciente', [
            'name' => 'Ana Torres',
            'telefono' => '0987654321',
        ]);
        $especialidad = Especialidad::factory()->create(['nombre' => 'Medicina general']);

        $cita = $this->createReminderAppointment(
            $paciente,
            $doctor,
            $especialidad,
            '14:00:00',
            '2026-04-09',
            '2026-04-07 08:00:00'
        );
        $recordatorio = CitaRecordatorio::firstWhere('cita_id', $cita->id);

        $response = $this->actingAs($admin)->patch(route('admin.recordatorios.enviado', $recordatorio));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $recordatorio->refresh();
        $this->assertSame(CitaRecordatorio::ESTADO_ENVIADO, $recordatorio->estado);
        $this->assertSame($admin->id, $recordatorio->gestionado_por);
        $this->assertNotNull($recordatorio->enviado_at);
    }

    public function test_recordatorio_enviado_aparece_en_vista_de_historial(): void
    {
        $admin = $this->createUserWithRole('administrador');
        $doctor = $this->createUserWithRole('doctor', ['name' => 'Sofia Cedeno']);
        $paciente = $this->createUserWithRole('paciente', [
            'name' => 'Ana Torres',
            'telefono' => '0987654321',
        ]);
        $especialidad = Especialidad::factory()->create(['nombre' => 'Medicina general']);

        $cita = $this->createReminderAppointment(
            $paciente,
            $doctor,
            $especialidad,
            '14:00:00',
            '2026-04-09',
            '2026-04-07 08:00:00'
        );
        $recordatorio = CitaRecordatorio::firstWhere('cita_id', $cita->id);

        $this->actingAs($admin)->patch(route('admin.recordatorios.enviado', $recordatorio));

        $response = $this->actingAs($admin)->get(route('admin.recordatorios.enviados'));

        $response->assertOk();
        $response->assertSee('Recordatorios enviados');
        $response->assertSee('Ana Torres');
        $response->assertSee('Enviado');
        $response->assertViewHas('recordatoriosEnviados', fn ($paginator) => $paginator->total() === 1);
    }

    public function test_estado_vacio_de_pendientes_muestra_enlace_a_recordatorios_enviados(): void
    {
        $admin = $this->createUserWithRole('administrador');

        $response = $this->actingAs($admin)->get(route('admin.recordatorios.index'));

        $response->assertOk();
        $response->assertSee('Recordatorios enviados');
        $response->assertSee(route('admin.recordatorios.enviados'), false);
        $response->assertDontSee('Volver al panel');
    }

    public function test_admin_no_puede_marcar_recordatorio_como_enviado_si_aun_faltan_dos_o_mas_dias(): void
    {
        $admin = $this->createUserWithRole('administrador');
        $doctor = $this->createUserWithRole('doctor');
        $paciente = $this->createUserWithRole('paciente', ['telefono' => '0995554433']);
        $especialidad = Especialidad::factory()->create();

        $cita = $this->createReminderAppointment(
            $paciente,
            $doctor,
            $especialidad,
            '12:00:00',
            '2026-04-10',
            '2026-04-07 08:00:00'
        );
        $recordatorio = CitaRecordatorio::firstWhere('cita_id', $cita->id);

        $response = $this->actingAs($admin)->patch(route('admin.recordatorios.enviado', $recordatorio));

        $response->assertRedirect();
        $response->assertSessionHasErrors('error');

        $recordatorio->refresh();
        $this->assertSame(CitaRecordatorio::ESTADO_PENDIENTE, $recordatorio->estado);
        $this->assertNull($recordatorio->gestionado_por);
        $this->assertNull($recordatorio->enviado_at);
    }

    public function test_admin_puede_omitir_recordatorio_cuando_ya_entro_en_ventana(): void
    {
        $admin = $this->createUserWithRole('administrador');
        $doctor = $this->createUserWithRole('doctor');
        $paciente = $this->createUserWithRole('paciente', ['telefono' => '0995554433']);
        $especialidad = Especialidad::factory()->create();

        $cita = $this->createReminderAppointment(
            $paciente,
            $doctor,
            $especialidad,
            '12:00:00',
            '2026-04-09',
            '2026-04-07 08:00:00'
        );
        $recordatorio = CitaRecordatorio::firstWhere('cita_id', $cita->id);

        $response = $this->actingAs($admin)->patch(route('admin.recordatorios.omitido', $recordatorio));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $recordatorio->refresh();
        $this->assertSame(CitaRecordatorio::ESTADO_OMITIDO, $recordatorio->estado);
        $this->assertSame($admin->id, $recordatorio->gestionado_por);
        $this->assertNotNull($recordatorio->omitido_at);
    }

    public function test_recordatorio_aparece_desde_las_doce_del_dia_anterior_pero_se_lista_antes_como_proximo(): void
    {
        $admin = $this->createUserWithRole('administrador');
        $doctor = $this->createUserWithRole('doctor');
        $paciente = $this->createUserWithRole('paciente', ['name' => 'Carlos Ruiz', 'telefono' => '0991112233']);
        $especialidad = Especialidad::factory()->create();

        $this->createReminderAppointment(
            $paciente,
            $doctor,
            $especialidad,
            '09:00:00',
            '2026-04-09',
            '2026-04-07 08:00:00'
        );

        Carbon::setTestNow(Carbon::parse('2026-04-08 11:59:00', 'America/Guayaquil'));
        $antes = $this->actingAs($admin)->get(route('admin.recordatorios.index'));
        $antes->assertOk();
        $antes->assertSee('Carlos Ruiz');
        $antes->assertSee('Proximamente');

        Carbon::setTestNow(Carbon::parse('2026-04-08 12:00:00', 'America/Guayaquil'));
        $despues = $this->actingAs($admin)->get(route('admin.recordatorios.index'));
        $despues->assertOk();
        $despues->assertSee('Carlos Ruiz');
        $despues->assertSee('Enviar por WhatsApp');
    }

    public function test_recordatorio_confirmado_del_mismo_dia_aun_no_vencido_sigue_visible_y_habilitado(): void
    {
        $admin = $this->createUserWithRole('administrador');
        $doctor = $this->createUserWithRole('doctor', ['name' => 'Elena Santos']);
        $paciente = $this->createUserWithRole('paciente', ['name' => 'Carlos Ruiz', 'telefono' => '0991112233']);
        $especialidad = Especialidad::factory()->create(['nombre' => 'Pediatria']);

        $this->createReminderAppointment(
            $paciente,
            $doctor,
            $especialidad,
            '12:30:00',
            '2026-04-09',
            '2026-04-07 08:00:00'
        );

        Carbon::setTestNow(Carbon::parse('2026-04-09 10:00:00', 'America/Guayaquil'));

        $response = $this->actingAs($admin)->get(route('admin.recordatorios.index'));

        $response->assertOk();
        $response->assertSee('Carlos Ruiz');
        $response->assertSee('Pediatria');
        $response->assertSee('12:30');
        $response->assertSee('Confirmada');
        $response->assertSee('Enviar por WhatsApp');
        $response->assertViewHas('stats', fn (array $stats): bool => $stats['pendientes'] === 1
            && $stats['con_telefono'] === 1
            && $stats['sin_telefono'] === 0);
    }

    public function test_cita_sin_un_dia_real_de_anticipacion_no_genera_recordatorio_nuevo(): void
    {
        $admin = $this->createUserWithRole('administrador');
        $doctor = $this->createUserWithRole('doctor');
        $paciente = $this->createUserWithRole('paciente', ['name' => 'Lucia Mora', 'telefono' => '0992223344']);
        $especialidad = Especialidad::factory()->create();

        $cita = $this->createReminderAppointment(
            $paciente,
            $doctor,
            $especialidad,
            '08:00:00',
            '2026-04-09',
            '2026-04-08 09:30:00'
        );

        $response = $this->actingAs($admin)->get(route('admin.recordatorios.index'));

        $response->assertOk();
        $response->assertDontSee('Lucia Mora');
        $this->assertDatabaseMissing('cita_recordatorios', [
            'cita_id' => $cita->id,
        ]);
    }

    public function test_dashboard_admin_sigue_cargando_si_falla_la_regeneracion_del_comprobante_de_una_cita_vencida(): void
    {
        $admin = $this->createUserWithRole('administrador');
        $doctor = $this->createUserWithRole('doctor');
        $paciente = $this->createUserWithRole('paciente', ['telefono' => '0992223344']);
        $especialidad = Especialidad::factory()->create();

        $cita = Cita::factory()->create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => '2026-04-08',
            'hora' => '10:30:00',
            'estado' => Cita::ESTADO_CONFIRMADA,
            'activo' => true,
        ]);

        $mock = \Mockery::mock(CitaComprobanteService::class);
        $mock->shouldReceive('sincronizarComprobante')
            ->once()
            ->andThrow(new \RuntimeException('dompdf fallo al regenerar logo webp'));
        $this->app->instance(CitaComprobanteService::class, $mock);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();

        $cita->refresh();
        $this->assertSame(Cita::ESTADO_NO_SE_PRESENTO, $cita->estado);
        $this->assertFalse($cita->activo);
    }

    private function createReminderAppointment(
        User $paciente,
        User $doctor,
        Especialidad $especialidad,
        string $hora,
        string $fecha,
        ?string $createdAt = null,
        string $estado = Cita::ESTADO_CONFIRMADA
    ): Cita {
        $attributes = [
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => $fecha,
            'hora' => $hora,
            'estado' => $estado,
            'activo' => true,
        ];

        if ($createdAt !== null) {
            $attributes['created_at'] = $createdAt;
        }

        return Cita::factory()->create($attributes);
    }

    private function createUserWithRole(string $roleName, array $attributes = []): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user = User::factory()->create(array_merge([
            'status' => User::STATUS_ACTIVE,
            'suspended_until' => null,
        ], $attributes));
        $user->roles()->sync([$role->id]);

        return $user;
    }
}
