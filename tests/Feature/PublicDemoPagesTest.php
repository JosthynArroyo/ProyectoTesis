<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicDemoPagesTest extends TestCase
{
    public function test_demo_pages_render_for_guests(): void
    {
        foreach ($this->demoEntryRoutes() as $routeName) {
            $response = $this->get(route($routeName));

            $response->assertOk();
            $response->assertDontSee("route('salir')", false);
            $response->assertDontSee('method="POST"', false);
        }
    }

    public function test_demo_index_lists_the_five_roles(): void
    {
        $this->get(route('demo.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'Superadmin',
                'Administrador',
                'Paciente',
                'Doctor',
                'Laboratorio',
            ], false);
    }

    public function test_demo_routes_are_public_and_read_only(): void
    {
        $demoRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'demo.'));

        $this->assertNotEmpty($demoRoutes);

        foreach ($demoRoutes as $route) {
            $allowedMethods = [['GET', 'HEAD'], ['POST'], ['PUT'], ['PATCH'], ['DELETE']];
            $this->assertTrue(
                collect($allowedMethods)->contains(fn ($m) => count(array_diff($route->methods(), $m)) === 0),
                "Route methods " . json_encode($route->methods()) . " not allowed for " . $route->uri()
            );
            $this->assertNotContains('auth', $route->gatherMiddleware(), $route->uri());
        }
    }

    /**
     * @return array<int, string>
     */
    private function demoEntryRoutes(): array
    {
        return [
            'demo.index',
            'demo.superadmin.dashboard',
            'demo.admin.dashboard',
            'demo.paciente.dashboard',
            'demo.doctor.dashboard',
            'demo.laboratorio.dashboard',
        ];
    }
}
