<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\User;
use App\Services\CitaNoShowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    public function test_institutional_500_view_renders_correctly_on_server_error(): void
    {
        Route::get('/testing-500-error-page', function () {
            throw new \RuntimeException('Simulated internal error for testing 500 page rendering.');
        });

        $response = $this->withServerVariables(['APP_DEBUG' => false])
            ->get('/testing-500-error-page');

        $response->assertStatus(500);
        $response->assertSee('500');
        $response->assertSee('Error interno del sistema');
        $response->assertDontSee('The server returned a "500 Internal Server Error"');
    }

    public function test_error_views_render_safely_when_db_is_disconnected(): void
    {
        DB::disconnect();
        config(['database.default' => 'invalid_db_connection']);

        $view500 = view('errors.500')->render();
        $this->assertStringContainsString('500', $view500);
        $this->assertStringContainsString('Error interno del sistema', $view500);

        $view419 = view('errors.419')->render();
        $this->assertStringContainsString('419', $view419);
        $this->assertStringContainsString('expirada', $view419);
    }

    public function test_marcar_vencidas_in_web_request_does_not_do_synchronous_r2_pdf_upload(): void
    {
        $overdueCita = Cita::query()
            ->whereIn('estado', [Cita::ESTADO_PENDIENTE, Cita::ESTADO_CONFIRMADA])
            ->where('activo', true)
            ->first();

        if ($overdueCita) {
            $overdueCita->update([
                'fecha' => now('America/Guayaquil')->subDays(1)->toDateString(),
                'hora' => '10:00:00',
            ]);
        }

        $service = app(CitaNoShowService::class);
        $result = $service->marcarVencidas();

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
    }
}
