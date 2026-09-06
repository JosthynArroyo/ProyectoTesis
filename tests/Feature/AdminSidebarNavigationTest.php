<?php

namespace Tests\Feature;

use App\Models\FeatureAccessRequest;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminSidebarNavigationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function createAdminUser(bool $canPersonalizacion = true): User
    {
        $role = Role::where('name', 'administrador')->firstOrFail();
        $admin = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ]);
        $admin->roles()->syncWithoutDetaching([$role->id]);

        if ($canPersonalizacion) {
            FeatureAccessRequest::create([
                'user_id' => $admin->id,
                'feature' => 'personalizacion',
                'status' => 'approved',
                'approved_until' => now()->addDays(7),
                'reviewed_at' => now(),
            ]);
        }

        return $admin;
    }

    /**
     * TEST 1 (UX-03 RED): In production mode, admin sidebar must render "Información de contacto"
     * for customization and "Mensajes recibidos" for the visitor contact messages inbox.
     */
    public function test_admin_sidebar_renders_informacion_de_contacto_and_mensajes_recibidos_in_production(): void
    {
        config(['app.mode' => 'production']);
        $admin = $this->createAdminUser(true);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $response->assertOk();

        $html = $response->getContent();

        // 1. Mensajes recibidos link must point to admin.contacto.mensajes and have text "Mensajes recibidos"
        $this->assertMatchesRegularExpression(
            '/<a[^>]*href="[^"]*admin\/contacto\/mensajes"[^>]*>[\s\S]*?Mensajes recibidos[\s\S]*?<\/a>/i',
            $html,
            'El enlace hacia admin.contacto.mensajes debe tener el texto "Mensajes recibidos".'
        );

        // 2. The old text "Notificaciones de contacto" must NOT appear in the messages link
        $this->assertDoesNotMatchRegularExpression(
            '/<a[^>]*href="[^"]*admin\/contacto\/mensajes"[^>]*>[\s\S]*?Notificaciones de contacto[\s\S]*?<\/a>/i',
            $html,
            'El enlace hacia admin.contacto.mensajes no debe contener "Notificaciones de contacto".'
        );

        // 3. Contact customization link must point to admin.personalizacion.contacto.edit and have text "Información de contacto"
        $this->assertMatchesRegularExpression(
            '/<a[^>]*href="[^"]*admin\/personalizacion\/contacto"[^>]*>[\s\S]*?Información de contacto[\s\S]*?<\/a>/i',
            $html,
            'El enlace hacia admin.personalizacion.contacto.edit debe tener el texto "Información de contacto".'
        );

        // 4. The isolated ambiguous text "Contacto" must NOT appear in the customization link
        $this->assertDoesNotMatchRegularExpression(
            '/<a[^>]*href="[^"]*admin\/personalizacion\/contacto"[^>]*>\s*Contacto\s*<\/a>/i',
            $html,
            'El enlace hacia admin.personalizacion.contacto.edit no debe tener el texto ambiguo aislado "Contacto".'
        );
    }

    /**
     * TEST 2 (UX-03 RED): In demo mode, admin sidebar must also render "Información de contacto"
     * and "Mensajes recibidos" without environment divergence.
     */
    public function test_admin_sidebar_renders_informacion_de_contacto_and_mensajes_recibidos_in_demo(): void
    {
        config(['app.mode' => 'demo']);
        $admin = $this->createAdminUser(true);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $response->assertOk();

        $html = $response->getContent();

        $this->assertMatchesRegularExpression(
            '/<a[^>]*href="[^"]*admin\/contacto\/mensajes"[^>]*>[\s\S]*?Mensajes recibidos[\s\S]*?<\/a>/i',
            $html,
            'En modo demo, el enlace hacia admin.contacto.mensajes debe tener el texto "Mensajes recibidos".'
        );

        $this->assertMatchesRegularExpression(
            '/<a[^>]*href="[^"]*admin\/personalizacion\/contacto"[^>]*>[\s\S]*?Información de contacto[\s\S]*?<\/a>/i',
            $html,
            'En modo demo, el enlace hacia admin.personalizacion.contacto.edit debe tener el texto "Información de contacto".'
        );
    }

    /**
     * TEST 3: When visiting admin.contacto.mensajes, that link must be active.
     */
    public function test_admin_sidebar_mensajes_recibidos_active_state(): void
    {
        config(['app.mode' => 'production']);
        $admin = $this->createAdminUser(true);

        $response = $this->actingAs($admin)->get(route('admin.contacto.mensajes'));
        $response->assertOk();

        $html = $response->getContent();

        // The link to admin.contacto.mensajes must have active styling classes
        $this->assertMatchesRegularExpression(
            '/<a[^>]*href="[^"]*admin\/contacto\/mensajes"[^>]*class="[^"]*bg-gray-100 text-gray-900 font-medium[^"]*"[^>]*>/i',
            $html,
            'El enlace de "Mensajes recibidos" debe tener la clase de estado activo.'
        );
    }

    /**
     * TEST 4: When visiting admin.personalizacion.contacto.edit, that link must be active.
     */
    public function test_admin_sidebar_informacion_de_contacto_active_state(): void
    {
        config(['app.mode' => 'production']);
        $admin = $this->createAdminUser(true);

        $response = $this->actingAs($admin)->get(route('admin.personalizacion.contacto.edit'));
        $response->assertOk();

        $html = $response->getContent();

        // The link to admin.personalizacion.contacto must have active styling
        $this->assertMatchesRegularExpression(
            '/<a[^>]*href="[^"]*admin\/personalizacion\/contacto"[^>]*class="[^"]*border-gray-200 bg-white text-teal-700 shadow-sm[^"]*"[^>]*>/i',
            $html,
            'El enlace de "Información de contacto" debe tener la clase de estado activo.'
        );
    }

    /**
     * TEST 5: When admin does not have personalizacion permission, modal trigger button shows "Información de contacto".
     */
    public function test_admin_sidebar_renders_informacion_de_contacto_button_when_cannot_personalizacion(): void
    {
        config(['app.mode' => 'production']);
        $admin = $this->createAdminUser(false);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $response->assertOk();

        $html = $response->getContent();

        $this->assertMatchesRegularExpression(
            '/<button[^>]*data-open-personalizacion[^>]*>[\s\S]*?Información de contacto[\s\S]*?<\/button>/i',
            $html,
            'El botón modal de personalización debe tener el texto "Información de contacto".'
        );
    }
}
