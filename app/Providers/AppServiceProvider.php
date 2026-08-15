<?php

namespace App\Providers;

use App\Models\Cita;
use App\Models\FeatureAccessRequest;
use App\Observers\CitaObserver;
use App\Observers\FeatureAccessRequestObserver;
use App\Services\ClinicIdentityService;
use App\Services\LandingWelcomeService;
use App\Services\LayoutMetricsService;
use App\Services\SiteSettingsService;
use App\Support\DestructiveDatabaseGuard;
use App\Support\ImageUrl;
use Illuminate\Console\Events\CommandStarting;
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
        $this->app->singleton(SiteSettingsService::class);
        $this->app->singleton(LandingWelcomeService::class);
        $this->app->singleton(ImageUrl::class);
        $this->app->singleton(ClinicIdentityService::class);
        $this->app->singleton(LayoutMetricsService::class);
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

        if ($this->app->runningInConsole()) {
            $this->app['events']->listen(CommandStarting::class, function (CommandStarting $event): void {
                app(DestructiveDatabaseGuard::class)->assertConsoleCommandIsSafe($event->command, $event->input);
            });
        }

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

        if (class_exists(FeatureAccessRequest::class) && class_exists(FeatureAccessRequestObserver::class)) {
            FeatureAccessRequest::observe(FeatureAccessRequestObserver::class);
        }

        $this->registerSharedViewContext();

        View::composer('components.layout.dashboard-header', function ($view): void {
            $view->with(
                'dashboardHeaderMetrics',
                app(LayoutMetricsService::class)->adminNotifications(auth()->user())
            );
        });

        View::composer('components.email.layout', function ($view): void {
            $view->with(
                'emailBranding',
                app(ClinicIdentityService::class)->emailBranding()
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

        RateLimiter::for('captcha.challenge', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('captcha.verify', function (Request $request) {
            $sessionId = $request->session()->getId() ?: $request->ip();
            return Limit::perMinute(10)->by($sessionId);
        });

        RateLimiter::for('chatbot.otp.send', function (Request $request) {
            $email = strtolower((string) $request->input('email', ''));
            $emailHash = hash('sha256', $email);
            return [
                Limit::perMinute(1)->by($request->ip()),
                Limit::perMinutes(10, 3)->by("send_otp:{$emailHash}"),
            ];
        });

        RateLimiter::for('chatbot.otp.verify', function (Request $request) {
            $email = strtolower((string) $request->input('email', ''));
            $emailHash = hash('sha256', $email);
            $sessionId = $request->session()->getId() ?: $request->ip();
            return [
                Limit::perMinutes(10, 5)->by("verify_otp:{$sessionId}"),
                Limit::perMinutes(10, 5)->by("verify_otp:{$emailHash}"),
            ];
        });

        RateLimiter::for('chatbot.message', function (Request $request) {
            $userId = $request->session()->get(\App\Support\ChatbotSessionKeys::SESSION_CHATBOT_USER_ID)
                ?: optional($request->user())->id;
            $key = $userId ? "user:{$userId}" : $request->ip();
            return Limit::perMinute(30)->by($key);
        });

        RateLimiter::for('face-enroll', function (Request $request) {
            $userId = optional($request->user())->id;
            $key = $userId ? "user:{$userId}" : $request->ip();

            return Limit::perMinute(6)->by($key);
        });
    }

    private function registerSharedViewContext(): void
    {
        View::composer([
            'welcome',
            'home',
            'servicios',
            'contacto',
            'layouts.*',
            'partials.*',
            'admin.*',
            'doctor.*',
            'paciente.*',
            'laboratorio.*',
            'superadmin.*',
            'shared.*',
            'components.layout.*',
            'emails.*',
            'pdf.*',
            'citas.*',
            'demo.*',
        ], function ($view): void {
            static $payload = null;

            if ($payload === null) {
                $payload = [
                    'siteSettings' => app(SiteSettingsService::class),
                    'landingWelcome' => app(LandingWelcomeService::class),
                    'imageUrl' => app(ImageUrl::class),
                    'clinicIdentity' => app(ClinicIdentityService::class),
                ];
            }

            $view->with($payload);
        });
    }
}
