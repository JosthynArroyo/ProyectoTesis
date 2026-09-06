<?php

namespace Tests\Feature;

use App\Models\Horario;
use App\Models\User;
use Database\Seeders\DemoLaboratorySeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoLaboratoryScheduleTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.mode' => 'demo']);
    }

    /**
     * Grupo A: Dataset de horarios y renderizado del índice para el usuario canónico de laboratorio.
     *
     * Contratos cubiertos:
     *   — Lic. Carlos Morales tiene al menos 10 bloques de horario registrados
     *   — Existen horarios en la semana actual
     *   — Cada bloque tiene hora_inicio < hora_fin e intervalo de 30 minutos
     *   — GET /laboratorio/horario responde 200 y muestra bloques de disponibilidad
     */
    public function test_canonical_laboratory_user_schedule_dataset_and_index(): void
    {
        $this->seed(DemoSeeder::class);

        $labUser = User::where('email', 'laboratorio@demo-clinigest.test')->firstOrFail();

        $horarios = Horario::where('doctor_id', $labUser->id)->get();
        $this->assertGreaterThanOrEqual(10, $horarios->count(), 'El usuario de laboratorio debe tener al menos 10 bloques de horario en el dataset demo.');

        $startOfWeek = now()->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();
        $endOfWeek   = now()->endOfWeek(\Carbon\Carbon::SUNDAY)->toDateString();
        $currentWeekHorarios = $horarios->whereBetween('fecha', [$startOfWeek, $endOfWeek]);
        $this->assertGreaterThan(0, $currentWeekHorarios->count(), 'El usuario de laboratorio debe tener horarios en la semana actual.');

        foreach ($horarios as $h) {
            $this->assertLessThan($h->hora_fin, $h->hora_inicio, 'La hora de inicio debe ser menor a la hora de fin.');
            $this->assertEquals(30, $h->intervalo_minutos, 'El intervalo debe ser de 30 minutos.');
        }

        // HTTP index
        $response = $this->actingAs($labUser)->get(route('laboratorio.horario.index'));
        $response->assertOk();
        $response->assertSee('Mi horario');
        $response->assertSee('Weekly schedule');
        $response->assertSee('Mis horarios');
        $response->assertSee('Recepción activa');
        $response->assertDontSee('Sin actividad semanal');
        $response->assertDontSee('Sin horarios en el rango.');
    }

    /**
     * Idempotencia: DemoLaboratorySeeder no duplica horarios al re-ejecutarse.
     * — Requiere 2 runs del seeder por diseño (inherente al contrato de idempotencia).
     */
    public function test_laboratory_horario_seeder_is_idempotent(): void
    {
        $this->seed(DemoSeeder::class);

        $labUser = User::where('email', 'laboratorio@demo-clinigest.test')->firstOrFail();
        $count1 = Horario::where('doctor_id', $labUser->id)->count();
        $this->assertGreaterThan(0, $count1);

        // Reejecutar seeder
        $this->seed(DemoLaboratorySeeder::class);
        $count2 = Horario::where('doctor_id', $labUser->id)->count();

        $this->assertSame($count1, $count2, 'Reejecutar DemoLaboratorySeeder no debe duplicar horarios.');
    }

    /**
     * Mutación en la vista de horarios: crear un nuevo horario responde exitosamente y aplica rollback en demo.
     */
    public function test_laboratory_schedule_store_action_works(): void
    {
        $this->seed(DemoSeeder::class);

        $labUser = User::where('email', 'laboratorio@demo-clinigest.test')->firstOrFail();

        $targetDate = now()->addDays(40)->toDateString();

        $response = $this->actingAs($labUser)->post(route('laboratorio.horario.store'), [
            'fecha'             => $targetDate,
            'hora_inicio'       => '08:00',
            'hora_fin'          => '12:00',
            'intervalo_minutos' => 30,
        ]);

        $response->assertSessionHas('success', 'Horario creado.');
        $this->assertDatabaseMissing('horarios', [
            'doctor_id'   => $labUser->id,
            'fecha'       => $targetDate,
            'hora_inicio' => '08:00:00',
            'hora_fin'    => '12:00:00',
        ]);
    }

    /**
     * DemoSeeder no puede ejecutarse en modo producción.
     * — No necesita DemoSeeder (verifica que lanza excepción al intentarlo).
     */
    public function test_demo_seeder_cannot_run_in_production_mode(): void
    {
        config(['app.mode' => 'production']);
        $this->expectException(\RuntimeException::class);
        $this->seed(DemoSeeder::class);
    }

    /**
     * Grupo ventana rodante: disponibilidad continua con avance temporal T0 → T0+90d → T1 = T0+90d.
     *
     * Contratos cubiertos:
     *   — En T0 existe disponibilidad a 45 días hacia el futuro
     *   — Tras re-seed en T1 (T0+90d) existe disponibilidad en semana actual de T1
     *   — Existe disponibilidad futura en T1+45d (ventana rodante)
     *   — Bloques obsoletos previos a T1-14d fueron depurados
     *   — Cantidad total de horarios estable (sin acumulación indefinida)
     *   — Vista HTTP en T1 responde 200 con bloques visibles
     */
    public function test_laboratory_schedule_rolling_window_and_time_advance_guarantees_perpetual_future_availability(): void
    {
        $t0 = \Carbon\Carbon::parse('2026-09-01 08:00:00');
        \Carbon\Carbon::setTestNow($t0);

        $this->seed(DemoSeeder::class);

        $labUser = User::where('email', 'laboratorio@demo-clinigest.test')->firstOrFail();

        // En T0 disponibilidad a 45 días
        $futureDateT0 = $t0->copy()->addDays(45)->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();
        $hasAvailabilityIn45Days = Horario::where('doctor_id', $labUser->id)->whereDate('fecha', $futureDateT0)->exists();
        $this->assertTrue($hasAvailabilityIn45Days, 'En T0 el laboratorio debe tener disponibilidad al menos a 45 días hacia el futuro.');

        $initialCount = Horario::where('doctor_id', $labUser->id)->count();

        // Simular avance de 3 meses (T1 = T0+90d)
        $t1 = \Carbon\Carbon::parse('2026-12-01 08:00:00');
        \Carbon\Carbon::setTestNow($t1);

        $this->seed(DemoLaboratorySeeder::class);

        // Disponibilidad en semana actual de T1
        $t1StartOfWeek = $t1->copy()->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();
        $t1EndOfWeek   = $t1->copy()->endOfWeek(\Carbon\Carbon::SUNDAY)->toDateString();
        $currentWeekT1Count = Horario::where('doctor_id', $labUser->id)
            ->whereBetween('fecha', [$t1StartOfWeek, $t1EndOfWeek])
            ->count();
        $this->assertGreaterThan(0, $currentWeekT1Count, 'En T1 debe existir disponibilidad en la semana actual.');

        // Disponibilidad futura en T1+45d
        $futureDateT1 = $t1->copy()->addDays(45)->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();
        $hasAvailabilityIn45DaysT1 = Horario::where('doctor_id', $labUser->id)->whereDate('fecha', $futureDateT1)->exists();
        $this->assertTrue($hasAvailabilityIn45DaysT1, 'En T1 debe existir disponibilidad a 45 días hacia el futuro.');

        // Bloques obsoletos depurados
        $oldBlocksExist = Horario::where('doctor_id', $labUser->id)
            ->whereDate('fecha', '<', $t1->copy()->subDays(14)->toDateString())
            ->exists();
        $this->assertFalse($oldBlocksExist, 'Los bloques demo obsoletos previos a la ventana rodante de T1 deben haber sido depurados.');

        // Cantidad estable
        $finalCount = Horario::where('doctor_id', $labUser->id)->count();
        $this->assertLessThanOrEqual($initialCount + 10, $finalCount, 'La cantidad de horarios no debe acumularse indefinidamente.');
        $this->assertGreaterThanOrEqual($initialCount - 10, $finalCount, 'La cantidad de horarios debe ser estable.');

        // Vista HTTP en T1
        $response = $this->actingAs($labUser)->get(route('laboratorio.horario.index'));
        $response->assertOk();
        $response->assertSee('Recepción activa');
        $response->assertDontSee('Sin actividad semanal');
    }

    /**
     * Comando demo:maintain-laboratory-schedule actualiza la ventana rodante en modo demo.
     */
    public function test_demo_maintain_laboratory_schedule_command_updates_rolling_window_in_demo(): void
    {
        $this->seed(DemoSeeder::class);

        $this->artisan('demo:maintain-laboratory-schedule')
            ->expectsOutputToContain('Ventana de disponibilidad del laboratorio actualizada')
            ->assertSuccessful();
    }

    /**
     * Comando demo:maintain-laboratory-schedule no modifica nada en modo producción.
     * — No necesita DemoSeeder.
     */
    public function test_demo_maintain_laboratory_schedule_command_does_nothing_in_production(): void
    {
        config(['app.mode' => 'production']);

        $this->artisan('demo:maintain-laboratory-schedule')
            ->expectsOutputToContain('Comando exclusivo para APP_MODE=demo')
            ->assertSuccessful();
    }

    /**
     * Avance temporal de 4 meses con mantenimiento automático vía scheduler garantiza disponibilidad
     * sin reseed manual y sin acumulación histórica.
     *
     * Contratos cubiertos:
     *   — Disponibilidad en semana de enero 2027
     *   — Disponibilidad futura a 90 días (abril 2027)
     *   — Bloques de septiembre 2026 depurados automáticamente
     *   — Cantidad total estable (±15)
     *   — Cero duplicados en la base de datos
     *   — Vista HTTP responde 200 en T1 con bloques visibles
     */
    public function test_temporal_advance_with_automatic_scheduled_maintenance_guarantees_perpetual_future_availability(): void
    {
        $t0 = \Carbon\Carbon::parse('2026-09-01 08:00:00');
        \Carbon\Carbon::setTestNow($t0);

        $this->seed(DemoSeeder::class);

        $labUser = User::where('email', 'laboratorio@demo-clinigest.test')->firstOrFail();
        $initialCount = Horario::where('doctor_id', $labUser->id)->count();

        // Simular despliegue desatendido de 4 meses
        $t1 = \Carbon\Carbon::parse('2027-01-01 08:00:00');
        \Carbon\Carbon::setTestNow($t1);

        $this->artisan('demo:maintain-laboratory-schedule')->assertSuccessful();

        // Disponibilidad en enero 2027
        $t1StartOfWeek = $t1->copy()->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();
        $t1EndOfWeek   = $t1->copy()->endOfWeek(\Carbon\Carbon::SUNDAY)->toDateString();
        $this->assertTrue(
            Horario::where('doctor_id', $labUser->id)->whereBetween('fecha', [$t1StartOfWeek, $t1EndOfWeek])->exists(),
            'Debe haber disponibilidad activa en la semana actual de enero 2027.'
        );

        // Disponibilidad futura a 90 días (abril 2027)
        $futureT1Date = $t1->copy()->addDays(90)->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();
        $this->assertTrue(
            Horario::where('doctor_id', $labUser->id)->whereDate('fecha', $futureT1Date)->exists(),
            'Debe haber disponibilidad futura a 90 días en la ventana rodante extendida.'
        );

        // Bloques de septiembre 2026 depurados
        $septemberDate = $t0->copy()->toDateString();
        $this->assertFalse(
            Horario::where('doctor_id', $labUser->id)->whereDate('fecha', $septemberDate)->exists(),
            'Los bloques antiguos de septiembre 2026 deben haber sido depurados automáticamente.'
        );

        // Cantidad estable
        $finalCount = Horario::where('doctor_id', $labUser->id)->count();
        $this->assertLessThanOrEqual($initialCount + 15, $finalCount);
        $this->assertGreaterThanOrEqual($initialCount - 15, $finalCount);

        // Cero duplicados
        $duplicateCount = Horario::where('doctor_id', $labUser->id)
            ->select('doctor_id', 'fecha', 'hora_inicio', 'hora_fin', DB::raw('count(*) as total'))
            ->groupBy('doctor_id', 'fecha', 'hora_inicio', 'hora_fin')
            ->havingRaw('count(*) > 1')
            ->count();
        $this->assertSame(0, $duplicateCount, 'No deben existir bloques de horario duplicados.');

        // Vista HTTP en T1
        $response = $this->actingAs($labUser)->get(route('laboratorio.horario.index'));
        $response->assertOk();
        $response->assertSee('Recepción activa');
        $response->assertDontSee('Sin actividad semanal');
    }

    protected function tearDown(): void
    {
        \Carbon\Carbon::setTestNow();
        parent::tearDown();
    }
}
