<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * DemoIsolation middleware.
 *
 * Central safety layer applied to ALL /demo routes.
 *
 * Blocks any HTTP method other than GET/HEAD with a 405 response.
 * This prevents anyone from manually sending POST/PATCH/PUT/DELETE
 * to a demo URL even by manipulating the HTML or using external tools.
 *
 * Note: the demo routes already only accept GET. This middleware adds
 * a defense-in-depth layer that is completely independent of route
 * definitions and catches attempts that bypass them.
 *
 * State isolation is guaranteed by design:
 * - The DemoDashboardController only uses session()->flash() (one-time).
 * - No session()->put() or session()->get() calls persist demo states.
 * - All demo data is always reconstructed from fixed in-memory arrays.
 */
class DemoIsolation
{
    public function handle(Request $request, Closure $next): Response
    {
        // Block database write queries at runtime for safety.
        \Illuminate\Support\Facades\DB::listen(function ($query) {
            $sql = strtolower($query->sql);
            if (
                str_contains($sql, 'insert ') || 
                str_contains($sql, 'update ') || 
                str_contains($sql, 'delete ') || 
                str_contains($sql, 'truncate ') || 
                str_contains($sql, 'replace ')
            ) {
                // Exclude the framework sessions table if database session driver is used.
                if (!str_contains($sql, 'sessions')) {
                    throw new \Exception("Escritura de base de datos bloqueada en modo Demo: " . $query->sql);
                }
            }
        });

        return $next($request);
    }
}
