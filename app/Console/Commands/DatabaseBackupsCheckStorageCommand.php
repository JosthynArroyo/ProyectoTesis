<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackup\BackupStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class DatabaseBackupsCheckStorageCommand extends Command
{
    protected $signature = 'database-backups:check-storage';
    protected $description = 'Verificar la conectividad y capacidad de lectura/escritura en el bucket R2 de respaldos';

    public function handle(BackupStorageService $storageService): int
    {
        $uuid = (string) Str::uuid();
        $testKey = "database/connectivity-checks/{$uuid}.txt";
        $testContent = "R2_CONNECTIVITY_TEST_" . time();

        $this->info("Iniciando prueba de conectividad con R2 [r2_backups]...");

        try {
            // 1. Upload test content
            $disk = \Illuminate\Support\Facades\Storage::disk(config('database_backups.disk', 'r2_backups'));

            if (! $disk->put($testKey, $testContent)) {
                $this->error("CONECTIVIDAD FALLIDA: No se pudo escribir el objeto de prueba en R2.");
                return Command::FAILURE;
            }

            // 2. Check existence
            if (! $disk->exists($testKey)) {
                $this->error("CONECTIVIDAD FALLIDA: El objeto escrito no reportó existencia en R2.");
                return Command::FAILURE;
            }

            // 3. Read and compare content
            $readContent = $disk->get($testKey);
            if ($readContent !== $testContent) {
                $this->error("CONECTIVIDAD FALLIDA: El contenido leído no coincide con el contenido escrito.");
                return Command::FAILURE;
            }

            // 4. Delete test object
            if (! $disk->delete($testKey)) {
                $this->error("ADVERTENCIA: No se pudo eliminar el objeto de prueba en R2.");
                return Command::FAILURE;
            }

            // 5. Confirm deletion
            if ($disk->exists($testKey)) {
                $this->error("CONECTIVIDAD FALLIDA: El objeto de prueba sigue existiendo tras la orden de borrado.");
                return Command::FAILURE;
            }

            $this->info("CONECTIVIDAD EXITOSA: Lectura, escritura, verificación y borrado en R2 comprobados correctamente.");
            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $this->error("ERROR DE ALMACENAMIENTO R2: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
