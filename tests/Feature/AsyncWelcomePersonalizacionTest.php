<?php

namespace Tests\Feature;

use App\Jobs\ProcessWelcomePersonalizationImages;
use App\Models\LandingWelcomeDoctor;
use App\Models\LandingWelcomeSetting;
use App\Models\LandingWelcomeSlide;
use App\Models\MediaProcessingBatch;
use App\Models\Role;
use App\Models\User;
use App\Services\ImageOptimizer;
use App\Services\SiteSettingsService;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AsyncWelcomePersonalizacionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('r2_private');
    }

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

    public function test_superadmin_get_welcome_page_includes_overlay_and_batch_url(): void
    {
        Storage::fake('local');
        Queue::fake();

        $superadmin = $this->makeUserWithRole('superadmin');

        $initialBatchCount = MediaProcessingBatch::query()->count();

        $response = $this->actingAs($superadmin)->get(route('superadmin.personalizacion.bienvenida.edit'));

        $response->assertOk();
        $response->assertSee('data-media-processing-overlay', false);
        $response->assertSee('data-batch-status-url-template', false);
        $response->assertSee('hidden', false);
        $response->assertSee('Preparando imágenes...', false);

        $this->assertSame($initialBatchCount, MediaProcessingBatch::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_admin_get_welcome_page_includes_overlay_and_batch_url(): void
    {
        Storage::fake('local');
        Queue::fake();

        $admin = $this->makeUserWithRole('administrador');
        \App\Models\FeatureAccessRequest::query()->create([
            'user_id' => $admin->id,
            'feature' => 'personalizacion',
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $initialBatchCount = MediaProcessingBatch::query()->count();

        $response = $this->actingAs($admin)->get(route('admin.personalizacion.bienvenida.edit'));

        $response->assertOk();
        $response->assertSee('data-media-processing-overlay', false);
        $response->assertSee('data-batch-status-url-template', false);

        $this->assertSame($initialBatchCount, MediaProcessingBatch::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_ajax_welcome_upload_creates_batch_dispatches_job_and_stores_temp_files(): void
    {
        Storage::fake('local');
        Queue::fake();

        $superadmin = $this->makeUserWithRole('superadmin');

        $logoFile = $this->fakeImage('logo.png');
        $faviconFile = $this->fakeImage('favicon.png');
        $slideFile = $this->fakeImage('slide1.png');
        $doctorFile = $this->fakeImage('doctor1.png');

        $payload = [
            'branding_name' => 'Clínica San Juan',
            'hero_title' => 'Tu salud en buenas manos',
            'header_logo' => $logoFile,
            'branding_favicon' => $faviconFile,
            'slides' => [
                [
                    'image' => $slideFile,
                    'title' => 'Slide 1',
                    'is_active' => '1',
                    'sort_order' => '1',
                ],
            ],
            'doctors' => [
                [
                    'name' => 'Dr. Pérez',
                    'specialty' => 'Cardiología',
                    'photo' => $doctorFile,
                    'is_active' => '1',
                    'sort_order' => '1',
                ],
            ],
        ];

        $response = $this->actingAs($superadmin)
            ->withHeader('Accept', 'application/json')
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->put(route('superadmin.personalizacion.bienvenida.update'), $payload);

        $response->assertStatus(202);
        $response->assertJsonStructure([
            'ok',
            'batch_uuid',
            'total',
            'message',
            'status_url',
        ]);

        $batchUuid = $response->json('batch_uuid');
        $this->assertNotEmpty($batchUuid);
        $this->assertSame(4, $response->json('total')); // 4 images: logo, favicon, 1 slide, 1 doctor

        $batch = MediaProcessingBatch::query()->where('uuid', $batchUuid)->first();
        $this->assertNotNull($batch);
        $this->assertSame('welcome_personalization', $batch->type);
        $this->assertSame('pending', $batch->status);
        $this->assertSame(4, $batch->total_items);
        $this->assertStringStartsWith('media-processing/welcome/', $batch->payload['temp_directory']);
        Storage::disk('r2_private')->assertExists($batch->payload['logo_temp_path']);
        Storage::disk('r2_private')->assertExists($batch->payload['favicon_temp_path']);
        Storage::disk('r2_private')->assertExists($batch->payload['slides'][0]['temp_path']);
        Storage::disk('r2_private')->assertExists($batch->payload['doctors'][0]['temp_path']);
        $this->assertSame([], Storage::disk('local')->allFiles());

        Queue::assertPushed(ProcessWelcomePersonalizationImages::class, function ($job) use ($batchUuid) {
            return $job->batchUuid === $batchUuid;
        });
    }

    public function test_welcome_job_processes_images_and_updates_database_atomically(): void
    {
        Storage::fake('local');
        Storage::fake('r2_public');
        config(['image_optimization.disk' => 'r2_public']);

        $superadmin = $this->makeUserWithRole('superadmin');

        $logoFile = $this->fakeImage('logo.png');
        $slideFile = $this->fakeImage('slide.jpg');

        $response = $this->actingAs($superadmin)
            ->withHeader('Accept', 'application/json')
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->put(route('superadmin.personalizacion.bienvenida.update'), [
                'branding_name' => 'Clínica Demo Async',
                'hero_title' => 'Bienvenido a la clínica',
                'header_logo' => $logoFile,
                'slides' => [
                    [
                        'image' => $slideFile,
                        'title' => 'Slide Test Async',
                        'is_active' => '1',
                        'sort_order' => '1',
                    ],
                ],
            ]);

        $batchUuid = $response->json('batch_uuid');

        $job = new ProcessWelcomePersonalizationImages($batchUuid);
        $job->handle(app(ImageOptimizer::class), app(SiteSettingsService::class));

        $batch = MediaProcessingBatch::query()->where('uuid', $batchUuid)->first();
        $this->assertSame('completed', $batch->status);
        $this->assertSame(2, $batch->processed_items);
        $this->assertSame([], Storage::disk('r2_private')->allFiles($batch->payload['temp_directory']));
        $this->assertSame([], Storage::disk('local')->allFiles());

        $welcomeSetting = LandingWelcomeSetting::query()->first();
        $this->assertNotNull($welcomeSetting);
        $this->assertSame('Clínica Demo Async', $welcomeSetting->header_name);
        $this->assertStringContainsString('branding', $welcomeSetting->header_logo);

        $slide = LandingWelcomeSlide::query()->first();
        $this->assertNotNull($slide);
        $this->assertSame('Slide Test Async', $slide->title);
        $this->assertStringContainsString('banners', $slide->image_path);
    }

    public function test_welcome_batch_status_endpoint_returns_correct_json_and_worker_absent_flag(): void
    {
        $superadmin = $this->makeUserWithRole('superadmin');

        $batch = MediaProcessingBatch::query()->create([
            'uuid' => 'test-welcome-batch-uuid',
            'user_id' => $superadmin->id,
            'type' => 'welcome_personalization',
            'status' => 'pending',
            'total_items' => 3,
            'processed_items' => 0,
            'payload' => [],
        ]);

        $response = $this->actingAs($superadmin)
            ->get(route('superadmin.personalizacion.bienvenida.batch', ['uuid' => $batch->uuid]));

        $response->assertOk();
        $response->assertJson([
            'status' => 'pending',
            'total' => 3,
            'processed' => 0,
            'percentage' => 0,
            'worker_absent' => false,
        ]);
    }

    public function test_welcome_job_failure_updates_batch_status_and_leaves_previous_data(): void
    {
        Storage::fake('local');
        Storage::fake('r2_public');
        config(['image_optimization.disk' => 'r2_public']);

        $superadmin = $this->makeUserWithRole('superadmin');

        LandingWelcomeSetting::query()->delete();
        LandingWelcomeSetting::query()->create([
            'header_name' => 'Nombre Original',
        ]);

        $tempPath = 'media-processing/welcome/fail-batch-uuid/dummy.png';
        Storage::disk('r2_private')->put($tempPath, 'dummy-content');

        $batch = MediaProcessingBatch::query()->create([
            'uuid' => 'fail-batch-uuid',
            'user_id' => $superadmin->id,
            'type' => 'welcome_personalization',
            'status' => 'pending',
            'total_items' => 1,
            'processed_items' => 0,
            'payload' => [
                'temp_directory' => 'media-processing/welcome/fail-batch-uuid',
                'logo_temp_path' => $tempPath,
            ],
        ]);

        // Mock ImageOptimizer to throw an exception
        $mockOptimizer = $this->createMock(ImageOptimizer::class);
        $mockOptimizer->method('disk')->willReturn('r2_public');
        $mockOptimizer->method('optimizeStoredPath')
            ->willThrowException(new Exception('Error de R2 simulado'));

        $job = new ProcessWelcomePersonalizationImages('fail-batch-uuid');

        try {
            $job->handle($mockOptimizer, app(SiteSettingsService::class));
        } catch (Exception $e) {
            $this->assertSame('Error de R2 simulado', $e->getMessage());
        }

        $batch->refresh();
        $this->assertSame('failed', $batch->status);
        $this->assertNotEmpty($batch->error_message);
        Storage::disk('r2_private')->assertMissing($tempPath);
        $this->assertSame([], Storage::disk('local')->allFiles());

        // Verify original data was preserved
        $welcomeSetting = LandingWelcomeSetting::query()->first();
        $this->assertSame('Nombre Original', $welcomeSetting->header_name);
    }

    public function test_cambio_de_color_sin_imagen_guardado_directo_sin_batch_ni_job(): void
    {
        Storage::fake('local');
        Queue::fake();

        $superadmin = $this->makeUserWithRole('superadmin');
        $initialBatchCount = MediaProcessingBatch::query()->count();

        $response = $this->actingAs($superadmin)
            ->withHeader('Accept', 'application/json')
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->put(route('superadmin.personalizacion.bienvenida.update'), [
                'branding_accent' => '#FF5733',
                'branding_accent_strong' => '#C70039',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
            'batch_uuid' => null,
            'total' => 0,
        ]);

        $this->assertSame($initialBatchCount, MediaProcessingBatch::query()->count());
        Queue::assertNothingPushed();

        $siteSettings = app(SiteSettingsService::class);
        $this->assertSame('#FF5733', $siteSettings->get('branding.accent'));
        $this->assertSame('#C70039', $siteSettings->get('branding.accent_strong'));
    }

    public function test_cambio_de_texto_sin_imagen_guardado_directo_sin_batch_ni_job(): void
    {
        Storage::fake('local');
        Queue::fake();

        $superadmin = $this->makeUserWithRole('superadmin');
        $initialBatchCount = MediaProcessingBatch::query()->count();

        $response = $this->actingAs($superadmin)
            ->withHeader('Accept', 'application/json')
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->put(route('superadmin.personalizacion.bienvenida.update'), [
                'hero_title' => 'Título de prueba sin imágenes',
                'hero_subtitle' => 'Subtítulo actualizado directamente',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
            'batch_uuid' => null,
            'total' => 0,
        ]);

        $this->assertSame($initialBatchCount, MediaProcessingBatch::query()->count());
        Queue::assertNothingPushed();

        $setting = LandingWelcomeSetting::query()->first();
        $this->assertNotNull($setting);
        $this->assertSame('Título de prueba sin imágenes', $setting->hero_title);
        $this->assertSame('Subtítulo actualizado directamente', $setting->hero_subtitle);
    }

    public function test_subida_de_una_imagen_crea_batch_con_total_items_uno_y_despacha_job(): void
    {
        Storage::fake('local');
        Queue::fake();

        $superadmin = $this->makeUserWithRole('superadmin');
        $logoFile = $this->fakeImage('logo.png');

        $response = $this->actingAs($superadmin)
            ->withHeader('Accept', 'application/json')
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->put(route('superadmin.personalizacion.bienvenida.update'), [
                'header_logo' => $logoFile,
            ]);

        $response->assertStatus(202);
        $response->assertJson([
            'ok' => true,
            'total' => 1,
        ]);

        $batchUuid = $response->json('batch_uuid');
        $this->assertNotEmpty($batchUuid);

        $batch = MediaProcessingBatch::query()->where('uuid', $batchUuid)->first();
        $this->assertNotNull($batch);
        $this->assertSame(1, $batch->total_items);

        Queue::assertPushed(ProcessWelcomePersonalizationImages::class, function ($job) use ($batchUuid) {
            return $job->batchUuid === $batchUuid;
        });
    }

    public function test_subida_de_varias_imagenes_crea_batch_con_total_items_correcto(): void
    {
        Storage::fake('local');
        Queue::fake();

        $superadmin = $this->makeUserWithRole('superadmin');
        $logoFile = $this->fakeImage('logo.png');
        $faviconFile = $this->fakeImage('favicon.png');
        $slideFile = $this->fakeImage('slide.jpg');

        $response = $this->actingAs($superadmin)
            ->withHeader('Accept', 'application/json')
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->put(route('superadmin.personalizacion.bienvenida.update'), [
                'header_logo' => $logoFile,
                'branding_favicon' => $faviconFile,
                'slides' => [
                    [
                        'image' => $slideFile,
                        'title' => 'Slide nuevo 1',
                        'is_active' => '1',
                        'sort_order' => '1',
                    ],
                ],
            ]);

        $response->assertStatus(202);
        $response->assertJson([
            'ok' => true,
            'total' => 3,
        ]);

        $batchUuid = $response->json('batch_uuid');
        $batch = MediaProcessingBatch::query()->where('uuid', $batchUuid)->first();
        $this->assertNotNull($batch);
        $this->assertSame(3, $batch->total_items);

        Queue::assertPushed(ProcessWelcomePersonalizationImages::class);
    }

    public function test_cambio_mixto_texto_y_color_mas_imagen_preserva_ambos_cambios(): void
    {
        Storage::fake('local');
        Storage::fake('r2_public');
        config(['image_optimization.disk' => 'r2_public']);

        $superadmin = $this->makeUserWithRole('superadmin');
        $logoFile = $this->fakeImage('logo_mixto.png');

        $response = $this->actingAs($superadmin)
            ->withHeader('Accept', 'application/json')
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->put(route('superadmin.personalizacion.bienvenida.update'), [
                'branding_accent' => '#0088CC',
                'hero_title' => 'Título Mixto Con Imagen',
                'header_logo' => $logoFile,
            ]);

        $response->assertStatus(202);
        $batchUuid = $response->json('batch_uuid');

        $job = new ProcessWelcomePersonalizationImages($batchUuid);
        $job->handle(app(ImageOptimizer::class), app(SiteSettingsService::class));

        $batch = MediaProcessingBatch::query()->where('uuid', $batchUuid)->first();
        $this->assertSame('completed', $batch->status);
        $this->assertSame(1, $batch->processed_items);

        $siteSettings = app(SiteSettingsService::class);
        $this->assertSame('#0088CC', $siteSettings->get('branding.accent'));

        $welcomeSetting = LandingWelcomeSetting::query()->first();
        $this->assertNotNull($welcomeSetting);
        $this->assertSame('Título Mixto Con Imagen', $welcomeSetting->hero_title);
        $this->assertStringContainsString('branding', $welcomeSetting->header_logo);
    }
}
