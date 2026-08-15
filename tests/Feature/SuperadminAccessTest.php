<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SuperadminAccessTest extends TestCase
{
    use DatabaseTransactions;

    public function test_superadmin_can_access_dashboard(): void
    {
        $role = Role::firstOrCreate(['name' => 'superadmin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role->id);

        $response = $this->actingAs($user)->get(route('superadmin.dashboard'));

        $response->assertOk();
    }

    public function test_admin_cannot_access_superadmin_dashboard(): void
    {
        $role = Role::firstOrCreate(['name' => 'administrador']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role->id);

        $response = $this->actingAs($user)->get(route('superadmin.dashboard'));

        $response->assertRedirect('/');
    }
}
