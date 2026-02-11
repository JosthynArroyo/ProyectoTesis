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

        $role = Role::create(['name' => 'superadmin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role->id);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
    }
}
