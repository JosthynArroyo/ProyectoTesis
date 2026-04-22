<?php

namespace Database\Factories;

use App\Models\Especialidad;
use Illuminate\Database\Eloquent\Factories\Factory;

class CitaFactory extends Factory
{
    public function definition()
    {
        return [
            'paciente_id' => \App\Models\User::factory(),
            'doctor_id' => \App\Models\User::factory(),
            'fecha' => $this->faker->date(),
            'hora' => $this->faker->time(),
            'motivo_consulta' => $this->faker->randomElement([
                'Consulta general',
                'Dolor de cabeza',
                'Fiebre',
            ]),
            'estado' => 'pendiente',
            'activo' => true,
            'prioridad_nivel' => 'BAJA',
            'prioridad_fuente' => 'AUTOMATICA',
            'prioridad_red_flag' => false,
            'prioridad_red_flag_tipo' => null,
            'prioridad_comentario' => null,
            'prioridad_es_adulto_mayor' => false,
            'prioridad_es_embarazo' => false,
            'prioridad_es_discapacidad' => false,
            'prioridad_es_cronico' => false,
            'especialidad_id' => Especialidad::factory(),
        ];
    }
}
