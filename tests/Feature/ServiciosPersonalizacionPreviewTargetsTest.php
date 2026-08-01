<?php

namespace Tests\Feature;

use App\Models\Especialidad;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class ServiciosPersonalizacionPreviewTargetsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_services_preview_targets_are_unique_and_pair_input_to_image(): void
    {
        $superadmin = User::factory()->create(['status' => 'active']);
        $especialidad = Especialidad::query()->create([
            'nombre' => 'Odontologia',
            'descripcion' => 'Atencion dental',
            'icono' => 'ri-tooth-line',
            'activo' => true,
            'orden' => 1,
        ]);

        $this->app->make('view')->share('errors', new ViewErrorBag());

        $response = $this->withoutMiddleware()->actingAs($superadmin)->get(route('superadmin.personalizacion.servicios.edit'));

        $response->assertOk();

        $content = $response->getContent();

        $this->assertSame(1, substr_count($content, 'data-preview-target="services-hero"'));
        $this->assertSame(1, substr_count($content, 'data-preview-id="services-hero"'));
        $this->assertSame(1, substr_count($content, 'data-preview-target="service-'.$especialidad->id.'"'));
        $this->assertSame(1, substr_count($content, 'data-preview-id="service-'.$especialidad->id.'"'));
        $this->assertSame(1, substr_count($content, 'data-preview-target="service-new-__INDEX__"'));
        $this->assertSame(1, substr_count($content, 'data-preview-id="service-new-__INDEX__"'));
    }
}
