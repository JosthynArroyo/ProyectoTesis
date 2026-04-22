<?php

namespace Tests\Feature;

use App\Events\CitaAgendada;
use App\Events\CitaAtendida;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Pago;
use App\Models\Role;
use App\Models\User;
use App\Services\CitaComprobanteService;
use App\Services\PagoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CitaComprobanteFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_agendar_cita_genera_comprobante_de_cita(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 08:00:00', 'America/Guayaquil'));

        [$paciente, $doctor, $especialidad] = $this->crearActoresBase();
        $cita = $this->crearCita($paciente, $doctor, $especialidad);

        event(new CitaAgendada($cita));

        $cita->refresh();

        $this->assertNotEmpty($cita->folio_cita);
        $this->assertNotEmpty($cita->token_validacion);
        $this->assertNotEmpty($cita->comprobante_pdf_path);
        $this->assertTrue($cita->tieneComprobanteCita());
        Storage::disk('local')->assertExists($cita->comprobante_pdf_path);
    }

    public function test_agendar_cita_no_genera_orden_de_pago(): void
    {
        [$paciente, $doctor, $especialidad] = $this->crearActoresBase();
        $cita = $this->crearCita($paciente, $doctor, $especialidad);

        event(new CitaAgendada($cita));

        $this->assertDatabaseMissing('pagos', [
            'cita_id' => $cita->id,
        ]);
    }

    public function test_reagendar_cita_actualiza_o_regenera_el_comprobante_con_la_nueva_fecha(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 08:00:00', 'America/Guayaquil'));

        [$paciente, $doctor, $especialidad] = $this->crearActoresBase();
        $cita = $this->crearCita($paciente, $doctor, $especialidad, [
            'fecha' => '2026-03-12',
            'hora' => '09:00:00',
        ]);

        event(new CitaAgendada($cita));

        $emitidoInicial = $cita->fresh()->comprobante_actualizado_en;

        Carbon::setTestNow(Carbon::parse('2026-03-10 09:30:00', 'America/Guayaquil'));

        $cita->forceFill([
            'fecha' => '2026-03-14',
            'hora' => '11:30:00',
            'estado' => Cita::ESTADO_PENDIENTE,
        ])->save();

        app(CitaComprobanteService::class)->sincronizarComprobante($cita);

        $cita->refresh();

        $this->actingAs($paciente)
            ->get(route('citas.comprobante.show', $cita->token_validacion))
            ->assertOk()
            ->assertSee('2026-03-14')
            ->assertSee('11:30');

        $this->assertTrue($cita->comprobante_actualizado_en->gt($emitidoInicial));
    }

    public function test_reagendar_cita_no_genera_pago(): void
    {
        [$paciente, $doctor, $especialidad] = $this->crearActoresBase();
        $cita = $this->crearCita($paciente, $doctor, $especialidad);

        event(new CitaAgendada($cita));

        $cita->forceFill([
            'fecha' => '2026-03-16',
            'hora' => '10:30:00',
        ])->save();

        app(CitaComprobanteService::class)->sincronizarComprobante($cita);

        $this->assertDatabaseMissing('pagos', [
            'cita_id' => $cita->id,
        ]);
    }

    public function test_cancelar_cita_invalida_comprobante_y_no_genera_pago(): void
    {
        [$paciente, $doctor, $especialidad] = $this->crearActoresBase();
        $cita = $this->crearCita($paciente, $doctor, $especialidad);

        event(new CitaAgendada($cita));

        $cita->forceFill([
            'estado' => Cita::ESTADO_CANCELADA,
            'activo' => false,
        ])->save();

        app(CitaComprobanteService::class)->sincronizarComprobante($cita);

        $this->actingAs($paciente)
            ->get(route('citas.comprobante.show', $cita->fresh()->token_validacion))
            ->assertOk()
            ->assertSee('Comprobante sin vigencia')
            ->assertSee('Cancelada');

        $this->assertDatabaseMissing('pagos', [
            'cita_id' => $cita->id,
        ]);
    }

    public function test_concluir_cita_si_genera_orden_de_pago(): void
    {
        [$paciente, $doctor, $especialidad] = $this->crearActoresBase();
        $cita = $this->crearCita($paciente, $doctor, $especialidad, [
            'estado' => Cita::ESTADO_REALIZADA,
        ]);

        event(new CitaAgendada($cita));
        event(new CitaAtendida($cita));

        $pago = Pago::query()->where('cita_id', $cita->id)->first();

        $this->assertNotNull($pago);
        $this->assertNotEmpty($pago->folio_unico);
        $this->assertNotEmpty($pago->token_publico);
        $this->assertNotEmpty($pago->orden_pdf_path);
        Storage::disk('local')->assertExists($pago->orden_pdf_path);

        $this->actingAs($paciente)
            ->get(route('pagos.token.show', $pago->token_publico))
            ->assertOk()
            ->assertSee($pago->folio_unico)
            ->assertSee($pago->token_publico);
    }

    public function test_paciente_con_cita_futura_no_queda_bloqueado(): void
    {
        [$paciente, $doctor, $especialidad] = $this->crearActoresBase();
        $cita = $this->crearCita($paciente, $doctor, $especialidad, [
            'fecha' => '2026-03-20',
            'hora' => '12:00:00',
        ]);

        event(new CitaAgendada($cita));

        $this->assertFalse(app(PagoService::class)->pacienteTieneBloqueo($paciente->id));
    }

    public function test_paciente_con_comprobante_sin_cita_concluida_no_aparece_con_deuda(): void
    {
        [$paciente, $doctor, $especialidad] = $this->crearActoresBase();
        $cita = $this->crearCita($paciente, $doctor, $especialidad);

        event(new CitaAgendada($cita));

        $this->actingAs($paciente)
            ->get(route('paciente.pagos.index'))
            ->assertOk()
            ->assertSee('No hay pagos para mostrar');
    }

    public function test_orden_de_pago_y_comprobante_usan_identificadores_distintos(): void
    {
        [$paciente, $doctor, $especialidad] = $this->crearActoresBase();
        $cita = $this->crearCita($paciente, $doctor, $especialidad, [
            'estado' => Cita::ESTADO_REALIZADA,
        ]);

        event(new CitaAgendada($cita));
        event(new CitaAtendida($cita));

        $cita->refresh();
        $pago = Pago::query()->where('cita_id', $cita->id)->firstOrFail();

        $this->assertNotSame($cita->folio_cita, $pago->folio_unico);
        $this->assertNotSame($cita->token_validacion, $pago->token_publico);
    }

    public function test_no_se_crean_duplicados_innecesarios(): void
    {
        [$paciente, $doctor, $especialidad] = $this->crearActoresBase();
        $cita = $this->crearCita($paciente, $doctor, $especialidad, [
            'estado' => Cita::ESTADO_REALIZADA,
        ]);

        event(new CitaAgendada($cita));
        $folioCita = $cita->fresh()->folio_cita;
        $tokenCita = $cita->fresh()->token_validacion;

        event(new CitaAgendada($cita));
        event(new CitaAtendida($cita));
        event(new CitaAtendida($cita));

        $cita->refresh();

        $this->assertSame($folioCita, $cita->folio_cita);
        $this->assertSame($tokenCita, $cita->token_validacion);
        $this->assertSame(1, Pago::query()->where('cita_id', $cita->id)->count());
    }

    private function crearActoresBase(): array
    {
        $paciente = User::factory()->create([
            'status' => 'active',
            'suspended_until' => null,
            'dni' => '0102030405',
        ]);
        $doctor = User::factory()->create([
            'precio_consulta' => 45.50,
            'moneda' => 'USD',
        ]);
        $especialidad = Especialidad::factory()->create();

        $rolPaciente = Role::query()->firstOrCreate(['name' => 'paciente']);
        $rolDoctor = Role::query()->firstOrCreate(['name' => 'doctor']);

        $paciente->roles()->syncWithoutDetaching([$rolPaciente->id]);
        $doctor->roles()->syncWithoutDetaching([$rolDoctor->id]);
        $doctor->especialidades()->syncWithoutDetaching([$especialidad->id]);

        return [$paciente, $doctor, $especialidad];
    }

    private function crearCita(User $paciente, User $doctor, Especialidad $especialidad, array $overrides = []): Cita
    {
        return Cita::factory()->create(array_merge([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => '2026-03-12',
            'hora' => '09:00:00',
            'estado' => Cita::ESTADO_PENDIENTE,
            'activo' => true,
        ], $overrides));
    }
}
