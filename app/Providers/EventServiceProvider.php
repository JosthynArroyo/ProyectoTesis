<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Events\CitaAgendada;
use App\Events\CitaAtendida;
use App\Listeners\NotificarDoctorListener;
use App\Listeners\CrearFacturaBorrador;
use App\Listeners\CrearPagoPendiente;
use App\Listeners\GenerarOrdenCobroAlAtenderCita;
use Illuminate\Auth\Events\Login;
use App\Listeners\UpdateLastLoginAndGuardStatus;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        CitaAgendada::class => [
            NotificarDoctorListener::class,
            CrearFacturaBorrador::class,
            CrearPagoPendiente::class,
        ],
        CitaAtendida::class => [
            GenerarOrdenCobroAlAtenderCita::class,
        ],
        Login::class => [
            UpdateLastLoginAndGuardStatus::class, // ahora solo actualiza last_login_at
        ],
    ];

    public function boot(): void {}
    public function shouldDiscoverEvents(): bool { return false; }
}
