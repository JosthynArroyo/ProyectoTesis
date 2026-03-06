<?php

namespace Tests\Feature;

use App\Models\FeatureAccessRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPersonalizacionRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_personalizacion_request_returns_json_without_redirecting(): void
    {
        $admin = $this->createAdminUser();

        $this->actingAs($admin)
            ->postJson(route('admin.personalizacion.request'), [
                'redirect_to' => route('admin.usuarios.index'),
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'status' => 'pending_created',
                'message' => 'Solicitud enviada al superadmin.',
            ]);

        $this->assertDatabaseHas('feature_access_requests', [
            'user_id' => $admin->id,
            'feature' => 'personalizacion',
            'status' => 'pending',
        ]);
    }

    public function test_admin_personalizacion_request_redirects_to_current_panel_when_not_using_js(): void
    {
        $admin = $this->createAdminUser();
        $redirectTo = route('admin.usuarios.index', ['role' => 'doctor']);

        $this->actingAs($admin)
            ->post(route('admin.personalizacion.request'), [
                'redirect_to' => $redirectTo,
            ])
            ->assertRedirect($redirectTo)
            ->assertSessionHas('success', 'Solicitud enviada al superadmin.');

        $this->assertSame(1, FeatureAccessRequest::query()->count());
    }

    private function createAdminUser(): User
    {
        $role = Role::firstOrCreate(['name' => 'administrador']);
        $user = User::factory()->create([
            'status' => 'active',
            'suspended_until' => null,
        ]);

        $user->roles()->attach($role->id);

        return $user;
    }
}
