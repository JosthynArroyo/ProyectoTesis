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
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CitaComprobanteFlowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['private_documents.disk' => 'r2_private']);
        Storage::fake('r2_private');
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
        $this->assertTrue($cita->tieneComprobanteCita());
        $this->assertEmpty($cita->comprobante_pdf_path);

        $this->actingAs($paciente)
            ->get(route('paciente.citas.comprobante.pdf', $cita))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $cita->refresh();
        $this->assertNotEmpty($cita->comprobante_pdf_path);
        $this->assertSame('r2_private', $cita->comprobante_pdf_disk);
        Storage::disk('r2_private')->assertExists($cita->comprobante_pdf_path);
        $pdfBytes = Storage::disk('r2_private')->get($cita->comprobante_pdf_path);
        $this->assertStringStartsWith('%PDF', $pdfBytes);
        $this->assertEmpty(Storage::disk('local')->allFiles('citas/comprobantes'));
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

        app(CitaComprobanteService::class)->obtenerOGenerarPdf($cita);
        $emitidoInicial = $cita->fresh()->comprobante_actualizado_en;

        Carbon::setTestNow(Carbon::parse('2026-03-10 09:30:00', 'America/Guayaquil'));

        $cita->forceFill([
            'fecha' => '2026-03-14',
            'hora' => '11:30:00',
            'estado' => Cita::ESTADO_PENDIENTE,
        ])->save();

        app(CitaComprobanteService::class)->obtenerOGenerarPdf($cita);

        $cita->refresh();

        $this->actingAs($paciente)
            ->get(route('paciente.citas.comprobante.pdf', $cita))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $cita->refresh();
        $this->assertNotEmpty($cita->comprobante_pdf_path);
        $this->assertSame('r2_private', $cita->comprobante_pdf_disk);
        Storage::disk('r2_private')->assertExists($cita->comprobante_pdf_path);
        $pdfBytes = Storage::disk('r2_private')->get($cita->comprobante_pdf_path);
        $this->assertStringStartsWith('%PDF', $pdfBytes);
        $this->assertEmpty(Storage::disk('local')->allFiles('citas/comprobantes'));

        $this->actingAs($paciente)
            ->get(route('citas.comprobante.show', $cita->token_validacion))
            ->assertOk()
            ->assertSee('14/03/2026')
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

        app(CitaComprobanteService::class)->obtenerOGenerarPdf($cita);

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

        app(CitaComprobanteService::class)->obtenerOGenerarPdf($cita);

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
        $this->assertEmpty($pago->orden_pdf_path);

        $this->actingAs($paciente)
            ->get(route('paciente.pagos.orden.pdf', $pago))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $pago->refresh();
        $this->assertNotEmpty($pago->orden_pdf_path);
        $disk = $pago->orden_pdf_disk ?: 'r2_private';
        Storage::disk($disk)->assertExists($pago->orden_pdf_path);

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
            ->assertSee('No hay órdenes de cobro para mostrar.');
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

    public function test_nuevo_comprobante_cita_requisitos_obligatorios(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 08:00:00', 'America/Guayaquil'));

        [$paciente, $doctor, $especialidad] = $this->crearActoresBase();

        $paciente->forceFill([
            'email' => 'paciente_secreto@ejemplo.com',
            'telefono' => '0999999999',
        ])->save();

        $cita = $this->crearCita($paciente, $doctor, $especialidad, [
            'motivo_consulta' => 'Dolor de cabeza severo con migraña',
        ]);

        app(CitaComprobanteService::class)->asegurarComprobante($cita);
        $cita->refresh();

        // 1. Una cita nueva recibe CSV único
        $this->assertNotEmpty($cita->csv);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{3}-\d{5}-[A-Z0-9]{3}$/', $cita->csv);

        // 2. Su QR apunta a /verificar/{csv}
        $docService = app(\App\Services\AppointmentConfirmationDocumentService::class);
        $pdfBytes = $docService->generateConfirmationContent($cita);
        $this->assertStringStartsWith('%PDF', $pdfBytes);

        // 3. Un visitante sin sesión recibe 200 HTML
        $response = $this->get(route('documentos.verificar.show', ['csv' => $cita->csv]));
        $response->assertStatus(200);

        // 4. Renderiza el header público y no requiere autenticación
        $response->assertSee('id="cnav-header"', false);
        $response->assertOk();

        // 6. No devuelve %PDF
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $this->assertStringStartsNotWith('%PDF', $response->getContent());

        // 7. No expone rutas locales ni claves R2
        $response->assertDontSee('documents/appointment-confirmations');
        $response->assertDontSee('r2_private');

        // 8. No muestra cédula, teléfono, correo ni motivo de consulta
        $response->assertDontSee($paciente->dni);
        $response->assertDontSee($paciente->telefono);
        $response->assertDontSee($paciente->email);
        $response->assertDontSee('Dolor de cabeza severo con migraña');

        // Mostramos información pública permitida únicamente:
        $response->assertSee('Comprobante de cita');
        $response->assertSee($cita->csv);
        $response->assertSee($cita->folio_cita);
        $response->assertSee($doctor->name);
        $response->assertSee($especialidad->nombre);
        // Nombre protegido
        $parts = explode(' ', trim($paciente->name));
        $expectedProtected = $parts[0].' '.strtoupper(substr($parts[1] ?? '', 0, 1)).'.';
        $response->assertSee($expectedProtected);
        $response->assertSee('Pendiente');

        // 9. Un CSV inexistente devuelve 404
        $this->get(route('documentos.verificar.show', ['csv' => 'XYZ-12345-ABC']))
            ->assertStatus(404);

        // 10. El enlace heredado /cita/comprobante/{token} funciona públicamente
        $legacyResponse = $this->get(route('citas.comprobante.show', ['token' => $cita->token_validacion]));
        $legacyResponse->assertStatus(200);
        $legacyResponse->assertDontSee($paciente->dni);
        $legacyResponse->assertDontSee($paciente->telefono);
        $legacyResponse->assertDontSee($paciente->email);
        $legacyResponse->assertDontSee('Dolor de cabeza severo con migraña');
        $legacyResponse->assertSee($cita->folio_cita);

        // 11. Las descargas reales continúan exigiendo autorización
        $this->get(route('paciente.citas.comprobante.pdf', $cita))
            ->assertRedirect(url('/?login=1'));

        // 12. Recetas, certificados, laboratorio, pagos y recibos no cambian
        $receta = \App\Models\Receta::create([
            'cita_id' => $cita->id,
            'diagnostico' => 'Gripe común',
            'medicamentos' => 'Paracetamol 500mg',
            'csv' => app(\App\Services\DocumentoCsvService::class)->generateCsv(),
            'pdf_path' => 'documents/recipes/dummy.pdf',
        ]);
        $recetaResponse = $this->get(route('documentos.verificar.show', ['csv' => $receta->csv]));
        $recetaResponse->assertStatus(200);
        $recetaResponse->assertSee('Receta médica');
    }

    public function test_comprobante_cita_para_dependiente(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 08:00:00', 'America/Guayaquil'));

        [$paciente, $doctor, $especialidad] = $this->crearActoresBase();

        $paciente->forceFill([
            'name' => 'Josthyn Arroyo',
        ])->save();

        $dependiente = \App\Models\Dependiente::create([
            'user_id' => $paciente->id,
            'nombre' => 'Anabel Arroyo',
            'tipo_documento' => 'cedula',
            'parentesco' => 'hija',
            'dni' => '0102030406',
            'fecha_nacimiento' => '2020-05-15',
            'sexo' => 'Femenino',
            'activo' => true,
        ]);

        $cita = $this->crearCita($paciente, $doctor, $especialidad, [
            'dependiente_id' => $dependiente->id,
        ]);

        app(CitaComprobanteService::class)->asegurarComprobante($cita);
        $cita->refresh();

        // Verify CSV verification page for dependent
        $response = $this->get(route('documentos.verificar.show', ['csv' => $cita->csv]));
        $response->assertStatus(200);

        // Representative name should NOT appear as the patient
        $response->assertDontSee('Josthyn A.');

        // Protected name of dependent should appear
        $response->assertSee('Anabel A.');

        // Legacy validation token route should also show the dependent's protected name
        $legacyResponse = $this->get(route('citas.comprobante.show', ['token' => $cita->token_validacion]));
        $legacyResponse->assertStatus(200);
        $legacyResponse->assertDontSee('Josthyn A.');
        $legacyResponse->assertSee('Anabel A.');
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
