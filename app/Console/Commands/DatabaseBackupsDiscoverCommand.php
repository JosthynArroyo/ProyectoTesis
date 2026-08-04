<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackup\BackupCoordinatorService;
use Illuminate\Console\Command;

class DatabaseBackupsDiscoverCommand extends Command
{
    protected $signature = 'database-backups:discover {--rebuild-catalog : Reconstruir el catálogo de la tabla local desde manifiestos}';
    protected $description = 'Descubrir y listar los manifiestos de respaldos almacenados en Cloudflare R2';

    public function handle(BackupCoordinatorService $coordinator): int
    {
        $this->info("Buscando manifiestos de respaldos en Cloudflare R2...");

        $manifests = $coordinator->discoverFromR2();

        if (empty($manifests)) {
            $this->warn("No se encontraron manifiestos de respaldos válidos en R2.");
            return Command::SUCCESS;
        }

        $rows = array_map(function ($m) {
            return [
                $m['uuid'],
                $m['type'],
                $m['timestamp'],
                round(($m['file_size'] ?? 0) / (1024 * 1024), 2) . ' MB',
                substr($m['sha256'] ?? '', 0, 12) . '...',
                $m['r2_key'],
            ];
        }, $manifests);

        $this->table(
            ['UUID', 'Tipo', 'Fecha (Guayaquil)', 'Tamaño', 'SHA-256 (Corto)', 'Clave R2'],
            $rows
        );

        if ($this->option('rebuild-catalog')) {
            $count = $coordinator->rebuildCatalogFromR2();
            $this->info("Se han sincronizado {$count} registros de manifiestos en la tabla local 'database_backups'.");
        }

        return Command::SUCCESS;
    }
}
