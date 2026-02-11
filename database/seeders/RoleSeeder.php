<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['superadmin','administrador','doctor','paciente','laboratorio'] as $name) {
            Role::firstOrCreate(['name' => $name]);
        }
    }
}
