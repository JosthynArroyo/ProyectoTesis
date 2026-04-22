<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            EspecialidadesSeeder::class,
            LabTestsSeeder::class,
        ]);

        $this->seedSuperadmin();
    }

    private function seedSuperadmin(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'superadmin@clinic.test'],
            [
                'name' => 'Superadmin',
                'email' => 'superadmin@clinic.test',
                'password' => 'superadmin1234',
                'telefono' => '0990000100',
                'dni' => '1000000100',
                'direccion' => 'Clinica central',
                'fecha_nacimiento' => '1985-01-10',
                'sexo' => 'Masculino',
                'status' => User::STATUS_ACTIVE,
            ]
        );

        $roleId = Role::query()->where('name', 'superadmin')->value('id');
        if ($roleId) {
            $user->roles()->sync([$roleId]);
        }

        $user->especialidades()->sync([]);
    }
}
