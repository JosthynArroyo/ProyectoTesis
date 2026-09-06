<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Horario;
use App\Models\User;
use App\Services\ProfessionalScheduleService;
use Carbon\Carbon;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoPatientAppointmentBookingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.mode' => 'demo']);
    }

    /**
     * Grupo A: Disponibilidad de slots para todos los médicos demo desde el punto de vista del paciente.
     *
     * Contratos cubiertos:
     *   — Javier Espinoza puede consultar slots del Dr. Fernando Alvarado a 45 días hacia el futuro
     *   — La respuesta contiene slots libres para agendamiento
     *   — Los 3 médicos demo tienen slots disponibles en sus respectivas especialidades a 30 días
     *   — Cada médico tiene al menos un slot libre en esa fecha futura
     */
    public function test_all_demo_doctors_have_slots_and_patient_can_retrieve_them(): void
    {
        $this->seed(DemoSeeder::class);

        $paciente = User::where('email', 'paciente@demo-clinigest.test')->firstOrFail();
        $doctor   = User::where('email', 'doctor.medicina@demo-clinigest.test')->firstOrFail();

        // Paciente consulta slots del Dr. Fernando a 45 días
        $targetDate45 = now()->addDays(45);
        while (! $targetDate45->isWeekday()) {
            $targetDate45->addDay();
        }
        $targetDateStr45 = $targetDate45->format('Y-m-d');

        $response = $this->actingAs($paciente)->getJson(route('api.doctor.slots', [
            'doctor' => $doctor->id,
            'fecha'  => $targetDateStr45,
        ]));

        $response->assertOk();
        $slots = $response->json('slots');
        $this->assertIsArray($slots);
        $this->assertNotEmpty($slots, "Dr. Fernando Alvarado debe tener slots disponibles en la fecha futura {$targetDateStr45}.");

        $freeSlots = array_filter($slots, fn ($s) => ($s['estado'] ?? '') === 'libre');
        $this->assertNotEmpty($freeSlots, "Debe existir al menos un slot libre para agendar el {$targetDateStr45}.");

        // Todos los médicos demo tienen slots a 30 días
        $targetDate30 = now()->addDays(30);
        while (! $targetDate30->isWeekday()) {
            $targetDate30->addDay();
        }
        $targetDateStr30 = $targetDate30->format('Y-m-d');

        $doctors = User::whereHas('roles', fn ($q) => $q->where('name', 'doctor'))->with('especialidades')->get();
        $this->assertCount(3, $doctors, 'El dataset demo debe contener exactamente 3 médicos.');

        foreach ($doctors as $doc) {
            $response = $this->actingAs($paciente)->getJson(route('api.doctor.slots', [
                'doctor' => $doc->id,
                'fecha'  => $targetDateStr30,
            ]));

            $response->assertOk();
            $slots = $response->json('slots');
            $this->assertNotEmpty($slots, "El doctor {$doc->name} debe tener slots en {$targetDateStr30}.");

            $freeSlots = array_filter($slots, fn ($s) => ($s['estado'] ?? '') === 'libre');
            $this->assertNotEmpty($freeSlots, "El doctor {$doc->name} debe tener slots libres en {$targetDateStr30}.");
        }
    }

    /**
     * Flujo E2E de agendamiento real de Javier Espinoza con Dr. Fernando Alvarado:
     * responde éxito, redirige y aplica rollback Demo (la cita no persiste).
     */
    public function test_e2e_patient_appointment_creation_flow_succeeds_and_rolls_back_in_demo(): void
    {
        $this->seed(DemoSeeder::class);

        $paciente    = User::where('email', 'paciente@demo-clinigest.test')->firstOrFail();
        $doctor      = User::where('email', 'doctor.medicina@demo-clinigest.test')->firstOrFail();
        $especialidad = $doctor->especialidades->firstOrFail();

        $targetDate = now()->addDays(20);
        while (! $targetDate->isWeekday()) {
            $targetDate->addDay();
        }
        $targetDateStr = $targetDate->format('Y-m-d');

        $motivoTest = 'Evaluación médica preventiva demo E2E '.uniqid();

        $response = $this->actingAs($paciente)->post(route('paciente.crear-cita.store'), [
            'especialidad_id' => $especialidad->id,
            'doctor_id'       => $doctor->id,
            'fecha'           => $targetDateStr,
            'hora'            => '09:00',
            'motivo_consulta' => $motivoTest,
            'tipo_paciente'   => 'titular',
        ]);

        $response->assertRedirect(route('paciente.citas'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('citas_medicas', [
            'motivo_consulta' => $motivoTest,
        ]);
    }

    /**
     * Avance temporal de 4 meses con mantenimiento automático vía scheduler conserva disponibilidad médica y de laboratorio.
     *
     * Contratos cubiertos:
     *   — Dr. Fernando tiene slots en enero 2027 y en abril 2027 (ventana rodante)
     *   — Lic. Carlos Morales tiene horarios en semana de enero 2027
     *   — Bloques médicos y de laboratorio de septiembre 2026 depurados
     *   — Cantidad de horarios estable (±20)
     *   — Cero duplicados en la base de datos
     */
    public function test_temporal_advance_with_automatic_scheduled_maintenance_retains_doctor_and_lab_availability(): void
    {
        $t0 = Carbon::parse('2026-09-01 08:00:00');
        Carbon::setTestNow($t0);

        $this->seed(DemoSeeder::class);

        $doctor  = User::where('email', 'doctor.medicina@demo-clinigest.test')->firstOrFail();
        $labUser = User::where('email', 'laboratorio@demo-clinigest.test')->firstOrFail();

        $initialDocCount = Horario::where('doctor_id', $doctor->id)->count();
        $initialLabCount = Horario::where('doctor_id', $labUser->id)->count();

        $t1 = Carbon::parse('2027-01-01 08:00:00');
        Carbon::setTestNow($t1);

        $this->artisan('demo:maintain-schedules')->assertSuccessful();

        // Disponibilidad médica en enero 2027
        $scheduleService = app(ProfessionalScheduleService::class);
        $slotsJan = $scheduleService->buildSlotsForDate($doctor->id, '2027-01-04');
        $this->assertNotEmpty($slotsJan, 'Dr. Fernando Alvarado debe tener slots en enero 2027 tras avance temporal.');

        // Disponibilidad médica a 90 días (abril 2027)
        $slotsApr = $scheduleService->buildSlotsForDate($doctor->id, '2027-04-05');
        $this->assertNotEmpty($slotsApr, 'Dr. Fernando Alvarado debe tener slots en abril 2027 en la ventana rodante.');

        // Disponibilidad de laboratorio en enero 2027
        $labHorariosJan = Horario::where('doctor_id', $labUser->id)
            ->whereBetween('fecha', ['2027-01-04', '2027-01-08'])
            ->count();
        $this->assertGreaterThan(0, $labHorariosJan, 'El laboratorio debe conservar disponibilidad en enero 2027.');

        // Bloques obsoletos de septiembre 2026 depurados
        $oldDocBlocks = Horario::where('doctor_id', $doctor->id)->whereDate('fecha', '2026-09-01')->exists();
        $oldLabBlocks = Horario::where('doctor_id', $labUser->id)->whereDate('fecha', '2026-09-01')->exists();
        $this->assertFalse($oldDocBlocks, 'Bloques médicos obsoletos de septiembre 2026 deben haber sido depurados.');
        $this->assertFalse($oldLabBlocks, 'Bloques de laboratorio obsoletos de septiembre 2026 deben haber sido depurados.');

        // Cantidad estable
        $finalDocCount = Horario::where('doctor_id', $doctor->id)->count();
        $finalLabCount = Horario::where('doctor_id', $labUser->id)->count();
        $this->assertLessThanOrEqual($initialDocCount + 20, $finalDocCount);
        $this->assertGreaterThanOrEqual($initialDocCount - 20, $finalDocCount);
        $this->assertLessThanOrEqual($initialLabCount + 20, $finalLabCount);
        $this->assertGreaterThanOrEqual($initialLabCount - 20, $finalLabCount);

        // Cero duplicados
        $duplicateCount = Horario::query()
            ->select('doctor_id', 'fecha', 'hora_inicio', 'hora_fin', DB::raw('count(*) as total'))
            ->groupBy('doctor_id', 'fecha', 'hora_inicio', 'hora_fin')
            ->havingRaw('count(*) > 1')
            ->count();
        $this->assertSame(0, $duplicateCount, 'No deben existir horarios duplicados en la base de datos.');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
