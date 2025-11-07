<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Cita;
use App\Observers\CitaObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (class_exists(Cita::class) && class_exists(CitaObserver::class)) {
            Cita::observe(CitaObserver::class);
        }
    }
}
