<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    private const GENERIC_MAINTENANCE_MESSAGE = 'El sistema está en mantenimiento. Intenta nuevamente más tarde.';

    public function test_maintenance_blocks_regular_users(): void
    {
        SiteSetting::create([
            'key' => 'maintenance.enabled',
            'value' => '1',
            'section' => 'maintenance',
            'type' => 'boolean',
        ]);

        $response = $this->get('/');

        $response->assertStatus(503);
    }



    public function test_superadmin_can_bypass_maintenance(): void
    {
        SiteSetting::create([
            'key' => 'maintenance.enabled',
            'value' => '1',
            'section' => 'maintenance',
            'type' => 'boolean',
        ]);

        $role = Role::firstOrCreate(['name' => 'superadmin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role->id);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
    }

    public function test_superadmin_can_save_maintenance_without_allowlisted_ips(): void
    {
        $role = Role::firstOrCreate(['name' => 'superadmin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role->id);

        $this->actingAs($user)
            ->get(route('superadmin.maintenance.edit'))
            ->assertOk()
            ->assertSee('id="maintenance_allow_ips"', false)
            ->assertDontSee('placeholder="Ej: 127.0.0.1, 190.0.0.10" required', false);

        $response = $this
            ->actingAs($user)
            ->from(route('superadmin.maintenance.edit'))
            ->put(route('superadmin.maintenance.update'), [
                'maintenance_enabled' => '1',
                'maintenance_message' => 'Ventana de mantenimiento',
                'maintenance_until' => now()->addHour()->format('Y-m-d\TH:i'),
                'maintenance_allow_ips' => '',
            ]);

        $response
            ->assertRedirect(route('superadmin.maintenance.edit'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Configuracion de mantenimiento actualizada.');

        $this->assertDatabaseHas('site_settings', [
            'key' => 'maintenance.message',
            'value' => 'Ventana de mantenimiento',
        ]);

        $this->assertDatabaseHas('site_settings', [
            'key' => 'maintenance.allow_ips',
            'value' => null,
        ]);
    }

    public function test_allowlisted_remote_ip_can_bypass_maintenance(): void
    {
        $this->enableMaintenance('192.168.18.42');

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '192.168.18.42'])
            ->get('/');

        $response->assertOk();
    }

    public function test_allowlisted_forwarded_ip_can_bypass_maintenance(): void
    {
        $this->enableMaintenance('192.168.18.42');

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '10.0.0.10'])
            ->withHeader('X-Forwarded-For', '192.168.18.42, 10.0.0.10')
            ->get('/');

        $response->assertOk();
    }

    public function test_allowlisted_ip_can_login_during_maintenance(): void
    {
        $this->enableMaintenance('192.168.18.42');

        $role = Role::firstOrCreate(['name' => 'paciente']);
        $user = User::factory()->create([
            'email' => 'paciente@example.com',
            'password' => 'password',
            'status' => User::STATUS_ACTIVE,
        ]);
        $user->roles()->attach($role->id);

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '192.168.18.42'])
            ->post(route('login'), [
                'email' => $user->email,
                'password' => 'password',
                'remember' => '0',
            ]);

        $response->assertRedirect('paciente/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_maintenance_rejects_non_superadmin_login_with_generic_message(): void
    {
        $this->enableMaintenance();

        $role = Role::firstOrCreate(['name' => 'paciente']);
        $user = User::factory()->create([
            'email' => 'paciente@example.com',
            'password' => 'password',
            'status' => User::STATUS_ACTIVE,
        ]);
        $user->roles()->attach($role->id);

        $response = $this
            ->from(url('/').'?login=1')
            ->post(route('login'), [
                'email' => $user->email,
                'password' => 'password',
                'remember' => '0',
            ]);

        $response->assertRedirect(url('/').'?login=1');
        $response->assertSessionHasErrorsIn('login', ['email']);
        $response->assertSessionHas('auth_error', self::GENERIC_MAINTENANCE_MESSAGE);
        $this->assertGuest();
    }

    public function test_maintenance_allows_active_superadmin_login(): void
    {
        $this->enableMaintenance();

        $role = Role::firstOrCreate(['name' => 'superadmin']);
        $user = User::factory()->create([
            'email' => 'superadmin@example.com',
            'password' => 'password',
            'status' => User::STATUS_ACTIVE,
        ]);
        $user->roles()->attach($role->id);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '0',
        ]);

        $response->assertRedirect('superadmin/dashboard');
        $this->assertAuthenticatedAs($user);
    }



    private function enableMaintenance(?string $allowIps = null): void
    {
        SiteSetting::create([
            'key' => 'maintenance.enabled',
            'value' => '1',
            'section' => 'maintenance',
            'type' => 'boolean',
        ]);

        if ($allowIps !== null) {
            SiteSetting::create([
                'key' => 'maintenance.allow_ips',
                'value' => $allowIps,
                'section' => 'maintenance',
                'type' => 'text',
            ]);
        }
    }
}
