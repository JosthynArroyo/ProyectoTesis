<?php

namespace App\Providers;

use App\Events\CitaAgendada;
use App\Events\CitaAtendida;
use App\Listeners\CrearFacturaBorrador;
use App\Listeners\GenerarComprobanteCita;
use App\Listeners\GenerarOrdenCobroAlAtenderCita;
use App\Listeners\NotificarDoctorListener;
use App\Listeners\UpdateLastLoginAndGuardStatus;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        CitaAgendada::class => [
            NotificarDoctorListener::class,
            CrearFacturaBorrador::class,
            GenerarComprobanteCita::class,
        ],
        CitaAtendida::class => [
            GenerarOrdenCobroAlAtenderCita::class,
        ],
        Login::class => [
            UpdateLastLoginAndGuardStatus::class, // ahora solo actualiza last_login_at
        ],
    ];

    public function boot(): void {}

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
