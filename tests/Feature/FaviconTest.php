<?php

namespace Tests\Feature;

use App\Services\ClinicIdentityService;
use App\Services\SiteSettingsService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FaviconTest extends TestCase
{
    public function test_unauthenticated_visitor_can_request_favicon_ico(): void
    {
        $response = $this->get('/favicon.ico');

        $response->assertStatus(200);
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $response->assertHeader('Pragma', 'no-cache');
        $this->assertNotNull($response->headers->get('Expires'));
    }

    public function test_favicon_ico_redirects_to_r2_public_url_when_custom_favicon_exists(): void
    {
        config([
            'image_optimization.disk' => 'r2_public',
            'filesystems.disks.r2_public.url' => 'https://pub-test.r2.dev',
        ]);
        Storage::fake('r2_public', ['url' => 'https://pub-test.r2.dev']);

        $siteSettings = $this->createMock(SiteSettingsService::class);
        $siteSettings->method('get')->willReturnCallback(function ($key, $default = null) {
            if ($key === 'branding.favicon') {
                return 'images/branding/original/favicon-custom-uuid-999.png';
            }
            return $default;
        });
        $this->app->instance(SiteSettingsService::class, $siteSettings);

        $response = $this->get('/favicon.ico');

        $response->assertStatus(302);
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $response->assertHeader('Pragma', 'no-cache');
        $this->assertNotNull($response->headers->get('Expires'));

        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringStartsWith('https://pub-test.r2.dev/images/branding/original/favicon-custom-uuid-999.png', $location);
        $this->assertStringNotContainsString('/storage/', $location);
        $this->assertStringNotContainsString('favicon.ico', $location);
    }

    public function test_favicon_ico_returns_fallback_200_when_no_custom_favicon_configured(): void
    {
        $siteSettings = $this->createMock(SiteSettingsService::class);
        $siteSettings->method('get')->willReturn(null);
        $this->app->instance(SiteSettingsService::class, $siteSettings);

        $response = $this->get('/favicon.ico');

        $response->assertStatus(200);
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $response->assertHeader('Pragma', 'no-cache');
        $this->assertNotNull($response->headers->get('Expires'));
        $this->assertStringContainsString('image/', (string) $response->headers->get('Content-Type'));
    }
}
