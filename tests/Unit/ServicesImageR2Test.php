<?php

namespace Tests\Unit;

use App\Services\ImageOptimizer;
use App\Support\ImageUrl;
use App\Support\ServicePageCatalog;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServicesImageR2Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('filesystems.disks.r2_public.url', 'https://pub-r2-test.dev');
        Storage::fake('r2_public', ['url' => 'https://pub-r2-test.dev']);
        Storage::fake('public');

        Storage::disk('public')->put('images/services/large/service-7.webp', 'dummy-bytes');
        Storage::disk('public')->put('images/services/medium/service-7.webp', 'dummy-bytes');
        Storage::disk('public')->put('images/services/thumb/service-7.webp', 'dummy-bytes');

        Storage::disk('r2_public')->put('images/services/large/service-7.webp', 'dummy-bytes');
        Storage::disk('r2_public')->put('images/services/medium/service-7.webp', 'dummy-bytes');
        Storage::disk('r2_public')->put('images/services/thumb/service-7.webp', 'dummy-bytes');
    }

    private function makeImageUrl(): ImageUrl
    {
        $this->app->forgetInstance(ImageOptimizer::class);
        $this->app->forgetInstance(ImageUrl::class);

        return $this->app->make(ImageUrl::class);
    }

    public function test_relative_service_image_key_generates_r2_url_when_r2_disk_active(): void
    {
        Config::set('image_optimization.disk', 'r2_public');

        $imageUrl = $this->makeImageUrl();
        $variants = $imageUrl->variants('images/services/large/service-7.webp', 'services', 'banner');

        $this->assertStringStartsWith('https://pub-r2-test.dev/images/services/', $variants['large']);
        $this->assertStringNotContainsString('/storage/', $variants['large']);
    }

    public function test_service_image_with_public_disk_generates_local_storage_url(): void
    {
        Config::set('image_optimization.disk', 'public');

        $imageUrl = $this->makeImageUrl();
        $variants = $imageUrl->variants('images/services/large/service-7.webp', 'services', 'banner');

        $this->assertStringContainsString('/storage/images/services/', $variants['large']);
    }

    public function test_external_https_service_url_remains_unchanged(): void
    {
        Config::set('image_optimization.disk', 'r2_public');

        $imageUrl = $this->makeImageUrl();
        $url = $imageUrl->url('https://cdn.example.com/custom-service.jpg');

        $this->assertEquals('https://cdn.example.com/custom-service.jpg', $url);
    }

    public function test_legacy_path_with_storage_prefix_is_normalized_correctly(): void
    {
        Config::set('image_optimization.disk', 'r2_public');

        $imageUrl = $this->makeImageUrl();
        $variants = $imageUrl->variants('storage/images/services/large/service-7.webp', 'services', 'banner');

        $this->assertStringStartsWith('https://pub-r2-test.dev/images/services/', $variants['large']);
    }

    public function test_fallback_static_asset_works_and_returns_asset_url(): void
    {
        Config::set('image_optimization.disk', 'r2_public');

        $imageUrl = $this->makeImageUrl();
        $fallbackPath = ServicePageCatalog::heroImagePath();
        $variants = $imageUrl->variants($fallbackPath, 'services', 'banner');

        $this->assertNotNull($variants['thumb']);
        $this->assertStringNotContainsString('pub-r2-test.dev', $variants['thumb']);
    }
}
