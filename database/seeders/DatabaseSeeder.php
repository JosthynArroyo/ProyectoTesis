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
            LabTestsSeeder::class,
        ]);

        $superadmin = User::updateOrCreate(
            ['email' => 'superadmin@clinic.test'],
            [
                'name'     => 'Superadmin',
                'password' => 'superadmin1234',
                'active'   => true,
                'status'   => 'active',
            ]
        );

        $superRole = Role::where('name', 'superadmin')->first();
        if ($superRole) {
            $superadmin->roles()->sync([$superRole->id]);
        }
    }
}
