<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthModalRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_get_redirects_to_landing_modal(): void
    {
        $response = $this->get(route('login'));

        $response->assertRedirect(url('/').'?login=1');
    }

    public function test_guest_access_to_protected_panel_redirects_to_landing_modal(): void
    {
        $response = $this->get(route('superadmin.dashboard'));

        $response->assertRedirect(url('/').'?login=1');
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

    public function test_landing_login_modal_exposes_remember_me_controls(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('data-remember-login-form', false);
        $response->assertSee('data-remember-login-email', false);
        $response->assertSee('data-remember-login-checkbox', false);
    }

    public function test_login_with_remember_sets_persistent_recaller_cookie(): void
    {
        $role = Role::create(['name' => 'paciente']);
        $user = User::factory()->create([
            'email' => 'paciente@example.com',
            'password' => 'password',
            'status' => User::STATUS_ACTIVE,
        ]);
        $user->roles()->attach($role->id);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ]);

        $response->assertRedirect('paciente/dashboard');
        $response->assertCookie(Auth::guard()->getRecallerName());
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_without_remember_does_not_set_recaller_cookie(): void
    {
        $role = Role::create(['name' => 'paciente']);
        $user = User::factory()->create([
            'email' => 'paciente@example.com',
            'password' => 'password',
            'status' => User::STATUS_ACTIVE,
        ]);
        $user->roles()->attach($role->id);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '0',
        ]);

        $response->assertRedirect('paciente/dashboard');
        $response->assertCookieMissing(Auth::guard()->getRecallerName());
        $this->assertAuthenticatedAs($user);
    }
}
