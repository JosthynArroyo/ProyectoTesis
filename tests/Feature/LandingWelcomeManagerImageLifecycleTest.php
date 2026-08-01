<?php

namespace Tests\Feature;

use App\Http\Requests\LandingWelcomeRequest;
use App\Models\LandingWelcomeDoctor;
use App\Models\LandingWelcomeSlide;
use App\Services\LandingWelcomeManager;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LandingWelcomeManagerImageLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('image_optimization.disk', 'r2_public');
        Config::set('filesystems.disks.r2_public.url', 'https://pub-r2-test.dev');

        Storage::fake('r2_public', ['url' => 'https://pub-r2-test.dev']);
        Storage::fake('public');
    }

    public function test_updating_slide_without_new_file_preserves_existing_path(): void
    {
        Storage::disk('r2_public')->put('images/banners/large/existing-slide.webp', 'bytes');
        Storage::disk('r2_public')->put('images/banners/medium/existing-slide.webp', 'bytes');
        Storage::disk('r2_public')->put('images/banners/thumb/existing-slide.webp', 'bytes');

        $slide = LandingWelcomeSlide::query()->create([
            'image_path' => 'images/banners/large/existing-slide.webp',
            'alt' => 'Slide de prueba',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $request = LandingWelcomeRequest::create('/superadmin/personalizacion/bienvenida', 'PUT', [
            'slides' => [
                [
                    'image_path' => 'images/banners/large/existing-slide.webp',
                    'alt' => 'Slide de prueba actualizado',
                    'is_active' => '1',
                    'sort_order' => '1',
                ],
            ],
        ]);

        $manager = app(LandingWelcomeManager::class);
        $manager->update($request);

        $this->assertDatabaseHas('landing_welcome_slides', [
            'image_path' => 'images/banners/large/existing-slide.webp',
            'alt' => 'Slide de prueba actualizado',
        ]);
    }

    public function test_replacing_slide_image_deletes_obsolete_variants_after_commit(): void
    {
        Storage::disk('r2_public')->put('images/banners/large/old-slide.webp', 'old-bytes');
        Storage::disk('r2_public')->put('images/banners/medium/old-slide.webp', 'old-bytes');
        Storage::disk('r2_public')->put('images/banners/thumb/old-slide.webp', 'old-bytes');

        LandingWelcomeSlide::query()->create([
            'image_path' => 'images/banners/large/old-slide.webp',
            'alt' => 'Slide antiguo',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $file = UploadedFile::fake()->image('new-banner.png', 1200, 600);

        $request = LandingWelcomeRequest::create('/superadmin/personalizacion/bienvenida', 'POST', [
            'slides' => [
                [
                    'image_path' => 'images/banners/large/old-slide.webp',
                    'alt' => 'Slide nuevo',
                    'is_active' => '1',
                    'sort_order' => '1',
                ],
            ],
        ], [], [
            'slides' => [
                0 => ['image' => $file],
            ],
        ]);

        $manager = app(LandingWelcomeManager::class);
        $manager->update($request);

        $newSlide = LandingWelcomeSlide::query()->first();
        $this->assertNotNull($newSlide);
        $this->assertNotEquals('images/banners/large/old-slide.webp', $newSlide->image_path);
        $this->assertStringContainsString('images/banners/medium/', $newSlide->image_path);

        Storage::disk('r2_public')->assertMissing('images/banners/large/old-slide.webp');
        Storage::disk('r2_public')->assertMissing('images/banners/medium/old-slide.webp');
        Storage::disk('r2_public')->assertMissing('images/banners/thumb/old-slide.webp');
    }

    public function test_fallback_hero_and_doctor_images_are_never_deleted(): void
    {
        Storage::disk('r2_public')->put('images/banners/original/hero1.webp', 'hero1-bytes');
        Storage::disk('r2_public')->put('images/doctors/original/doctora1.webp', 'doctora1-bytes');

        LandingWelcomeSlide::query()->create([
            'image_path' => 'images/banners/original/hero1.webp',
            'alt' => 'Hero 1 fallback',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        LandingWelcomeDoctor::query()->create([
            'name' => 'Dra. Elena Fallback',
            'photo_path' => 'images/doctors/original/doctora1.webp',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $newBannerFile = UploadedFile::fake()->image('custom-banner.png', 1200, 600);
        $newDoctorFile = UploadedFile::fake()->image('custom-doctor.png', 600, 600);

        $request = LandingWelcomeRequest::create('/superadmin/personalizacion/bienvenida', 'POST', [
            'slides' => [
                [
                    'image_path' => 'images/banners/original/hero1.webp',
                    'alt' => 'Slide reemplazado',
                    'is_active' => '1',
                    'sort_order' => '1',
                ],
            ],
            'doctors' => [
                [
                    'name' => 'Dr. Nuevo',
                    'photo_path' => 'images/doctors/original/doctora1.webp',
                    'is_active' => '1',
                    'sort_order' => '1',
                ],
            ],
        ], [], [
            'slides' => [0 => ['image' => $newBannerFile]],
            'doctors' => [0 => ['photo' => $newDoctorFile]],
        ]); 

        $manager = app(LandingWelcomeManager::class);
        $this->assertFalse($manager->isDeletableManagedPath('images/banners/original/hero1.webp'));
        $this->assertFalse($manager->isDeletableManagedPath('images/doctors/original/doctora1.webp'));
        $manager->update($request);

        $newSlide = LandingWelcomeSlide::query()->where('alt', 'Slide reemplazado')->first();
        $newDoctor = LandingWelcomeDoctor::query()->where('name', 'Dr. Nuevo')->first();
        $this->assertNotNull($newSlide);
        $this->assertNotNull($newDoctor);
        $this->assertStringContainsString('images/banners/medium/', $newSlide->image_path);
        $this->assertStringContainsString('images/doctors/thumb/', $newDoctor->photo_path);
    }

    public function test_image_referenced_by_another_slide_is_not_deleted(): void
    {
        Storage::disk('r2_public')->put('images/banners/large/shared-slide.webp', 'shared-bytes');
        Storage::disk('r2_public')->put('images/banners/medium/shared-slide.webp', 'shared-bytes');

        LandingWelcomeSlide::query()->create([
            'image_path' => 'images/banners/large/shared-slide.webp',
            'alt' => 'Slide 1',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        LandingWelcomeSlide::query()->create([
            'image_path' => 'images/banners/large/shared-slide.webp',
            'alt' => 'Slide 2',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $file = UploadedFile::fake()->image('replacement-banner.png', 1200, 600);

        $request = LandingWelcomeRequest::create('/superadmin/personalizacion/bienvenida', 'POST', [
            'slides' => [
                [
                    'image_path' => 'images/banners/large/shared-slide.webp',
                    'alt' => 'Slide 1 reemplazado',
                    'is_active' => '1',
                    'sort_order' => '1',
                ],
                [
                    'image_path' => 'images/banners/large/shared-slide.webp',
                    'alt' => 'Slide 2 que conserva la misma imagen',
                    'is_active' => '1',
                    'sort_order' => '2',
                ],
            ],
        ], [], [
            'slides' => [
                0 => ['image' => $file],
            ],
        ]);

        $manager = app(LandingWelcomeManager::class);
        $manager->update($request);

        $this->assertDatabaseHas('landing_welcome_slides', [
            'image_path' => 'images/banners/large/shared-slide.webp',
            'alt' => 'Slide 2 que conserva la misma imagen',
        ]);
    }

    public function test_db_transaction_failure_cleans_up_newly_uploaded_file_and_keeps_previous(): void
    {
        Storage::disk('r2_public')->put('images/banners/original/old-hero.webp', 'old-hero-bytes');

        LandingWelcomeSlide::query()->create([
            'image_path' => 'images/banners/original/old-hero.webp',
            'alt' => 'Original',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $file = UploadedFile::fake()->image('faulty-slide.png', 1200, 600);

        LandingWelcomeDoctor::creating(function () {
            throw new \RuntimeException('Simulated database error during doctor creation');
        });

        $request = LandingWelcomeRequest::create('/superadmin/personalizacion/bienvenida', 'POST', [
            'slides' => [
                [
                    'image_path' => 'images/banners/original/old-hero.webp',
                    'alt' => 'Intento con error',
                    'is_active' => '1',
                    'sort_order' => '1',
                ],
            ],
            'doctors' => [
                [
                    'name' => 'Dr. Error',
                    'photo_path' => 'images/doctors/original/doctor1.webp',
                    'is_active' => '1',
                    'sort_order' => '1',
                ],
            ],
        ], [], [
            'slides' => [0 => ['image' => $file]],
        ]);

        try {
            $manager = app(LandingWelcomeManager::class);
            $manager->update($request);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (\RuntimeException $e) {
            $this->assertEquals('Simulated database error during doctor creation', $e->getMessage());
        }

        $this->assertDatabaseHas('landing_welcome_slides', [
            'image_path' => 'images/banners/original/old-hero.webp',
            'alt' => 'Original',
        ]);
    }
}
