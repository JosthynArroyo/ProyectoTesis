<?php

namespace Tests\Feature;

use App\Jobs\CleanupReplacedServiceImagesJob;
use App\Jobs\ProcessServicesPersonalizationImages;
use App\Models\Especialidad;
use App\Models\MediaProcessingBatch;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\ImageOptimizer;
use App\Services\SiteSettingsService;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AsyncServiciosPersonalizacionTest extends TestCase
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

    private function submitServicesUpdate(User $user, array $payload, string $routeName): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->post(route($routeName), array_merge(['_method' => 'PUT'], $payload));
    }

    public function test_superadmin_get_services_page_does_not_create_batches_or_jobs(): void
    {
        Storage::fake('local');
        Queue::fake();

        $superadmin = $this->makeUserWithRole('superadmin');

        $initialBatchCount = MediaProcessingBatch::query()->count();

        $response = $this->actingAs($superadmin)->get(route('superadmin.personalizacion.servicios.edit'));

        $response->assertOk();
        $response->assertSee('data-media-processing-overlay', false);
        $response->assertSee('hidden', false);
        $response->assertSee('style="display: none;"', false);
        $response->assertSee('Preparando imágenes...', false);

        $this->assertSame($initialBatchCount, MediaProcessingBatch::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_admin_get_services_page_does_not_create_batches_or_jobs(): void
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

        $response = $this->actingAs($admin)->get(route('admin.personalizacion.servicios.edit'));

        $response->assertOk();
        $response->assertSee('data-media-processing-overlay', false);
        $response->assertSee('hidden', false);
        $response->assertSee('style="display: none;"', false);

        $this->assertSame($initialBatchCount, MediaProcessingBatch::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_ajax_services_upload_dispatches_job_creates_batch_and_stores_temp_files(): void
    {
        Storage::fake('local');
        Storage::fake('r2_public');
        config(['image_optimization.disk' => 'r2_public']);
        Queue::fake();

        $superadmin = $this->makeUserWithRole('superadmin');
        $especialidad1 = Especialidad::query()->firstOrCreate(['nombre' => 'Odontologia'], ['activo' => true, 'orden' => 1]);
        $especialidad2 = Especialidad::query()->firstOrCreate(['nombre' => 'Pediatria'], ['activo' => true, 'orden' => 2]);

        $payload = [
            'services_title' => 'Nuestros Servicios',
            'services_subtitle' => 'Atencion de calidad',
            'services_cta_text' => 'Reservar',
            'services_hero_image' => $this->fakeImage('hero.png'),
            'especialidades' => [
                $especialidad1->id => [
                    'nombre' => 'Odontologia Avanzada',
                    'descripcion' => 'Salud dental',
                    'icono' => 'ri-tooth-line',
                    'activo' => '1',
                    'orden' => '1',
                    'image' => $this->fakeImage('odontologia.jpg'),
                ],
                $especialidad2->id => [
                    'nombre' => 'Pediatria Infantil',
                    'descripcion' => 'Atencion para ninos',
                    'icono' => 'ri-heart-line',
                    'activo' => '1',
                    'orden' => '2',
                    'image' => $this->fakeImage('pediatria.jpg'),
                ],
            ],
            'nuevas' => [
                0 => [
                    'nombre' => 'Cardiologia',
                    'descripcion' => 'Salud cardiovascular',
                    'icono' => 'ri-heart-pulse-line',
                    'activo' => '1',
                    'orden' => '3',
                    'image' => $this->fakeImage('cardiologia.jpg'),
                ],
            ],
        ];

        $response = $this->submitServicesUpdate($superadmin, $payload, 'superadmin.personalizacion.servicios.update');

        $response->assertStatus(202);
        $response->assertJsonStructure(['ok', 'batch_uuid', 'total', 'status_url']);
        $response->assertJson([
            'ok' => true,
            'total' => 4,
        ]);

        $batchUuid = $response->json('batch_uuid');
        $this->assertNotNull($batchUuid);

        $this->assertDatabaseHas('media_processing_batches', [
            'uuid' => $batchUuid,
            'user_id' => $superadmin->id,
            'type' => 'services',
            'status' => 'pending',
            'total_items' => 4,
        ]);

        Queue::assertPushed(ProcessServicesPersonalizationImages::class, function ($job) use ($batchUuid) {
            return $job->batchUuid === $batchUuid;
        });

        $batch = MediaProcessingBatch::query()->where('uuid', $batchUuid)->firstOrFail();
        $this->assertStringStartsWith('media-processing/services/', $batch->payload['temp_directory']);
        Storage::disk('r2_private')->assertExists($batch->payload['hero_image_temp_path']);
        Storage::disk('r2_private')->assertExists($batch->payload['especialidades'][$especialidad1->id]['temp_path']);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_batch_progress_endpoint_reports_processing_and_elapsed_time(): void
    {
        Storage::fake('local');
        Carbon::setTestNow(Carbon::parse('2026-08-01 09:39:38'));

        try {
            $superadmin = $this->makeUserWithRole('superadmin');
            $otherUser = $this->makeUserWithRole('administrador');
            \App\Models\FeatureAccessRequest::query()->create([
                'user_id' => $otherUser->id,
                'feature' => 'personalizacion',
                'status' => 'approved',
                'approved_at' => now(),
            ]);

            $batch = MediaProcessingBatch::query()->create([
                'uuid' => 'test-uuid-1234',
                'user_id' => $superadmin->id,
                'type' => 'services',
                'status' => 'processing',
                'total_items' => 7,
                'processed_items' => 3,
                'payload' => [],
            ]);

            $batch->forceFill([
                'created_at' => now()->subSeconds(36),
                'started_at' => now()->subSeconds(33),
            ])->save();

            $response = $this->actingAs($superadmin)
                ->getJson(route('superadmin.personalizacion.servicios.batch', ['uuid' => $batch->uuid]));

            $response->assertOk();
            $response->assertJsonPath('status', 'processing');
            $response->assertJsonPath('total', 7);
            $response->assertJsonPath('processed', 3);
            $response->assertJsonPath('percentage', 42);
            $this->assertStringContainsString('3 de 7', (string) $response->json('message'));
            $this->assertSame(36, (int) $response->json('elapsed_seconds'));
            $this->assertSame(3, (int) $response->json('waiting_seconds'));
            $this->assertSame(33, (int) $response->json('processing_seconds'));

            $unauthResponse = $this->actingAs($otherUser)
                ->getJson(route('admin.personalizacion.servicios.batch', ['uuid' => $batch->uuid]));

            $unauthResponse->assertStatus(403);
            $unauthResponse->assertJsonPath('message', 'No autorizado.');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_completed_batch_reports_7_of_7_and_final_elapsed_time(): void
    {
        $superadmin = $this->makeUserWithRole('superadmin');
        Carbon::setTestNow(Carbon::parse('2026-08-01 09:40:00'));

        try {
            $batch = MediaProcessingBatch::query()->create([
                'uuid' => 'completed-uuid-999',
                'user_id' => $superadmin->id,
                'type' => 'services',
                'status' => 'completed',
                'total_items' => 7,
                'processed_items' => 7,
                'payload' => [],
            ]);

            $batch->forceFill([
                'created_at' => now()->subSeconds(36),
                'started_at' => now()->subSeconds(33),
                'finished_at' => now(),
            ])->save();

            $response = $this->actingAs($superadmin)
                ->getJson(route('superadmin.personalizacion.servicios.batch', ['uuid' => $batch->uuid]));

            $response->assertOk();
            $response->assertJsonPath('status', 'completed');
            $response->assertJsonPath('total', 7);
            $response->assertJsonPath('processed', 7);
            $response->assertJsonPath('percentage', 100);
            $this->assertStringContainsString('correctamente', (string) $response->json('message'));
            $this->assertSame(36, (int) $response->json('elapsed_seconds'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_job_execution_generates_only_profile_variants_updates_progress_and_commits_atomically(): void
    {
        Storage::fake('local');
        Storage::fake('r2_public');
        config(['image_optimization.disk' => 'r2_public']);
        Queue::fake();

        $superadmin = $this->makeUserWithRole('superadmin');
        $especialidad = Especialidad::query()->firstOrCreate(
            ['nombre' => 'Dermatologia'],
            ['activo' => true, 'orden' => 1]
        );

        $response = $this->submitServicesUpdate($superadmin, [
            'services_title' => 'Servicios Modificados',
            'services_hero_image' => $this->fakeImage('hero-new.jpg'),
            'especialidades' => [
                $especialidad->id => [
                    'nombre' => 'Dermatologia Avanzada',
                    'activo' => '1',
                    'image' => $this->fakeImage('derma-new.jpg'),
                ],
            ],
        ], 'superadmin.personalizacion.servicios.update');

        $response->assertStatus(202);

        $batchUuid = $response->json('batch_uuid');

        $job = new ProcessServicesPersonalizationImages($batchUuid);
        $job->handle(app(ImageOptimizer::class), app(SiteSettingsService::class));

        $batch = MediaProcessingBatch::query()->where('uuid', $batchUuid)->first();
        $this->assertNotNull($batch);
        $this->assertSame('completed', $batch->status);
        $this->assertSame(100, $batch->percentage);
        $this->assertSame(2, $batch->total_items);
        $this->assertSame(2, $batch->processed_items);

        $especialidad->refresh();
        $this->assertSame('Dermatologia Avanzada', $especialidad->nombre);
        $this->assertNotNull(SiteSetting::query()->where('key', 'services.hero_image')->value('value'));
        $this->assertNotNull(SiteSetting::query()->where('key', "services.specialty_image.{$especialidad->id}")->value('value'));

        Storage::disk('r2_private')->assertMissing($batch->payload['hero_image_temp_path']);
        Storage::disk('r2_private')->assertMissing($batch->payload['especialidades'][$especialidad->id]['temp_path']);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_failed_batch_preserves_old_paths_and_cleans_new_variants(): void
    {
        Storage::fake('local');
        Storage::fake('r2_public');
        config(['image_optimization.disk' => 'r2_public']);

        $superadmin = $this->makeUserWithRole('superadmin');
        $heroPath = 'images/services/original/old-hero.jpg';
        Storage::disk('r2_public')->put($heroPath, 'old-hero-bytes');

        SiteSetting::query()->updateOrCreate(
            ['key' => 'services.hero_image'],
            ['section' => 'services', 'type' => 'image', 'value' => $heroPath]
        );

        $tempDir = 'media-processing/services/fail-batch-123';
        $tempFile = "{$tempDir}/hero.jpg";
        Storage::disk('r2_private')->putFileAs($tempDir, $this->fakeImage('hero.jpg'), 'hero.jpg');

        $batch = MediaProcessingBatch::query()->create([
            'uuid' => 'fail-batch-123',
            'user_id' => $superadmin->id,
            'type' => 'services',
            'status' => 'pending',
            'total_items' => 1,
            'processed_items' => 0,
            'payload' => [
                'temp_directory' => $tempDir,
                'hero_image_temp_path' => $tempFile,
                'hero_image_current_path' => $heroPath,
                'text_settings' => [
                    'services.title' => 'Servicios',
                ],
                'especialidades' => [],
                'nuevas' => [],
            ],
        ]);

        $settings = $this->createMock(SiteSettingsService::class);
        $settings->method('get')->willReturnCallback(function ($key, $default = null) use ($heroPath) {
            if ($key === 'services.hero_image') {
                return $heroPath;
            }

            return $default;
        });
        $settings->expects($this->once())
            ->method('setMany')
            ->willThrowException(new Exception('Simulated failure during DB write'));

        $job = new ProcessServicesPersonalizationImages($batch->uuid);

        try {
            $job->handle(app(ImageOptimizer::class), $settings);
            $this->fail('Expected exception was not thrown.');
        } catch (Exception $exception) {
            $this->assertSame('Simulated failure during DB write', $exception->getMessage());
        }

        $batch->refresh();
        $this->assertSame('failed', $batch->status);
        $this->assertSame($heroPath, SiteSetting::query()->where('key', 'services.hero_image')->value('value'));
        Storage::disk('r2_public')->assertMissing('images/services/medium/services-hero.webp');
        Storage::disk('r2_public')->assertMissing('images/services/large/services-hero.webp');
        Storage::disk('r2_public')->assertMissing('images/services/original/services-hero.jpg');
        Storage::disk('r2_private')->assertMissing($tempFile);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_cleanup_job_is_resilient_to_failures(): void
    {
        Storage::fake('r2_public');

        $cleanupJob = new CleanupReplacedServiceImagesJob(['services/old-hero.jpg', 'services/old-service.jpg']);
        $cleanupJob->handle(app(ImageOptimizer::class));

        $this->assertTrue(true);
    }

    public function test_public_servicios_page_renders_specialty_descriptions(): void
    {
        Especialidad::query()->firstOrCreate(
            ['nombre' => 'Odontología'],
            ['orden' => 1, 'activo' => true]
        );

        $response = $this->get('/servicios');

        $response->assertOk();
        $response->assertSeeText('Odontología');
        $response->assertSeeText('Prevención, diagnóstico y tratamiento de problemas dentales y de salud bucal.');
    }

    public function test_hero_upload_writes_to_r2_not_public_and_db_stores_r2_path(): void
    {
        Storage::fake('local');
        Storage::fake('r2_public', ['url' => 'https://pub-test.r2.dev']);
        Storage::fake('public');
        config([
            'image_optimization.disk' => 'r2_public',
            'filesystems.disks.r2_public.url' => 'https://pub-test.r2.dev',
        ]);
        Queue::fake();

        $superadmin = $this->makeUserWithRole('superadmin');

        // 1. Submit hero upload — dispatches the job.
        $response = $this->submitServicesUpdate($superadmin, [
            'services_title'    => 'Hero en R2',
            'services_hero_image' => $this->fakeImage('hero-r2.jpg', 50),
        ], 'superadmin.personalizacion.servicios.update');

        $response->assertStatus(202);
        $batchUuid = $response->json('batch_uuid');
        $this->assertNotNull($batchUuid);

        // 2. Run the job synchronously.
        $job = new ProcessServicesPersonalizationImages($batchUuid);
        $job->handle(app(\App\Services\ImageOptimizer::class), app(\App\Services\SiteSettingsService::class));

        // 3. Batch completed 1/1.
        $batch = MediaProcessingBatch::query()->where('uuid', $batchUuid)->firstOrFail();
        $this->assertSame('completed', $batch->status);
        $this->assertSame(1, $batch->total_items);
        $this->assertSame(1, $batch->processed_items);

        // 4. DB stores a new relative path under images/services/ — NOT the old stale placeholder.
        $storedHeroPath = SiteSetting::query()->where('key', 'services.hero_image')->value('value');
        $this->assertNotNull($storedHeroPath);
        $this->assertStringStartsWith('images/services/', $storedHeroPath);
        $this->assertNotEquals('images/services/medium/services-hero.webp', $storedHeroPath,
            'DB must not store the old stale placeholder path after a successful upload.');
        $this->assertNotEquals('images/services/large/services-hero.webp', $storedHeroPath);

        // 5. Written to r2_public, NOT to local public disk.
        Storage::disk('public')->assertMissing($storedHeroPath);

        // 6. medium and large WebP variants exist on r2_public (public_hero profile).
        // The stored path is the preferred_size (medium). Derive large from it.
        $baseName = pathinfo($storedHeroPath, PATHINFO_FILENAME);
        Storage::disk('r2_public')->assertExists('images/services/medium/' . $baseName . '.webp');
        Storage::disk('r2_public')->assertExists('images/services/large/' . $baseName . '.webp');

        // 7. No thumb variant (public_hero profile omits thumb).
        Storage::disk('r2_public')->assertMissing('images/services/thumb/' . $baseName . '.webp');

        // 8. No AVIF variants (public_hero disables AVIF).
        Storage::disk('r2_public')->assertMissing('images/services/medium/' . $baseName . '.avif');
        Storage::disk('r2_public')->assertMissing('images/services/large/' . $baseName . '.avif');

        // 9. ImageUrl resolves the stored path to the R2 CDN domain, never local host.
        $imageUrl = app(\App\Support\ImageUrl::class);
        $variants = $imageUrl->variants($storedHeroPath, 'services', 'banner', 'public_hero');
        $this->assertStringStartsWith('https://pub-test.r2.dev/', $variants['medium']);
        $this->assertStringStartsWith('https://pub-test.r2.dev/', $variants['large']);
        $this->assertStringNotContainsString('192.168.', $variants['medium']);
        $this->assertStringNotContainsString('127.0.0.1', $variants['medium']);

        // 10. The new UUID path does NOT equal the static local default,
        // so the Blade guard (which now only fires for empty or the exact local path)
        // will NOT replace it with the fallback. Verify the guard logic directly.
        $localDefault = \App\Support\ServicePageCatalog::heroImagePath();
        $this->assertNotEquals($storedHeroPath, $localDefault,
            'Uploaded hero path must not equal the local static default.');
        $this->assertFalse(empty($storedHeroPath),
            'Uploaded hero path must not be empty.');
        // Therefore: heroIsLocalDefault = false → guard does not fire → R2 URL is used.

        // 11. Even the old fixed-name path (images/services/medium/services-hero.webp)
        // now passes through the guard and resolves to an R2 URL instead of the local fallback.
        $oldFixedPath = 'images/services/medium/services-hero.webp';
        $this->assertNotEquals($oldFixedPath, $localDefault);
        $this->assertFalse(empty($oldFixedPath));
        // Guard does not fire → ImageUrl returns R2 domain URL.
        $oldVariants = $imageUrl->variants($oldFixedPath, 'services', 'banner', 'public_hero');
        $this->assertStringStartsWith('https://pub-test.r2.dev/', $oldVariants['medium'],
            'Even the old fixed-name path must resolve to R2 CDN, not local.');
    }

    public function test_cards_upload_writes_to_r2_not_public_and_hero_remains_unchanged(): void
    {
        Storage::fake('local');
        Storage::fake('r2_public', ['url' => 'https://pub-test.r2.dev']);
        Storage::fake('public');
        config([
            'image_optimization.disk' => 'r2_public',
            'filesystems.disks.r2_public.url' => 'https://pub-test.r2.dev',
        ]);
        Queue::fake();

        $superadmin = $this->makeUserWithRole('superadmin');
        $esp1 = Especialidad::query()->firstOrCreate(['nombre' => 'Odontología'], ['descripcion' => 'Descripción Odontología', 'activo' => true, 'orden' => 1]);
        $esp2 = Especialidad::query()->firstOrCreate(['nombre' => 'Pediatría'], ['descripcion' => 'Descripción Pediatría', 'activo' => true, 'orden' => 2]);
        $esp3 = Especialidad::query()->firstOrCreate(['nombre' => 'Dermatología'], ['descripcion' => 'Descripción Dermatología', 'activo' => true, 'orden' => 3]);

        $initialHero = SiteSetting::query()->where('key', 'services.hero_image')->value('value') ?? \App\Support\ServicePageCatalog::heroImagePath();

        // Submit update for 2 cards (esp1 and esp2), no hero
        $response = $this->submitServicesUpdate($superadmin, [
            'services_title' => 'Especialidades',
            'especialidades' => [
                $esp1->id => [
                    'nombre' => 'Odontología Avanzada',
                    'descripcion' => 'Descripción Odontología',
                    'icono' => 'ri-tooth-line',
                    'activo' => '1',
                    'orden' => '1',
                    'image' => $this->fakeImage('odontologia.jpg', 30),
                ],
                $esp2->id => [
                    'nombre' => 'Pediatría Infantil',
                    'descripcion' => 'Descripción Pediatría',
                    'icono' => 'ri-heart-line',
                    'activo' => '1',
                    'orden' => '2',
                    'image' => $this->fakeImage('pediatria.jpg', 30),
                ],
            ],
        ], 'superadmin.personalizacion.servicios.update');

        $response->assertStatus(202);
        $batchUuid = $response->json('batch_uuid');
        $this->assertNotNull($batchUuid);

        // Run job synchronously
        $job = new ProcessServicesPersonalizationImages($batchUuid);
        $job->handle(app(\App\Services\ImageOptimizer::class), app(\App\Services\SiteSettingsService::class));

        // Check batch
        $batch = MediaProcessingBatch::query()->where('uuid', $batchUuid)->firstOrFail();
        $this->assertSame('completed', $batch->status);
        $this->assertSame(2, $batch->total_items);
        $this->assertSame(2, $batch->processed_items);

        // Check Hero remained unchanged
        $currentHero = SiteSetting::query()->where('key', 'services.hero_image')->value('value');
        $this->assertSame($initialHero, $currentHero, 'Hero setting must not change when updating only cards.');

        // Check DB paths for esp1 and esp2
        $path1 = SiteSetting::query()->where('key', "services.specialty_image.{$esp1->id}")->value('value');
        $path2 = SiteSetting::query()->where('key', "services.specialty_image.{$esp2->id}")->value('value');
        $path3 = SiteSetting::query()->where('key', "services.specialty_image.{$esp3->id}")->value('value');

        $this->assertNotNull($path1);
        $this->assertNotNull($path2);
        $this->assertNull($path3, 'Unmodified card setting should remain unchanged.');

        $this->assertStringStartsWith('images/services/', $path1);
        $this->assertStringStartsWith('images/services/', $path2);

        // Check that thumb and medium exist on r2_public, NOT on local public
        Storage::disk('public')->assertMissing($path1);
        Storage::disk('public')->assertMissing($path2);

        $baseName1 = pathinfo($path1, PATHINFO_FILENAME);
        $baseName2 = pathinfo($path2, PATHINFO_FILENAME);

        Storage::disk('r2_public')->assertExists('images/services/thumb/' . $baseName1 . '.webp');
        Storage::disk('r2_public')->assertExists('images/services/medium/' . $baseName1 . '.webp');
        Storage::disk('r2_public')->assertExists('images/services/thumb/' . $baseName2 . '.webp');
        Storage::disk('r2_public')->assertExists('images/services/medium/' . $baseName2 . '.webp');

        // Verify NO large variant, NO AVIF variant
        Storage::disk('r2_public')->assertMissing('images/services/large/' . $baseName1 . '.webp');
        Storage::disk('r2_public')->assertMissing('images/services/thumb/' . $baseName1 . '.avif');
        Storage::disk('r2_public')->assertMissing('images/services/medium/' . $baseName1 . '.avif');

        // Check ImageUrl produces R2 CDN domain
        $imageUrl = app(\App\Support\ImageUrl::class);
        $variants1 = $imageUrl->variants($path1, 'services', 'banner', 'public_card');
        $this->assertStringStartsWith('https://pub-test.r2.dev/', $variants1['thumb']);
        $this->assertStringStartsWith('https://pub-test.r2.dev/', $variants1['medium']);
        $this->assertStringNotContainsString('192.168.', $variants1['medium']);
        $this->assertStringNotContainsString('127.0.0.1', $variants1['medium']);

        // Check specialty names and descriptions remain
        $esp1->refresh();
        $this->assertSame('Odontología Avanzada', $esp1->nombre);
        $this->assertSame('Descripción Odontología', $esp1->descripcion);
    }

    public function test_failed_card_upload_preserves_old_paths_and_hero(): void
    {
        Storage::fake('local');
        Storage::fake('r2_public', ['url' => 'https://pub-test.r2.dev']);
        config(['image_optimization.disk' => 'r2_public']);

        $superadmin = $this->makeUserWithRole('superadmin');
        $esp1 = Especialidad::query()->create(['nombre' => 'Cardiología', 'activo' => true, 'orden' => 1]);
        $oldCardPath = 'images/services/thumb/old-card.jpg';
        Storage::disk('r2_public')->put($oldCardPath, 'old-card-bytes');

        SiteSetting::query()->updateOrCreate(
            ['key' => "services.specialty_image.{$esp1->id}"],
            ['section' => 'services', 'type' => 'image', 'value' => $oldCardPath]
        );

        $initialHero = SiteSetting::query()->where('key', 'services.hero_image')->value('value') ?? \App\Support\ServicePageCatalog::heroImagePath();

        $tempDir = 'media-processing/services/fail-card-batch-999';
        $tempFile = "{$tempDir}/spec-{$esp1->id}.jpg";
        Storage::disk('r2_private')->putFileAs($tempDir, $this->fakeImage('spec.jpg'), "spec-{$esp1->id}.jpg");

        $batch = MediaProcessingBatch::query()->create([
            'uuid' => 'fail-card-batch-999',
            'user_id' => $superadmin->id,
            'type' => 'services',
            'status' => 'pending',
            'total_items' => 1,
            'processed_items' => 0,
            'payload' => [
                'temp_directory' => $tempDir,
                'hero_image_temp_path' => null,
                'hero_image_current_path' => $initialHero,
                'text_settings' => [
                    'services.title' => 'Servicios',
                ],
                'especialidades' => [
                    $esp1->id => [
                        'nombre' => 'Cardiología',
                        'current_path' => $oldCardPath,
                        'temp_path' => $tempFile,
                    ],
                ],
                'nuevas' => [],
            ],
        ]);

        $settings = $this->createMock(SiteSettingsService::class);
        $settings->method('get')->willReturnCallback(function ($key, $default = null) use ($oldCardPath, $initialHero) {
            if ($key === "services.specialty_image.{$esp1->id}") {
                return $oldCardPath;
            }
            if ($key === 'services.hero_image') {
                return $initialHero;
            }

            return $default;
        });
        $settings->expects($this->once())
            ->method('setMany')
            ->willThrowException(new \Exception('Simulated failure during card DB write'));

        $job = new ProcessServicesPersonalizationImages($batch->uuid);

        try {
            $job->handle(app(\App\Services\ImageOptimizer::class), $settings);
            $this->fail('Expected exception was not thrown.');
        } catch (\Exception $exception) {
            $this->assertSame('Simulated failure during card DB write', $exception->getMessage());
        }

        $batch->refresh();
        $this->assertSame('failed', $batch->status);
        $this->assertSame($oldCardPath, SiteSetting::query()->where('key', "services.specialty_image.{$esp1->id}")->value('value'));

        // Check hero remained intact
        $currentHero = SiteSetting::query()->where('key', 'services.hero_image')->value('value') ?? \App\Support\ServicePageCatalog::heroImagePath();
        $this->assertSame($initialHero, $currentHero);

        // Check old path was preserved in r2_public
        Storage::disk('r2_public')->assertExists($oldCardPath);
        Storage::disk('r2_private')->assertMissing($tempFile);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_servicios_update_sin_imagenes_guarda_directamente_sin_batch_ni_job(): void
    {
        Storage::fake('local');
        Queue::fake();

        $superadmin = $this->makeUserWithRole('superadmin');
        $especialidad = Especialidad::query()->firstOrCreate(['nombre' => 'Dermatologia'], ['activo' => true, 'orden' => 1]);
        $initialBatchCount = MediaProcessingBatch::query()->count();

        $response = $this->submitServicesUpdate($superadmin, [
            'services_title' => 'Nuestros Servicios Directos',
            'services_subtitle' => 'Sin procesamiento de imágenes',
            'especialidades' => [
                $especialidad->id => [
                    'nombre' => 'Dermatologia Clínica',
                    'descripcion' => 'Descripción nueva sin foto',
                    'icono' => 'ri-tooth-line',
                    'activo' => '1',
                    'orden' => '1',
                ],
            ],
        ], 'superadmin.personalizacion.servicios.update');

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
            'batch_uuid' => null,
            'total' => 0,
        ]);

        $this->assertSame($initialBatchCount, MediaProcessingBatch::query()->count());
        Queue::assertNothingPushed();

        $siteSettings = app(SiteSettingsService::class);
        $this->assertSame('Nuestros Servicios Directos', $siteSettings->get('services.title'));

        $especialidad->refresh();
        $this->assertSame('Dermatologia Clínica', $especialidad->nombre);
        $this->assertSame('Descripción nueva sin foto', $especialidad->descripcion);
    }
}
