<?php

namespace App\Http\Middleware;

use App\Services\MaintenanceAccessService;
use App\Services\SiteSettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventRequestsDuringMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        $settings = app(SiteSettingsService::class);
        $maintenance = $settings->maintenanceSnapshot();

        if (! ($maintenance['enabled'] ?? false)) {
            return $next($request);
        }

        if ($this->isMaintenanceBypassRequest($request)) {
            return $next($request);
        }

        $user = $request->user();
        if ($user && $user->hasRole('superadmin')) {
            return $next($request);
        }

        if (app(MaintenanceAccessService::class)->requestIpIsAllowlisted($request)) {
            return $next($request);
        }

        $message = $maintenance['message'] ?? $settings->get('maintenance.message');
        $until = $maintenance['until'] ?? $settings->get('maintenance.until');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 503);
        }

        return response()->view('maintenance', [
            'message' => $message,
            'until' => $until,
        ], 503);
    }

    private function isMaintenanceBypassRequest(Request $request): bool
    {
        if ($request->is('superadmin') || $request->is('superadmin/*')) {
            return true;
        }

        if ($request->isMethod('GET') && $request->is('login')) {
            return true;
        }

        if ($request->isMethod('POST') && $request->is('login')) {
            return true;
        }

        if ($request->isMethod('POST') && $request->is('face/login')) {
            return true;
        }

        if ($request->isMethod('POST') && $request->is('salir')) {
            return true;
        }

        return false;
    }
}
