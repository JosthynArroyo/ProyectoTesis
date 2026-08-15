<?php

namespace App\Support;

use Illuminate\Database\DatabaseManager;
use Symfony\Component\Console\Input\InputInterface;
use RuntimeException;

final class DestructiveDatabaseGuard
{
    private const MANUAL_DATABASE = 'clinica_donbosco_db';

    private const TEST_DATABASE = 'clinica_donbosco_db_test';

    private const DESTRUCTIVE_COMMANDS = [
        'db:wipe',
        'database-backups:restore',
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'migrate:rollback',
    ];

    public function __construct(private readonly DatabaseManager $databaseManager)
    {
    }

    public function assertConsoleCommandIsSafe(string $command, InputInterface $input): void
    {
        if (! in_array($command, self::DESTRUCTIVE_COMMANDS, true)) {
            return;
        }

        $this->assertCurrentDatabaseIsAllowed("comando [{$command}]");

        if ($command !== 'database-backups:restore') {
            return;
        }

        $targetDatabase = trim((string) $input->getParameterOption('--target-database', ''));
        if ($targetDatabase !== '') {
            $this->assertTargetDatabaseIsAllowed($targetDatabase, "comando [{$command}]");
        }
    }

    public function assertCurrentDatabaseIsAllowed(string $context): void
    {
        $databaseName = $this->resolveEffectiveDatabaseName();

        if ($databaseName === null || $databaseName === '') {
            throw new RuntimeException("ABORTANDO {$context}: no fue posible demostrar la base efectiva del proceso.");
        }

        if (! $this->isAllowedProcessDatabase($databaseName)) {
            throw new RuntimeException(
                "ABORTANDO {$context}: la base efectiva '{$databaseName}' no es '".self::TEST_DATABASE."'."
            );
        }
    }

    public function assertTargetDatabaseIsAllowed(string $databaseName, string $context): void
    {
        $normalized = $this->normalizeDatabaseName($databaseName);

        if ($normalized === '') {
            throw new RuntimeException("ABORTANDO {$context}: no se proporcionó una base de datos destino válida.");
        }

        if ($normalized === self::MANUAL_DATABASE) {
            throw new RuntimeException(
                "ABORTANDO {$context}: la base '{$databaseName}' está en denylist absoluta."
            );
        }

        if (! $this->isAllowedTargetDatabase($normalized)) {
            throw new RuntimeException(
                "ABORTANDO {$context}: solo se permiten operaciones destructivas sobre bases de pruebas que terminen en '_test'."
            );
        }
    }

    public function resolveEffectiveDatabaseName(): ?string
    {
        try {
            $row = $this->databaseManager->connection()->selectOne('SELECT DATABASE() AS db');

            if (is_object($row) && isset($row->db)) {
                $databaseName = trim((string) $row->db);
                if ($databaseName !== '') {
                    return $databaseName;
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private function isAllowedProcessDatabase(string $databaseName): bool
    {
        return $this->normalizeDatabaseName($databaseName) === self::TEST_DATABASE;
    }

    private function isAllowedTargetDatabase(string $databaseName): bool
    {
        return str_ends_with($databaseName, '_test');
    }

    private function normalizeDatabaseName(string $databaseName): string
    {
        return strtolower(trim($databaseName));
    }
}
