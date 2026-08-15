<?php

namespace Tests\Feature;

use App\Models\Especialidad;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminOverrideAvailabilityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_override_create_uses_dynamic_professional_and_slot_selects(): void
    {
        $admin = $this->createUserWithRole('administrador', 'Admin QA');
        $this->createUserWithRole('paciente', 'Paciente QA');

        $medicina = Especialidad::factory()->create(['nombre' => 'Medicina General']);
        $laboratorio = Especialidad::factory()->create(['nombre' => 'Laboratorio Clínico']);

        $doctor = $this->createUserWithRole('doctor', 'Doctor Medicina');
        $doctor->especialidades()->attach($medicina->id);

        $lab = $this->createUserWithRole('laboratorio', 'Laboratorio QA');
        $lab->especialidades()->attach($laboratorio->id);

        $response = $this->actingAs($admin)->get(route('admin.citas.override.create'));

        $response->assertOk()
            ->assertSee('data-endpoint-template="'.route('especialidades.doctores', ['especialidad' => 'ESP_ID']).'"', false)
            ->assertSee('data-slots-template="'.url('/api/doctor/DOC_ID/fecha/FECHA/slots').'"', false)
            ->assertSee('data-laboratorio-id="'.$laboratorio->id.'"', false)
            ->assertSee('<select id="doctor_id" name="doctor_id" class="form-select" required disabled>', false)
            ->assertSee('<select id="hora" name="hora" class="form-select" required disabled>', false)
            ->assertDontSee('type="time"', false)
            ->assertDontSee($doctor->name, false)
            ->assertDontSee($lab->name, false);
    }

    public function test_doctores_endpoint_returns_only_doctors_for_medical_specialty(): void
    {
        $medicina = Especialidad::factory()->create(['nombre' => 'Medicina General']);
        $laboratorio = Especialidad::factory()->create(['nombre' => 'Laboratorio Clínico']);

        $doctor = $this->createUserWithRole('doctor', 'Doctor Filtrado');
        $doctor->especialidades()->attach($medicina->id);

        $lab = $this->createUserWithRole('laboratorio', 'Laboratorio Filtrado');
        $lab->especialidades()->attach([$medicina->id, $laboratorio->id]);

        $this->get(route('especialidades.doctores', $medicina))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $doctor->id,
                'name' => $doctor->name,
            ])
            ->assertJsonMissing([
                'id' => $lab->id,
                'name' => $lab->name,
            ]);
    }

    public function test_doctores_endpoint_returns_only_laboratory_users_for_laboratory_specialty(): void
    {
        $laboratorio = Especialidad::factory()->create(['nombre' => 'Laboratorio Clínico']);

        $doctor = $this->createUserWithRole('doctor', 'Doctor No Lab');
        $doctor->especialidades()->attach($laboratorio->id);

        $lab = $this->createUserWithRole('laboratorio', 'Laboratorio Correcto');
        $lab->especialidades()->attach($laboratorio->id);

        $this->get(route('especialidades.doctores', $laboratorio))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $lab->id,
                'name' => $lab->name,
            ])
            ->assertJsonMissing([
                'id' => $doctor->id,
                'name' => $doctor->name,
            ]);
    }

    private function createUserWithRole(string $roleName, string $name): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        $user = User::factory()->create([
            'name' => $name,
            'status' => 'active',
            'suspended_until' => null,
        ]);

        $user->roles()->attach($role->id);

        return $user;
    }
}
