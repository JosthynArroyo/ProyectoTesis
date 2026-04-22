<?php

namespace Database\Seeders;

use App\Models\Especialidad;
use Illuminate\Database\Seeder;

class EspecialidadesSeeder extends Seeder
{
    public function run(): void
    {
        $especialidades = [
            [
                'nombre' => 'Odontología',
                'descripcion' => 'Prevención, diagnóstico y tratamiento de problemas dentales y de salud bucal.',
            ],
            [
                'nombre' => 'Pediatría',
                'descripcion' => 'Atención médica de niños y adolescentes.',
            ],
            [
                'nombre' => 'Dermatología',
                'descripcion' => 'Salud de la piel, cabello y uñas.',
            ],
            [
                'nombre' => 'Medicina General',
                'descripcion' => 'Atención primaria integral para adultos.',
            ],
            [
                'nombre' => 'Ginecología',
                'descripcion' => 'Atención integral de salud femenina y controles preventivos.',
            ],
            [
                'nombre' => 'Laboratorio Clínico',
                'descripcion' => 'Exámenes de rutina y perfiles especializados con resultados oportunos.',
            ],
        ];

        foreach ($especialidades as $orden => $especialidad) {
            Especialidad::updateOrCreate(
                ['nombre' => $especialidad['nombre']],
                [
                    'descripcion' => $especialidad['descripcion'],
                    'activo' => true,
                    'orden' => $orden + 1,
                ]
            );
        }
    }
}
