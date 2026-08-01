<?php

namespace Tests\Unit;

use App\Services\ImageOptimizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageOptimizerDiskTest extends TestCase
{
    public function test_image_optimizer_uses_configured_disk_name(): void
    {
        config(['image_optimization.disk' => 'public']);
        $optimizer = app(ImageOptimizer::class);
        $this->assertEquals('public', $optimizer->disk());

        config(['image_optimization.disk' => 'r2_public']);
        $this->assertEquals('r2_public', $optimizer->disk());
    }

    public function test_optimizer_stores_and_deletes_in_simulated_r2_public_disk(): void
    {
        config(['image_optimization.disk' => 'r2_public']);
        Storage::fake('r2_public');

        $optimizer = app(ImageOptimizer::class);
        $file = UploadedFile::fake()->image('test-banner.jpg', 800, 600);

        $storedPath = $optimizer->optimizeAndStore($file, 'banners', baseName: 'test-slide');

        $this->assertEquals('images/banners/thumb/test-slide.webp', $storedPath);
        Storage::disk('r2_public')->assertExists('images/banners/thumb/test-slide.webp');
        Storage::disk('r2_public')->assertExists('images/banners/medium/test-slide.webp');
        Storage::disk('r2_public')->assertExists('images/banners/large/test-slide.webp');

        $optimizer->deleteByStoredPath($storedPath, 'banners');

        Storage::disk('r2_public')->assertMissing('images/banners/large/test-slide.webp');
        Storage::disk('r2_public')->assertMissing('images/banners/medium/test-slide.webp');
        Storage::disk('r2_public')->assertMissing('images/banners/thumb/test-slide.webp');
    }

    public function test_optimizer_can_delete_multiple_related_paths_in_one_call_on_simulated_r2_public_disk(): void
    {
        config(['image_optimization.disk' => 'r2_public']);
        Storage::fake('r2_public');

        $optimizer = app(ImageOptimizer::class);

        $firstFile = UploadedFile::fake()->image('first-banner.jpg', 800, 600);
        $secondFile = UploadedFile::fake()->image('second-banner.jpg', 800, 600);

        $firstPath = $optimizer->optimizeAndStore($firstFile, 'banners', baseName: 'first-slide');
        $secondPath = $optimizer->optimizeAndStore($secondFile, 'banners', baseName: 'second-slide');

        $optimizer->deleteManyByStoredPaths([$firstPath, $secondPath], 'banners');

        foreach (['first-slide', 'second-slide'] as $baseName) {
            Storage::disk('r2_public')->assertMissing('images/banners/large/'.$baseName.'.webp');
            Storage::disk('r2_public')->assertMissing('images/banners/medium/'.$baseName.'.webp');
            Storage::disk('r2_public')->assertMissing('images/banners/thumb/'.$baseName.'.webp');
        }
    }

    public function test_public_hero_profile_generates_only_medium_and_large_webp_and_original_fallback(): void
    {
        config(['image_optimization.disk' => 'r2_public']);
        Storage::fake('r2_public');

        $optimizer = app(ImageOptimizer::class);
        $file = UploadedFile::fake()->image('hero-slide.jpg', 1200, 600);

        $storedPath = $optimizer->optimizeAndStore(
            $file,
            'banners',
            baseName: 'hero-slide',
            profile: 'public_hero',
            storeOriginal: true
        );

        $this->assertSame('images/banners/medium/hero-slide.webp', $storedPath);
        Storage::disk('r2_public')->assertExists('images/banners/medium/hero-slide.webp');
        Storage::disk('r2_public')->assertExists('images/banners/large/hero-slide.webp');
        Storage::disk('r2_public')->assertExists('images/banners/original/hero-slide.jpg');
        Storage::disk('r2_public')->assertMissing('images/banners/thumb/hero-slide.webp');
        Storage::disk('r2_public')->assertMissing('images/banners/medium/hero-slide.avif');
        Storage::disk('r2_public')->assertMissing('images/banners/large/hero-slide.avif');
    }

    public function test_public_card_profile_generates_only_thumb_and_medium_webp_and_original_fallback(): void
    {
        config(['image_optimization.disk' => 'r2_public']);
        Storage::fake('r2_public');

        $optimizer = app(ImageOptimizer::class);
        $file = UploadedFile::fake()->image('service-card.jpg', 900, 700);

        $storedPath = $optimizer->optimizeAndStore(
            $file,
            'services',
            baseName: 'service-card',
            profile: 'public_card',
            storeOriginal: true
        );

        $this->assertSame('images/services/thumb/service-card.webp', $storedPath);
        Storage::disk('r2_public')->assertExists('images/services/thumb/service-card.webp');
        Storage::disk('r2_public')->assertExists('images/services/medium/service-card.webp');
        $originalFiles = array_values(array_filter(
            Storage::disk('r2_public')->files('images/services/original'),
            fn ($path) => str_starts_with($path, 'images/services/original/service-card.')
        ));
        $this->assertNotEmpty($originalFiles);
        Storage::disk('r2_public')->assertMissing('images/services/large/service-card.webp');
        Storage::disk('r2_public')->assertMissing('images/services/thumb/service-card.avif');
        Storage::disk('r2_public')->assertMissing('images/services/medium/service-card.avif');
    }

    public function test_public_doctor_profile_generates_only_thumb_and_medium_webp_and_original_fallback(): void
    {
        config(['image_optimization.disk' => 'r2_public']);
        Storage::fake('r2_public');

        $optimizer = app(ImageOptimizer::class);
        $file = UploadedFile::fake()->image('doctor-photo.jpg', 900, 900);

        $storedPath = $optimizer->optimizeAndStore(
            $file,
            'doctors',
            baseName: 'doctor-photo',
            profile: 'public_doctor',
            storeOriginal: true
        );

        $this->assertSame('images/doctors/thumb/doctor-photo.webp', $storedPath);
        Storage::disk('r2_public')->assertExists('images/doctors/thumb/doctor-photo.webp');
        Storage::disk('r2_public')->assertExists('images/doctors/medium/doctor-photo.webp');
        $originalFiles = array_values(array_filter(
            Storage::disk('r2_public')->files('images/doctors/original'),
            fn ($path) => str_starts_with($path, 'images/doctors/original/doctor-photo.')
        ));
        $this->assertNotEmpty($originalFiles);
        Storage::disk('r2_public')->assertMissing('images/doctors/large/doctor-photo.webp');
        Storage::disk('r2_public')->assertMissing('images/doctors/thumb/doctor-photo.avif');
        Storage::disk('r2_public')->assertMissing('images/doctors/medium/doctor-photo.avif');
    }
}
