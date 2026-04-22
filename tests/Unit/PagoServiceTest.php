<?php

namespace Tests\Unit;

use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Pago;
use App\Models\Role;
use App\Models\User;
use App\Services\PagoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PagoServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

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

    public function test_crear_pago_para_cita_no_habilita_orden_hasta_concluir_la_cita(): void
    {
        $paciente = User::factory()->create();
        $doctor = User::factory()->create([
            'precio_consulta' => 35.50,
            'moneda' => 'USD',
        ]);
        $especialidad = Especialidad::factory()->create();

        $cita = Cita::factory()->create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
        ]);

        $pago = app(PagoService::class)->crearParaCita($cita);

        $this->assertSame(Pago::ESTADO_PENDIENTE, $pago->estado);
        $this->assertNull($pago->folio_unico);
        $this->assertNull($pago->token_publico);
        $this->assertFalse($pago->tieneOrdenCobro());
    }

    public function test_asegurar_datos_orden_asigna_identificadores_unicos_y_estables(): void
    {
        $paciente = User::factory()->create();
        $doctor = User::factory()->create();
        $especialidad = Especialidad::factory()->create();

        $cita = Cita::factory()->create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => '2026-03-08',
            'hora' => '09:00:00',
            'estado' => Cita::ESTADO_REALIZADA,
        ]);

        $pago = Pago::query()->create([
            'cita_id' => $cita->id,
            'paciente_id' => $paciente->id,
            'monto' => 40.00,
            'moneda' => 'USD',
            'estado' => Pago::ESTADO_PENDIENTE,
        ]);

        $orden = app(PagoService::class)->asegurarDatosOrden($pago);

        $this->assertMatchesRegularExpression('/^OC-20260308-\d{6}$/', $orden->folio_unico);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{48}$/', $orden->token_publico);
        $this->assertSame(1, Pago::query()->where('folio_unico', $orden->folio_unico)->count());
        $this->assertSame(1, Pago::query()->where('token_publico', $orden->token_publico)->count());

        $folio = $orden->folio_unico;
        $token = $orden->token_publico;

        $segundaPasada = app(PagoService::class)->asegurarDatosOrden($orden);

        $this->assertSame($folio, $segundaPasada->folio_unico);
        $this->assertSame($token, $segundaPasada->token_publico);
    }

    public function test_multiples_ordenes_no_reutilizan_folios_ni_tokens(): void
    {
        $paciente = User::factory()->create();
        $doctor = User::factory()->create();
        $especialidad = Especialidad::factory()->create();
        $service = app(PagoService::class);

        $folios = [];
        $tokens = [];

        for ($i = 0; $i < 5; $i++) {
            $cita = Cita::factory()->create([
                'paciente_id' => $paciente->id,
                'doctor_id' => $doctor->id,
                'especialidad_id' => $especialidad->id,
                'fecha' => '2026-03-08',
                'hora' => sprintf('09:%02d:00', $i * 5),
                'estado' => Cita::ESTADO_REALIZADA,
            ]);

            $pago = Pago::query()->create([
                'cita_id' => $cita->id,
                'paciente_id' => $paciente->id,
                'monto' => 40.00,
                'moneda' => 'USD',
                'estado' => Pago::ESTADO_PENDIENTE,
            ]);

            $orden = $service->asegurarDatosOrden($pago);
            $folios[] = $orden->folio_unico;
            $tokens[] = $orden->token_publico;
        }

        $this->assertCount(5, array_unique($folios));
        $this->assertCount(5, array_unique($tokens));
    }

    public function test_colision_de_folio_usa_sufijo_sin_romper_la_orden(): void
    {
        $paciente = User::factory()->create();
        $doctor = User::factory()->create();
        $especialidad = Especialidad::factory()->create();

        $citaReservada = Cita::factory()->create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => '2026-03-08',
            'hora' => '09:00:00',
            'estado' => Cita::ESTADO_REALIZADA,
        ]);
        $citaConColision = Cita::factory()->create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => '2026-03-08',
            'hora' => '09:30:00',
            'estado' => Cita::ESTADO_REALIZADA,
        ]);

        $pagoReservado = Pago::query()->create([
            'cita_id' => $citaReservada->id,
            'paciente_id' => $paciente->id,
            'monto' => 40.00,
            'moneda' => 'USD',
            'estado' => Pago::ESTADO_PENDIENTE,
        ]);
        $pagoConColision = Pago::query()->create([
            'cita_id' => $citaConColision->id,
            'paciente_id' => $paciente->id,
            'monto' => 40.00,
            'moneda' => 'USD',
            'estado' => Pago::ESTADO_PENDIENTE,
        ]);

        $folioBaseDelSegundoPago = sprintf('OC-20260308-%06d', $pagoConColision->id);
        $pagoReservado->forceFill([
            'folio_unico' => $folioBaseDelSegundoPago,
            'token_publico' => Str::lower(Str::random(48)),
        ])->save();

        $orden = app(PagoService::class)->asegurarDatosOrden($pagoConColision);

        $this->assertSame($folioBaseDelSegundoPago.'-2', $orden->folio_unico);
        $this->assertNotEmpty($orden->token_publico);
        $this->assertSame(1, Pago::query()->where('folio_unico', $orden->folio_unico)->count());
    }

    public function test_bloqueo_aplica_solo_a_ordenes_vencidas_de_citas_realizadas(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 12:00:00', 'America/Guayaquil'));

        $paciente = User::factory()->create();
        $doctor = User::factory()->create();
        $especialidad = Especialidad::factory()->create();

        $citaFutura = Cita::factory()->create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => '2026-03-15',
            'hora' => '10:00:00',
            'estado' => Cita::ESTADO_CONFIRMADA,
            'activo' => true,
        ]);

        Pago::query()->create([
            'cita_id' => $citaFutura->id,
            'paciente_id' => $paciente->id,
            'monto' => 25.00,
            'moneda' => 'USD',
            'estado' => Pago::ESTADO_PENDIENTE,
        ]);

        $this->assertFalse(app(PagoService::class)->pacienteTieneBloqueo($paciente->id));

        $citaVencida = Cita::factory()->create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => '2026-03-08',
            'hora' => '09:00:00',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);

        Pago::query()->create([
            'cita_id' => $citaVencida->id,
            'paciente_id' => $paciente->id,
            'monto' => 40.00,
            'moneda' => 'USD',
            'estado' => Pago::ESTADO_EN_VERIFICACION,
        ]);

        $this->assertTrue(app(PagoService::class)->pacienteTieneBloqueo($paciente->id));
    }
}
