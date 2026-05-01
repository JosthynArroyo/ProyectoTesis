<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountActive
{
    private const LAST_ACTIVITY_SESSION_KEY = 'auth.last_activity_write_at';

    private const LAST_ACTIVITY_WRITE_INTERVAL_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $u = $request->user();

            if (! $u->isBlocked() && ! $u->isSuspended()) {
                $this->touchLastActivityIfDue($request);
            }

            if (! $u->isActive()) {
                Auth::logout();

                // NO invalidate aquí para no perder los flashes
                return redirect(url('/').'?login=1')
                    ->withErrors(['email' => 'Tu cuenta está deshabilitada o suspendida.'])
                    ->with('auth_error', 'Tu cuenta está deshabilitada o suspendida.');
            }
        }

        return $next($request);
    }

    private function touchLastActivityIfDue(Request $request): void
    {
        $session = $request->session();
        $now = now();
        $lastWriteAt = (int) $session->get(self::LAST_ACTIVITY_SESSION_KEY, 0);

        if ($lastWriteAt > 0 && ($now->timestamp - $lastWriteAt) < self::LAST_ACTIVITY_WRITE_INTERVAL_SECONDS) {
            return;
        }

        $request->user()?->forceFill(['last_activity_at' => $now])->saveQuietly();
        $session->put(self::LAST_ACTIVITY_SESSION_KEY, $now->timestamp);
    }
}
