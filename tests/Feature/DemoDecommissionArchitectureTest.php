<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DemoDecommissionArchitectureTest extends TestCase
{
    public function test_legacy_demo_classes_and_files_do_not_exist(): void
    {
        $this->assertFalse(
            class_exists('App\Http\Controllers\DemoDashboardController', false),
            'DemoDashboardController must be completely removed.'
        );
        $this->assertFileDoesNotExist(
            app_path('Http/Controllers/DemoDashboardController.php'),
            'DemoDashboardController.php must not exist.'
        );

        $this->assertFalse(
            class_exists('App\Http\Middleware\DemoIsolation', false),
            'DemoIsolation middleware must be completely removed.'
        );
        $this->assertFileDoesNotExist(
            app_path('Http/Middleware/DemoIsolation.php'),
            'DemoIsolation.php must not exist.'
        );
    }

    public function test_legacy_demo_views_and_directories_do_not_exist(): void
    {
        $this->assertFileDoesNotExist(
            resource_path('views/layouts/demo.blade.php'),
            'Legacy layouts/demo.blade.php must not exist.'
        );
        $this->assertFileDoesNotExist(
            resource_path('views/demo/index.blade.php'),
            'Legacy demo/index.blade.php must not exist.'
        );
        $this->assertDirectoryDoesNotExist(
            resource_path('views/demo/admin'),
            'Legacy views/demo/admin must not exist.'
        );
        $this->assertDirectoryDoesNotExist(
            resource_path('views/demo/doctor'),
            'Legacy views/demo/doctor must not exist.'
        );
        $this->assertDirectoryDoesNotExist(
            resource_path('views/demo/laboratorio'),
            'Legacy views/demo/laboratorio must not exist.'
        );
        $this->assertDirectoryDoesNotExist(
            resource_path('views/demo/paciente'),
            'Legacy views/demo/paciente must not exist.'
        );
        $this->assertDirectoryDoesNotExist(
            resource_path('views/demo/superadmin'),
            'Legacy views/demo/superadmin must not exist.'
        );
        $this->assertDirectoryDoesNotExist(
            resource_path('views/demo/partials'),
            'Legacy views/demo/partials must not exist.'
        );

        $this->assertFileExists(
            resource_path('views/demo/selector.blade.php'),
            'Official selector.blade.php must remain.'
        );
    }

    public function test_only_official_demo_routes_are_registered_and_no_mock_routes_exist(): void
    {
        $allDemoRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'demo.'))
            ->map(fn ($route) => $route->getName())
            ->values()
            ->all();

        $expectedOfficialDemoRoutes = [
            'demo.clinic',
            'demo.access.selector',
            'demo.access.role',
        ];

        sort($allDemoRoutes);
        sort($expectedOfficialDemoRoutes);

        $this->assertSame(
            $expectedOfficialDemoRoutes,
            $allDemoRoutes,
            'Only official demo routes must be registered in the route table.'
        );
    }

    public function test_no_routes_use_legacy_demo_isolation_middleware(): void
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $middleware = $route->gatherMiddleware();
            $this->assertNotContains(
                'demo.isolation',
                $middleware,
                "Route [{$route->uri()}] must not use demo.isolation middleware."
            );
        }
    }

    public function test_legacy_demo_endpoints_return_404(): void
    {
        $legacyEndpoints = [
            '/demo',
            '/demo/admin',
            '/demo/doctor',
            '/demo/paciente',
            '/demo/laboratorio',
            '/demo/superadmin',
            '/demo/admin/usuarios',
            '/demo/doctor/citas',
        ];

        foreach ($legacyEndpoints as $endpoint) {
            $response = $this->get($endpoint);
            $response->assertStatus(404);
        }
    }
}
