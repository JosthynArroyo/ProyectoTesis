<?php

namespace App\Http\Middleware;

use App\Services\ApplicationModeService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSessionModeIsolation
{
    public function __construct(
        private readonly ApplicationModeService $applicationMode
    ) {}

    /**
     * Handle an incoming request.
     *
     * Ensures authenticated sessions are strictly isolated between application modes (production vs demo).
     * If a session originated from a different mode than the active runtime mode, authentication is revoked.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession()) {
            $currentMode = $this->applicationMode->getMode();
            $session = $request->session();

            if ($session->has('_app_mode')) {
                $sessionMode = (string) $session->get('_app_mode');
                if ($sessionMode !== $currentMode) {
                    if (Auth::check()) {
                        Auth::logout();
                    }
                    $session->invalidate();
                    $session->regenerateToken();
                }
            }

            $session->put('_app_mode', $currentMode);
        }

        return $next($request);
    }
}
