<?php

namespace Tests\Feature;

use App\Models\Especialidad;
use App\Models\FeatureAccessRequest;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PersonalizacionOptionalFieldsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.media_connection' => 'sync']);
    }

    public function test_superadmin_personalizacion_forms_do_not_render_required_attributes(): void
    {
        $superadmin = $this->makeUserWithRole('superadmin');

        foreach ([
            route('superadmin.personalizacion.bienvenida.edit'),
            route('superadmin.personalizacion.contacto.edit'),
            route('superadmin.personalizacion.servicios.edit'),
        ] as $route) {
            $response = $this->actingAs($superadmin)->get($route)->assertOk();
            $html = $response->getContent();
            $this->assertStringNotContainsString('name="telefono_emergencia" required', $html);
            $this->assertStringNotContainsString('name="horarios_atencion" required', $html);
        }
    }

    public function test_admin_personalizacion_forms_do_not_render_required_attributes(): void
    {
        $admin = $this->makeUserWithRole('administrador');

        FeatureAccessRequest::query()->create([
            'user_id' => $admin->id,
            'feature' => 'personalizacion',
            'status' => 'approved',
            'approved_until' => now()->addDay(),
            'reviewed_at' => now(),
        ]);

        foreach ([
            route('admin.personalizacion.bienvenida.edit'),
            route('admin.personalizacion.contacto.edit'),
            route('admin.personalizacion.servicios.edit'),
        ] as $route) {
            $response = $this->actingAs($admin)->get($route)->assertOk();
            $html = $response->getContent();
            $this->assertStringNotContainsString('name="telefono_emergencia" required', $html);
            $this->assertStringNotContainsString('name="horarios_atencion" required', $html);
        }
    }

    public function test_superadmin_can_save_contact_personalizacion_with_empty_values(): void
    {
        $superadmin = $this->makeUserWithRole('superadmin');

        $response = $this->actingAs($superadmin)->put(route('superadmin.personalizacion.contacto.update'), [
            'contact_info_badge' => '',
            'contact_title' => '',
            'contact_subtitle' => '',
            'contact_address_label' => '',
            'contact_address' => '',
            'contact_phone_label' => '',
            'contact_phone' => '',
            'contact_email' => '',
            'contact_hours_label' => '',
            'contact_hours' => '',
            'contact_map_title' => '',
            'contact_map_embed' => '',
            'contact_form_section_badge' => '',
            'contact_form_title' => '',
            'contact_form_badge' => '',
            'contact_form_submit_text' => '',
            'contact_form_name_label' => '',
            'contact_form_name_placeholder' => '',
            'contact_form_email_label' => '',
            'contact_form_email_placeholder' => '',
            'contact_form_phone_label' => '',
            'contact_form_phone_placeholder' => '',
            'contact_form_subject_label' => '',
            'contact_form_subject_placeholder' => '',
            'contact_form_message_label' => '',
            'contact_form_message_placeholder' => '',
            'contact_form_message_help' => '',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Seccion de contacto actualizada correctamente.');
        $this->assertNull(SiteSetting::query()->where('key', 'contact.title')->value('value'));
        $this->assertNull(SiteSetting::query()->where('key', 'contact.map_embed')->value('value'));
    }

    public function test_superadmin_services_update_ignores_blank_new_rows_and_preserves_existing_name_when_empty(): void
    {
        $superadmin = $this->makeUserWithRole('superadmin');

        $especialidad = Especialidad::query()->create([
            'nombre' => 'Dermatologia Base',
            'descripcion' => 'Descripcion original',
            'icono' => null,
            'activo' => true,
            'orden' => 3,
        ]);

        $response = $this->actingAs($superadmin)->put(route('superadmin.personalizacion.servicios.update'), [
            'especialidades' => [
                $especialidad->id => [
                    'nombre' => '',
                    'descripcion' => '',
                    'icono' => '',
                    'activo' => '1',
                    'orden' => '',
                ],
            ],
            'nuevas' => [
                [
                    'nombre' => '',
                    'descripcion' => '',
                    'icono' => '',
                    'activo' => '1',
                    'orden' => '',
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Personalización guardada correctamente.');

        $this->assertSame(0, \App\Models\MediaProcessingBatch::query()->count());

        $especialidad->refresh();

        $this->assertSame('Dermatologia Base', $especialidad->nombre);
        $this->assertNull($especialidad->descripcion);
        $this->assertSame(3, $especialidad->orden);
        $this->assertSame(1, Especialidad::query()->count());
    }

    private function makeUserWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role->id);

        return $user;
    }
}
