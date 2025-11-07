<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            EspecialidadesSeeder::class,
        ]);

        $admin = User::updateOrCreate(
            ['email' => 'admin@clinic.test'], 
            [
                'name'     => 'Administrador',
                'password' => 'admin1234', 
                'active'   => true,
            ]
        );

        $adminRole = Role::where('name', 'administrador')->first();
        if ($adminRole) {
            $admin->roles()->sync([$adminRole->id]);
        }
    }
}
