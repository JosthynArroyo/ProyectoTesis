<?php

namespace App\Providers;

use App\Models\Cita;
use App\Observers\CitaObserver;
use App\Services\LandingWelcomeService;
use App\Services\LayoutMetricsService;
use App\Services\SiteSettingsService;
use App\Support\ImageUrl;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Vite as ViteManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
            $request = $this->app['request'];
            $host = $request->getSchemeAndHttpHost();
            $requestHost = $request->getHost();
            config(['app.url' => $host]);
            URL::forceRootUrl($host);

            $usesNgrokTunnel = str_contains($requestHost, 'ngrok-free.app') || str_contains($requestHost, 'ngrok.app');

            if ($usesNgrokTunnel) {
                URL::forceScheme('https');
            }

            // Tunnelled clients cannot access the local Vite HMR server.
            app(ViteManager::class)->useHotFile(
                $usesNgrokTunnel
                    ? storage_path('framework/vite.hot.disabled')
                    : public_path('hot')
            );
        }

        if (class_exists(Cita::class) && class_exists(CitaObserver::class)) {
            Cita::observe(CitaObserver::class);
        }

        View::share('siteSettings', app(SiteSettingsService::class));
        View::share('landingWelcome', app(LandingWelcomeService::class));
        View::share('imageUrl', app(ImageUrl::class));

        View::composer('components.layout.dashboard-header', function ($view): void {
            $view->with(
                'dashboardHeaderMetrics',
                app(LayoutMetricsService::class)->adminNotifications(auth()->user())
            );
        });

        View::composer('components.email.layout', function ($view): void {
            $view->with(
                'emailBranding',
                app(SiteSettingsService::class)->emailBranding()
            );
        });

        View::composer(['layouts.admin', 'admin.partials.sidebar'], function ($view): void {
            $view->with(
                'adminLayoutMetrics',
                app(LayoutMetricsService::class)->adminSidebar(auth()->user())
            );
        });

        View::composer('superadmin.partials.sidebar', function ($view): void {
            $view->with(
                'pendingPersonalizacion',
                app(LayoutMetricsService::class)->pendingPersonalizacion(auth()->user())
            );
        });

        View::composer('paciente.partials.sidebar', function ($view): void {
            $view->with(
                'bloqueoPagosPendientes',
                app(LayoutMetricsService::class)->patientHasPaymentBlock(auth()->user())
            );
        });

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
