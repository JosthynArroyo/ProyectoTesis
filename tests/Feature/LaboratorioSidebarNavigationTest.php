<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\LaboratorioOrden;
use App\Models\PedidoLaboratorio;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LaboratorioSidebarNavigationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function createLabUser(): User
    {
        $role = Role::where('name', 'laboratorio')->firstOrFail();
        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    public function test_laboratorio_sidebar_renders_citas_de_laboratorio_and_ordenes_medicas_internas_in_production(): void
    {
        config(['app.mode' => 'production']);
        $labUser = $this->createLabUser();

        $response = $this->actingAs($labUser)->get(route('laboratorio.dashboard'));

        $response->assertOk();
        $content = $response->getContent();

        // Must display "Citas de laboratorio" pointing to /laboratorio/ordenes
        $this->assertMatchesRegularExpression(
            '/<a[^>]*href="[^"]*\/laboratorio\/ordenes"[^>]*>[\s\S]*?Citas de laboratorio[\s\S]*?<\/a>/i',
            $content,
            'El sidebar debe mostrar el enlace "Citas de laboratorio" apuntando a /laboratorio/ordenes.'
        );

        // Must display "Órdenes médicas internas" pointing to /laboratorio/pedidos
        $this->assertMatchesRegularExpression(
            '/<a[^>]*href="[^"]*\/laboratorio\/pedidos(?:-mvp)?"[^>]*>[\s\S]*?Órdenes médicas internas[\s\S]*?<\/a>/i',
            $content,
            'El sidebar debe mostrar el enlace "Órdenes médicas internas" apuntando a /laboratorio/pedidos.'
        );

        // Must NOT display deprecated labels in the sidebar
        $this->assertDoesNotMatchRegularExpression(
            '/<a[^>]*href="[^"]*\/laboratorio\/ordenes"[^>]*>[\s\S]*?Citas y resultados[\s\S]*?<\/a>/i',
            $content,
            'El sidebar no debe mostrar la etiqueta ambigua "Citas y resultados".'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/<a[^>]*href="[^"]*\/laboratorio\/pedidos(?:-mvp)?"[^>]*>[\s\S]*?Pedidos Médicos[\s\S]*?<\/a>/i',
            $content,
            'El sidebar no debe mostrar la etiqueta ambigua "Pedidos Médicos".'
        );
    }

    public function test_laboratorio_sidebar_renders_identical_labels_in_demo_mode(): void
    {
        config(['app.mode' => 'demo']);
        $labUser = $this->createLabUser();

        $response = $this->actingAs($labUser)->get(route('laboratorio.dashboard'));

        $response->assertOk();
        $content = $response->getContent();

        $this->assertMatchesRegularExpression(
            '/<a[^>]*href="[^"]*\/laboratorio\/ordenes"[^>]*>[\s\S]*?Citas de laboratorio[\s\S]*?<\/a>/i',
            $content
        );

        $this->assertMatchesRegularExpression(
            '/<a[^>]*href="[^"]*\/laboratorio\/pedidos(?:-mvp)?"[^>]*>[\s\S]*?Órdenes médicas internas[\s\S]*?<\/a>/i',
            $content
        );
    }

    public function test_laboratorio_ordenes_view_shows_clear_title_active_state_and_filter(): void
    {
        config(['app.mode' => 'production']);
        $labUser = $this->createLabUser();

        $response = $this->actingAs($labUser)->get(route('laboratorio.ordenes.index'));

        $response->assertOk();
        $content = $response->getContent();

        // Active state on "Citas de laboratorio"
        $this->assertMatchesRegularExpression(
            '/<a[^>]*href="[^"]*\/laboratorio\/ordenes"[^>]*class="[^"]*bg-gray-100 text-gray-900 font-medium[^"]*"[^>]*>/',
            $content,
            'El enlace de "Citas de laboratorio" debe marcarse como activo en /laboratorio/ordenes.'
        );

        // Header title must say "Citas de laboratorio"
        $this->assertStringContainsString('Citas de laboratorio', $content);

        // Filter option for final state must say "Resultados listos"
        $this->assertMatchesRegularExpression(
            '/<option[^>]*value="resultado_disponible"[^>]*>[\s\S]*?Resultados listos[\s\S]*?<\/option>/i',
            $content,
            'El filtro de estado final en citas de laboratorio debe mostrar "Resultados listos" con value="resultado_disponible".'
        );
    }

    public function test_laboratorio_pedidos_view_shows_clear_title_active_state_filter_and_badge(): void
    {
        config(['app.mode' => 'production']);
        $labUser = $this->createLabUser();

        $doctor = User::factory()->create();
        $patient = User::factory()->create();
        $specialty = Especialidad::firstOrCreate(['nombre' => 'Medicina General'], ['precio' => 30]);

        $cita = Cita::create([
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $specialty->id,
            'fecha' => '2026-08-15',
            'hora' => '09:00:00',
            'motivo_consulta' => 'Consulta medica',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);

        $pedido = PedidoLaboratorio::create([
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'csv' => 'CSV-TEST-UX04',
            'examenes' => ['glucosa'],
            'estado' => PedidoLaboratorio::ESTADO_RESULTADO_LISTO,
            'sample_collected_at' => now(),
            'resultado_publicado_at' => now(),
        ]);

        $response = $this->actingAs($labUser)->get(route('laboratorio.pedidos.index'));

        $response->assertOk();
        $content = $response->getContent();

        // Active state on "Órdenes médicas internas"
        $this->assertMatchesRegularExpression(
            '/<a[^>]*href="[^"]*\/laboratorio\/pedidos(?:-mvp)?"[^>]*class="[^"]*bg-gray-100 text-gray-900 font-medium[^"]*"[^>]*>/',
            $content,
            'El enlace de "Órdenes médicas internas" debe marcarse como activo en /laboratorio/pedidos.'
        );

        // Header title must say "Órdenes médicas internas"
        $this->assertStringContainsString('Órdenes médicas internas', $content);

        // Filter option for final state must say "Resultados listos"
        $this->assertMatchesRegularExpression(
            '/<option[^>]*value="resultado_listo"[^>]*>[\s\S]*?Resultados listos[\s\S]*?<\/option>/i',
            $content,
            'El filtro de estado final en pedidos de laboratorio debe mostrar "Resultados listos" con value="resultado_listo".'
        );

        // Badge for individual ready order must display "Resultado listo"
        $this->assertStringContainsString('Resultado listo', $content);
    }

    public function test_filter_query_params_remain_functional(): void
    {
        config(['app.mode' => 'production']);
        $labUser = $this->createLabUser();

        // 1. Citas de laboratorio with ?estado=resultado_disponible
        $responseOrdenes = $this->actingAs($labUser)->get(route('laboratorio.ordenes.index', ['estado' => 'resultado_disponible']));
        $responseOrdenes->assertOk();
        $this->assertMatchesRegularExpression(
            '/<option[^>]*value="resultado_disponible"[^>]*selected/i',
            $responseOrdenes->getContent()
        );

        // 2. Órdenes médicas internas with ?estado=resultado_listo
        $responsePedidos = $this->actingAs($labUser)->get(route('laboratorio.pedidos.index', ['estado' => 'resultado_listo']));
        $responsePedidos->assertOk();
        $this->assertMatchesRegularExpression(
            '/<option[^>]*value="resultado_listo"[^>]*selected/i',
            $responsePedidos->getContent()
        );
    }
}
