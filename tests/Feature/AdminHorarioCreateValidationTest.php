<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Cita;
use App\Models\Horario;
use App\Models\Especialidad;
use App\Services\SiteSettingsService;
use App\Services\ProfessionalScheduleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdminHorarioCreateValidationTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_create_schedule_form_sets_today_as_minimum_start_date(): void
    {
        Carbon::setTestNow('2026-04-03 09:00:00');

        $admin = $this->createUserWithRole('administrador');
        $this->createUserWithRole('doctor');

        $this->actingAs($admin)
            ->get(route('admin.horarios.create'))
            ->assertOk()
            ->assertSee('id="fecha_inicio"', false)
            ->assertSee('min="2026-04-03"', false);
    }

    public function test_admin_schedule_store_rejects_past_start_dates(): void
    {
        Carbon::setTestNow('2026-04-03 09:00:00');

        $admin = $this->createUserWithRole('administrador');
        $doctor = $this->createUserWithRole('doctor');

        $this->actingAs($admin)
            ->from(route('admin.horarios.create'))
            ->post(route('admin.horarios.store'), [
                'doctor_id' => $doctor->id,
                'fecha_inicio' => '2026-04-02',
                'fecha_fin' => '2026-04-04',
                'dias' => [1, 2, 3, 4, 5],
                'misma_franja' => '1',
                'hora_inicio' => '09:00',
                'hora_fin' => '10:00',
            ])
            ->assertRedirect(route('admin.horarios.create'))
            ->assertSessionHasErrors([
                'fecha_inicio' => 'La fecha desde no puede ser anterior a hoy.',
            ]);

        $this->assertDatabaseCount('horarios', 0);
    }

    public function test_admin_horarios_save_exact_clinic_bounds(): void
    {
        Carbon::setTestNow('2026-04-06 09:00:00'); // Monday

        $admin = $this->createUserWithRole('administrador');
        $doctor = $this->createUserWithRole('doctor');

        $settings = app(SiteSettingsService::class);
        $settings->setMany([
            'clinic_hours.1.status' => '1',
            'clinic_hours.1.opening' => '08:00',
            'clinic_hours.1.closing' => '18:00',
        ]);
        $settings->forgetCache();

        $this->actingAs($admin)
            ->post(route('admin.horarios.store'), [
                'doctor_id' => $doctor->id,
                'fecha_inicio' => '2026-04-06',
                'fecha_fin' => '2026-04-06',
                'dias' => [1],
                'misma_franja' => '1',
                'hora_inicio' => '08:00',
                'hora_fin' => '18:00',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('horarios', 1);
        $h = Horario::first();
        $this->assertEquals('08:00:00', $h->hora_inicio);
        $this->assertEquals('18:00:00', $h->hora_fin);
    }

    public function test_admin_horarios_rejects_closed_day(): void
    {
        Carbon::setTestNow('2026-04-05 09:00:00'); // Sunday

        $admin = $this->createUserWithRole('administrador');
        $doctor = $this->createUserWithRole('doctor');

        $settings = app(SiteSettingsService::class);
        $settings->setMany([
            'clinic_hours.7.status' => '0',
        ]);
        $settings->forgetCache();

        $this->actingAs($admin)
            ->post(route('admin.horarios.store'), [
                'doctor_id' => $doctor->id,
                'fecha_inicio' => '2026-04-05',
                'fecha_fin' => '2026-04-05',
                'dias' => [7],
                'misma_franja' => '1',
                'hora_inicio' => '08:00',
                'hora_fin' => '12:00',
            ])
            ->assertRedirect()
            ->assertSessionHas('success'); // Returns generation summary indicating 0 created, because Sunday is closed

        $this->assertDatabaseCount('horarios', 0);
    }

    public function test_admin_horarios_rejects_out_of_clinic_bounds(): void
    {
        Carbon::setTestNow('2026-04-06 09:00:00'); // Monday

        $admin = $this->createUserWithRole('administrador');
        $doctor = $this->createUserWithRole('doctor');

        $settings = app(SiteSettingsService::class);
        $settings->setMany([
            'clinic_hours.1.status' => '1',
            'clinic_hours.1.opening' => '08:00',
            'clinic_hours.1.closing' => '18:00',
        ]);
        $settings->forgetCache();

        $this->actingAs($admin)
            ->post(route('admin.horarios.store'), [
                'doctor_id' => $doctor->id,
                'fecha_inicio' => '2026-04-06',
                'fecha_fin' => '2026-04-06',
                'dias' => [1],
                'misma_franja' => '1',
                'hora_inicio' => '07:30',
                'hora_fin' => '18:00',
            ]);

        $this->assertDatabaseCount('horarios', 0);
    }

    public function test_admin_horarios_rejects_overlapping_schedules_transactional(): void
    {
        Carbon::setTestNow('2026-04-06 09:00:00');

        $admin = $this->createUserWithRole('administrador');
        $doctor = $this->createUserWithRole('doctor');

        Horario::create([
            'doctor_id' => $doctor->id,
            'fecha' => '2026-04-06',
            'hora_inicio' => '08:00',
            'hora_fin' => '12:00',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.horarios.store'), [
                'doctor_id' => $doctor->id,
                'fecha_inicio' => '2026-04-06',
                'fecha_fin' => '2026-04-06',
                'dias' => [1],
                'misma_franja' => '1',
                'hora_inicio' => '11:30',
                'hora_fin' => '13:00',
            ]);

        $this->assertDatabaseCount('horarios', 1);
    }

    public function test_admin_horarios_allows_contiguous_schedules(): void
    {
        Carbon::setTestNow('2026-04-06 09:00:00');

        $admin = $this->createUserWithRole('administrador');
        $doctor = $this->createUserWithRole('doctor');

        Horario::create([
            'doctor_id' => $doctor->id,
            'fecha' => '2026-04-06',
            'hora_inicio' => '08:00',
            'hora_fin' => '12:00',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.horarios.store'), [
                'doctor_id' => $doctor->id,
                'fecha_inicio' => '2026-04-06',
                'fecha_fin' => '2026-04-06',
                'dias' => [1],
                'misma_franja' => '1',
                'hora_inicio' => '12:00',
                'hora_fin' => '16:00',
            ]);

        $this->assertDatabaseCount('horarios', 2);
    }

    public function test_admin_horarios_requires_confirmation_on_citas_conflicts(): void
    {
        Carbon::setTestNow('2026-04-06 09:00:00');

        $admin = $this->createUserWithRole('administrador');
        $doctor = $this->createUserWithRole('doctor');

        $horario = Horario::create([
            'doctor_id' => $doctor->id,
            'fecha' => '2026-04-07', // Tomorrow (Tuesday)
            'hora_inicio' => '08:00',
            'hora_fin' => '12:00',
            'intervalo_minutos' => 30,
        ]);

        $especialidad = Especialidad::create(['nombre' => 'General', 'activo' => true]);

        Cita::create([
            'paciente_id' => $this->createUserWithRole('paciente')->id,
            'doctor_id' => $doctor->id,
            'fecha' => '2026-04-07',
            'hora' => '09:00',
            'estado' => Cita::ESTADO_PENDIENTE,
            'activo' => 1,
            'especialidad_id' => $especialidad->id,
        ]);

        // Attempt delete without confirmation
        $this->actingAs($admin)
            ->delete(route('admin.horarios.destroy', $horario))
            ->assertRedirect()
            ->assertSessionHas('horario_conflicts', 1);

        $this->assertDatabaseCount('horarios', 1);

        // Attempt delete with confirmation
        $this->actingAs($admin)
            ->delete(route('admin.horarios.destroy', $horario), ['confirmar_conflictos' => '1'])
            ->assertRedirect();

        $this->assertDatabaseCount('horarios', 0);
    }

    public function test_cache_invalidation_works_correctly(): void
    {
        $settings = app(SiteSettingsService::class);
        $settings->setMany([
            'clinic_hours.1.status' => '1',
            'clinic_hours.1.opening' => '08:00',
            'clinic_hours.1.closing' => '18:00',
        ]);
        $settings->forgetCache();

        $this->assertEquals('08:00', app(ProfessionalScheduleService::class)->getClinicHours(1)['opening']);

        // Directly modify database row to simulate setting change without invalidation
        \DB::table('site_settings')->where('key', 'clinic_hours.1.opening')->update(['value' => '09:00']);

        // Since cache is still active, it should return '08:00'
        $this->assertEquals('08:00', app(ProfessionalScheduleService::class)->getClinicHours(1)['opening']);

        // Clear cache and check again
        $settings->forgetCache();
        $this->assertEquals('09:00', app(ProfessionalScheduleService::class)->getClinicHours(1)['opening']);
    }

    public function test_admin_horario_create_view_declarative_visibility_and_no_inline_styles(): void
    {
        $admin = $this->createUserWithRole('administrador');
        $this->createUserWithRole('doctor');

        $this->actingAs($admin)
            ->get(route('admin.horarios.create'))
            ->assertOk()
            ->assertSee('id="franjas-por-dia"', false)
            ->assertSee('id="franja-global"', false)
            ->assertDontSee('style="display:none"', false);

        $bladePath = resource_path('views/admin/horarios/create.blade.php');
        $this->assertFileExists($bladePath);
        $blade = file_get_contents($bladePath);
        $this->assertStringNotContainsString('style=', $blade);

        $jsPath = resource_path('js/admin/horarios/create.js');
        $this->assertFileExists($jsPath);
        $js = file_get_contents($jsPath);
        $this->assertStringContainsString("boxGlobal.classList.toggle('hidden', !on)", $js);
        $this->assertStringContainsString("boxPerDay.classList.toggle('hidden', on)", $js);
        $this->assertStringNotContainsString('boxGlobal.style.display', $js);
        $this->assertStringNotContainsString('boxPerDay.style.display', $js);
    }

    public function test_validation_error_with_misma_franja_checked_preserves_global_mode_ssr(): void
    {
        $admin = $this->createUserWithRole('administrador');
        $doctor = $this->createUserWithRole('doctor');

        // Post invalid data (missing required hours) with misma_franja=1
        $response = $this->actingAs($admin)
            ->from(route('admin.horarios.create'))
            ->post(route('admin.horarios.store'), [
                'doctor_id' => $doctor->id,
                'fecha_inicio' => now()->toDateString(),
                'fecha_fin' => now()->addDays(2)->toDateString(),
                'dias' => [1],
                'misma_franja' => '1',
                // omit hora_inicio and hora_fin to trigger validation error
            ]);

        $response->assertRedirect(route('admin.horarios.create'))
            ->assertSessionHasErrors(['hora_inicio']);

        // Follow redirect to inspect rendered SSR HTML
        $followResponse = $this->actingAs($admin)
            ->get(route('admin.horarios.create'));

        $followResponse->assertOk()
            ->assertSee('id="misma_franja"', false)
            ->assertSee('checked', false)
            ->assertSee('<div id="franja-global" class="grid gap-4 sm:grid-cols-2 ">', false)
            ->assertSee('<div id="franjas-por-dia" class="mt-4 space-y-3 hidden">', false);
    }

    public function test_validation_error_with_misma_franja_unchecked_preserves_per_day_mode_ssr(): void
    {
        $admin = $this->createUserWithRole('administrador');
        $doctor = $this->createUserWithRole('doctor');

        // Post invalid data (missing required hours array) with misma_franja=0
        $response = $this->actingAs($admin)
            ->from(route('admin.horarios.create'))
            ->post(route('admin.horarios.store'), [
                'doctor_id' => $doctor->id,
                'fecha_inicio' => now()->toDateString(),
                'fecha_fin' => now()->addDays(2)->toDateString(),
                'dias' => [1],
                'misma_franja' => '0',
                // omit horas to trigger validation error in per-day mode
            ]);

        $response->assertRedirect(route('admin.horarios.create'))
            ->assertSessionHasErrors(['horas']);

        // Follow redirect to inspect rendered SSR HTML
        $followResponse = $this->actingAs($admin)
            ->get(route('admin.horarios.create'));

        $followResponse->assertOk()
            ->assertSee('id="misma_franja"', false)
            ->assertDontSee('id="misma_franja" name="misma_franja" value="1" checked', false)
            ->assertSee('<div id="franja-global" class="grid gap-4 sm:grid-cols-2 hidden">', false)
            ->assertSee('<div id="franjas-por-dia" class="mt-4 space-y-3 ">', false);
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'suspended_until' => null,
        ]);

        $user->roles()->attach($role->id);

        return $user;
    }
}
