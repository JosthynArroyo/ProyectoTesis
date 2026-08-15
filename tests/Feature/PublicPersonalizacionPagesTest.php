<?php

namespace Tests\Feature;

use App\Models\Especialidad;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicPersonalizacionPagesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('r2_private');
        Storage::fake('r2_public', ['url' => 'https://pub-test.r2.dev']);
        config([
            'filesystems.disks.r2_public.url' => 'https://pub-test.r2.dev',
            'image_optimization.disk' => 'r2_public',
            'queue.media_connection' => 'sync',
        ]);
    }

    public function test_superadmin_can_customize_services_copy_and_images_without_breaking_public_view(): void
    {
        Storage::fake('public');

        $superadmin = $this->makeUserWithRole('superadmin');
        $especialidad = Especialidad::query()->create([
            'nombre' => 'Dermatologia',
            'descripcion' => 'Atencion inicial',
            'icono' => 'ri-user-heart-line',
            'activo' => true,
            'orden' => 1,
        ]);

        $response = $this->actingAs($superadmin)->put(route('superadmin.personalizacion.servicios.update'), [
            'services_title' => 'Servicios integrales para toda la familia',
            'services_subtitle' => 'Configura imagenes y textos sin alterar el flujo publico.',
            'services_cta_text' => 'Reservar ahora',
            'services_hero_image' => $this->fakePngUpload('hero-servicios.png'),
            'especialidades' => [
                $especialidad->id => [
                    'nombre' => 'Dermatologia clinica',
                    'descripcion' => 'Control, seguimiento y procedimientos dermatologicos.',
                    'icono' => 'ri-user-heart-line',
                    'activo' => '1',
                    'orden' => '2',
                    'image' => $this->fakePngUpload('dermatologia.png'),
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Imágenes recibidas. Estamos procesando los cambios.');

        $heroImage = SiteSetting::query()->where('key', 'services.hero_image')->value('value');
        $serviceImage = SiteSetting::query()->where('key', 'services.specialty_image.'.$especialidad->id)->value('value');

        $this->assertNotNull($heroImage);
        $this->assertNotNull($serviceImage);
        Storage::disk('r2_public')->assertExists($heroImage);
        Storage::disk('r2_public')->assertExists($serviceImage);
        Storage::disk('public')->assertMissing($heroImage);
        Storage::disk('public')->assertMissing($serviceImage);

        $publicResponse = $this->get(route('servicios.index'));

        $publicResponse->assertOk();
        $publicResponse->assertSeeText('Servicios integrales para toda la familia');
        $publicResponse->assertSeeText('Configura imagenes y textos sin alterar el flujo publico.');
        $publicResponse->assertSeeText('Dermatologia clinica');
        $publicResponse->assertSee('https://pub-test.r2.dev/images/services/', false);
    }

    public function test_superadmin_can_customize_contact_copy_email_and_placeholders_in_public_view(): void
    {
        $superadmin = $this->makeUserWithRole('superadmin');

        $response = $this->actingAs($superadmin)->put(route('superadmin.personalizacion.contacto.update'), [
            'contact_info_badge' => 'Atencion directa',
            'contact_title' => 'Clinica de contacto inmediato',
            'contact_subtitle' => 'Soporte para pacientes y consultas administrativas.',
            'contact_address_label' => 'Sucursal',
            'contact_address' => 'Av. Principal 123',
            'contact_phone_label' => 'Central',
            'contact_phone' => '0990001112',
            'contact_email' => 'contacto@clinica.test',
            'contact_hours_label' => 'Horario',
            'contact_hours' => 'Lunes a Sabado, 07:00 - 19:00',
            'contact_map_title' => 'Mapa central',
            'contact_map_embed' => 'https://www.google.com/maps/embed?pb=test',
            'contact_form_section_badge' => 'Escribenos',
            'contact_form_title' => 'Formulario rapido',
            'contact_form_badge' => 'Respuesta prioritaria',
            'contact_form_submit_text' => 'Enviar consulta',
            'contact_form_name_label' => 'Nombre completo',
            'contact_form_name_placeholder' => 'Nombre test',
            'contact_form_email_label' => 'Correo de respuesta',
            'contact_form_email_placeholder' => 'correo@test.dev',
            'contact_form_phone_label' => 'Telefono movil',
            'contact_form_phone_placeholder' => '0991112233',
            'contact_form_subject_label' => 'Motivo',
            'contact_form_subject_placeholder' => 'Consulta breve',
            'contact_form_message_label' => 'Detalle',
            'contact_form_message_placeholder' => 'Describe tu consulta',
            'contact_form_message_help' => 'Incluye el contexto necesario.',
        ]);

        $response->assertRedirect();

        $publicResponse = $this->get(route('contacto.form'));

        $publicResponse->assertOk();
        $publicResponse->assertSeeText('Clinica de contacto inmediato');
        $publicResponse->assertSeeText('contacto@clinica.test');
        $publicResponse->assertSeeText('Formulario rapido');
        $publicResponse->assertSee('placeholder="Nombre test"', false);
        $publicResponse->assertSee('placeholder="correo@test.dev"', false);
        $publicResponse->assertSee('placeholder="0991112233"', false);
        $publicResponse->assertSee('placeholder="Consulta breve"', false);
        $publicResponse->assertSee('Describe tu consulta', false);
    }

    public function test_public_verificar_documento_button_uses_dynamic_accent_color_and_not_hardcoded_green(): void
    {
        SiteSetting::query()->updateOrCreate(
            ['key' => 'branding.accent'],
            ['value' => '#1e40af', 'type' => 'text', 'section' => 'branding']
        );
        SiteSetting::query()->updateOrCreate(
            ['key' => 'branding.accent_strong'],
            ['value' => '#1e3a8a', 'type' => 'text', 'section' => 'branding']
        );

        $response = $this->get(route('documentos.verificar.form'));

        $response->assertOk();
        $response->assertSee('--accent:', false);
        $response->assertSee('#1e40af', false);
        $response->assertSee('class="btn btn-primary flex w-full items-center justify-center gap-2 rounded-2xl px-4 py-3 text-base font-semibold transition"', false);
        $response->assertDontSee('bg-emerald-600', false);
        $response->assertDontSee('bg-green-600', false);
    }

    private function makeUserWithRole(string $roleName): User
    {
        $role = Role::query()->firstOrCreate(['name' => $roleName]);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function fakePngUpload(string $name): UploadedFile
    {
        return UploadedFile::fake()->image($name, 800, 400);
    }
}
