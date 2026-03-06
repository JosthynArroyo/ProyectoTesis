<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Blade;
use App\Models\Cita;
use App\Observers\CitaObserver;
use Illuminate\Support\Facades\View;
use App\Services\SiteSettingsService;
use App\Services\LandingWelcomeService;
use App\Support\ImageUrl;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Blade::precompiler(function ($value) {
            static $property = null;
            if (! $property) {
                $compiler = app('blade.compiler');
                $property = new \ReflectionProperty($compiler, 'forElseCounter');
                $property->setAccessible(true);
            }
            $property->setValue(app('blade.compiler'), 0);

            return $value;
        });

        if (! $this->app->runningInConsole()) {
            $host = $this->app['request']->getSchemeAndHttpHost();
            config(['app.url' => $host]);
            URL::forceRootUrl($host);

            if (str_contains($host, 'ngrok-free.app')) {
                URL::forceScheme('https');
            }
        }

        if (class_exists(Cita::class) && class_exists(CitaObserver::class)) {
            Cita::observe(CitaObserver::class);
        }

        View::share('siteSettings', app(SiteSettingsService::class));
        View::share('landingWelcome', app(LandingWelcomeService::class));
        View::share('imageUrl', app(ImageUrl::class));

        RateLimiter::for('contacto', function (Request $request) {
            $email = mb_strtolower((string) $request->input('email', 'anon'));
            return Limit::perMinute(5)->by($request->ip().'|'.$email);
        });

        RateLimiter::for('chatbot', function (Request $request) {
            $userId = optional($request->user())->id;
            $key = $userId ? "user:{$userId}" : $request->ip();
            return Limit::perMinute(30)->by($key);
        });

        RateLimiter::for('face-enroll', function (Request $request) {
            $userId = optional($request->user())->id;
            $key = $userId ? "user:{$userId}" : $request->ip();
            return Limit::perMinute(6)->by($key);
        });
    }
}
