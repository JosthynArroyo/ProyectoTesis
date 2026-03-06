<?php

namespace App\Http\Middleware;

use App\Services\PagoService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNoPendingPaymentsForBooking
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user || !$user->hasRole('paciente')) {
            return $next($request);
        }

        $pagoService = app(PagoService::class);
        if (!$pagoService->pacienteTieneBloqueo($user->id)) {
            return $next($request);
        }

        $message = PagoService::MENSAJE_BLOQUEO;
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'message' => $message,
            ], 423);
        }

        if ($request->routeIs('paciente.pagos.*')) {
            return $next($request);
        }

        return redirect()
            ->route('paciente.pagos.index')
            ->withErrors(['error' => $message]);
    }
}
