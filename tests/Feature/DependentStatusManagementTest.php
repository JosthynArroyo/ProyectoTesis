<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\ClinicalRecord;
use App\Models\Dependiente;
use App\Models\Especialidad;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DependentStatusManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dependiente_se_puede_desactivar_y_reactivar_sin_perder_visibilidad(): void
    {
        $titular = $this->paciente();
        $dependiente = $this->dependiente($titular);

        $this->actingAs($titular)
            ->patch(route('paciente.dependientes.deactivate', $dependiente))
            ->assertRedirect(route('paciente.dependientes.index'));

        $this->assertDatabaseHas('dependientes', [
            'id' => $dependiente->id,
            'activo' => false,
        ]);

        $this->actingAs($titular)
            ->get(route('paciente.dependientes.index'))
            ->assertOk()
            ->assertSee('Dependientes inactivos')
            ->assertSee('Activar');

        $this->actingAs($titular)
            ->patch(route('paciente.dependientes.activate', $dependiente))
            ->assertRedirect(route('paciente.dependientes.index'));

        $this->assertDatabaseHas('dependientes', [
            'id' => $dependiente->id,
            'activo' => true,
        ]);
    }

    public function test_eliminacion_definitiva_se_bloquea_si_tiene_citas_y_se_mantiene_desactivado(): void
    {
        $titular = $this->paciente();
        $dependiente = $this->dependiente($titular);
        $doctor = $this->doctor();
        $especialidad = Especialidad::factory()->create();

        Cita::create([
            'paciente_id' => $titular->id,
            'dependiente_id' => $dependiente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => now()->addDay()->toDateString(),
            'hora' => '09:00:00',
            'motivo_consulta' => 'Control asociado',
            'estado' => Cita::ESTADO_CONFIRMADA,
            'activo' => true,
        ]);

        $this->actingAs($titular)
            ->delete(route('paciente.dependientes.destroy', $dependiente))
            ->assertRedirect(route('paciente.dependientes.index'));

        $this->assertDatabaseHas('dependientes', [
            'id' => $dependiente->id,
            'activo' => false,
        ]);
        $this->assertDatabaseHas('citas_medicas', [
            'dependiente_id' => $dependiente->id,
        ]);
    }

    public function test_eliminacion_definitiva_funciona_si_no_tiene_historial(): void
    {
        $titular = $this->paciente();
        $dependiente = $this->dependiente($titular);

        $this->actingAs($titular)
            ->delete(route('paciente.dependientes.destroy', $dependiente))
            ->assertRedirect(route('paciente.dependientes.index'));

        $this->assertDatabaseMissing('dependientes', [
            'id' => $dependiente->id,
        ]);
    }

    private function paciente(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'paciente']));

        return $user;
    }

    private function doctor(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'doctor']));

        return $user;
    }

    private function dependiente(User $titular): Dependiente
    {
        $dependiente = Dependiente::create([
            'user_id' => $titular->id,
            'nombre' => 'Dependiente Demo',
            'dni' => '0987654321',
            'fecha_nacimiento' => now()->subYears(10)->toDateString(),
            'sexo' => 'Femenino',
            'parentesco' => 'hija',
            'activo' => true,
        ]);

        ClinicalRecord::create([
            'dependiente_id' => $dependiente->id,
            'patient_id' => null,
            'allergies_status' => ClinicalRecord::ALLERGIES_UNKNOWN,
        ]);

        return $dependiente;
    }
}
