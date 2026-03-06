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

        $fecha = '2026-03-15';

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

    public function test_doctor_laboratory_order_form_uses_slot_select_instead_of_manual_time(): void
    {
        $doctor = $this->createUserWithRole('doctor');
        $paciente = $this->createUserWithRole('paciente');
        $laboratorio = $this->createUserWithRole('laboratorio');
        $especialidad = Especialidad::factory()->create(['nombre' => 'Laboratorio Clinico']);
        $laboratorio->especialidades()->attach($especialidad->id);

        $response = $this->actingAs($doctor)->get(route('doctor.laboratorio.create', ['paciente_id' => $paciente->id]));

        $response->assertOk()
            ->assertSee('data-slots-template="'.url('/api/doctor/DOC_ID/fecha/FECHA/slots').'"', false)
            ->assertSee('<select id="hora" name="hora" required disabled class="form-select">', false)
            ->assertDontSee('type="time"', false);
    }

    public function test_doctor_laboratory_order_rejects_slot_outside_configured_schedule(): void
    {
        $doctor = $this->createUserWithRole('doctor');
        $paciente = $this->createUserWithRole('paciente');
        $laboratorio = $this->createUserWithRole('laboratorio');
        $especialidad = Especialidad::factory()->create(['nombre' => 'Laboratorio Clinico']);
        $laboratorio->especialidades()->attach($especialidad->id);

        Horario::create([
            'doctor_id' => $laboratorio->id,
            'fecha' => '2026-03-16',
            'hora_inicio' => '09:00',
            'hora_fin' => '10:00',
            'intervalo_minutos' => 20,
        ]);

        $this->actingAs($doctor)
            ->from(route('doctor.laboratorio.create'))
            ->post(route('doctor.laboratorio.store'), [
                'paciente_id' => $paciente->id,
                'doctor_id' => $laboratorio->id,
                'fecha' => '2026-03-16',
                'hora' => '08:00',
                'motivo_consulta' => 'Control general',
                'tipo_examen' => 'Hemograma',
                'prioridad' => 'normal',
                'indicaciones' => 'Sin indicaciones especiales',
                'preparacion' => 'Ayuno',
            ])
            ->assertRedirect(route('doctor.laboratorio.create'))
            ->assertSessionHasErrors([
                'hora' => 'No hay horario configurado para ese laboratorio en ese dia y hora.',
            ]);
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user = User::factory()->create([
            'status' => 'active',
            'suspended_until' => null,
        ]);

        $user->roles()->attach($role->id);

        return $user;
    }
}
