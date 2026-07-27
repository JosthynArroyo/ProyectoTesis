<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        if (! $this->app) {
            $this->refreshApplication();
        }

        $env = config('app.env') ?: env('APP_ENV');
        $db = config('database.connections.mysql.database') ?: env('DB_DATABASE');

        if ($env !== 'testing' || $db !== 'clinica_donbosco_db_test' || $db === 'clinica_donbosco_db') {
            throw new \RuntimeException("PROTECCIÓN BD: Ejecución detenida. Entorno incorrecto ($env) o base de datos no permitida ($db). Las pruebas sólo se ejecutan en 'clinica_donbosco_db_test'.");
        }

        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }
}
