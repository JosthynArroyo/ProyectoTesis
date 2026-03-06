<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperadminAdminsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_admins_index_renders(): void
    {
        $superadminRole = Role::create(['name' => 'superadmin']);
        $adminRole = Role::create(['name' => 'administrador']);

        $superadmin = User::factory()->create(['status' => 'active']);
        $superadmin->roles()->attach($superadminRole->id);

        $admin = User::factory()->create([
            'name' => 'Admin Demo',
            'status' => 'active',
        ]);
        $admin->roles()->attach($adminRole->id);

        $response = $this->actingAs($superadmin)->get(route('superadmin.admins.index'));

        $response->assertOk();
        $response->assertSee('Cuentas de administrador');
        $response->assertSee('Admin Demo');
    }
}
