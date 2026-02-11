<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Especialidad;
use Illuminate\Support\Facades\Schema;

class EspecialidadesSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        Especialidad::truncate();
        Schema::enableForeignKeyConstraints();

        Especialidad::create([
            'nombre' => 'Odontologia',
            'descripcion' => 'Prevencion, diagnostico y tratamiento de problemas dentales y de salud bucal.',
        ]);
        Especialidad::create([
            'nombre' => 'Pediatria',
            'descripcion' => 'Atencion medica de ninos y adolescentes.',
        ]);
        Especialidad::create([
            'nombre' => 'Dermatologia',
            'descripcion' => 'Salud de la piel, cabello y unas.',
        ]);
        Especialidad::create([
            'nombre' => 'Medicina General',
            'descripcion' => 'Atencion primaria integral para adultos.',
        ]);
        Especialidad::create([
            'nombre' => 'Ginecologia',
            'descripcion' => 'Atencion integral de salud femenina y controles preventivos.',
        ]);
        Especialidad::create([
            'nombre' => 'Laboratorio Clinico',
            'descripcion' => 'Examenes de rutina y perfiles especializados con resultados oportunos.',
        ]);
    }
}
