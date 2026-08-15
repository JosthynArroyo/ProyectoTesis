<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SidebarProfileOrderTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_sidebar_places_profile_before_logout(): void
    {
        $admin = $this->createUserWithRole('administrador');

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSeeInOrder(['Historial clínico', 'Perfil', 'Cerrar sesión'], false);
    }

    public function test_doctor_sidebar_places_profile_before_logout(): void
    {
        $doctor = $this->createUserWithRole('doctor');

        $response = $this->actingAs($doctor)->get(route('doctor.dashboard'));

        $response->assertOk();
        $response->assertSeeInOrder(['Mi horario', 'Perfil', 'Cerrar sesión'], false);
    }

    public function test_paciente_sidebar_keeps_profile_before_logout(): void
    {
        $paciente = $this->createUserWithRole('paciente');

        $response = $this->actingAs($paciente)->get(route('paciente.dashboard'));

        $response->assertOk();
        $response->assertSeeInOrder(['Agendar cita', 'Perfil', 'Cerrar sesión'], false);
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role->id);

        return $user;
    }
}
