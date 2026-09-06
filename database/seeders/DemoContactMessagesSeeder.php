<?php

namespace Database\Seeders;

use App\Models\ContactMessage;
use Illuminate\Database\Seeder;

class DemoContactMessagesSeeder extends Seeder
{
    /**
     * Run the demo contact messages seeds.
     */
    public function run(): void
    {
        $messages = [
            [
                'nombre' => 'María Fernanda Cevallos',
                'correo' => 'maria.cevallos@contacto-demo.test',
                'telefono' => '0981234501',
                'asunto' => 'Consulta sobre disponibilidad en Cardiología',
                'mensaje' => 'Buenas tardes, quisiera consultar si tienen disponibilidad para una valoración cardiológica preventiva esta semana y cuáles son los horarios de atención para consultas de primera vez. Muchas gracias.',
                'estado' => 'nuevo',
                'created_at' => now()->subHours(2),
                'updated_at' => now()->subHours(2),
            ],
            [
                'nombre' => 'Andrés Molina Delgado',
                'correo' => 'andres.molina@contacto-demo.test',
                'telefono' => '0981234502',
                'asunto' => 'Requisitos para exámenes de laboratorio en ayunas',
                'mensaje' => 'Estimados, requiero realizarme un perfil lipídico y glucosa en el laboratorio clínico de la clínica. ¿Es necesario agendar turno previo o la atención en laboratorio es por orden de llegada desde las 07:00? Saludos cordiales.',
                'estado' => 'nuevo',
                'created_at' => now()->subHours(8),
                'updated_at' => now()->subHours(8),
            ],
            [
                'nombre' => 'Camila Paredes Vintimilla',
                'correo' => 'camila.paredes@contacto-demo.test',
                'telefono' => '0981234503',
                'asunto' => 'Atención pediátrica para control de niño sano',
                'mensaje' => 'Hola, me gustaría información sobre los pediatras disponibles en la clínica y si atienden los días sábados en jornada matutina para el control de mi hija de 4 años.',
                'estado' => 'leido',
                'created_at' => now()->subDay(),
                'updated_at' => now()->subHours(18),
            ],
            [
                'nombre' => 'Roberto Carlos Aguirre',
                'correo' => 'roberto.aguirre@contacto-demo.test',
                'telefono' => '0981234504',
                'asunto' => 'Convenios institucionales y formas de pago',
                'mensaje' => 'Buen día, deseo conocer si cuentan con cobertura o convenios institucionales para atención ambulatoria en medicina general y qué métodos de pago aceptan en caja.',
                'estado' => 'leido',
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(2),
            ],
        ];

        foreach ($messages as $data) {
            ContactMessage::updateOrCreate(
                [
                    'correo' => $data['correo'],
                    'asunto' => $data['asunto'],
                ],
                [
                    'nombre' => $data['nombre'],
                    'telefono' => $data['telefono'],
                    'mensaje' => $data['mensaje'],
                    'estado' => $data['estado'],
                    'created_at' => $data['created_at'],
                    'updated_at' => $data['updated_at'],
                ]
            );
        }
    }
}
