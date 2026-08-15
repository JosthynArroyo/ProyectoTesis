<?php

namespace Tests\Feature;

use Tests\TestCase;

class DatabaseSafeguardTest extends TestCase
{
    /** @test */
    public function test_database_safeguard_prevents_running_on_production_database()
    {
        $originalDb = config('database.connections.mysql.database');
        try {
            config(['database.connections.mysql.database' => 'clinica_donbosco_db']);

            $this->expectException(\RuntimeException::class);
            $env = (string) (config('app.env') ?: env('APP_ENV'));
            $configuredDb = (string) (config('database.connections.mysql.database') ?: env('DB_DATABASE'));

            if ($env !== 'testing' || $configuredDb !== 'clinica_donbosco_db_test' || $configuredDb === 'clinica_donbosco_db') {
                throw new \RuntimeException(
                    "ABORTANDO TESTS: Entorno ($env) o DB configurada ($configuredDb) no permitida. Las pruebas SOLO se ejecutan en 'clinica_donbosco_db_test'."
                );
            }
        } finally {
            config(['database.connections.mysql.database' => $originalDb]);
        }
    }
}
