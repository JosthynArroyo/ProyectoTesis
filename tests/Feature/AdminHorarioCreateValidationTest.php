<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminHorarioCreateValidationTest extends TestCase
{
    use RefreshDatabase;

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
