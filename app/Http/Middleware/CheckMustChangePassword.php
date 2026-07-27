<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckMustChangePassword
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->must_change_password) {
                // Allow access only to the must-change-password page, its update endpoint, and logout
                $allowedRoutes = [
                    'auth.must-change-password',
                    'auth.must-change-password.update',
                    'logout',
                ];

                if (!$request->routeIs($allowedRoutes)) {
                    return redirect()->route('auth.must-change-password');
                }
            }
        }

        return $next($request);
    }
}
