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

    public function boot(): void
    {
        // 1. Hook into mail.manager resolving to dynamically set mail.from.name in config
        $this->app->resolving('mail.manager', function ($mailManager) {
            try {
                $clinicName = '';
                if (app()->bound(\App\Services\ClinicIdentityService::class)) {
                    $clinicName = trim(app(\App\Services\ClinicIdentityService::class)->name());
                }

                $invalidNames = ['nombre de la clínica', 'nombre de la clinica', 'clinicadb', 'laravel'];
                if ($clinicName === '' || in_array(mb_strtolower($clinicName), $invalidNames)) {
                    $clinicName = config('mail.from.name');
                    if (!$clinicName || in_array(mb_strtolower(trim($clinicName)), $invalidNames)) {
                        $clinicName = 'Clínica';
                    }
                }
                config(['mail.from.name' => $clinicName]);
            } catch (\Throwable $e) {
                // Fail silently during early boot/console commands
            }
        });

        // 2. Intercept MessageSending to rewrite Symfony message From header (failsafe for already resolved mailers/direct Symfony usage)
        $events = $this->app['events'];
        $events->listen(\Illuminate\Mail\Events\MessageSending::class, function (\Illuminate\Mail\Events\MessageSending $event) {
            try {
                $email = $event->message;
                $froms = $email->getFrom();

                if (! empty($froms)) {
                    $clinicName = '';
                    if (app()->bound(\App\Services\ClinicIdentityService::class)) {
                        $clinicName = trim(app(\App\Services\ClinicIdentityService::class)->name());
                    }

                    $invalidNames = ['nombre de la clínica', 'nombre de la clinica', 'clinicadb', 'laravel'];
                    if ($clinicName === '' || in_array(mb_strtolower($clinicName), $invalidNames)) {
                        $clinicName = config('mail.from.name');
                        if (!$clinicName || in_array(mb_strtolower(trim($clinicName)), $invalidNames)) {
                            $clinicName = 'Clínica';
                        }
                    }

                    $clinicName = str_replace(["\r", "\n"], '', $clinicName);

                    $newFroms = [];
                    foreach ($froms as $address) {
                        $newFroms[] = new \Symfony\Component\Mime\Address($address->getAddress(), $clinicName);
                    }
                    $email->from(...$newFroms);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Error estableciendo remitente dinámico: ' . $e->getMessage());
            }
        });
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
