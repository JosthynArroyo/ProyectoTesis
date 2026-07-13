<?php

namespace Tests\Feature;

use App\Models\Horario;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelBackButtonTest extends TestCase
{
    use RefreshDatabase;

    public function test_nested_superadmin_page_renders_panel_back_button(): void
    {
        $user = $this->createUserWithRole('superadmin');

        $response = $this->actingAs($user)->get(route('superadmin.admins.create'));

        $response->assertOk();
        $response->assertSee('data-panel-back-anchor', false);
    }

    public function test_sidebar_superadmin_page_does_not_render_panel_back_button(): void
    {
        $user = $this->createUserWithRole('superadmin');

        $response = $this->actingAs($user)->get(route('superadmin.admins.index'));

        $response->assertOk();
        $response->assertDontSee('data-panel-back-anchor', false);
    }

    public function test_nested_admin_page_renders_panel_back_button(): void
    {
        $admin = $this->createUserWithRole('administrador');
        $user = $this->createUserWithRole('paciente');

        $response = $this->actingAs($admin)->get(route('admin.usuarios.show', $user));

        $response->assertOk();
        $response->assertSee('data-panel-back-anchor', false);
        $response->assertSee(route('admin.dashboard'), false);
    }

    public function test_admin_create_user_page_renders_panel_back_button_to_users_index(): void
    {
        $admin = $this->createUserWithRole('administrador');

        $response = $this->actingAs($admin)->get(route('admin.usuarios.create'));

        $response->assertOk();
        $response->assertSee('data-panel-back-anchor', false);
        $response->assertSee('data-panel-back-fallback="'.route('admin.usuarios.index').'"', false);
    }

    public function test_admin_override_appointment_page_renders_panel_back_button(): void
    {
        $admin = $this->createUserWithRole('administrador');

        $response = $this->actingAs($admin)->get(route('admin.citas.override.create'));

        $response->assertOk();
        $response->assertSee('data-panel-back-anchor', false);
        $response->assertSee('data-panel-back-fallback="'.route('admin.pagos.index').'"', false);
    }

    public function test_admin_sidebar_contact_messages_page_does_not_render_panel_back_button(): void
    {
        $admin = $this->createUserWithRole('administrador');

        $response = $this->actingAs($admin)->get(route('admin.contacto.mensajes'));

        $response->assertOk();
        $response->assertDontSee('data-panel-back-anchor', false);
    }

    public function test_admin_nested_reminders_sent_page_renders_panel_back_button_with_custom_fallback(): void
    {
        $admin = $this->createUserWithRole('administrador');

        $response = $this->actingAs($admin)->get(route('admin.recordatorios.enviados'));

        $response->assertOk();
        $response->assertSee('data-panel-back-anchor', false);
        $response->assertSee('data-panel-back-fallback="'.route('admin.recordatorios.index').'"', false);
    }

    public function test_superadmin_non_sidebar_personalizacion_service_page_renders_panel_back_button(): void
    {
        $superadmin = $this->createUserWithRole('superadmin');

        $response = $this->actingAs($superadmin)->get(route('superadmin.personalizacion.servicios.edit'));

        $response->assertOk();
        $response->assertSee('data-panel-back-anchor', false);
    }

    public function test_doctor_schedule_edit_page_renders_panel_back_button(): void
    {
        $doctor = $this->createUserWithRole('doctor');
        $horario = Horario::query()->create([
            'doctor_id' => $doctor->id,
            'fecha' => '2026-04-20',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '12:00:00',
            'intervalo_minutos' => 30,
        ]);

        $response = $this->actingAs($doctor)->get(route('doctor.horario.edit', $horario));

        $response->assertOk();
        $response->assertSee('data-panel-back-anchor', false);
    }

    public function test_laboratorio_schedule_edit_page_renders_panel_back_button(): void
    {
        $laboratorio = $this->createUserWithRole('laboratorio');
        $horario = Horario::query()->create([
            'doctor_id' => $laboratorio->id,
            'fecha' => '2026-04-20',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '12:00:00',
            'intervalo_minutos' => 30,
        ]);

        $response = $this->actingAs($laboratorio)->get(route('laboratorio.horario.edit', $horario));

        $response->assertOk();
        $response->assertSee('data-panel-back-anchor', false);
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::create(['name' => $roleName]);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role->id);

        return $user;
    }
}
