<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        if (! $this->app) {
            $this->refreshApplication();
        }

        config([
            'app.env' => 'testing',
            'database.default' => 'mysql',
            'database.connections.mysql.database' => 'clinica_donbosco_db_test',
            'database.connections.mysql.foreign_key_constraints' => false,
        ]);

        $env = (string) (config('app.env') ?: env('APP_ENV'));
        $configuredDb = (string) (config('database.connections.mysql.database') ?: env('DB_DATABASE'));

        if ($env !== 'testing' || $configuredDb !== 'clinica_donbosco_db_test' || $configuredDb === 'clinica_donbosco_db') {
            throw new \RuntimeException(
                "ABORTANDO TESTS: Entorno ($env) o DB configurada ($configuredDb) no permitida. Las pruebas SOLO se ejecutan en 'clinica_donbosco_db_test'."
            );
        }

        try {
            $effectiveDb = DB::connection()->getDatabaseName();
        } catch (\Throwable $e) {
            $effectiveDb = null;
        }

        if ($effectiveDb !== 'clinica_donbosco_db_test' || $effectiveDb === 'clinica_donbosco_db') {
            throw new \RuntimeException(
                "ABORTANDO TESTS: La base efectiva detectada en runtime es '".($effectiveDb ?? 'NULL')."' y NO 'clinica_donbosco_db_test'. Interrumpiendo ejecución inmediatamente."
            );
        }

        DB::listen(function ($query) {
            try {
                $currentDb = DB::connection()->getDatabaseName();
                if ($currentDb !== 'clinica_donbosco_db_test' || $currentDb === 'clinica_donbosco_db') {
                    throw new \RuntimeException(
                        "ABORTANDO QUERY DE TEST: Intento de consulta contra la base '{$currentDb}'. Las pruebas tienen PROHIBIDO tocar 'clinica_donbosco_db'."
                    );
                }
            } catch (\RuntimeException $re) {
                throw $re;
            } catch (\Throwable $e) {
                // Ignore query logging connection errors
            }
        });

        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }
}
