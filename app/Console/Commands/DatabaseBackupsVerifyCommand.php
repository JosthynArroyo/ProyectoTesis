<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackup\BackupCoordinatorService;
use Illuminate\Console\Command;

class DatabaseBackupsVerifyCommand extends Command
{
    protected $signature = 'database-backups:verify {uuid : UUID del respaldo a verificar}';
    protected $description = 'Verificar la integridad del archivo cifrado y el contenido SQL de un respaldo';

    public function handle(BackupCoordinatorService $coordinator): int
    {
        $uuid = $this->argument('uuid');
        $this->info("Iniciando verificación de integridad para el respaldo UUID [{$uuid}]...");

        try {
            $backup = $coordinator->verifyBackup($uuid);
            $this->info("VERIFICACIÓN EXITOSA: El respaldo [{$uuid}] es válido. Hash SHA-256 y descifrado comprobados.");
            $this->line("Tipo: {$backup->type}");
            $this->line("Tamaño: {$backup->formattedSize()}");
            $this->line("Fecha de verificación: {$backup->last_verified_at}");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("VERIFICACIÓN FALLIDA: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
