<?php

namespace Tests\Unit;

use App\Services\ImageOptimizer;
use App\Support\ImageUrl;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageUrlTest extends TestCase
{
    public function test_local_storage_variants_resolve_public_urls(): void
    {
        config(['image_optimization.disk' => 'public']);
        Storage::fake('public');

        Storage::disk('public')->put('images/doctors/thumb/ana.webp', 'thumb');
        Storage::disk('public')->put('images/doctors/medium/ana.webp', 'medium');
        Storage::disk('public')->put('images/doctors/large/ana.webp', 'large');

        $variants = app(ImageUrl::class)->variants('images/doctors/large/ana.webp', 'doctors', 'doctor');

        $this->assertStringContainsString('/storage/images/doctors/thumb/ana.webp', $variants['thumb']);
        $this->assertStringContainsString('/storage/images/doctors/medium/ana.webp', $variants['medium']);
        $this->assertStringContainsString('/storage/images/doctors/medium/ana.webp', $variants['large']);
        $this->assertStringContainsString('/storage/images/doctors/thumb/ana.webp', $variants['srcset']);
    }

    public function test_external_http_data_and_blob_urls_pass_through(): void
    {
        $imageUrl = app(ImageUrl::class);

        $httpUrl = 'https://example.com/logo.png';
        $dataUrl = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $blobUrl = 'blob:http://localhost/1234-5678';

        $this->assertEquals($httpUrl, $imageUrl->url($httpUrl));
        $this->assertEquals($dataUrl, $imageUrl->url($dataUrl));
        $this->assertEquals($blobUrl, $imageUrl->url($blobUrl));
    }

    public function test_simulated_r2_public_disk_resolves_r2_url(): void
    {
        config([
            'image_optimization.disk' => 'r2_public',
            'filesystems.disks.r2_public.key' => 'fake-key',
            'filesystems.disks.r2_public.secret' => 'fake-secret',
            'filesystems.disks.r2_public.bucket' => 'clinica-public-test',
            'filesystems.disks.r2_public.url' => 'https://pub-test.r2.dev',
        ]);

        $imageUrl = new ImageUrl(new ImageOptimizer());
        $variants = $imageUrl->variants('images/banners/large/hero.webp', 'banners', 'banner');

        $this->assertStringContainsString('images/banners/medium/hero.webp', $variants['src']);
        $this->assertEquals('https://pub-test.r2.dev/images/banners/medium/hero.webp', $variants['thumb']);
        $this->assertEquals('https://pub-test.r2.dev/images/banners/medium/hero.webp', $variants['medium']);
        $this->assertEquals('https://pub-test.r2.dev/images/banners/large/hero.webp', $variants['large']);
        $this->assertFalse($variants['is_placeholder']);
    }

    public function test_public_hero_profile_only_builds_medium_and_large_srcset_entries(): void
    {
        config([
            'image_optimization.disk' => 'r2_public',
            'filesystems.disks.r2_public.key' => 'fake-key',
            'filesystems.disks.r2_public.secret' => 'fake-secret',
            'filesystems.disks.r2_public.bucket' => 'clinica-public-test',
            'filesystems.disks.r2_public.url' => 'https://pub-test.r2.dev',
        ]);

        $imageUrl = new ImageUrl(new ImageOptimizer());
        $variants = $imageUrl->variants('images/banners/medium/hero-slide.webp', 'banners', 'banner', 'public_hero');

        $this->assertStringContainsString('images/banners/medium/hero-slide.webp', $variants['src']);
        $this->assertStringContainsString('images/banners/medium/hero-slide.webp', $variants['srcset']);
        $this->assertStringContainsString('images/banners/large/hero-slide.webp', $variants['srcset']);
        $this->assertSame($variants['medium'], $variants['src']);
        $this->assertStringContainsString('images/banners/large/hero-slide.webp', $variants['large']);
    }

    public function test_public_card_profile_only_builds_thumb_and_medium_srcset_entries(): void
    {
        config([
            'image_optimization.disk' => 'r2_public',
            'filesystems.disks.r2_public.key' => 'fake-key',
            'filesystems.disks.r2_public.secret' => 'fake-secret',
            'filesystems.disks.r2_public.bucket' => 'clinica-public-test',
            'filesystems.disks.r2_public.url' => 'https://pub-test.r2.dev',
        ]);

        $imageUrl = new ImageUrl(new ImageOptimizer());
        $variants = $imageUrl->variants('images/services/thumb/service-card.webp', 'services', 'banner', 'public_card');

        $this->assertStringContainsString('images/services/thumb/service-card.webp', $variants['thumb']);
        $this->assertStringContainsString('images/services/thumb/service-card.webp', $variants['srcset']);
        $this->assertStringContainsString('images/services/medium/service-card.webp', $variants['srcset']);
        $this->assertStringNotContainsString('large', (string) $variants['srcset']);
    }

    public function test_public_doctor_profile_only_builds_thumb_and_medium_srcset_entries(): void
    {
        config([
            'image_optimization.disk' => 'r2_public',
            'filesystems.disks.r2_public.key' => 'fake-key',
            'filesystems.disks.r2_public.secret' => 'fake-secret',
            'filesystems.disks.r2_public.bucket' => 'clinica-public-test',
            'filesystems.disks.r2_public.url' => 'https://pub-test.r2.dev',
        ]);

        $imageUrl = new ImageUrl(new ImageOptimizer());
        $variants = $imageUrl->variants('images/doctors/thumb/doctor-photo.webp', 'doctors', 'doctor', 'public_doctor');

        $this->assertStringContainsString('images/doctors/thumb/doctor-photo.webp', $variants['thumb']);
        $this->assertStringContainsString('images/doctors/thumb/doctor-photo.webp', $variants['srcset']);
        $this->assertStringContainsString('images/doctors/medium/doctor-photo.webp', $variants['srcset']);
        $this->assertStringNotContainsString('large', (string) $variants['srcset']);
    }

    public function test_fallback_returns_default_placeholder(): void
    {
        $fallbackUrl = app(ImageUrl::class)->fallback('doctor');
        $this->assertStringContainsString('img/placeholders/doctor.svg', $fallbackUrl);
    }

    public function test_custom_r2_domain_url_resolution_does_not_leak_local_host(): void
    {
        config([
            'image_optimization.disk' => 'r2_public',
            'filesystems.disks.r2_public.key' => 'fake-key',
            'filesystems.disks.r2_public.secret' => 'fake-secret',
            'filesystems.disks.r2_public.bucket' => 'clinica-public-test',
            'filesystems.disks.r2_public.url' => 'https://pub-example.r2.dev',
        ]);

        $imageUrl = new ImageUrl(new ImageOptimizer());
        $variants = $imageUrl->variants('images/services/medium/service-1.webp', 'services', 'banner', 'public_card');

        $expectedUrl = 'https://pub-example.r2.dev/images/services/thumb/service-1.webp';
        $this->assertEquals($expectedUrl, $variants['src']);
        $this->assertStringNotContainsString('http://localhost', $variants['src']);
        $this->assertStringNotContainsString('192.168.', $variants['src']);
        $this->assertStringStartsWith('https://pub-example.r2.dev/', $variants['src']);
        $this->assertStringStartsWith('https://pub-example.r2.dev/', $variants['thumb']);
        $this->assertStringStartsWith('https://pub-example.r2.dev/', $variants['medium']);
    }
}
