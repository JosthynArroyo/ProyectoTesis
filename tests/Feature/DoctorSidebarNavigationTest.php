<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DoctorSidebarNavigationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function getOrCreateDemoDoctor(): User
    {
        $roleDoctor = Role::firstOrCreate(['name' => 'doctor'], ['label' => 'Doctor']);
        $doctor = User::firstOrCreate(
            ['email' => 'doctor.medicina@demo-clinigest.test'],
            [
                'name' => 'Dr. Fernando Alvarado',
                'password' => \Illuminate\Support\Facades\Hash::make('Demo1234!'),
                'status' => User::STATUS_ACTIVE,
                'must_change_password' => false,
                'email_verified_at' => now(),
            ]
        );
        $doctor->update(['must_change_password' => false, 'status' => User::STATUS_ACTIVE]);
        $doctor->roles()->syncWithoutDetaching([$roleDoctor->id]);

        return $doctor->fresh(['roles']);
    }

    public function test_doctor_pedidos_laboratorio_marks_pedidos_laboratorio_active_and_not_citas_in_demo_mode(): void
    {
        config(['app.mode' => 'demo']);
        $doctor = $this->getOrCreateDemoDoctor();

        $response = $this->actingAs($doctor)
            ->get(route('doctor.pedidos-laboratorio.index'));

        $response->assertOk();

        $content = $response->getContent();

        // Active class on "Pedidos de laboratorio"
        $this->assertMatchesRegularExpression(
            '/href="[^"]*\/doctor\/pedidos-laboratorio"[^>]*class="[^"]*bg-gray-100 text-gray-900 font-medium[^"]*"/',
            $content,
            'El enlace de "Pedidos de laboratorio" debe tener las clases de ítem activo (bg-gray-100 text-gray-900 font-medium).'
        );

        // Inactive class on "Mis citas"
        $this->assertMatchesRegularExpression(
            '/href="[^"]*\/doctor\/citas"[^>]*class="[^"]*text-gray-600 hover:bg-gray-50[^"]*"/',
            $content,
            'El enlace de "Mis citas" debe tener las clases de ítem inactivo (text-gray-600 hover:bg-gray-50).'
        );
    }

    public function test_doctor_citas_marks_citas_active_and_not_pedidos_laboratorio(): void
    {
        config(['app.mode' => 'demo']);
        $doctor = $this->getOrCreateDemoDoctor();

        $response = $this->actingAs($doctor)
            ->get(route('doctor.citas'));

        $response->assertOk();

        $content = $response->getContent();

        // Active class on "Mis citas"
        $this->assertMatchesRegularExpression(
            '/href="[^"]*\/doctor\/citas"[^>]*class="[^"]*bg-gray-100 text-gray-900 font-medium[^"]*"/',
            $content,
            'El enlace de "Mis citas" debe estar activo al visitar /doctor/citas.'
        );

        // Inactive class on "Pedidos de laboratorio"
        $this->assertMatchesRegularExpression(
            '/href="[^"]*\/doctor\/pedidos-laboratorio"[^>]*class="[^"]*text-gray-600 hover:bg-gray-50[^"]*"/',
            $content,
            'El enlace de "Pedidos de laboratorio" no debe estar activo al visitar /doctor/citas.'
        );
    }

    public function test_doctor_recetas_marks_recetas_active_and_not_pedidos_laboratorio(): void
    {
        config(['app.mode' => 'demo']);
        $doctor = $this->getOrCreateDemoDoctor();

        $response = $this->actingAs($doctor)
            ->get(route('doctor.recetas.index'));

        $response->assertOk();

        $content = $response->getContent();

        // Active class on "Historial de recetas"
        $this->assertMatchesRegularExpression(
            '/href="[^"]*\/doctor\/recetas"[^>]*class="[^"]*bg-gray-100 text-gray-900 font-medium[^"]*"/',
            $content,
            'El enlace de "Historial de recetas" debe estar activo al visitar /doctor/recetas.'
        );

        // Inactive class on "Pedidos de laboratorio"
        $this->assertMatchesRegularExpression(
            '/href="[^"]*\/doctor\/pedidos-laboratorio"[^>]*class="[^"]*text-gray-600 hover:bg-gray-50[^"]*"/',
            $content,
            'El enlace de "Pedidos de laboratorio" no debe estar activo al visitar /doctor/recetas.'
        );
    }

    public function test_doctor_pedidos_laboratorio_navigation_in_production_mode(): void
    {
        config(['app.mode' => 'production']);

        $doctor = User::factory()->create(['status' => 'active']);
        $roleDoctor = Role::where('name', 'doctor')->firstOrFail();
        $doctor->roles()->sync([$roleDoctor->id]);

        $response = $this->actingAs($doctor)
            ->get(route('doctor.pedidos-laboratorio.index'));

        $response->assertOk();

        $content = $response->getContent();

        // Active class on "Pedidos de laboratorio"
        $this->assertMatchesRegularExpression(
            '/href="[^"]*\/doctor\/pedidos-laboratorio"[^>]*class="[^"]*bg-gray-100 text-gray-900 font-medium[^"]*"/',
            $content,
            'En producción, el enlace de "Pedidos de laboratorio" debe estar activo.'
        );

        // Inactive class on "Mis citas"
        $this->assertMatchesRegularExpression(
            '/href="[^"]*\/doctor\/citas"[^>]*class="[^"]*text-gray-600 hover:bg-gray-50[^"]*"/',
            $content,
            'En producción, "Mis citas" no debe estar activo.'
        );
    }

    public function test_doctor_pedidos_laboratorio_index_does_not_render_panel_back_button(): void
    {
        config(['app.mode' => 'demo']);
        $doctor = $this->getOrCreateDemoDoctor();

        $response = $this->actingAs($doctor)
            ->get(route('doctor.pedidos-laboratorio.index'));

        $response->assertOk();
        $response->assertDontSee('data-panel-back-anchor', false);
    }
}
