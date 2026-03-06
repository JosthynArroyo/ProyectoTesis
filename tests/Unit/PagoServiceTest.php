<?php

namespace Tests\Unit;

use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Pago;
use App\Models\Role;
use App\Models\User;
use App\Services\PagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagoServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_guarda_aprobador_al_rechazar_pago(): void
    {
        $paciente = User::factory()->create();
        $doctor = User::factory()->create();
        $especialidad = Especialidad::factory()->create();

        $cita = Cita::factory()->create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
        ]);

        $actor = User::factory()->create();
        $adminRole = Role::query()->firstOrCreate(['name' => 'administrador']);
        $actor->roles()->attach($adminRole->id);

        $pago = Pago::query()->create([
            'cita_id' => $cita->id,
            'paciente_id' => $paciente->id,
            'monto' => 25.00,
            'moneda' => 'USD',
            'estado' => Pago::ESTADO_PENDIENTE,
        ]);

        app(PagoService::class)->cambiarEstado(
            pago: $pago,
            nuevoEstado: Pago::ESTADO_RECHAZADO,
            actor: $actor,
            motivo: 'Comprobante invalido'
        );

        $pago->refresh();

        $this->assertSame(Pago::ESTADO_RECHAZADO, $pago->estado);
        $this->assertNull($pago->aprobado_por);
        $this->assertNull($pago->aprobado_en);
    }
}

