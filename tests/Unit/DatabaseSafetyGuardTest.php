<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use Tests\TestCase;

class DatabaseSafetyGuardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_safety_guard_ensures_testing_environment_and_test_database(): void
    {
        $this->assertSame('testing', config('app.env'));
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('clinica_donbosco_db_test', config('database.connections.mysql.database'));

        $effectiveDb = DB::connection()->getDatabaseName();
        $this->assertSame('clinica_donbosco_db_test', $effectiveDb);
    }

    public function test_testing_credentials_can_write_test_database_but_cannot_access_manual_database(): void
    {
        $testUser = User::factory()->create([
            'name' => 'Fake Test Isolation User',
            'email' => 'fake_isolation_test_' . uniqid() . '@example.com',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $testUser->id,
            'name' => 'Fake Test Isolation User',
        ]);

        $configManual = config('database.connections.mysql');
        $configManual['database'] = 'clinica_donbosco_db';

        try {
            new PDO(
                "mysql:host={$configManual['host']};port={$configManual['port']};dbname={$configManual['database']}",
                $configManual['username'],
                $configManual['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $exception) {
            $this->assertSame(
                1044,
                (int) ($exception->errorInfo[1] ?? 0),
                'Las credenciales de testing deben ser validas, pero carecer de acceso a la DB manual.'
            );

            return;
        }

        $this->fail('Las credenciales de testing pudieron acceder a clinica_donbosco_db.');
    }
}
