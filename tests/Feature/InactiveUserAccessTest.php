<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InactiveUserAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_user_cannot_log_in(): void
    {
        $role = Role::firstOrCreate(['name' => 'paciente']);
        $user = User::factory()->inactive()->create([
            'email' => 'inactive.patient@clinic.test',
        ]);
        $user->roles()->sync([$role->id]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '0',
        ])
            ->assertRedirect('/?login=1')
            ->assertSessionHasErrorsIn('login', ['email']);

        $this->assertGuest();
        $this->assertFalse($user->fresh()->active);
    }

    public function test_inactive_user_is_logged_out_when_accessing_a_protected_panel(): void
    {
        $role = Role::firstOrCreate(['name' => 'paciente']);
        $user = User::factory()->create([
            'email' => 'patient.session@clinic.test',
            'password' => 'password',
        ]);
        $user->roles()->sync([$role->id]);

        $this->actingAs($user)
            ->get(route('paciente.dashboard'))
            ->assertOk();

        $user->forceFill([
            'status' => User::STATUS_INACTIVE,
            'deactivation_reason' => 'QA',
        ])->save();

        $this->get(route('paciente.dashboard'))
            ->assertRedirect('/?login=1')
            ->assertSessionHasErrorsIn('login', ['email']);

        $this->assertGuest();
        $this->assertFalse($user->fresh()->active);
    }
}
