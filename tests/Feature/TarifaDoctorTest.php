<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TarifaDoctorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'doctor']);
        Role::firstOrCreate(['name' => 'paciente']);
        Role::firstOrCreate(['name' => 'administrador']);
    }

    public function test_devuelve_tarifa_de_doctor_activo()
    {
        $doctor = User::factory()->create([
            'active'          => true,
            'precio_consulta' => 25.50,
            'moneda'          => 'USD',
        ]);
        $doctor->roles()->sync([Role::where('name','doctor')->first()->id]);

        $res = $this->get(route('api.tarifa.doctor.show', ['id' => $doctor->id]));
        $res->assertOk()
            ->assertJson([
                'ok'        => true,
                'doctor_id' => $doctor->id,
                'precio'    => 25.50,
                'moneda'    => 'USD',
                'definido'  => true,
            ]);
    }

    public function test_404_si_no_es_doctor_o_inactivo()
    {
        $user = User::factory()->create(['active' => false]);

        $this->get(route('api.tarifa.doctor.show', ['id' => $user->id]))
            ->assertStatus(404);
    }
}
