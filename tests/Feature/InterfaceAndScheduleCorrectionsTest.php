<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Horario;
use App\Models\Role;
use App\Models\User;
use App\Services\ProfessionalScheduleService;
use App\Services\SiteSettingsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InterfaceAndScheduleCorrectionsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function admin_usuario_crear_page_loads_with_correct_buttons()
    {
        $role = Role::firstOrCreate(['name' => 'administrador']);
        $admin = User::factory()->create();
        $admin->roles()->attach($role);

        $response = $this->actingAs($admin)->get('/admin/usuarios/crear');

        $response->assertStatus(200);
        $response->assertSee('Registrar');
        $response->assertSee('Limpiar');
        $response->assertSee('btn-registrar-usuario');
        $response->assertSee('btn-limpiar-usuario');
    }

    #[Test]
    public function clinic_weekly_schedule_supports_all_7_days_dynamically()
    {
        $settingsService = app(SiteSettingsService::class);
        $scheduleService = app(ProfessionalScheduleService::class);

        // Configure Tuesday (2) as closed, Sunday (7) as open (09:00-12:00)
        $payload = [
            'clinic_hours.1.status' => '1',
            'clinic_hours.1.opening' => '08:00',
            'clinic_hours.1.closing' => '18:00',
            'clinic_hours.2.status' => '0', // Tuesday closed
            'clinic_hours.2.opening' => '08:00',
            'clinic_hours.2.closing' => '18:00',
            'clinic_hours.3.status' => '1',
            'clinic_hours.3.opening' => '08:00',
            'clinic_hours.3.closing' => '18:00',
            'clinic_hours.4.status' => '1',
            'clinic_hours.4.opening' => '08:00',
            'clinic_hours.4.closing' => '18:00',
            'clinic_hours.5.status' => '1',
            'clinic_hours.5.opening' => '08:00',
            'clinic_hours.5.closing' => '18:00',
            'clinic_hours.6.status' => '1',
            'clinic_hours.6.opening' => '08:00',
            'clinic_hours.6.closing' => '13:00',
            'clinic_hours.7.status' => '1', // Sunday open
            'clinic_hours.7.opening' => '09:00',
            'clinic_hours.7.closing' => '12:00',
        ];

        $settingsService->setMany($payload);

        // Verify Tuesday is closed
        $tueHours = $scheduleService->getClinicHours(2);
        $this->assertEquals(0, $tueHours['status']);

        // Verify Sunday is open with custom hours
        $sunHours = $scheduleService->getClinicHours(7);
        $this->assertEquals(1, $sunHours['status']);
        $this->assertEquals('09:00', $sunHours['opening']);
        $this->assertEquals('12:00', $sunHours['closing']);
    }

    #[Test]
    public function closed_day_generates_no_slots_and_open_sunday_generates_slots()
    {
        $role = Role::firstOrCreate(['name' => 'doctor']);
        $doctor = User::factory()->create();
        $doctor->roles()->attach($role);

        $scheduleService = app(ProfessionalScheduleService::class);

        // Set Sunday (7) open 09:00-12:00 and Tuesday (2) closed
        $settingsService = app(SiteSettingsService::class);
        $settingsService->setMany([
            'clinic_hours.7.status' => '1',
            'clinic_hours.7.opening' => '09:00',
            'clinic_hours.7.closing' => '12:00',
            'clinic_hours.2.status' => '0',
        ]);

        // Find next Tuesday and next Sunday
        $nextTuesday = Carbon::now()->next(Carbon::TUESDAY)->toDateString();
        $nextSunday = Carbon::now()->next(Carbon::SUNDAY)->toDateString();

        // Create doctor schedule for next Tuesday and next Sunday
        Horario::firstOrCreate([
            'doctor_id' => $doctor->id,
            'fecha' => $nextTuesday,
            'hora_inicio' => '09:00:00',
            'hora_fin' => '12:00:00',
            'intervalo_minutos' => 30,
        ]);

        Horario::firstOrCreate([
            'doctor_id' => $doctor->id,
            'fecha' => $nextSunday,
            'hora_inicio' => '09:00:00',
            'hora_fin' => '12:00:00',
            'intervalo_minutos' => 30,
        ]);

        // Slots on closed Tuesday must be empty
        $tueSlots = $scheduleService->buildSlotsForDate($doctor->id, $nextTuesday);
        $this->assertEmpty($tueSlots, 'Closed Tuesday should not generate availability slots.');

        // Slots on open Sunday must be generated
        $sunSlots = $scheduleService->buildSlotsForDate($doctor->id, $nextSunday);
        $this->assertNotEmpty($sunSlots, 'Open Sunday should generate availability slots.');
        $this->assertEquals('09:00', $sunSlots[0]['hora']);
    }

    #[Test]
    public function global_confirm_modal_and_legal_modals_included_in_admin_layout()
    {
        $role = Role::firstOrCreate(['name' => 'administrador']);
        $admin = User::factory()->create();
        $admin->roles()->attach($role);

        $response = $this->actingAs($admin)->get('/admin/usuarios');
        $response->assertStatus(200);
        $response->assertSee('global-confirm-modal');
        $response->assertSee('global-confirm-submit-btn');
    }

    #[Test]
    public function schedule_formatter_handles_all_5_cases_correctly()
    {
        $scheduleService = app(ProfessionalScheduleService::class);

        // Caso 1: Todos los días abiertos con el mismo horario
        $case1 = [];
        for ($d = 1; $d <= 7; $d++) {
            $case1[$d] = ['status' => 1, 'opening' => '08:00', 'closing' => '18:00'];
        }
        $res1 = $scheduleService->getFormattedClinicSchedule($case1);
        $this->assertEquals('Lunes a domingo, 08:00–18:00', $res1['summary']);

        // Caso 2: Días consecutivos con el mismo horario (Lunes a sábado, Domingo cerrado)
        $case2 = $case1;
        $case2[7]['status'] = 0;
        $res2 = $scheduleService->getFormattedClinicSchedule($case2);
        $this->assertEquals('Lunes a sábado, 08:00–18:00', $res2['summary']);
        $this->assertEquals('Lunes a sábado', $res2['lines'][0]['label']);
        $this->assertEquals('Domingo', $res2['lines'][1]['label']);

        // Caso 3: Días no consecutivos con el mismo horario (Lunes, miércoles y viernes)
        $case3 = [];
        for ($d = 1; $d <= 7; $d++) {
            $case3[$d] = ['status' => in_array($d, [1, 3, 5]) ? 1 : 0, 'opening' => '08:00', 'closing' => '18:00'];
        }
        $res3 = $scheduleService->getFormattedClinicSchedule($case3);
        $this->assertEquals('Lunes, miércoles y viernes: 08:00–18:00', $res3['summary']);

        // Caso 4: Horarios diferentes
        $case4 = [
            1 => ['status' => 1, 'opening' => '08:00', 'closing' => '18:00'],
            2 => ['status' => 0, 'opening' => '08:00', 'closing' => '18:00'],
            3 => ['status' => 1, 'opening' => '08:00', 'closing' => '20:00'],
            4 => ['status' => 0, 'opening' => '08:00', 'closing' => '18:00'],
            5 => ['status' => 1, 'opening' => '08:00', 'closing' => '18:00'],
            6 => ['status' => 1, 'opening' => '09:00', 'closing' => '13:00'],
            7 => ['status' => 1, 'opening' => '09:00', 'closing' => '13:00'],
        ];
        $res4 = $scheduleService->getFormattedClinicSchedule($case4);
        $this->assertEquals('Lunes y viernes', $res4['lines'][0]['label']);
        $this->assertEquals('08:00–18:00', $res4['lines'][0]['hours']);
        $this->assertEquals('Miércoles', $res4['lines'][1]['label']);
        $this->assertEquals('08:00–20:00', $res4['lines'][1]['hours']);
        $this->assertEquals('Sábado y domingo', $res4['lines'][2]['label']);
        $this->assertEquals('09:00–13:00', $res4['lines'][2]['hours']);
        $this->assertEquals('Martes y jueves', $res4['lines'][3]['label']);
        $this->assertEquals('Cerrado', $res4['lines'][3]['hours']);

        // Caso 5: Todos los días cerrados
        $case5 = [];
        for ($d = 1; $d <= 7; $d++) {
            $case5[$d] = ['status' => 0, 'opening' => '08:00', 'closing' => '18:00'];
        }
        $res5 = $scheduleService->getFormattedClinicSchedule($case5);
        $this->assertEquals('Temporalmente cerrado', $res5['summary']);
        $this->assertTrue($res5['is_all_closed']);
    }

    #[Test]
    public function public_contacto_page_renders_dynamic_schedule_correctly()
    {
        $settingsService = app(SiteSettingsService::class);
        $payload = [
            'clinic_hours.1.status' => '1',
            'clinic_hours.1.opening' => '08:00',
            'clinic_hours.1.closing' => '18:00',
            'clinic_hours.2.status' => '0',
            'clinic_hours.3.status' => '1',
            'clinic_hours.3.opening' => '08:00',
            'clinic_hours.3.closing' => '18:00',
            'clinic_hours.4.status' => '0',
            'clinic_hours.5.status' => '1',
            'clinic_hours.5.opening' => '08:00',
            'clinic_hours.5.closing' => '18:00',
            'clinic_hours.6.status' => '1',
            'clinic_hours.6.opening' => '09:00',
            'clinic_hours.6.closing' => '13:00',
            'clinic_hours.7.status' => '1',
            'clinic_hours.7.opening' => '09:00',
            'clinic_hours.7.closing' => '13:00',
        ];
        $settingsService->setMany($payload);

        $response = $this->get('/contacto');
        $response->assertStatus(200);
        $response->assertSee('Lunes, miércoles y viernes');
        $response->assertSee('Sábado y domingo');
        $response->assertSee('Martes y jueves');
        $response->assertSee('Cerrado');
    }
}
