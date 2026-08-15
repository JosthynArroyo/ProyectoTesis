<?php

namespace Tests\Unit;

use App\Support\DestructiveDatabaseGuard;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;

class DestructiveDatabaseGuardTest extends TestCase
{
    public function test_allows_only_the_explicit_test_database_for_current_process(): void
    {
        $guard = $this->makeGuard('clinica_donbosco_db_test');

        $guard->assertCurrentDatabaseIsAllowed('unit test');

        $this->addToAssertionCount(1);
    }

    public function test_rejects_manual_database_for_current_process(): void
    {
        $guard = $this->makeGuard('clinica_donbosco_db');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("clinica_donbosco_db_test");

        $guard->assertCurrentDatabaseIsAllowed('unit test');
    }

    public function test_rejects_manual_target_database_for_restores(): void
    {
        $guard = $this->makeGuard('clinica_donbosco_db_test');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('denylist absoluta');

        $guard->assertTargetDatabaseIsAllowed('clinica_donbosco_db', 'unit test');
    }

    public function test_rejects_restore_command_when_target_is_not_a_test_database(): void
    {
        $guard = $this->makeGuard('clinica_donbosco_db_test');
        $input = $this->createMock(InputInterface::class);
        $input->method('getParameterOption')->willReturn('clinica_donbosco_restore');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("terminen en '_test'");

        $guard->assertConsoleCommandIsSafe('database-backups:restore', $input);
    }

    public function test_rejects_migrate_fresh_when_process_database_is_manual(): void
    {
        $guard = $this->makeGuard('clinica_donbosco_db');
        $input = $this->createMock(InputInterface::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("clinica_donbosco_db_test");

        $guard->assertConsoleCommandIsSafe('migrate:fresh', $input);
    }

    #[DataProvider('destructiveRollbackCommands')]
    public function test_rejects_destructive_rollback_commands_when_process_database_is_manual(string $command): void
    {
        $guard = $this->makeGuard('clinica_donbosco_db');
        $input = $this->createMock(InputInterface::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("clinica_donbosco_db_test");

        $guard->assertConsoleCommandIsSafe($command, $input);
    }

    public static function destructiveRollbackCommands(): array
    {
        return [
            'migrate reset' => ['migrate:reset'],
            'migrate rollback' => ['migrate:rollback'],
        ];
    }

    private function makeGuard(string $databaseName): DestructiveDatabaseGuard
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('selectOne')->willReturn((object) ['db' => $databaseName]);

        $databaseManager = $this->createMock(DatabaseManager::class);
        $databaseManager->method('connection')->willReturn($connection);

        return new DestructiveDatabaseGuard($databaseManager);
    }
}
