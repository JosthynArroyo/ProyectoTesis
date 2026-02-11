<?php

namespace App\Http\Middleware;

use App\Services\SiteSettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventRequestsDuringMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        $settings = app(SiteSettingsService::class);

        if (! $settings->getBool('maintenance.enabled', false)) {
            return $next($request);
        }

        if ($request->routeIs('login') || $request->routeIs('face.login')) {
            return $next($request);
        }

        $user = $request->user();
        if ($user && $user->hasRole('superadmin')) {
            return $next($request);
        }

        if ($this->isAllowlistedIp($request->ip(), $settings->get('maintenance.allow_ips', ''))) {
            return $next($request);
        }

        $message = $settings->get('maintenance.message');
        $until = $settings->get('maintenance.until');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 503);
        }

        return response()->view('maintenance', [
            'message' => $message,
            'until' => $until,
        ], 503);
    }

    private function isAllowlistedIp(string $ip, string $allowlist): bool
    {
        if (! $ip) {
            return false;
        }

        $items = array_filter(array_map('trim', explode(',', $allowlist)));
        return in_array($ip, $items, true);
    }
}
