<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Pago;
use App\Models\Role;
use App\Models\User;
use App\Services\PagoService;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DemoPacienteAgendarCitaSinDeudaTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    /**
     * Test 1: En modo demo, Javier Espinoza tiene historial financiero pero cero deuda vencida bloqueante.
     */
    public function test_canonical_patient_has_clean_financial_history_without_overdue_blocking_debt_in_demo_mode(): void
    {
        config(['app.mode' => 'demo']);
        $this->seed(DemoSeeder::class);

        $paciente = User::where('email', 'paciente@demo-clinigest.test')->firstOrFail();

        $pagos = Pago::where('paciente_id', $paciente->id)->get();
        $this->assertGreaterThanOrEqual(2, $pagos->count(), 'Javier Espinoza debe tener al menos 2 pagos en su historial.');

        $pagoService = app(PagoService::class);
        $tieneBloqueo = $pagoService->pacienteTieneBloqueo($paciente->id);

        $this->assertFalse(
            $tieneBloqueo,
            'Javier Espinoza no debe tener bloqueo financiero activo en modo demo.'
        );

        // Verificar que el dataset general aún contiene variedad de estados para otros pacientes
        $otrosPagos = Pago::where('paciente_id', '!=', $paciente->id)->get();
        $this->assertGreaterThan(0, $otrosPagos->where('estado', Pago::ESTADO_EN_VERIFICACION)->count(), 'Debe haber pagos en verificación en el dataset.');
        $this->assertGreaterThan(0, $otrosPagos->where('estado', Pago::ESTADO_PENDIENTE)->count(), 'Debe haber pagos pendientes en el dataset.');
    }

    /**
     * Test 2: Javier Espinoza puede entrar a Agendar Cita (/paciente/crear-cita) con HTTP 200 sin ser redirigido por deuda.
     */
    public function test_canonical_patient_can_access_create_appointment_flow_without_payment_block_in_demo_mode(): void
    {
        config(['app.mode' => 'demo']);
        $this->seed(DemoSeeder::class);

        $paciente = User::where('email', 'paciente@demo-clinigest.test')->firstOrFail();

        // 1. Vista de citas no muestra alerta de bloqueo
        $responseCitas = $this->actingAs($paciente)->get(route('paciente.citas'));
        $responseCitas->assertOk();
        $responseCitas->assertDontSee(PagoService::MENSAJE_BLOQUEO);

        // 2. Ruta de agendar cita abre exitosamente con 200 y no redirige a /paciente/pagos
        $responseCrear = $this->actingAs($paciente)->get(route('paciente.crear-cita'));
        $responseCrear->assertOk();
        $responseCrear->assertSee('Agendar');
        $responseCrear->assertDontSee(PagoService::MENSAJE_BLOQUEO);
    }

    /**
     * Test 3: En producción, un paciente con deuda vencida real de cita realizada continúa estrictamente bloqueado.
     */
    public function test_production_mode_strictly_blocks_patient_with_overdue_debt(): void
    {
        config(['app.mode' => 'production']);

        $rolePaciente = Role::where('name', 'paciente')->firstOrFail();
        $roleDoctor = Role::where('name', 'doctor')->firstOrFail();

        $paciente = User::factory()->create(['status' => 'active']);
        $paciente->roles()->sync([$rolePaciente->id]);

        $doctor = User::factory()->create(['status' => 'active', 'precio_consulta' => 35.00]);
        $doctor->roles()->sync([$roleDoctor->id]);

        $especialidad = Especialidad::firstOrCreate(['nombre' => 'Medicina General']);

        // Cita realizada hace 3 días (deuda vencida)
        $cita = Cita::create([
            'folio_cita' => 'CIT-PROD-TEST-01',
            'doctor_id' => $doctor->id,
            'paciente_id' => $paciente->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => now()->subDays(3)->format('Y-m-d'),
            'hora' => '10:00:00',
            'motivo_consulta' => 'Consulta médica previa',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
            'prioridad_nivel' => 'media',
            'prioridad_fuente' => 'auto',
            'token_validacion' => 'token_prod_test_01',
        ]);

        // Pago pendiente en la cita concluida
        Pago::create([
            'cita_id' => $cita->id,
            'paciente_id' => $paciente->id,
            'monto' => 35.00,
            'moneda' => 'USD',
            'estado' => Pago::ESTADO_PENDIENTE,
            'folio_unico' => 'PAG-PROD-TEST-01',
            'token_publico' => 'token_pago_prod_01',
            'csv' => 'PAG-PROD-TEST-01',
        ]);

        $pagoService = app(PagoService::class);
        $this->assertTrue($pagoService->pacienteTieneBloqueo($paciente->id));

        // GET /paciente/crear-cita debe ser bloqueado y redirigido a /paciente/pagos
        $response = $this->actingAs($paciente)->get(route('paciente.crear-cita'));
        $response->assertRedirect(route('paciente.pagos.index'));
        $response->assertSessionHasErrors('error');
    }

    /**
     * Test 4: En modo demo NO existe un bypass global: un paciente con deuda vencida real sigue siendo bloqueado por la regla real.
     */
    public function test_demo_mode_does_not_have_global_bypass_for_patients_with_real_overdue_debt(): void
    {
        config(['app.mode' => 'demo']);

        $rolePaciente = Role::where('name', 'paciente')->firstOrFail();
        $roleDoctor = Role::where('name', 'doctor')->firstOrFail();

        $pacienteConDeuda = User::factory()->create(['status' => 'active']);
        $pacienteConDeuda->roles()->sync([$rolePaciente->id]);

        $doctor = User::factory()->create(['status' => 'active', 'precio_consulta' => 35.00]);
        $doctor->roles()->sync([$roleDoctor->id]);

        $especialidad = Especialidad::firstOrCreate(['nombre' => 'Medicina General']);

        // Cita realizada hace 4 días
        $cita = Cita::create([
            'folio_cita' => 'CIT-DEMO-TEST-DEUDA',
            'doctor_id' => $doctor->id,
            'paciente_id' => $pacienteConDeuda->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => now()->subDays(4)->format('Y-m-d'),
            'hora' => '10:00:00',
            'motivo_consulta' => 'Consulta con deuda no saldada',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
            'prioridad_nivel' => 'media',
            'prioridad_fuente' => 'auto',
            'token_validacion' => 'token_demo_test_deuda',
        ]);

        Pago::create([
            'cita_id' => $cita->id,
            'paciente_id' => $pacienteConDeuda->id,
            'monto' => 35.00,
            'moneda' => 'USD',
            'estado' => Pago::ESTADO_EN_VERIFICACION,
            'folio_unico' => 'PAG-DEMO-TEST-DEUDA',
            'token_publico' => 'token_pago_demo_deuda',
            'csv' => 'PAG-DEMO-TEST-DEUDA',
        ]);

        $pagoService = app(PagoService::class);
        $this->assertTrue(
            $pagoService->pacienteTieneBloqueo($pacienteConDeuda->id),
            'La regla financiera debe seguir bloqueando a cualquier paciente que tenga deuda vencida en modo demo.'
        );

        $response = $this->actingAs($pacienteConDeuda)->get(route('paciente.crear-cita'));
        $response->assertRedirect(route('paciente.pagos.index'));
        $response->assertSessionHasErrors('error');
    }
}
