<?php

namespace Tests\Feature;

use App\Models\AppointmentSlotHold;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Horario;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ActiveSchedulerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-05-01 10:00:00', 'America/Guayaquil'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * CASO A — EXPIRACIÓN REGISTRADA: citas:expirar-slot-holds está en el scheduler activo.
     */
    public function test_caso_a_expiracion_slot_holds_esta_registrada_en_scheduler_activo(): void
    {
        $events = $this->getScheduledEvents();
        $holdsEvents = array_filter($events, fn (Event $e) => str_contains((string) $e->command, 'citas:expirar-slot-holds'));

        $this->assertCount(1, $holdsEvents, 'citas:expirar-slot-holds debe estar registrada exactamente una vez en el scheduler.');
    }

    /**
     * CASO B — PRIORIDAD REGISTRADA: citas:recalcular-prioridad está en el scheduler activo.
     */
    public function test_caso_b_recalcular_prioridad_esta_registrada_en_scheduler_activo(): void
    {
        $events = $this->getScheduledEvents();
        $priorityEvents = array_filter($events, fn (Event $e) => str_contains((string) $e->command, 'citas:recalcular-prioridad'));

        $this->assertCount(1, $priorityEvents, 'citas:recalcular-prioridad debe estar registrada exactamente una vez en el scheduler.');
    }

    /**
     * CASO C — WHATSAPP NO PROGRAMADO: citas:recordatorio-whatsapp NO debe existir en el scheduler.
     */
    public function test_caso_c_whatsapp_no_esta_programado(): void
    {
        $events = $this->getScheduledEvents();
        $whatsappEvents = array_filter($events, fn (Event $e) => str_contains((string) $e->command, 'citas:recordatorio-whatsapp'));

        $this->assertEmpty($whatsappEvents, 'citas:recordatorio-whatsapp no debe formar parte del scheduler activo.');
    }

    /**
     * CASO D — SIN DUPLICADOS: Cada comando programado debe aparecer a lo sumo una vez.
     */
    public function test_caso_d_sin_comandos_duplicados_en_scheduler(): void
    {
        $events = $this->getScheduledEvents();
        $commands = [];

        foreach ($events as $event) {
            if ($event->command) {
                $commands[] = $event->command;
            }
        }

        $uniqueCommands = array_unique($commands);
        $this->assertCount(count($uniqueCommands), $commands, 'No debe haber comandos programados duplicados en el scheduler.');
    }

    /**
     * CASO E — FRECUENCIAS: Las frecuencias configuradas corresponden a las legítimas.
     */
    public function test_caso_e_frecuencias_y_restricciones_configuradas(): void
    {
        $events = $this->getScheduledEvents();

        $eventMap = [];
        foreach ($events as $event) {
            if ($event->command) {
                $eventMap[$event->command] = $event;
            }
        }

        // citas:expirar-slot-holds -> cada minuto (* * * * *)
        $holdEvent = $this->findEventByCommandSnippet($events, 'citas:expirar-slot-holds');
        $this->assertNotNull($holdEvent, 'citas:expirar-slot-holds debe existir');
        $this->assertSame('* * * * *', $holdEvent->expression);
        $this->assertTrue($holdEvent->withoutOverlapping);

        // citas:recalcular-prioridad -> cada 30 minutos (*/30 * * * *)
        $prioEvent = $this->findEventByCommandSnippet($events, 'citas:recalcular-prioridad');
        $this->assertNotNull($prioEvent, 'citas:recalcular-prioridad debe existir');
        $this->assertSame('*/30 * * * *', $prioEvent->expression);
        $this->assertTrue($prioEvent->withoutOverlapping);

        // citas:marcar-no-show -> cada 10 minutos (*/10 * * * *)
        $noShowEvent = $this->findEventByCommandSnippet($events, 'citas:marcar-no-show');
        $this->assertNotNull($noShowEvent);
        $this->assertSame('*/10 * * * *', $noShowEvent->expression);
        $this->assertTrue($noShowEvent->withoutOverlapping);

        // users:deactivate-inactive -> diario a las 02:30 (30 2 * * *)
        $deactivateEvent = $this->findEventByCommandSnippet($events, 'users:deactivate-inactive');
        $this->assertNotNull($deactivateEvent);
        $this->assertSame('30 2 * * *', $deactivateEvent->expression);
        $this->assertTrue($deactivateEvent->withoutOverlapping);
    }

    /**
     * CASO F — COMANDOS EXISTENTES: Todos los comandos programados son válidos y reconocidos por Artisan.
     */
    public function test_caso_f_todos_los_comandos_programados_existen_en_artisan(): void
    {
        $events = $this->getScheduledEvents();
        $artisan = Artisan::all();

        foreach ($events as $event) {
            if (! $event->command) {
                continue; // Schedule::call u otros callbacks
            }

            // Extraer el nombre del comando Artisan tras la invocación de artisan
            if (preg_match("/artisan(?:'|\")?\s+([a-z0-9\-_:]+)/i", $event->command, $matches)) {
                $cmdName = $matches[1];
                $this->assertArrayHasKey($cmdName, $artisan, "El comando programado '{$cmdName}' debe existir en la lista de comandos de Artisan.");
            }
        }
    }

    /**
     * CASO G — EXPIRACIÓN FUNCIONAL: Ejecución de citas:expirar-slot-holds expira únicamente holds vencidos.
     */
    public function test_caso_g_expiracion_funcional_de_slot_holds(): void
    {
        [$doctor] = $this->createDoctorWithSchedule('2026-05-20');

        // Hold vencido
        $expiredHold = AppointmentSlotHold::create([
            'doctor_id' => $doctor->id,
            'fecha' => '2026-05-20',
            'hora' => '09:00:00',
            'token' => 'stale-token-test',
            'status' => AppointmentSlotHold::STATUS_ACTIVE,
            'expires_at' => now()->subMinute(),
        ]);

        // Hold activo vigente
        $activeHold = AppointmentSlotHold::create([
            'doctor_id' => $doctor->id,
            'fecha' => '2026-05-20',
            'hora' => '09:30:00',
            'token' => 'active-token-test',
            'status' => AppointmentSlotHold::STATUS_ACTIVE,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->artisan('citas:expirar-slot-holds')
            ->expectsOutputToContain('Holds expirados: 1')
            ->assertSuccessful();

        $this->assertSame(AppointmentSlotHold::STATUS_EXPIRED, $expiredHold->fresh()->status);
        $this->assertSame(AppointmentSlotHold::STATUS_ACTIVE, $activeHold->fresh()->status);
    }

    /**
     * CASO H — RECÁLCULO FUNCIONAL: Ejecución de citas:recalcular-prioridad procesa citas correctamente.
     */
    public function test_caso_h_recalculo_funcional_de_prioridad(): void
    {
        [$doctor, $especialidad] = $this->createDoctorWithSchedule('2026-05-20');
        $paciente = $this->createUserWithRole('paciente');

        $cita = Cita::create([
            'doctor_id' => $doctor->id,
            'paciente_id' => $paciente->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => '2026-05-20 00:00:00',
            'hora' => '09:00:00',
            'estado' => Cita::ESTADO_PENDIENTE,
            'activo' => true,
            'motivo_consulta' => 'Control de rutina',
            'prioridad_fuente' => Cita::FUENTE_PRIORIDAD_AUTOMATICA,
            'prioridad_nivel' => Cita::PRIORIDAD_BAJA,
        ]);

        $this->artisan('citas:recalcular-prioridad')
            ->assertSuccessful();

        $this->assertDatabaseHas('citas_medicas', [
            'id' => $cita->id,
            'estado' => Cita::ESTADO_PENDIENTE,
        ]);
    }

    /**
     * CASO I — MANTENIMIENTO DEMO: demo:maintain-schedules está configurado a las 03:00 y condicionado a modo demo.
     */
    public function test_caso_i_demo_maintain_schedules_condicionado_a_modo_demo(): void
    {
        $events = $this->getScheduledEvents();
        $demoEvent = $this->findEventByCommandSnippet($events, 'demo:maintain-schedules');

        $this->assertNotNull($demoEvent, 'demo:maintain-schedules debe estar registrado en el scheduler.');
        $this->assertSame('0 3 * * *', $demoEvent->expression);
        $this->assertTrue($demoEvent->withoutOverlapping);

        // En modo demo, los filtros permiten su ejecución
        config(['app.mode' => 'demo']);
        $this->assertTrue($demoEvent->filtersPass($this->app), 'El evento debe permitir ejecución cuando APP_MODE=demo.');

        // En modo producción, los filtros bloquean estrictamente su ejecución
        config(['app.mode' => 'production']);
        $this->assertFalse($demoEvent->filtersPass($this->app), 'El evento debe bloquear ejecución cuando APP_MODE=production.');
    }




    /**
     * @return array<int, Event>
     */
    protected function getScheduledEvents(): array
    {
        $schedule = app(Schedule::class);

        return $schedule->events();
    }

    protected function findEventByCommandSnippet(array $events, string $snippet): ?Event
    {
        foreach ($events as $event) {
            if ($event->command && str_contains($event->command, $snippet)) {
                return $event;
            }
        }

        return null;
    }

    protected function createDoctorWithSchedule(string $fecha): array
    {
        $doctor = $this->createUserWithRole('doctor');
        $especialidad = Especialidad::factory()->create(['nombre' => 'Medicina General '.uniqid()]);
        $doctor->especialidades()->attach($especialidad->id);

        Horario::create([
            'doctor_id' => $doctor->id,
            'fecha' => $fecha,
            'hora_inicio' => '09:00',
            'hora_fin' => '10:00',
            'intervalo_minutos' => 30,
        ]);

        return [$doctor, $especialidad];
    }

    protected function createUserWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user = User::factory()->create([
            'status' => 'active',
            'suspended_until' => null,
        ]);

        $user->roles()->attach($role->id);

        return $user;
    }
}
