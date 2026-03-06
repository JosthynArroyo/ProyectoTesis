<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (! Auth::guard($guard)->check()) {
                continue;
            }

            $user = $request->user();

            if ($user?->hasRole('superadmin')) {
                return redirect()->route('superadmin.dashboard');
            }

            if ($user?->hasRole('administrador')) {
                return redirect()->route('admin.dashboard');
            }

            if ($user?->hasRole('paciente')) {
                return redirect()->route('paciente.dashboard');
            }

            if ($user?->hasRole('doctor')) {
                return redirect()->route('doctor.dashboard');
            }

            if ($user?->hasRole('laboratorio')) {
                return redirect()->route('laboratorio.dashboard');
            }

            return redirect('/');
        }

        return $next($request);
    }
}
