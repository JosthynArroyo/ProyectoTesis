<?php

namespace Tests\Unit;

use App\Services\ClinicIdentityService;
use App\Services\SiteSettingsService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClinicIdentityLogoR2Test extends TestCase
{
    public function test_logo_base64_for_pdf_reads_from_simulated_r2_public_disk_without_path_call(): void
    {
        config(['image_optimization.disk' => 'r2_public']);
        Storage::fake('r2_public');

        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        Storage::disk('r2_public')->put('images/branding/original/logo.png', $pngBytes);

        $siteSettings = $this->createMock(SiteSettingsService::class);
        $siteSettings->method('get')->willReturnCallback(function ($key, $default = null) {
            if ($key === 'branding.logo') {
                return 'images/branding/original/logo.png';
            }
            return $default;
        });

        $this->app->instance(SiteSettingsService::class, $siteSettings);

        $service = app(ClinicIdentityService::class);

        $base64Plain = $service->logoBase64();
        $base64Pdf = $service->logoBase64ForPdf();

        $this->assertNotNull($base64Plain);
        $this->assertStringStartsWith('data:image/png;base64,', $base64Plain);
        $this->assertNotNull($base64Pdf);
        $this->assertStringStartsWith('data:image/png;base64,', $base64Pdf);
    }

    public function test_logo_base64_falls_back_to_static_placeholder_if_logo_missing(): void
    {
        config(['image_optimization.disk' => 'r2_public']);
        Storage::fake('r2_public');

        $siteSettings = $this->createMock(SiteSettingsService::class);
        $siteSettings->method('get')->willReturn(null);
        $this->app->instance(SiteSettingsService::class, $siteSettings);

        $service = app(ClinicIdentityService::class);

        $this->assertNull($service->logoBase64());
        $this->assertNotNull($service->logoBase64ForPdf());
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $service->logoBase64ForPdf());
    }

    public function test_logo_and_favicon_urls_keep_branding_asset_treatment(): void
    {
        config(['image_optimization.disk' => 'r2_public']);
        Storage::fake('r2_public');

        $siteSettings = $this->createMock(SiteSettingsService::class);
        $siteSettings->method('get')->willReturnCallback(function ($key, $default = null) {
            return match ($key) {
                'branding.logo' => 'images/branding/original/logo.png',
                'branding.favicon' => 'images/branding/original/favicon.png',
                default => $default,
            };
        });
        $this->app->instance(SiteSettingsService::class, $siteSettings);

        $service = app(ClinicIdentityService::class);

        $logoUrl = $service->logoUrl();
        $faviconUrl = $service->faviconUrl();

        $this->assertStringContainsString('images/branding/original/logo.png', $logoUrl);
        $this->assertStringContainsString('images/branding/original/favicon.png', $faviconUrl);
        $this->assertStringNotContainsString('/thumb/', $logoUrl);
        $this->assertStringNotContainsString('/medium/', $logoUrl);
        $this->assertStringNotContainsString('/thumb/', $faviconUrl);
        $this->assertStringNotContainsString('/medium/', $faviconUrl);
    }

    public function test_favicon_url_resolves_to_r2_custom_domain(): void
    {
        config([
            'image_optimization.disk' => 'r2_public',
            'filesystems.disks.r2_public.url' => 'https://pub-test.r2.dev',
        ]);
        Storage::fake('r2_public', ['url' => 'https://pub-test.r2.dev']);

        $siteSettings = $this->createMock(SiteSettingsService::class);
        $siteSettings->method('get')->willReturnCallback(function ($key, $default = null) {
            if ($key === 'branding.favicon') {
                return 'images/branding/original/favicon-uuid-123.webp';
            }
            return $default;
        });
        $this->app->instance(SiteSettingsService::class, $siteSettings);

        $service = app(ClinicIdentityService::class);
        $faviconUrl = $service->faviconUrl();

        $this->assertStringStartsWith('https://pub-test.r2.dev/images/branding/original/favicon-uuid-123.webp', $faviconUrl);
        $this->assertStringNotContainsString('192.168.', $faviconUrl);
        $this->assertStringNotContainsString('127.0.0.1', $faviconUrl);
    }
}
