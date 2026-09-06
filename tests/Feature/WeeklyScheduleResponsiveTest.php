<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Horario;
use App\Models\Role;
use App\Models\User;
use App\Support\WeeklyCalendarData;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class WeeklyScheduleResponsiveTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * TEST 1: WeeklyCalendarData::build calcula correctamente el lane_count por día y max_lane_count.
     */
    public function test_weekly_calendar_data_computes_lane_counts_per_day(): void
    {
        $weekStart = Carbon::parse('2026-08-31', 'America/Guayaquil'); // Lunes

        $entries = [
            // Lunes: 1 doctor
            ['layer' => 'background', 'date' => '2026-08-31', 'start' => '08:00', 'end' => '12:00', 'title' => 'Dr. A', 'lane_key' => 'doctor:1'],
            // Martes: 3 doctores en paralelo
            ['layer' => 'background', 'date' => '2026-09-01', 'start' => '08:00', 'end' => '12:00', 'title' => 'Dr. A', 'lane_key' => 'doctor:1'],
            ['layer' => 'background', 'date' => '2026-09-01', 'start' => '08:00', 'end' => '14:00', 'title' => 'Dr. B', 'lane_key' => 'doctor:2'],
            ['layer' => 'background', 'date' => '2026-09-01', 'start' => '10:00', 'end' => '16:00', 'title' => 'Dr. C', 'lane_key' => 'doctor:3'],
            // Miércoles: 6 doctores en paralelo
            ['layer' => 'background', 'date' => '2026-09-02', 'start' => '08:00', 'end' => '12:00', 'title' => 'Dr. 1', 'lane_key' => 'doctor:1'],
            ['layer' => 'background', 'date' => '2026-09-02', 'start' => '08:00', 'end' => '12:00', 'title' => 'Dr. 2', 'lane_key' => 'doctor:2'],
            ['layer' => 'background', 'date' => '2026-09-02', 'start' => '08:00', 'end' => '12:00', 'title' => 'Dr. 3', 'lane_key' => 'doctor:3'],
            ['layer' => 'background', 'date' => '2026-09-02', 'start' => '08:00', 'end' => '12:00', 'title' => 'Dr. 4', 'lane_key' => 'doctor:4'],
            ['layer' => 'background', 'date' => '2026-09-02', 'start' => '08:00', 'end' => '12:00', 'title' => 'Dr. 5', 'lane_key' => 'doctor:5'],
            ['layer' => 'background', 'date' => '2026-09-02', 'start' => '08:00', 'end' => '12:00', 'title' => 'Dr. 6', 'lane_key' => 'doctor:6'],
        ];

        $calendar = WeeklyCalendarData::build($weekStart, $entries);

        $this->assertArrayHasKey('days', $calendar);
        $this->assertCount(7, $calendar['days']);

        // Lunes (index 0): 1 carril
        $this->assertSame(1, $calendar['days'][0]['lane_count'] ?? 0);
        // Martes (index 1): 3 carriles
        $this->assertSame(3, $calendar['days'][1]['lane_count'] ?? 0);
        // Miércoles (index 2): 6 carriles
        $this->assertSame(6, $calendar['days'][2]['lane_count'] ?? 0);

        // max_lane_count global de la semana debe ser 6
        $this->assertSame(6, $calendar['max_lane_count'] ?? 0);
    }

    /**
     * TEST 2: En modo Demo, la vista /admin/horarios con múltiples doctores renderiza con estructura responsive.
     */
    public function test_admin_horarios_view_renders_responsive_board_with_multiple_doctors_in_demo(): void
    {
        config(['app.mode' => 'demo']);

        $adminRole = Role::firstOrCreate(['name' => 'administrador'], ['label' => 'Administrador']);
        $admin = User::firstOrCreate(
            ['email' => 'admin@demo-clinigest.test'],
            [
                'name' => 'Dra. Valeria Mendoza',
                'password' => \Illuminate\Support\Facades\Hash::make('Demo1234!'),
                'status' => User::STATUS_ACTIVE,
                'must_change_password' => false,
                'email_verified_at' => now(),
            ]
        );
        $admin->update(['must_change_password' => false, 'status' => User::STATUS_ACTIVE]);
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        $doctorRole = Role::firstOrCreate(['name' => 'doctor'], ['label' => 'Doctor']);
        $doctor = User::firstOrCreate(
            ['email' => 'doctor.medicina@demo-clinigest.test'],
            [
                'name' => 'Dr. Fernando Alvarado',
                'password' => \Illuminate\Support\Facades\Hash::make('Demo1234!'),
                'status' => User::STATUS_ACTIVE,
                'must_change_password' => false,
                'email_verified_at' => now(),
            ]
        );
        $doctor->update(['must_change_password' => false, 'status' => User::STATUS_ACTIVE]);
        $doctor->roles()->syncWithoutDetaching([$doctorRole->id]);

        Horario::firstOrCreate(
            [
                'doctor_id' => $doctor->id,
                'fecha' => now()->toDateString(),
                'hora_inicio' => '08:00:00',
                'hora_fin' => '12:00:00',
            ],
            [
                'intervalo_minutos' => 30,
            ]
        );

        $response = $this->actingAs($admin->fresh(['roles']))->get(route('admin.horarios.index'));

        $response->assertOk();
        $content = $response->getContent();

        // Verifica que la vista contiene los contenedores desktop y mobile
        $response->assertSee('weekly-schedule__viewport');
        $response->assertSee('weekly-schedule__board');
        $response->assertSee('weekly-schedule__mobile');
        $response->assertSee('weekly-schedule__corner');

        // Los eventos deben incluir title accesible
        $this->assertStringContainsString('title="', $content);
    }

    /**
     * TEST 3: En modo Producción, la vista /admin/horarios y /doctor/agenda operan correctamente.
     */
    public function test_production_views_render_properly(): void
    {
        config(['app.mode' => 'production']);
        $adminRole = Role::firstOrCreate(['name' => 'administrador']);
        $admin = User::factory()->create();
        $admin->roles()->sync([$adminRole->id]);

        $doctorRole = Role::firstOrCreate(['name' => 'doctor']);
        $doctor = User::factory()->create();
        $doctor->roles()->sync([$doctorRole->id]);

        // 1. Admin Horarios
        $responseAdmin = $this->actingAs($admin)->get(route('admin.horarios.index'));
        $responseAdmin->assertOk();

        // 2. Doctor Agenda
        $responseDoctor = $this->actingAs($doctor)->get(route('doctor.agenda'));
        $responseDoctor->assertOk();
        $responseDoctor->assertSee('weekly-schedule__viewport');
    }
}
