<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Modular route files aggregated in deterministic order.
|
*/

require __DIR__.'/web/public.php';
require __DIR__.'/web/demo.php';
require __DIR__.'/web/admin.php';
require __DIR__.'/web/superadmin.php';
require __DIR__.'/web/paciente.php';
require __DIR__.'/web/doctor.php';
require __DIR__.'/web/laboratorio.php';
require __DIR__.'/web/chatbot.php';

// Asigna nombre a rutas del framework/UI que se registran sin ->name()
foreach (Route::getRoutes() as $route) {
    if ($route->getName() !== null) {
        continue;
    }

    $methods = $route->methods();
    $uri = $route->uri();

    if (in_array('POST', $methods, true) && $uri === 'login') {
        $route->name('auth.login');

        continue;
    }

    if (in_array('POST', $methods, true) && $uri === 'password/confirm') {
        $route->name('auth.password.confirm');

        continue;
    }

    if (in_array('GET', $methods, true) && $uri === 'up') {
        $route->name('system.health');
    }
}
