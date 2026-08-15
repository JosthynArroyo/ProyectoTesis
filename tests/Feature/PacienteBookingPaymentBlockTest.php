<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Pago;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PacienteBookingPaymentBlockTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_paciente_puede_agendar_si_el_pago_pendiente_es_de_una_cita_futura(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 12:00:00', 'America/Guayaquil'));

        [$paciente, $doctor, $especialidad] = $this->crearActoresBase();

        $cita = Cita::factory()->create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => '2026-03-14',
            'hora' => '10:00:00',
            'estado' => Cita::ESTADO_CONFIRMADA,
            'activo' => true,
        ]);

        Pago::query()->create([
            'cita_id' => $cita->id,
            'paciente_id' => $paciente->id,
            'monto' => 30.00,
            'moneda' => 'USD',
            'estado' => Pago::ESTADO_PENDIENTE,
        ]);

        $this->actingAs($paciente)
            ->get(route('paciente.crear-cita'))
            ->assertOk();
    }

    public function test_paciente_no_puede_agendar_si_tiene_pago_pendiente_de_cita_vencida(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 12:00:00', 'America/Guayaquil'));

        [$paciente, $doctor, $especialidad] = $this->crearActoresBase();

        $cita = Cita::factory()->create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => '2026-03-08',
            'hora' => '08:30:00',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);

        Pago::query()->create([
            'cita_id' => $cita->id,
            'paciente_id' => $paciente->id,
            'monto' => 30.00,
            'moneda' => 'USD',
            'estado' => Pago::ESTADO_EN_VERIFICACION,
        ]);

        $this->actingAs($paciente)
            ->get(route('paciente.crear-cita'))
            ->assertRedirect(route('paciente.pagos.index'))
            ->assertSessionHasErrors('error');
    }

    private function crearActoresBase(): array
    {
        $paciente = User::factory()->create([
            'status' => 'active',
            'suspended_until' => null,
        ]);
        $doctor = User::factory()->create();
        $especialidad = Especialidad::factory()->create();
        Especialidad::factory()->create(['nombre' => 'Laboratorio Clínico']);

        $rolPaciente = Role::query()->firstOrCreate(['name' => 'paciente']);
        $rolDoctor = Role::query()->firstOrCreate(['name' => 'doctor']);

        $paciente->roles()->attach($rolPaciente->id);
        $doctor->roles()->attach($rolDoctor->id);
        $doctor->especialidades()->attach($especialidad->id);

        return [$paciente, $doctor, $especialidad];
    }
}
