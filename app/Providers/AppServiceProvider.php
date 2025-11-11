<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use App\Models\Cita;
use App\Observers\CitaObserver;

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
        if (str_contains(config('app.url'), 'ngrok-free.app')) {
            URL::forceScheme('https');
        }

        if (class_exists(Cita::class) && class_exists(CitaObserver::class)) {
            Cita::observe(CitaObserver::class);
        }

        RateLimiter::for('contacto', function (Request $request) {
            $email = mb_strtolower((string) $request->input('email', 'anon'));
            return Limit::perMinute(5)->by($request->ip().'|'.$email);
        });
    }
}
