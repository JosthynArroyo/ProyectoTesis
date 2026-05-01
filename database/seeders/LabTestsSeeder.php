<?php

namespace Database\Seeders;

use App\Models\LabTest;
use Illuminate\Database\Seeder;

class LabTestsSeeder extends Seeder
{
    public function run(): void
    {
        $tests = [
            [
                'nombre' => 'Hemograma completo',
                'tipo' => 'rutina',
                'categoria' => 'Sangre',
                'requiere_orden' => false,
                'es_rutina' => true,
                'preparacion_default' => 'Ayuno: No requiere ayuno. Agua: Agua permitida. Horario recomendado: Mañana.',
                'indicaciones_default' => 'Evita ejercicio intenso 12 horas antes.',
                'ayuno_horas' => null,
                'restricciones' => null,
                'duracion_minutos' => 10,
            ],
            [
                'nombre' => 'Perfil lipídico',
                'tipo' => 'rutina',
                'categoria' => 'Sangre',
                'requiere_orden' => false,
                'es_rutina' => true,
                'preparacion_default' => 'Ayuno: 9 a 12 horas. Agua: Agua permitida. Horario recomendado: Mañana.',
                'indicaciones_default' => 'Evita comidas grasas 24 horas antes.',
                'ayuno_horas' => 9,
                'restricciones' => 'No consumir alcohol 24 horas antes.',
                'duracion_minutos' => 10,
            ],
            [
                'nombre' => 'Glucosa en ayunas',
                'tipo' => 'rutina',
                'categoria' => 'Sangre',
                'requiere_orden' => false,
                'es_rutina' => true,
                'preparacion_default' => 'Ayuno: 8 horas. Agua: Agua permitida. Horario recomendado: Antes de las 09:00.',
                'indicaciones_default' => 'No realizar actividad física intensa antes de la toma.',
                'ayuno_horas' => 8,
                'restricciones' => 'Evitar bebidas azucaradas.',
                'duracion_minutos' => 5,
            ],
            [
                'nombre' => 'Examen general de orina',
                'tipo' => 'rutina',
                'categoria' => 'Orina',
                'requiere_orden' => false,
                'es_rutina' => true,
                'preparacion_default' => 'Ayuno: No requiere ayuno. Agua: Evitar exceso antes de la toma. Horario recomendado: Primera orina de la mañana.',
                'indicaciones_default' => 'Recolecta muestra de mitad de la micción en frasco estéril.',
                'ayuno_horas' => null,
                'restricciones' => 'Evitar diuréticos si tu doctor no lo indicó.',
                'duracion_minutos' => 5,
            ],
            [
                'nombre' => 'Examen de heces',
                'tipo' => 'rutina',
                'categoria' => 'Heces',
                'requiere_orden' => false,
                'es_rutina' => true,
                'preparacion_default' => 'Ayuno: No requiere ayuno. Agua: Agua permitida. Horario recomendado: Entregar muestra el mismo día.',
                'indicaciones_default' => 'No mezclar la muestra con orina ni agua.',
                'ayuno_horas' => null,
                'restricciones' => 'Evitar laxantes 48 horas antes.',
                'duracion_minutos' => 5,
            ],
            [
                'nombre' => 'Perfil renal',
                'tipo' => 'especializado',
                'categoria' => 'Sangre',
                'requiere_orden' => true,
                'es_rutina' => false,
                'preparacion_default' => 'Ayuno: 8 horas. Agua: Agua permitida. Horario recomendado: Mañana.',
                'indicaciones_default' => 'Informar si toma diuréticos o antibióticos.',
                'ayuno_horas' => 8,
                'restricciones' => 'Evitar suplementos de creatina 48 horas antes.',
                'duracion_minutos' => 10,
            ],
        ];

        foreach ($tests as $test) {
            LabTest::updateOrCreate(
                ['nombre' => $test['nombre']],
                [
                    'tipo' => $test['tipo'],
                    'categoria' => $test['categoria'],
                    'requiere_orden' => $test['requiere_orden'],
                    'es_rutina' => $test['es_rutina'],
                    'preparacion_default' => $test['preparacion_default'],
                    'indicaciones_default' => $test['indicaciones_default'],
                    'ayuno_horas' => $test['ayuno_horas'],
                    'restricciones' => $test['restricciones'],
                    'duracion_minutos' => $test['duracion_minutos'],
                    'activo' => true,
                ]
            );
        }
    }
}
