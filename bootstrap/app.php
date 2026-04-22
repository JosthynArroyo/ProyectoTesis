<?php

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureFeatureAccess;
use App\Http\Middleware\EnsureNoPendingPaymentsForBooking;
use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\PreventBackHistory;
use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Http\Middleware\RedirectIfAuthenticated;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $reminderHour = str_pad((string) (int) config('services.whatsapp.reminder_previous_day_hour', 12), 2, '0', STR_PAD_LEFT);

        $schedule->command('users:deactivate-inactive')->dailyAt('02:30')->withoutOverlapping();
        $schedule->command('citas:marcar-no-show')->everyTenMinutes()->withoutOverlapping();
        $schedule->command('citas:recordatorio-whatsapp')->dailyAt($reminderHour.':00')->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', [
            PreventRequestsDuringMaintenance::class,
            PreventBackHistory::class,
            EnsureAccountActive::class,
        ]);
        $middleware->alias([
            'auth' => Authenticate::class,
            'guest' => RedirectIfAuthenticated::class,
            'role' => EnsureUserRole::class,
            'feature' => EnsureFeatureAccess::class,
            'no_pending_payments' => EnsureNoPendingPaymentsForBooking::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
