<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LimpiarFiltrosUxTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['name' => 'administrador'], ['guard_name' => 'web']);
        $doctorRole = Role::firstOrCreate(['name' => 'doctor'], ['guard_name' => 'web']);

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->roles()->sync([$adminRole->id]);

        $this->doctor = User::factory()->create(['status' => 'active']);
        $this->doctor->roles()->sync([$doctorRole->id]);
    }

    /**
     * Test 1: Doctor citas list renders "Limpiar" link when visited WITHOUT query filters.
     */
    public function test_doctor_citas_shows_limpiar_link_without_filters(): void
    {
        $response = $this->actingAs($this->doctor)->get(route('doctor.citas'));

        $response->assertOk();
        $content = $response->getContent();

        // Must contain "Limpiar" text with link to canonical doctor.citas route
        $expectedRoute = route('doctor.citas');
        $this->assertStringContainsString('Limpiar', $content);
        $this->assertMatchesRegularExpression(
            '/<a[^>]*href=["\']' . preg_quote($expectedRoute, '/') . '["\'][^>]*>[\s\S]*?Limpiar[\s\S]*?<\/a>/i',
            $content,
            'Failed asserting that doctor/citas contains a "Limpiar" link pointing to ' . $expectedRoute . ' when no filters are active.'
        );
    }

    /**
     * Test 2: Admin dashboard recent citas filter renders "Limpiar" link when visited WITHOUT query filters.
     */
    public function test_admin_dashboard_citas_shows_limpiar_link_without_filters(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $content = $response->getContent();

        $expectedRoute = route('admin.dashboard');
        $this->assertStringContainsString('Limpiar', $content);
        $this->assertMatchesRegularExpression(
            '/<a[^>]*href=["\']' . preg_quote($expectedRoute, '/') . '["\'][^>]*>[\s\S]*?Limpiar[\s\S]*?<\/a>/i',
            $content,
            'Failed asserting that admin/dashboard contains a "Limpiar" link pointing to ' . $expectedRoute . ' when no filters are active.'
        );
    }

    /**
     * Test 3: Admin usuarios list renders "Limpiar" link when visited WITHOUT query filters.
     */
    public function test_admin_usuarios_shows_limpiar_link_without_filters(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.usuarios.index'));

        $response->assertOk();
        $content = $response->getContent();

        $expectedRoute = route('admin.usuarios.index');
        $this->assertStringContainsString('Limpiar', $content);
        $this->assertMatchesRegularExpression(
            '/<a[^>]*href=["\']' . preg_quote($expectedRoute, '/') . '["\'][^>]*>[\s\S]*?Limpiar[\s\S]*?<\/a>/i',
            $content,
            'Failed asserting that admin/usuarios contains a "Limpiar" link pointing to ' . $expectedRoute . ' when no filters are active.'
        );
    }

    /**
     * Test 4: Doctor citas renders "Limpiar" link WITH multiple active filters and links to clean route.
     */
    public function test_doctor_citas_shows_limpiar_link_with_filters(): void
    {
        $response = $this->actingAs($this->doctor)->get(route('doctor.citas', [
            'estado' => 'confirmada',
            'prioridad' => 'ALTA',
        ]));

        $response->assertOk();
        $content = $response->getContent();

        $expectedRoute = route('doctor.citas');
        $this->assertMatchesRegularExpression(
            '/<a[^>]*href=["\']' . preg_quote($expectedRoute, '/') . '["\'][^>]*>[\s\S]*?Limpiar[\s\S]*?<\/a>/i',
            $content
        );
    }

    /**
     * Test 5: Admin dashboard renders "Limpiar" link WITH active priority filter.
     */
    public function test_admin_dashboard_citas_shows_limpiar_link_with_filters(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.dashboard', [
            'prioridad' => 'ALTA',
        ]));

        $response->assertOk();
        $content = $response->getContent();

        $expectedRoute = route('admin.dashboard');
        $this->assertMatchesRegularExpression(
            '/<a[^>]*href=["\']' . preg_quote($expectedRoute, '/') . '["\'][^>]*>[\s\S]*?Limpiar[\s\S]*?<\/a>/i',
            $content
        );
    }

    /**
     * Test 6: Admin usuarios renders "Limpiar" link WITH text search and role filters.
     */
    public function test_admin_usuarios_shows_limpiar_link_with_filters(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.usuarios.index', [
            'buscar' => 'Juan',
            'role' => 'doctor',
        ]));

        $response->assertOk();
        $content = $response->getContent();

        $expectedRoute = route('admin.usuarios.index');
        $this->assertMatchesRegularExpression(
            '/<a[^>]*href=["\']' . preg_quote($expectedRoute, '/') . '["\'][^>]*>[\s\S]*?Limpiar[\s\S]*?<\/a>/i',
            $content
        );
    }

    /**
     * Test 7: Admin cambios-citas maintains permanent "Limpiar" link without regression.
     */
    public function test_admin_cambios_citas_preserves_permanent_limpiar_link(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cambios-citas.index'));

        $response->assertOk();
        $content = $response->getContent();

        $expectedRoute = route('admin.cambios-citas.index');
        $this->assertMatchesRegularExpression(
            '/<a[^>]*href=["\']' . preg_quote($expectedRoute, '/') . '["\'][^>]*>[\s\S]*?Limpiar[\s\S]*?<\/a>/i',
            $content
        );
    }

    /**
     * Test 8: Demo mode consistency across all three views.
     */
    public function test_demo_mode_renders_limpiar_links_consistently(): void
    {
        config(['app.mode' => 'demo']);

        $docResponse = $this->actingAs($this->doctor)->get(route('doctor.citas'));
        $docResponse->assertOk();
        $this->assertMatchesRegularExpression(
            '/<a[^>]*href=["\']' . preg_quote(route('doctor.citas'), '/') . '["\'][^>]*>[\s\S]*?Limpiar[\s\S]*?<\/a>/i',
            $docResponse->getContent()
        );

        $adminDashResponse = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $adminDashResponse->assertOk();
        $this->assertMatchesRegularExpression(
            '/<a[^>]*href=["\']' . preg_quote(route('admin.dashboard'), '/') . '["\'][^>]*>[\s\S]*?Limpiar[\s\S]*?<\/a>/i',
            $adminDashResponse->getContent()
        );

        $adminUsersResponse = $this->actingAs($this->admin)->get(route('admin.usuarios.index'));
        $adminUsersResponse->assertOk();
        $this->assertMatchesRegularExpression(
            '/<a[^>]*href=["\']' . preg_quote(route('admin.usuarios.index'), '/') . '["\'][^>]*>[\s\S]*?Limpiar[\s\S]*?<\/a>/i',
            $adminUsersResponse->getContent()
        );
    }
}
