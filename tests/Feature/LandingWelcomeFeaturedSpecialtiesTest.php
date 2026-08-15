<?php

namespace Tests\Feature;

use App\Models\Especialidad;
use App\Models\LandingWelcomeFeaturedSpecialty;
use App\Models\LandingWelcomeSetting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LandingWelcomeFeaturedSpecialtiesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        LandingWelcomeSetting::query()->delete();
        LandingWelcomeFeaturedSpecialty::query()->delete();
        Especialidad::query()->update(['activo' => false]);
    }

    public function test_home_fallback_shows_only_three_active_specialties_when_featured_is_empty(): void
    {
        $especialidades = collect([
            ['nombre' => 'Cardiologia', 'orden' => 1],
            ['nombre' => 'Pediatria', 'orden' => 2],
            ['nombre' => 'Dermatologia', 'orden' => 3],
            ['nombre' => 'Neurologia', 'orden' => 4],
            ['nombre' => 'Ginecologia', 'orden' => 5],
        ])->map(fn (array $item) => Especialidad::query()->create([
            'nombre' => $item['nombre'],
            'descripcion' => $item['nombre'].' desc',
            'activo' => true,
            'orden' => $item['orden'],
        ]));

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSeeText($especialidades[0]->nombre);
        $response->assertSeeText($especialidades[1]->nombre);
        $response->assertSeeText($especialidades[2]->nombre);
        $response->assertDontSeeText($especialidades[3]->nombre);
        $response->assertDontSeeText($especialidades[4]->nombre);
    }

    public function test_home_shows_only_first_three_selected_featured_specialties(): void
    {
        $especialidades = collect([
            ['nombre' => 'Traumatologia', 'orden' => 1],
            ['nombre' => 'Odontologia', 'orden' => 2],
            ['nombre' => 'Oftalmologia', 'orden' => 3],
            ['nombre' => 'Endocrinologia', 'orden' => 4],
        ])->map(fn (array $item) => Especialidad::query()->create([
            'nombre' => $item['nombre'],
            'descripcion' => $item['nombre'].' desc',
            'activo' => true,
            'orden' => $item['orden'],
        ]));

        foreach ($especialidades as $index => $especialidad) {
            LandingWelcomeFeaturedSpecialty::query()->create([
                'especialidad_id' => $especialidad->id,
                'sort_order' => $index + 1,
            ]);
        }

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSeeText($especialidades[0]->nombre);
        $response->assertSeeText($especialidades[1]->nombre);
        $response->assertSeeText($especialidades[2]->nombre);
        $response->assertDontSeeText($especialidades[3]->nombre);
    }
}
