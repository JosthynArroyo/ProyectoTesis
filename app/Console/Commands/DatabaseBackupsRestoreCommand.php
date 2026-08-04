<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackup\BackupCoordinatorService;
use Illuminate\Console\Command;

class DatabaseBackupsRestoreCommand extends Command
{
    protected $signature = 'database-backups:restore {uuid : UUID del respaldo a restaurar} {--target-database= : Nombre de la base de datos destino independiente} {--force : Omitir confirmación interactiva}';
    protected $description = 'Restaurar de forma segura un respaldo en una base de datos secundaria independiente';

    public function handle(BackupCoordinatorService $coordinator): int
    {
        $uuid = (string) $this->argument('uuid');
        $targetDatabase = (string) $this->option('target-database');

        if (empty($targetDatabase)) {
            $this->error("DEBE ESPECIFICAR LA OPCIÓN --target-database CON EL NOMBRE DE LA BASE DE DATOS DESTINO.");
            return Command::FAILURE;
        }

        $activeDb = (string) config('database.connections.' . config('database.default', 'mysql') . '.database');

        if (strtolower($targetDatabase) === strtolower($activeDb)) {
            $this->error("RESTAURACIÓN RECHAZADA: No se permite restaurar directamente sobre la base activa [{$activeDb}].");
            return Command::FAILURE;
        }

        $isTestEnv = (app()->environment() === 'testing') || str_contains(config('database.default'), 'testing');

        if ($isTestEnv && ! str_ends_with(strtolower($targetDatabase), '_test')) {
            $this->error("RESTAURACIÓN RECHAZADA EN TESTING: La base destino debe terminar en '_test'. Base ingresada: [{$targetDatabase}].");
            return Command::FAILURE;
        }

        if (app()->environment('production')) {
            $this->warn("=================== ADVERTENCIA DE PRODUCCIÓN ===================");
            $this->warn("Está a punto de ejecutar una restauración en el entorno de PRODUCCIÓN.");
            $this->warn("Base destino independiente: {$targetDatabase}");
            $this->warn("=================================================================");
        }

        if (! $this->option('force')) {
            $confirmUuid = $this->ask("Para confirmar la restauración en '{$targetDatabase}', escriba exactamente el UUID del respaldo ({$uuid}):");
            if ($confirmUuid !== $uuid) {
                $this->error("Confirmación fallida. El UUID ingresado no coincide.");
                return Command::FAILURE;
            }
        }

        $this->info("Iniciando restauración del respaldo [{$uuid}] en la base de datos [{$targetDatabase}]...");

        try {
            $coordinator->restoreBackup($uuid, $targetDatabase, $isTestEnv);
            $this->info("RESTAURACIÓN COMPLETADA CON ÉXITO sobre la base independiente [{$targetDatabase}].");
            $this->info("La base activa [{$activeDb}] y la configuración .env se mantuvieron intactas.");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("ERROR EN RESTAURACIÓN: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
