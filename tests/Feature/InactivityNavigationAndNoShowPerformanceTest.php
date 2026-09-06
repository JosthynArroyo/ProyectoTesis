<?php

namespace Tests\Feature;

use App\Jobs\NotificarCambioEstadoCitaJob;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Horario;
use App\Models\Role;
use App\Models\User;
use App\Services\CitaNoShowService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InactivityNavigationAndNoShowPerformanceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-08-26 10:00:00', 'America/Guayaquil'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createAdmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'administrador'], ['label' => 'Administrador']);
        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function createDoctor(string $name = 'Doctor Test'): User
    {
        $role = Role::firstOrCreate(['name' => 'doctor'], ['label' => 'Doctor']);
        $user = User::factory()->create([
            'name' => $name,
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function createPatient(string $name = 'Paciente Test'): User
    {
        $role = Role::firstOrCreate(['name' => 'paciente'], ['label' => 'Paciente']);
        $user = User::factory()->create([
            'name' => $name,
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    public function test_admin_can_navigate_to_horarios_successfully(): void
    {
        $admin = $this->createAdmin();
        $doctor = $this->createDoctor();
        $especialidad = Especialidad::factory()->create();

        Horario::create([
            'doctor_id' => $doctor->id,
            'fecha' => '2026-08-26',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '12:00:00',
            'intervalo_minutos' => 30,
            'activo' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.horarios.index'));

        $response->assertOk();
        $response->assertSee('Horarios de doctores');
    }

    public function test_no_show_service_dispatches_notification_job_to_queue_asynchronously(): void
    {
        Queue::fake();

        $doctor = $this->createDoctor();
        $paciente = $this->createPatient();
        $especialidad = Especialidad::factory()->create();

        $citaVencida = Cita::create([
            'doctor_id' => $doctor->id,
            'paciente_id' => $paciente->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => '2026-08-26',
            'hora' => '08:00:00',
            'estado' => Cita::ESTADO_CONFIRMADA,
            'activo' => true,
            'origen' => 'web',
        ]);

        $service = app(CitaNoShowService::class);
        $marcadas = $service->marcarVencidas('America/Guayaquil', 30);

        $this->assertCount(1, $marcadas);
        $this->assertEquals(Cita::ESTADO_NO_SE_PRESENTO, $citaVencida->fresh()->estado);
        $this->assertFalse((bool) $citaVencida->fresh()->activo);

        Queue::assertPushed(NotificarCambioEstadoCitaJob::class, function ($job) use ($citaVencida) {
            return $job->cita->id === $citaVencida->id
                && $job->evento === 'no_se_presento'
                && $job->quien === 'sistema';
        });
    }

    public function test_navigation_to_horarios_after_inactivity_with_expired_appointments_responds_200_without_500(): void
    {
        Queue::fake();

        $admin = $this->createAdmin();
        $doctor = $this->createDoctor();
        $paciente = $this->createPatient();
        $especialidad = Especialidad::factory()->create();

        // Simular cita que venció durante el período de inactividad
        Cita::create([
            'doctor_id' => $doctor->id,
            'paciente_id' => $paciente->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => '2026-08-26',
            'hora' => '07:30:00',
            'estado' => Cita::ESTADO_CONFIRMADA,
            'activo' => true,
            'origen' => 'web',
        ]);

        // Simular llamada de resumen y posterior navegación a horarios
        $resumenResponse = $this->actingAs($admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('admin.dashboard.resumen'));
        $resumenResponse->assertOk();
        $resumenResponse->assertJsonStructure(['agendadas', 'completadas', 'canceladas']);

        $horariosResponse = $this->actingAs($admin)->get(route('admin.horarios.index'));
        $horariosResponse->assertOk();
        $horariosResponse->assertSee('Horarios de doctores');
    }
}
