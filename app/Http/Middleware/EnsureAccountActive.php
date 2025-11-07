<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $u = $request->user();

            if (!$u->isBlocked() && !$u->isSuspended()) {
                $u->forceFill(['last_activity_at' => now()])->saveQuietly();
            }

            if (!$u->isActive()) {
                Auth::logout();
                // NO invalidate aquí para no perder los flashes
                return redirect()->route('login')
                    ->withErrors(['email' => 'Tu cuenta está deshabilitada o suspendida.'])
                    ->with('auth_error', 'Tu cuenta está deshabilitada o suspendida.');
            }
        }
        return $next($request);
    }
}
