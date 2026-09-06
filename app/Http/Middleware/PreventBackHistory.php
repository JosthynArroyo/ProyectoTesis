<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventBackHistory
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldPreventBackHistory($request)) {
            $response->headers->set('Cache-Control', 'no-cache, no-store, max-age=0, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', 'Sat, 01 Jan 1990 00:00:00 GMT');
        }

        return $response;
    }

    private function shouldPreventBackHistory(Request $request): bool
    {
        // 1. Si el usuario está autenticado, la respuesta contiene datos de sesión/privados
        if ($request->user() !== null) {
            return true;
        }

        // 2. Si la ruta explícitamente requiere autenticación
        $route = $request->route();
        if ($route !== null) {
            $middleware = $route->gatherMiddleware();
            if (in_array('auth', $middleware, true) || in_array(Authenticate::class, $middleware, true)) {
                return true;
            }
        }

        // 3. Peticiones de cierre de sesión o transiciones de autenticación
        if ($request->is('salir') || $request->is('logout') || ($route !== null && $route->named('salir', 'logout'))) {
            return true;
        }

        return false;
    }
}
