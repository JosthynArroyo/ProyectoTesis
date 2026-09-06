<?php

namespace Tests\Feature;

use App\Models\PedidoLaboratorio;
use App\Models\PedidoLaboratorioResultado;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DemoLaboratorySeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoDoctorPedidosLaboratorioTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.mode' => 'demo']);
    }

    /**
     * Grupo A: Coherencia del dataset de laboratorio y renderizado del índice para el Doctor canónico.
     *
     * Contratos cubiertos:
     *   — El Dr. Fernando Alvarado tiene al menos 1 pedido en estado resultado_listo
     *   — El pedido publicado tiene pdf_path, resultado_path, csv y timestamps coherentes
     *   — Existe un resultado asociado con estado publicado, pdf_path y publicado_at
     *   — Al menos un pedido está asociado al paciente canónico Javier Espinoza
     *   — Sin PII en la serialización del dataset
     *   — GET /doctor/pedidos-laboratorio responde 200 y muestra secciones esperadas
     */
    public function test_canonical_doctor_lab_orders_structure_and_index(): void
    {
        $this->seed(DemoSeeder::class);

        $doctor = User::where('email', 'doctor.medicina@demo-clinigest.test')->first();
        $this->assertNotNull($doctor, 'Canonical doctor Dr. Fernando Alvarado must exist');

        $pedidos = PedidoLaboratorio::with(['resultados', 'paciente'])
            ->where('doctor_id', $doctor->id)
            ->get();

        $this->assertGreaterThanOrEqual(1, $pedidos->count(), 'Canonical doctor must have at least 1 laboratory order');

        $publishedOrder = $pedidos->firstWhere('estado', PedidoLaboratorio::ESTADO_RESULTADO_LISTO);
        $this->assertNotNull($publishedOrder, 'Doctor must have at least one order with estado=resultado_listo');

        $publishedResult = $publishedOrder->resultados->firstWhere('estado', PedidoLaboratorioResultado::ESTADO_PUBLICADO);
        $this->assertNotNull($publishedResult, 'Published order must have an associated resultado with estado=publicado');

        // Coherence invariants
        $this->assertNotEmpty($publishedOrder->pdf_path, 'Order must have a valid pdf_path');
        $this->assertNotEmpty($publishedResult->pdf_path, 'Published result must have a valid pdf_path');
        $this->assertNotEmpty($publishedOrder->resultado_path, 'Order must reference the published resultado_path');
        $this->assertNotNull($publishedResult->publicado_at, 'Result must have publicado_at timestamp');
        $this->assertNotNull($publishedOrder->resultado_publicado_at, 'Order must have resultado_publicado_at timestamp');

        // Distinct CSV codes
        $this->assertNotEquals($publishedOrder->csv, $publishedResult->csv, 'Order and result must have distinct CSV codes');
        $this->assertStringStartsWith('ORD-2026-', $publishedOrder->csv);
        $this->assertStringStartsWith('LAB-2026-', $publishedResult->csv);

        // Canonical patient association
        $hasCanonicalPatient = $pedidos->contains(fn ($p) => $p->paciente?->email === 'paciente@demo-clinigest.test');
        $this->assertTrue($hasCanonicalPatient, 'At least one order must be associated with canonical patient Javier Espinoza');

        // Zero PII
        $serialized = json_encode($pedidos->toArray());
        $this->assertStringNotContainsStringIgnoringCase('josthyn', $serialized);
        $this->assertStringNotContainsStringIgnoringCase('arroyo', $serialized);

        // HTTP index renders correctly
        $response = $this->actingAs($doctor)->get(route('doctor.pedidos-laboratorio.index'));
        $response->assertStatus(200);
        $response->assertSee('Pedidos de laboratorio');
        $response->assertSee('Resultados publicados');
        $response->assertSee('Ver informe');
        $response->assertSee('Descargar informe');
        $response->assertSee('Verificación pública');
        $response->assertSee('Ver orden firmada');
        $response->assertSee('Javier Espinoza');
        $response->assertDontSee('El informe aún no está publicado.');
    }

    /**
     * Grupo B: Descargas PDF de resultado y orden, control de acceso y verificación pública.
     *
     * Contratos cubiertos:
     *   — GET resultado PDF devuelve 200 con Content-Type application/pdf y attachment
     *   — GET orden PDF devuelve 200 con Content-Type application/pdf
     *   — Otro doctor recibe 403 al intentar descargar resultado ajeno
     *   — Página pública de verificación CSV devuelve 200 con nombre del doctor
     */
    public function test_canonical_doctor_lab_order_downloads_and_access_control(): void
    {
        $this->seed(DemoSeeder::class);

        $doctor      = User::where('email', 'doctor.medicina@demo-clinigest.test')->firstOrFail();
        $otherDoctor = User::where('email', 'doctor.pediatria@demo-clinigest.test')->firstOrFail();

        $pedido = PedidoLaboratorio::where('doctor_id', $doctor->id)
            ->where('estado', PedidoLaboratorio::ESTADO_RESULTADO_LISTO)
            ->firstOrFail();

        $resultado = $pedido->resultados()
            ->where('estado', PedidoLaboratorioResultado::ESTADO_PUBLICADO)
            ->firstOrFail();

        // Download resultado PDF — attachment
        $response = $this->actingAs($doctor)->get(route('doctor.pedidos-laboratorio.resultado.download', [
            'pedido'      => $pedido->id,
            'disposition' => 'attachment',
        ]));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition') ?? '');

        // Download orden PDF
        $response = $this->actingAs($doctor)->get(route('doctor.pedidos-laboratorio.download', $pedido));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');

        // Access control: otro doctor recibe 403
        $response = $this->actingAs($otherDoctor)->get(route('doctor.pedidos-laboratorio.resultado.download', [
            'pedido' => $pedido->id,
        ]));
        $response->assertStatus(403);

        // Public verification page
        $response = $this->get(route('documentos.verificar.show', ['csv' => $resultado->csv]));
        $response->assertStatus(200);
        $response->assertSee('Informe de laboratorio');
        $response->assertSee('Verificado');
        $response->assertSee('Dr. Fernando Alvarado');
    }

    /**
     * Idempotencia: DemoLaboratorySeeder no duplica órdenes ni resultados al re-ejecutarse.
     * — Requiere 2 runs del seeder por diseño (esto es inherente al contrato de idempotencia).
     */
    public function test_demo_laboratory_seeder_is_idempotent(): void
    {
        $this->seed(DemoSeeder::class);
        $countPedidos1   = PedidoLaboratorio::count();
        $countResultados1 = PedidoLaboratorioResultado::count();

        // Re-run seeder
        $this->seed(DemoLaboratorySeeder::class);
        $countPedidos2   = PedidoLaboratorio::count();
        $countResultados2 = PedidoLaboratorioResultado::count();

        $this->assertEquals($countPedidos1, $countPedidos2, 'Re-running DemoLaboratorySeeder must not duplicate orders');
        $this->assertEquals($countResultados1, $countResultados2, 'Re-running DemoLaboratorySeeder must not duplicate results');
    }
}
