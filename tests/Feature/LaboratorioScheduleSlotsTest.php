<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Horario;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaboratorioScheduleSlotsTest extends TestCase
{
    use RefreshDatabase;

    public function test_laboratory_slots_api_uses_configured_schedule_only(): void
    {
        $laboratorioRole = Role::firstOrCreate(['name' => 'laboratorio']);
        $pacienteRole = Role::firstOrCreate(['name' => 'paciente']);

        $especialidad = Especialidad::factory()->create(['nombre' => 'Laboratorio Clinico']);
        $laboratorio = User::factory()->create(['status' => 'active', 'suspended_until' => null]);
        $laboratorio->roles()->attach($laboratorioRole->id);
        $laboratorio->especialidades()->attach($especialidad->id);

        $paciente = User::factory()->create(['status' => 'active', 'suspended_until' => null]);
        $paciente->roles()->attach($pacienteRole->id);

        $fecha = '2026-03-16';

        Horario::create([
            'doctor_id' => $laboratorio->id,
            'fecha' => $fecha,
            'hora_inicio' => '09:10',
            'hora_fin' => '10:10',
            'intervalo_minutos' => 20,
        ]);

        Cita::create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $laboratorio->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => $fecha,
            'hora' => '09:30:00',
            'motivo_consulta' => 'Control',
            'estado' => Cita::ESTADO_PENDIENTE,
            'activo' => true,
        ]);

        $this->get(route('api.doctor.slots', ['doctor' => $laboratorio->id, 'fecha' => $fecha]))
            ->assertOk()
            ->assertJsonFragment(['hora' => '09:10', 'estado' => 'libre'])
            ->assertJsonFragment(['hora' => '09:30', 'estado' => 'ocupado'])
            ->assertJsonFragment(['hora' => '09:50', 'estado' => 'libre'])
            ->assertJsonMissing(['hora' => '08:00']);
    }
}
