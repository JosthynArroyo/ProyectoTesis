<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthModalRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_get_redirects_to_landing_modal(): void
    {
        $response = $this->get(route('login'));

        $response->assertRedirect(url('/') . '?login=1');
    }

    public function test_guest_access_to_protected_panel_redirects_to_landing_modal(): void
    {
        $response = $this->get(route('superadmin.dashboard'));

        $response->assertRedirect(url('/') . '?login=1');
    }

    public function test_salir_logs_out_and_returns_to_landing(): void
    {
        $role = Role::create(['name' => 'superadmin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role->id);

        $response = $this->actingAs($user)->post(route('salir'));

        $response->assertRedirect('/');
        $this->assertGuest();
    }
}
