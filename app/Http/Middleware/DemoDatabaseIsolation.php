<?php

namespace App\Http\Middleware;

use App\Services\ApplicationModeService;
use App\Services\DemoExternalEffectsGuard;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class DemoDatabaseIsolation
{
    public function __construct(
        private readonly ApplicationModeService $applicationMode,
        private readonly DemoExternalEffectsGuard $externalEffectsGuard,
    ) {}

    /**
     * Handle an incoming request.
     *
     * In demo mode (APP_MODE=demo), activates external effects guards,
     * wraps the request lifecycle in a database transaction, and automatically
     * rolls back all database mutations upon completion.
     * In production mode (APP_MODE=production), passes through transparently.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->applicationMode->isDemo()) {
            return $next($request);
        }

        $this->externalEffectsGuard->apply();

        $startingLevel = DB::transactionLevel();
        DB::beginTransaction();

        try {
            return $next($request);
        } finally {
            while (DB::transactionLevel() > $startingLevel) {
                DB::rollBack();
            }
        }
    }
}
