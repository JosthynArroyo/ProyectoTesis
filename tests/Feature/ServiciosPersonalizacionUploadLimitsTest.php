<?php

namespace Tests\Feature;

use App\Jobs\ProcessServicesPersonalizationImages;
use App\Models\Especialidad;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\ImageOptimizer;
use App\Services\SiteSettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServiciosPersonalizacionUploadLimitsTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUserWithRole(string $roleName): User
    {
        $role = Role::query()->firstOrCreate(['name' => $roleName]);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function fakeImage(string $name = 'test.jpg', int $kilobytes = 10): UploadedFile
    {
        return UploadedFile::fake()->image($name, 800, 600)->size($kilobytes);
    }

    private function submitAjax(User $user, array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->post(route('superadmin.personalizacion.servicios.update'), array_merge(['_method' => 'PUT'], $payload));
    }

    public function test_superadmin_services_form_uses_multipart_and_upload_limit_metadata(): void
    {
        $superadmin = $this->makeUserWithRole('superadmin');

        $response = $this->actingAs($superadmin)->get(route('superadmin.personalizacion.servicios.edit'));

        $response->assertOk();
        $response->assertSee('enctype="multipart/form-data"', false);
        $response->assertSee('data-upload-limits-enabled="1"', false);
        $response->assertSee('data-upload-max-file-bytes="10485760"', false);
        $response->assertSee('data-upload-max-total-bytes="52428800"', false);
        $response->assertSee('data-upload-alert', false);
        $response->assertSee('data-batch-status-url-template', false);
        $response->assertSee('data-media-processing-overlay', false);
        $response->assertSee('Preparando imágenes...', false);
        $response->assertDontSee('value="data:', false);
    }

    public function test_superadmin_services_accepts_valid_images_below_the_limit_without_writing_real_objects(): void
    {
        Storage::fake('local');
        Storage::fake('r2_public');
        config(['image_optimization.disk' => 'r2_public']);

        $superadmin = $this->makeUserWithRole('superadmin');
        $especialidad = Especialidad::query()->create([
            'nombre' => 'Dermatologia',
            'descripcion' => 'Atencion inicial',
            'icono' => 'ri-user-heart-line',
            'activo' => true,
            'orden' => 1,
        ]);

        $response = $this->submitAjax($superadmin, [
            'services_title' => 'Servicios integrales',
            'services_subtitle' => 'Actualizacion segura.',
            'services_cta_text' => 'Reservar ahora',
            'services_hero_image' => $this->fakeImage('hero-servicios.png', 9000),
            'especialidades' => [
                $especialidad->id => [
                    'nombre' => 'Dermatologia clinica',
                    'descripcion' => 'Control y seguimiento.',
                    'icono' => 'ri-user-heart-line',
                    'activo' => '1',
                    'orden' => '2',
                    'image' => $this->fakeImage('dermatologia.png', 9000),
                ],
            ],
        ]);

        $response->assertStatus(202);
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('total', 2);

        $batchUuid = $response->json('batch_uuid');
        $this->assertNotNull($batchUuid);

        $job = new ProcessServicesPersonalizationImages($batchUuid);
        $job->handle(app(ImageOptimizer::class), app(SiteSettingsService::class));

        $heroImage = SiteSetting::query()->where('key', 'services.hero_image')->value('value');
        $serviceImage = SiteSetting::query()->where('key', 'services.specialty_image.'.$especialidad->id)->value('value');

        $this->assertNotNull($heroImage);
        $this->assertNotNull($serviceImage);
    }

    public function test_superadmin_services_accepts_multiple_changed_images_and_replaces_previous_paths(): void
    {
        Storage::fake('local');
        Storage::fake('r2_public');
        config(['image_optimization.disk' => 'r2_public']);

        $superadmin = $this->makeUserWithRole('superadmin');
        $firstSpecialty = Especialidad::query()->create([
            'nombre' => 'Cardiologia',
            'descripcion' => 'Atencion inicial',
            'icono' => 'ri-heart-pulse-line',
            'activo' => true,
            'orden' => 1,
        ]);
        $secondSpecialty = Especialidad::query()->create([
            'nombre' => 'Neurologia',
            'descripcion' => 'Atencion inicial',
            'icono' => 'ri-stethoscope-line',
            'activo' => true,
            'orden' => 2,
        ]);

        SiteSetting::query()->updateOrCreate(
            ['key' => 'services.hero_image'],
            ['value' => 'images/services/original/old-hero.jpg', 'type' => 'image', 'section' => 'services']
        );
        SiteSetting::query()->updateOrCreate(
            ['key' => 'services.specialty_image.'.$firstSpecialty->id],
            ['value' => 'images/services/original/old-cardio.jpg', 'type' => 'image', 'section' => 'services']
        );
        SiteSetting::query()->updateOrCreate(
            ['key' => 'services.specialty_image.'.$secondSpecialty->id],
            ['value' => 'images/services/original/old-neuro.jpg', 'type' => 'image', 'section' => 'services']
        );

        $response = $this->submitAjax($superadmin, [
            'services_title' => 'Servicios con varias imagenes',
            'services_subtitle' => 'Actualizacion con reemplazo multiple.',
            'services_cta_text' => 'Reservar ahora',
            'services_hero_image' => $this->fakeImage('hero-nuevo.png', 9000),
            'especialidades' => [
                $firstSpecialty->id => [
                    'nombre' => 'Cardiologia avanzada',
                    'descripcion' => 'Actualizacion de imagen.',
                    'icono' => 'ri-heart-pulse-line',
                    'activo' => '1',
                    'orden' => '1',
                    'image' => $this->fakeImage('cardio-nueva.png', 9000),
                ],
                $secondSpecialty->id => [
                    'nombre' => 'Neurologia avanzada',
                    'descripcion' => 'Actualizacion de imagen.',
                    'icono' => 'ri-stethoscope-line',
                    'activo' => '1',
                    'orden' => '2',
                    'image' => $this->fakeImage('neuro-nueva.png', 9000),
                ],
            ],
        ]);

        $response->assertStatus(202);

        $batchUuid = $response->json('batch_uuid');
        $job = new ProcessServicesPersonalizationImages($batchUuid);
        $job->handle(app(ImageOptimizer::class), app(SiteSettingsService::class));

        $heroImage = SiteSetting::query()->where('key', 'services.hero_image')->value('value');
        $firstImage = SiteSetting::query()->where('key', 'services.specialty_image.'.$firstSpecialty->id)->value('value');
        $secondImage = SiteSetting::query()->where('key', 'services.specialty_image.'.$secondSpecialty->id)->value('value');

        $this->assertNotNull($heroImage);
        $this->assertNotNull($firstImage);
        $this->assertNotNull($secondImage);
    }

    public function test_superadmin_services_rejects_a_single_image_over_ten_megabytes(): void
    {
        Storage::fake('public');
        Storage::fake('r2_public');

        $superadmin = $this->makeUserWithRole('superadmin');

        $response = $this->actingAs($superadmin)->from(route('superadmin.personalizacion.servicios.edit'))->put(route('superadmin.personalizacion.servicios.update'), [
            'services_title' => 'Servicios integrales',
            'services_subtitle' => 'Actualizacion segura.',
            'services_cta_text' => 'Reservar ahora',
            'services_hero_image' => $this->fakeImage('hero-pesado.png', 11000),
        ]);

        $response->assertRedirect(route('superadmin.personalizacion.servicios.edit'));
        $response->assertSessionHasErrors(['services_hero_image']);
        $response->assertSessionDoesntHaveErrors(['services_upload_total']);
    }

    public function test_superadmin_services_rejects_when_the_total_upload_size_exceeds_fifty_megabytes(): void
    {
        Storage::fake('public');
        Storage::fake('r2_public');

        $superadmin = $this->makeUserWithRole('superadmin');

        $payload = [
            'services_title' => 'Servicios integrales',
            'services_subtitle' => 'Actualizacion segura.',
            'services_cta_text' => 'Reservar ahora',
            'services_hero_image' => $this->fakeImage('hero-1.png', 9000),
            'nuevas' => [],
        ];

        for ($i = 1; $i <= 5; $i++) {
            $payload['nuevas'][$i] = [
                'nombre' => 'Servicio '.$i,
                'descripcion' => 'Descripcion '.$i,
                'icono' => 'ri-heart-pulse-line',
                'activo' => '1',
                'orden' => (string) $i,
                'image' => $this->fakeImage('servicio-'.$i.'.png', 9000),
            ];
        }

        $response = $this->actingAs($superadmin)->from(route('superadmin.personalizacion.servicios.edit'))->put(route('superadmin.personalizacion.servicios.update'), $payload);

        $response->assertRedirect(route('superadmin.personalizacion.servicios.edit'));
        $response->assertSessionHasErrors(['services_upload_total']);
    }

    public function test_superadmin_services_update_without_new_images_still_succeeds(): void
    {
        Storage::fake('local');
        Storage::fake('r2_public');
        config(['image_optimization.disk' => 'r2_public']);

        $superadmin = $this->makeUserWithRole('superadmin');
        $especialidad = Especialidad::query()->create([
            'nombre' => 'Pediatria',
            'descripcion' => 'Atencion infantil',
            'icono' => 'ri-empathize-line',
            'activo' => true,
            'orden' => 2,
        ]);

        $response = $this->submitAjax($superadmin, [
            'services_title' => 'Servicios sin imagen nueva',
            'services_subtitle' => 'Solo texto.',
            'services_cta_text' => 'Ver servicios',
            'especialidades' => [
                $especialidad->id => [
                    'nombre' => 'Pediatria general',
                    'descripcion' => 'Atencion infantil.',
                    'icono' => 'ri-user-heart-line',
                    'activo' => '1',
                    'orden' => '2',
                ],
            ],
        ]);

        $response->assertStatus(202);
        $response->assertJsonPath('total', 0);
    }

    public function test_superadmin_services_413_response_is_rendered_with_system_design(): void
    {
        \Illuminate\Support\Facades\Route::post('/superadmin/personalizacion/servicios', function (): void {
            throw new \Illuminate\Http\Exceptions\PostTooLargeException();
        });

        $response = $this->post('/superadmin/personalizacion/servicios');

        $response->assertStatus(413);
        $response->assertSee('Solicitud demasiado grande', false);
        $response->assertSee('Volver al formulario', false);
        $response->assertDontSee('Sorry, the page you are looking for could not be found.', false);
    }
}
