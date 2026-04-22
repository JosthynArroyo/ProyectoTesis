<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('especialidades')->where('nombre', 'Odontologia')->update([
            'nombre' => 'Odontología',
            'descripcion' => 'Prevención, diagnóstico y tratamiento de problemas dentales y de salud bucal.',
        ]);

        DB::table('especialidades')->where('nombre', 'Pediatria')->update([
            'nombre' => 'Pediatría',
            'descripcion' => 'Atención médica de niños y adolescentes.',
        ]);

        DB::table('especialidades')->where('nombre', 'Dermatologia')->update([
            'nombre' => 'Dermatología',
            'descripcion' => 'Salud de la piel, cabello y uñas.',
        ]);

        DB::table('especialidades')->where('nombre', 'Medicina General')->update([
            'descripcion' => 'Atención primaria integral para adultos.',
        ]);

        DB::table('especialidades')->where('nombre', 'Ginecologia')->update([
            'nombre' => 'Ginecología',
            'descripcion' => 'Atención integral de salud femenina y controles preventivos.',
        ]);

        DB::table('especialidades')->where('nombre', 'Laboratorio Clinico')->update([
            'nombre' => 'Laboratorio Clínico',
            'descripcion' => 'Exámenes de rutina y perfiles especializados con resultados oportunos.',
        ]);
    }

    public function down(): void
    {
        DB::table('especialidades')->where('nombre', 'Odontología')->update([
            'nombre' => 'Odontologia',
            'descripcion' => 'Prevencion, diagnostico y tratamiento de problemas dentales y de salud bucal.',
        ]);

        DB::table('especialidades')->where('nombre', 'Pediatría')->update([
            'nombre' => 'Pediatria',
            'descripcion' => 'Atencion medica de ninos y adolescentes.',
        ]);

        DB::table('especialidades')->where('nombre', 'Dermatología')->update([
            'nombre' => 'Dermatologia',
            'descripcion' => 'Salud de la piel, cabello y unas.',
        ]);

        DB::table('especialidades')->where('nombre', 'Medicina General')->update([
            'descripcion' => 'Atencion primaria integral para adultos.',
        ]);

        DB::table('especialidades')->where('nombre', 'Ginecología')->update([
            'nombre' => 'Ginecologia',
            'descripcion' => 'Atencion integral de salud femenina y controles preventivos.',
        ]);

        DB::table('especialidades')->where('nombre', 'Laboratorio Clínico')->update([
            'nombre' => 'Laboratorio Clinico',
            'descripcion' => 'Examenes de rutina y perfiles especializados con resultados oportunos.',
        ]);
    }
};
