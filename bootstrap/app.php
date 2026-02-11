<?php

use Illuminate\Foundation\Application;
use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\EnsureFeatureAccess;
use App\Http\Middleware\PreventBackHistory;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance as FrameworkMaintenance;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->replace(FrameworkMaintenance::class, PreventRequestsDuringMaintenance::class);
        $middleware->appendToGroup('web', [
            PreventBackHistory::class,
            EnsureAccountActive::class,
        ]);
        $middleware->alias([
            'role'=>EnsureUserRole::class,
            'feature' => EnsureFeatureAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
