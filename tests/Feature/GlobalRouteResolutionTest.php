<?php

namespace Tests\Feature;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

class GlobalRouteResolutionTest extends TestCase
{
    /**
     * Valida que todas las rutas registradas en la aplicación apunten a controladores
     * y métodos existentes, previniendo errores de reflexión y HTTP 500 predespliegue.
     */
    public function test_all_registered_controller_routes_resolve_to_existing_classes_and_methods(): void
    {
        $routes = RouteFacade::getRoutes()->getRoutes();
        $this->assertNotEmpty($routes, 'La colección de rutas no debe estar vacía.');

        $evaluatedRoutesCount = 0;

        foreach ($routes as $route) {
            /** @var Route $route */
            $uri = $route->uri();
            $name = $route->getName() ?? '(sin nombre)';
            $methods = implode('|', $route->methods());
            $action = $route->getAction();

            // Ignorar closures anónimos
            if (isset($action['uses']) && $action['uses'] instanceof \Closure) {
                continue;
            }

            $controllerClass = $route->getControllerClass();

            // Si la ruta no usa un controlador de clase, continuar
            if (!$controllerClass) {
                continue;
            }

            // Validar que la clase del controlador exista
            $this->assertTrue(
                class_exists($controllerClass),
                "Error en ruta [{$methods} {$uri}] (Nombre: {$name}): La clase controladora [{$controllerClass}] no existe."
            );

            $actionUses = $action['uses'] ?? '';

            if (is_string($actionUses) && str_contains($actionUses, '@')) {
                [, $method] = explode('@', $actionUses);
            } else {
                $method = '__invoke';
            }

            $this->assertTrue(
                method_exists($controllerClass, $method),
                "Error en ruta [{$methods} {$uri}] (Nombre: {$name}): El método [{$method}] no existe en el controlador [{$controllerClass}]."
            );

            $evaluatedRoutesCount++;
        }

        $this->assertGreaterThan(0, $evaluatedRoutesCount, 'Se debe haber evaluado al menos una ruta de controlador.');
    }

    /**
     * Valida explícitamente la ausencia de referencias a controladores obsoletos eliminados.
     */
    public function test_no_routes_reference_obsolete_laboratory_catalog_controller(): void
    {
        $routes = RouteFacade::getRoutes()->getRoutes();

        foreach ($routes as $route) {
            $actionName = $route->getActionName();
            $uri = $route->uri();

            $this->assertStringNotContainsString(
                'CatalogoLaboratorioController',
                $actionName,
                "Se detectó una referencia residual a CatalogoLaboratorioController en la ruta [{$uri}]."
            );
        }
    }
}
