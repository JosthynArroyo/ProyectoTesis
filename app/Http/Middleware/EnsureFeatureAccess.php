<?php

namespace App\Http\Middleware;

use App\Services\FeatureAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureAccess
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect('/login');
        }

        $service = app(FeatureAccessService::class);
        if ($service->hasAccess($user, $feature)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Acceso denegado.');
        }

        $redirect = redirect()
            ->route('admin.dashboard')
            ->with('error', 'Necesitas aprobacion de un superadmin para acceder a esta seccion.');

        if ($feature === 'personalizacion') {
            $redirect->with('open_personalizacion_modal', true);
        }

        return $redirect;
    }
}
