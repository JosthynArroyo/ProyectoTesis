<?php

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\DemoIsolation;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureFeatureAccess;
use App\Http\Middleware\EnsureNoPendingPaymentsForBooking;
use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\PreventBackHistory;
use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Http\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
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
        $schedule->command('users:deactivate-inactive')->dailyAt('02:30')->withoutOverlapping();
        $schedule->command('citas:marcar-no-show')->everyTenMinutes()->withoutOverlapping();
        $schedule->command('citas:sync-recordatorios')->everyTenMinutes()->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', [
            PreventRequestsDuringMaintenance::class,
            PreventBackHistory::class,
            EnsureAccountActive::class,
            \App\Http\Middleware\CheckMustChangePassword::class,
        ]);
        $middleware->alias([
            'auth' => Authenticate::class,
            'guest' => RedirectIfAuthenticated::class,
            'role' => EnsureUserRole::class,
            'feature' => EnsureFeatureAccess::class,
            'no_pending_payments' => EnsureNoPendingPaymentsForBooking::class,
            'captcha_verified' => \App\Http\Middleware\EnsureCaptchaVerified::class,
            'chatbot_identity' => \App\Http\Middleware\EnsureChatbotIdentityVerified::class,
            // Aislamiento global de todas las rutas /demo.
            'demo.isolation' => DemoIsolation::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (PostTooLargeException $exception, Request $request) {
            if ($request->is('superadmin/personalizacion/servicios')) {
                return response()->view('errors.413', [], 413);
            }

            return null;
        });
    })->create();
