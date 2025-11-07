<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Especialidad;

class CitaFactory extends Factory
{
    public function definition()
    {
        return [
            'paciente_id' => \App\Models\User::factory(),
            'doctor_id' => \App\Models\User::factory(),
            'fecha' => $this->faker->date(),
            'hora' => $this->faker->time(),
            'estado' => 'pendiente',
            'especialidad_id' => Especialidad::factory(),
        ];
    }
}
