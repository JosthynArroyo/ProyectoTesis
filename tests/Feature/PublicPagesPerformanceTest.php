<?php

namespace Tests\Feature;

use App\Services\CaptchaImageSynchronizer;
use App\Services\LandingWelcomeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class PublicPagesPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('image_optimization.disk', 'r2_public');
        Config::set('filesystems.disks.r2_public.url', 'https://pub-r2-test.dev');

        Storage::fake('r2_public', ['url' => 'https://pub-r2-test.dev']);
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_home_and_login_do_not_trigger_captcha_sync(): void
    {
        $captchaSynchronizer = Mockery::mock(CaptchaImageSynchronizer::class);
        $captchaSynchronizer->shouldNotReceive('ensureSynchronized');
        $this->app->instance(CaptchaImageSynchronizer::class, $captchaSynchronizer);

        $this->get('/')->assertOk();
        $this->get('/login')->assertOk();
    }

    public function test_public_pages_continue_to_render_on_repeated_requests(): void
    {
        $captchaSynchronizer = Mockery::mock(CaptchaImageSynchronizer::class);
        $captchaSynchronizer->shouldNotReceive('ensureSynchronized');
        $this->app->instance(CaptchaImageSynchronizer::class, $captchaSynchronizer);

        for ($i = 0; $i < 5; $i++) {
            $this->get('/')->assertOk();
            $this->get('/login')->assertOk();
        }
    }

    public function test_landing_welcome_settings_are_loaded_once_when_the_row_is_missing(): void
    {
        DB::table('landing_welcome_settings')->delete();

        $this->app->forgetInstance(LandingWelcomeService::class);
        $service = $this->app->make(LandingWelcomeService::class);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->assertNotEmpty($service->get('hero_title'));
        $this->assertNotEmpty($service->get('hero_subtitle'));
        $this->assertNotEmpty($service->get('intro_feature_1_title'));

        $landingWelcomeQueries = collect(DB::getQueryLog())
            ->filter(static fn (array $entry): bool => str_contains($entry['query'], 'from `landing_welcome_settings`')
                || str_contains($entry['query'], 'from "landing_welcome_settings"')
                || str_contains($entry['query'], 'from landing_welcome_settings'))
            ->values();

        $this->assertCount(1, $landingWelcomeQueries);
    }
}
