<?php

namespace Tests\Feature;

use App\Models\FeatureAccessRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SuperadminPersonalizacionRequestExpiryTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-03-27 12:00:00', 'America/Guayaquil'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_expired_approved_request_is_rendered_as_expired_without_revoke_action(): void
    {
        $superadmin = $this->createUserWithRole('superadmin');
        $admin = $this->createUserWithRole('administrador');

        FeatureAccessRequest::query()->create([
            'user_id' => $admin->id,
            'feature' => 'personalizacion',
            'status' => 'approved',
            'approved_until' => now()->subDays(3),
            'reviewed_at' => now()->subDays(4),
            'reviewed_by' => $superadmin->id,
        ]);

        $this->actingAs($superadmin)
            ->get(route('superadmin.solicitudes.personalizacion.index'))
            ->assertOk()
            ->assertSeeText('Expirado')
            ->assertSeeText('Acceso expirado')
            ->assertDontSeeText('Revocar');
    }

    public function test_superadmin_cannot_revoke_an_expired_request(): void
    {
        $superadmin = $this->createUserWithRole('superadmin');
        $admin = $this->createUserWithRole('administrador');

        $request = FeatureAccessRequest::query()->create([
            'user_id' => $admin->id,
            'feature' => 'personalizacion',
            'status' => 'approved',
            'approved_until' => now()->subHour(),
            'reviewed_at' => now()->subDay(),
            'reviewed_by' => $superadmin->id,
        ]);

        $this->actingAs($superadmin)
            ->from(route('superadmin.solicitudes.personalizacion.index'))
            ->patch(route('superadmin.solicitudes.personalizacion.revocar', $request))
            ->assertRedirect(route('superadmin.solicitudes.personalizacion.index'))
            ->assertSessionHasErrors([
                'access_request' => 'El acceso aprobado ya expiro.',
            ]);

        $this->assertDatabaseHas('feature_access_requests', [
            'id' => $request->id,
            'status' => 'approved',
            'revoked_at' => null,
            'revoked_by' => null,
        ]);
    }

    public function test_status_filters_separate_active_approved_from_expired_requests(): void
    {
        $superadmin = $this->createUserWithRole('superadmin');
        $expiredAdmin = $this->createUserWithRole('administrador');
        $activeAdmin = $this->createUserWithRole('administrador');

        $expiredAdmin->update(['name' => 'Admin Expirado']);
        $activeAdmin->update(['name' => 'Admin Vigente']);

        FeatureAccessRequest::query()->create([
            'user_id' => $expiredAdmin->id,
            'feature' => 'personalizacion',
            'status' => 'approved',
            'approved_until' => now()->subHour(),
            'reviewed_at' => now()->subDay(),
            'reviewed_by' => $superadmin->id,
        ]);

        FeatureAccessRequest::query()->create([
            'user_id' => $activeAdmin->id,
            'feature' => 'personalizacion',
            'status' => 'approved',
            'approved_until' => now()->addDay(),
            'reviewed_at' => now()->subDay(),
            'reviewed_by' => $superadmin->id,
        ]);

        $this->actingAs($superadmin)
            ->get(route('superadmin.solicitudes.personalizacion.index', ['status' => 'expired']))
            ->assertOk()
            ->assertSeeText('Admin Expirado')
            ->assertDontSeeText('Admin Vigente');

        $this->actingAs($superadmin)
            ->get(route('superadmin.solicitudes.personalizacion.index', ['status' => 'approved']))
            ->assertOk()
            ->assertSeeText('Admin Vigente')
            ->assertDontSeeText('Admin Expirado');
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
