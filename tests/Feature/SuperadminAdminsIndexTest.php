<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperadminAdminsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_admins_index_renders(): void
    {
        $superadminRole = Role::firstOrCreate(['name' => 'superadmin']);
        $adminRole = Role::firstOrCreate(['name' => 'administrador']);

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

    public function test_superadmin_admin_creation_redirects_back_to_admins_index_with_success_message(): void
    {
        $superadminRole = Role::firstOrCreate(['name' => 'superadmin']);
        $adminRole = Role::firstOrCreate(['name' => 'administrador']);

        $superadmin = User::factory()->create([
            'status' => 'active',
            'password' => Hash::make('superadmin1234'),
        ]);
        $superadmin->roles()->attach($superadminRole->id);

        $response = $this->actingAs($superadmin)->post(route('superadmin.admins.store'), [
            'name' => 'Nuevo Admin',
            'email' => 'nuevo.admin@example.test',
            'password' => 'Admin1234*',
            'password_confirmation' => 'Admin1234*',
            'telefono' => '0991112233',
            'dni' => '0912345678',
            'direccion' => 'Calle Principal 123',
            'fecha_nacimiento' => '1990-01-10',
            'sexo' => 'Masculino',
        ]);

        $admin = User::query()->where('email', 'nuevo.admin@example.test')->firstOrFail();

        $response->assertRedirect(route('superadmin.admins.index'));
        $response->assertSessionHas('success', 'Administrador creado correctamente.');
        $this->assertTrue($admin->hasRole('administrador'));
        $this->assertTrue(Hash::check('Admin1234*', $admin->password));
        $this->assertNotEquals($superadmin->id, $admin->id);
        $this->assertSame($adminRole->id, $admin->roles()->firstOrFail()->id);
    }
}
