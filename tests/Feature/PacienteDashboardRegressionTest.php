<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PacienteDashboardRegressionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_paciente_real_puede_entrar_al_dashboard_sin_error(): void
    {
        $patient = User::where('email', 'josthynarroyo627@gmail.com')->first();
        if (! $patient) {
            $patient = User::factory()->create([
                'name' => 'Josthyn Arroyo',
                'email' => 'josthynarroyo627@gmail.com',
                'password' => 'admin1234*',
                'status' => User::STATUS_ACTIVE,
            ]);
            $patient->roles()->attach(Role::firstOrCreate(['name' => 'paciente']));
        }

        $login = $this->post(route('login'), [
            'email' => $patient->email,
            'password' => 'admin1234*',
            'remember' => 0,
        ]);

        $login->assertRedirect(route('paciente.dashboard'));

        $this->get(route('paciente.dashboard'))
            ->assertOk()
            ->assertSee('Panel del paciente');
    }
}
