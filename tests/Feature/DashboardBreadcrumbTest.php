<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardBreadcrumbTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_breadcrumb_inicio_points_to_dashboard(): void
    {
        $user = $this->createUserWithRole('superadmin');

        $response = $this->actingAs($user)->get(route('superadmin.users.index'));

        $response->assertOk();
        $response->assertSeeInOrder([
            '<nav aria-label="Breadcrumb" class="mt-1 lg:hidden">',
            'href="'.route('superadmin.dashboard').'"',
            '>Inicio<',
            '>Usuarios<',
        ], false);
        $response->assertDontSee('href="'.url('/superadmin').'" class="hover:text-slate-700">Inicio</a>', false);
    }

    public function test_dashboard_view_does_not_render_breadcrumb(): void
    {
        $user = $this->createUserWithRole('superadmin');

        $response = $this->actingAs($user)->get(route('superadmin.dashboard'));

        $response->assertOk();
        $response->assertDontSee('aria-label="Breadcrumb"', false);
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::create(['name' => $roleName]);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role->id);

        return $user;
    }
}
