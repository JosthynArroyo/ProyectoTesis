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

    public function test_demo_superadmin_sidebar_has_full_parity_with_real_superadmin_sidebar(): void
    {
        $realSidebarHtml = view('superadmin.partials.sidebar', [
            'clinicIdentity' => app(\App\Services\ClinicIdentityService::class),
            'pendingPersonalizacion' => 2,
        ])->render();

        $demoSidebarHtml = view('demo.partials.sidebar-superadmin-demo', [
            'clinicIdentity' => app(\App\Services\ClinicIdentityService::class),
            'pendingPersonalizacion' => 2,
            'logoutUrl' => '#',
        ])->render();

        $expectedModules = [
            'Inicio',
            'Administradores',
            'Usuarios',
            'Solicitudes',
            'Personalización',
            'Mantenimiento',
            'Respaldos DB',
        ];

        foreach ($expectedModules as $module) {
            $this->assertStringContainsString($module, $realSidebarHtml, "Real sidebar missing {$module}");
            $this->assertStringContainsString($module, $demoSidebarHtml, "Demo sidebar missing {$module}");
        }
    }

    public function test_demo_superadmin_respaldos_route_renders_and_has_visual_parity(): void
    {
        $response = $this->get(route('demo.superadmin.respaldos'));

        $response->assertOk();
        $response->assertSeeText('Respaldos de base de datos');
        $response->assertSeeText('Último respaldo exitoso');
        $response->assertSeeText('Cifrado de respaldos');
        $response->assertSeeText('Crear respaldo manual');
        $response->assertSeeText('AES-256');
        $response->assertSeeText('Josthyn Admin');
        $response->assertSeeText('Sistema (Automático)');
    }

    public function test_demo_superadmin_respaldos_actions_do_not_mutate_data_or_dispatch_jobs(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        \Illuminate\Support\Facades\Bus::fake();
        \Illuminate\Support\Facades\Storage::fake('r2');

        $backupsCountBefore = \App\Models\DatabaseBackup::query()->count();

        $response = $this->get(route('demo.superadmin.respaldos', ['simulated_action' => 'created']));

        $response->assertRedirect(route('demo.superadmin.respaldos'));

        $backupsCountAfter = \App\Models\DatabaseBackup::query()->count();
        $this->assertEquals($backupsCountBefore, $backupsCountAfter);

        \Illuminate\Support\Facades\Queue::assertNothingPushed();
        \Illuminate\Support\Facades\Bus::assertNothingDispatched();
        \Illuminate\Support\Facades\Storage::disk('r2')->assertDirectoryEmpty('');
    }

    public function test_demo_superadmin_respaldos_links_do_not_point_to_real_superadmin_routes(): void
    {
        $response = $this->get(route('demo.superadmin.respaldos'));

        $response->assertOk();
        $response->assertDontSee(route('superadmin.respaldos.store'), false);
        $response->assertDontSee('/superadmin/respaldos/', false);
    }

    public function test_superadmin_respaldos_badges_include_dark_mode_contrast_classes(): void
    {
        $response = $this->get(route('demo.superadmin.respaldos'));

        $response->assertOk();
        $response->assertSee('dark:bg-emerald-950/80', false);
        $response->assertSee('dark:text-emerald-300', false);
        $response->assertSee('dark:bg-purple-950/80', false);
        $response->assertSee('dark:bg-blue-950/80', false);
    }

    public function test_superadmin_panel_theme_css_includes_apexcharts_dark_mode_fixes(): void
    {
        $cssPath = resource_path('css/panel-theme.css');
        $this->assertFileExists($cssPath);

        $css = file_get_contents($cssPath);

        $this->assertStringContainsString('html.dashboard-root.panel-theme-dark .apexcharts-datalabel-label', $css);
        $this->assertStringContainsString('html.dashboard-root.panel-theme-dark .apexcharts-datalabel-value', $css);
        $this->assertStringContainsString('fill: #f3f4f6 !important;', $css);
    }

    /**
     * @return array<int, string>
     */
    private function demoEntryRoutes(): array
    {
        return [
            'demo.index',
            'demo.superadmin.dashboard',
            'demo.superadmin.respaldos',
            'demo.admin.dashboard',
            'demo.paciente.dashboard',
            'demo.doctor.dashboard',
            'demo.laboratorio.dashboard',
        ];
    }
}
