<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelThemePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_theme_preference(): void
    {
        $user = User::factory()->create([
            'theme_preference' => 'light',
            'status' => 'active',
            'suspended_until' => null,
        ]);

        $this->actingAs($user)
            ->patchJson(route('panel.theme.update'), ['theme' => 'dark'])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'theme' => 'dark',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'theme_preference' => 'dark',
        ]);
    }

    public function test_theme_preference_validation_rejects_invalid_value(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'suspended_until' => null,
        ]);

        $this->actingAs($user)
            ->patchJson(route('panel.theme.update'), ['theme' => 'blue'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('theme');
    }

    public function test_theme_update_requires_authentication(): void
    {
        $this->patchJson(route('panel.theme.update'), ['theme' => 'dark'])
            ->assertStatus(401);
    }

    public function test_public_landing_does_not_include_panel_theme_metadata(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('panel-theme-initial', false)
            ->assertDontSee('panel-theme-update-url', false);
    }

    public function test_panel_layout_defaults_to_light_theme_and_renders_confirmation_modal(): void
    {
        $user = $this->createPanelUser('administrador', 'light');

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('meta name="panel-theme-initial" content="light"', false)
            ->assertSee('data-theme-preference-modal', false)
            ->assertSeeInOrder(['</header>', 'id="themePreferenceModal"'], false);
    }

    public function test_panel_layout_uses_dark_theme_when_user_preference_is_dark(): void
    {
        $user = $this->createPanelUser('administrador', 'dark');

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('meta name="panel-theme-initial" content="dark"', false);
    }

    private function createPanelUser(string $roleName, string $theme): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user = User::factory()->create([
            'theme_preference' => $theme,
            'status' => 'active',
            'suspended_until' => null,
        ]);

        $user->roles()->attach($role->id);

        return $user;
    }
}
