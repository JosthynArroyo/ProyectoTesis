<?php

namespace Tests\Feature;

use App\Models\FeatureAccessRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPersonalizacionAccessCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sidebar_updates_immediately_after_superadmin_approves_personalizacion_without_expiration(): void
    {
        $admin = $this->createUserWithRole('administrador');
        $superadmin = $this->createUserWithRole('superadmin');

        $this->actingAs($admin)
            ->post(route('admin.personalizacion.request'), [
                'redirect_to' => route('admin.dashboard'),
            ])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('success', 'Solicitud enviada al superadmin.');

        $accessRequest = FeatureAccessRequest::query()
            ->where('user_id', $admin->id)
            ->where('feature', 'personalizacion')
            ->firstOrFail();

        $this->actingAs($superadmin)
            ->from(route('superadmin.solicitudes.personalizacion.index'))
            ->patch(route('superadmin.solicitudes.personalizacion.aprobar', $accessRequest), [
                'req_id' => $accessRequest->id,
                'duration_hours' => 24,
                'no_expire' => 1,
            ])
            ->assertRedirect(route('superadmin.solicitudes.personalizacion.index'))
            ->assertSessionHas('success', 'Solicitud aprobada.');

        $accessRequest->refresh();

        $this->assertSame('approved', $accessRequest->status);
        $this->assertNull($accessRequest->approved_until);
        $this->assertTrue($accessRequest->isActive());

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.personalizacion.bienvenida.edit'), false)
            ->assertDontSee('data-open-personalizacion', false);
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::query()->firstOrCreate(['name' => $roleName]);

        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'active' => true,
            'suspended_until' => null,
        ]);

        $user->roles()->sync([$role->id]);

        return $user;
    }
}
