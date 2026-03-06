<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelBackButtonTest extends TestCase
{
    use RefreshDatabase;

    public function test_nested_superadmin_page_renders_panel_back_button(): void
    {
        $user = $this->createUserWithRole('superadmin');

        $response = $this->actingAs($user)->get(route('superadmin.admins.create'));

        $response->assertOk();
        $response->assertSee('data-panel-back-anchor', false);
    }

    public function test_sidebar_superadmin_page_does_not_render_panel_back_button(): void
    {
        $user = $this->createUserWithRole('superadmin');

        $response = $this->actingAs($user)->get(route('superadmin.admins.index'));

        $response->assertOk();
        $response->assertDontSee('data-panel-back-anchor', false);
    }

    public function test_nested_admin_page_renders_panel_back_button(): void
    {
        $admin = $this->createUserWithRole('administrador');
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($admin)->get(route('admin.usuarios.show', $user));

        $response->assertOk();
        $response->assertSee('data-panel-back-anchor', false);
        $response->assertSee(route('admin.dashboard'), false);
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::create(['name' => $roleName]);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role->id);

        return $user;
    }
}
