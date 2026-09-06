<?php

namespace Tests\Feature;

use App\Http\Middleware\DemoDatabaseIsolation;
use App\Http\Middleware\EnsureSessionModeIsolation;
use App\Models\FeatureAccessRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\ApplicationModeService;
use App\Services\FeatureAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * HALLAZGO MANUAL DEMO 5
 *
 * Verifies that in APP_MODE=demo the administrador can access /admin/personalizacion
 * without requiring a FeatureAccessRequest, while production continues to enforce
 * the request-based authorization contract (fail-closed).
 *
 * Implementation point: FeatureAccessService::hasAccess() injects ApplicationModeService
 * and short-circuits for demo+administrador. All other modes remain unchanged.
 *
 * Test design notes:
 *  - The admin.personalizacion.index route redirects internally to bienvenida.edit
 *    (HTTP 302 with Location pointing to the first sub-page). Tests therefore target
 *    admin.personalizacion.bienvenida.edit directly to assert 200/redirect on the
 *    feature:personalizacion middleware decisión.
 *  - DemoDatabaseIsolation and EnsureSessionModeIsolation are bypassed in HTTP tests
 *    because they are infra-level middlewares whose singletons are resolved at app boot,
 *    before any per-test binding override takes effect. Their behaviour is already
 *    tested in dedicated suites (DemoDatabaseConnectionIsolationTest, SessionModeIsolationTest).
 *  - EnsureFeatureAccess (the authorization gate) is always kept active.
 *  - Service-level (unit) tests use the full container with no middleware at all.
 */
class DemoPersonalizacionAdminAccessTest extends TestCase
{
    use DatabaseTransactions;

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    private function adminUser(): User
    {
        $role = Role::firstOrCreate(['name' => 'administrador']);
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->roles()->attach($role->id);
        return $user;
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->roles()->attach($role->id);
        return $user;
    }

    /**
     * Bind ApplicationModeService so it always reports demo mode.
     * Flushes existing instances so the new binding is used by the container.
     */
    private function forceDemo(): void
    {
        $this->app->bind(ApplicationModeService::class, static function () {
            return new class extends ApplicationModeService {
                public function getMode(): string { return 'demo'; }
                public function isDemo(): bool { return true; }
                public function isProduction(): bool { return false; }
                public function shouldPersist(): bool { return false; }
                public function allowExternalEffects(): bool { return false; }
            };
        });
        $this->app->forgetInstance(ApplicationModeService::class);
        $this->app->forgetInstance(FeatureAccessService::class);
    }

    /**
     * Bind ApplicationModeService so it always reports production mode.
     */
    private function forceProduction(): void
    {
        $this->app->bind(ApplicationModeService::class, static function () {
            return new class extends ApplicationModeService {
                public function getMode(): string { return 'production'; }
                public function isDemo(): bool { return false; }
                public function isProduction(): bool { return true; }
                public function shouldPersist(): bool { return true; }
                public function allowExternalEffects(): bool { return true; }
            };
        });
        $this->app->forgetInstance(ApplicationModeService::class);
        $this->app->forgetInstance(FeatureAccessService::class);
    }

    /**
     * Returns the list of infra middlewares to bypass in HTTP tests.
     * These carry constructor-injected singletons resolved before per-test binding
     * overrides take effect. Their own suites cover them separately.
     */
    private function infraMiddleware(): array
    {
        return [
            DemoDatabaseIsolation::class,
            EnsureSessionModeIsolation::class,
        ];
    }

    /**
     * The canonical personalizacion sub-route to assert against.
     * (The index route returns HTTP 302 to bienvenida.edit by design.)
     */
    private function personalizacionUrl(): string
    {
        return route('admin.personalizacion.bienvenida.edit');
    }

    // ─────────────────────────────────────────────────────────────────────
    // DEMO MODE — access always granted for administrador
    // ─────────────────────────────────────────────────────────────────────

    /**
     * TEST 1: Demo admin has access with NO FeatureAccessRequest at all.
     */
    public function test_demo_admin_has_access_to_personalizacion_without_any_request(): void
    {
        $this->forceDemo();
        $admin = $this->adminUser();

        $this->assertDatabaseMissing('feature_access_requests', ['user_id' => $admin->id]);

        $this->withoutMiddleware($this->infraMiddleware())
            ->actingAs($admin)
            ->get($this->personalizacionUrl())
            ->assertOk();
    }

    /**
     * TEST 2: Demo admin has access with a PENDING request.
     */
    public function test_demo_admin_has_access_to_personalizacion_with_pending_request(): void
    {
        $this->forceDemo();
        $admin = $this->adminUser();

        FeatureAccessRequest::query()->create([
            'user_id' => $admin->id,
            'feature' => 'personalizacion',
            'status' => 'pending',
        ]);

        $this->withoutMiddleware($this->infraMiddleware())
            ->actingAs($admin)
            ->get($this->personalizacionUrl())
            ->assertOk();
    }

    /**
     * TEST 3: Demo admin has access with a REJECTED request.
     */
    public function test_demo_admin_has_access_to_personalizacion_with_rejected_request(): void
    {
        $this->forceDemo();
        $superadmin = $this->userWithRole('superadmin');
        $admin = $this->adminUser();

        FeatureAccessRequest::query()->create([
            'user_id' => $admin->id,
            'feature' => 'personalizacion',
            'status' => 'rejected',
            'reviewed_at' => now()->subDay(),
            'reviewed_by' => $superadmin->id,
        ]);

        $this->withoutMiddleware($this->infraMiddleware())
            ->actingAs($admin)
            ->get($this->personalizacionUrl())
            ->assertOk();
    }

    /**
     * TEST 3b: Demo admin has access with an EXPIRED request.
     */
    public function test_demo_admin_has_access_to_personalizacion_with_expired_request(): void
    {
        $this->forceDemo();
        $superadmin = $this->userWithRole('superadmin');
        $admin = $this->adminUser();

        FeatureAccessRequest::query()->create([
            'user_id' => $admin->id,
            'feature' => 'personalizacion',
            'status' => 'approved',
            'approved_until' => now()->subDay(),
            'reviewed_at' => now()->subDays(10),
            'reviewed_by' => $superadmin->id,
        ]);

        $this->withoutMiddleware($this->infraMiddleware())
            ->actingAs($admin)
            ->get($this->personalizacionUrl())
            ->assertOk();
    }

    /**
     * TEST: Demo admin can access all representative personalizacion sub-routes.
     */
    public function test_demo_admin_can_access_all_personalizacion_subroutes(): void
    {
        $this->forceDemo();
        $admin = $this->adminUser();

        $routes = [
            route('admin.personalizacion.bienvenida.edit'),
            route('admin.personalizacion.servicios.edit'),
            route('admin.personalizacion.contacto.edit'),
        ];

        foreach ($routes as $url) {
            $this->withoutMiddleware($this->infraMiddleware())
                ->actingAs($admin)
                ->get($url)
                ->assertOk("Expected 200 for URL: {$url}");
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // PRODUCTION MODE — fail-closed enforcement (unchanged behavior)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * TEST 4: Production admin WITHOUT request is redirected (blocked).
     */
    public function test_production_admin_without_request_is_denied_personalizacion(): void
    {
        $this->forceProduction();
        $admin = $this->adminUser();

        $this->assertDatabaseMissing('feature_access_requests', ['user_id' => $admin->id]);

        $this->withoutMiddleware($this->infraMiddleware())
            ->actingAs($admin)
            ->get($this->personalizacionUrl())
            ->assertRedirect(route('admin.dashboard'));
    }

    /**
     * TEST 5: Production admin with PENDING request is redirected (still blocked).
     */
    public function test_production_admin_with_pending_request_is_denied_personalizacion(): void
    {
        $this->forceProduction();
        $admin = $this->adminUser();

        FeatureAccessRequest::query()->create([
            'user_id' => $admin->id,
            'feature' => 'personalizacion',
            'status' => 'pending',
        ]);

        $this->withoutMiddleware($this->infraMiddleware())
            ->actingAs($admin)
            ->get($this->personalizacionUrl())
            ->assertRedirect(route('admin.dashboard'));
    }

    /**
     * TEST 6: Production admin with APPROVED active request has access.
     */
    public function test_production_admin_with_approved_active_request_has_personalizacion_access(): void
    {
        $this->forceProduction();
        $admin = $this->adminUser();

        FeatureAccessRequest::query()->create([
            'user_id' => $admin->id,
            'feature' => 'personalizacion',
            'status' => 'approved',
            'approved_until' => now()->addDays(30),
            'reviewed_at' => now()->subHour(),
        ]);

        $this->withoutMiddleware($this->infraMiddleware())
            ->actingAs($admin)
            ->get($this->personalizacionUrl())
            ->assertOk();
    }

    /**
     * TEST 7: Production admin with EXPIRED request is denied.
     */
    public function test_production_admin_with_expired_request_is_denied_personalizacion(): void
    {
        $this->forceProduction();
        $admin = $this->adminUser();

        FeatureAccessRequest::query()->create([
            'user_id' => $admin->id,
            'feature' => 'personalizacion',
            'status' => 'approved',
            'approved_until' => now()->subDay(),
            'reviewed_at' => now()->subDays(10),
        ]);

        $this->withoutMiddleware($this->infraMiddleware())
            ->actingAs($admin)
            ->get($this->personalizacionUrl())
            ->assertRedirect(route('admin.dashboard'));
    }

    // ─────────────────────────────────────────────────────────────────────
    // ROLE ISOLATION — demo bypass is strictly for administrador only
    // ─────────────────────────────────────────────────────────────────────

    /**
     * TEST: In demo mode, a paciente does NOT gain admin personalizacion access.
     * The role:administrador middleware blocks the request before feature check.
     */
    public function test_demo_paciente_does_not_gain_admin_personalizacion_access(): void
    {
        $this->forceDemo();
        $paciente = $this->userWithRole('paciente');

        $response = $this->withoutMiddleware($this->infraMiddleware())
            ->actingAs($paciente)
            ->get($this->personalizacionUrl());

        $this->assertNotEquals(200, $response->status(),
            'Paciente must not access admin personalizacion routes.');
    }

    /**
     * TEST: In demo mode, a doctor does NOT gain admin personalizacion access.
     */
    public function test_demo_doctor_does_not_gain_admin_personalizacion_access(): void
    {
        $this->forceDemo();
        $doctor = $this->userWithRole('doctor');

        $response = $this->withoutMiddleware($this->infraMiddleware())
            ->actingAs($doctor)
            ->get($this->personalizacionUrl());

        $this->assertNotEquals(200, $response->status(),
            'Doctor must not access admin personalizacion routes.');
    }

    // ─────────────────────────────────────────────────────────────────────
    // FeatureAccessService — unit-level contract assertions (no middleware)
    // ─────────────────────────────────────────────────────────────────────

    public function test_feature_access_service_grants_demo_admin_without_request(): void
    {
        $this->forceDemo();
        $admin = $this->adminUser();

        $service = app(FeatureAccessService::class);

        $this->assertTrue(
            $service->hasAccess($admin, 'personalizacion'),
            'FeatureAccessService must return true for administrador in demo without any request.'
        );
    }

    public function test_feature_access_service_denies_production_admin_without_request(): void
    {
        $this->forceProduction();
        $admin = $this->adminUser();

        $service = app(FeatureAccessService::class);

        $this->assertFalse(
            $service->hasAccess($admin, 'personalizacion'),
            'FeatureAccessService must return false for administrador in production without an approved request.'
        );
    }

    public function test_feature_access_service_denies_demo_access_for_invalid_mode(): void
    {
        // ApplicationModeService falls back to 'production' for unknown modes.
        // We verify the raw service reads config (no binding override here).
        config(['app.mode' => 'staging']); // not in VALID_MODES
        $this->app->forgetInstance(ApplicationModeService::class);
        $this->app->forgetInstance(FeatureAccessService::class);

        $admin = $this->adminUser();
        $service = app(FeatureAccessService::class);

        $this->assertFalse(
            $service->hasAccess($admin, 'personalizacion'),
            'Invalid app.mode must not grant the demo bypass (falls back to production fail-closed).'
        );
    }
}
