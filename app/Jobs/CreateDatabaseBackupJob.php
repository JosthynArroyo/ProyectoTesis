<?php

namespace App\Jobs;

use App\Models\DatabaseBackup;
use App\Services\DatabaseBackup\BackupCoordinatorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CreateDatabaseBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries = 1;

    public function __construct(
        public string $type = DatabaseBackup::TYPE_MANUAL,
        public ?int $userId = null
    ) {
        $this->onConnection((string) config('database_backups.queue_connection', 'database_backups'));
        $this->onQueue((string) config('database_backups.queue', 'backups'));
    }

    public function handle(BackupCoordinatorService $coordinator): void
    {
        $lockKey = 'lock:database_backup_running';
        // Lock TTL must be strictly greater than Job timeout (2100 seconds > 1800 seconds)
        $lock = Cache::lock($lockKey, 2100);

        if (! $lock->get()) {
            Log::warning("CreateDatabaseBackupJob OMITIDO: Ya existe un proceso de respaldo ejecutándose en segundo plano.");
            return;
        }

        try {
            $coordinator->performBackup($this->type, $this->userId);
        } finally {
            optional($lock)->release();
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("CreateDatabaseBackupJob FALLÓ: " . $exception->getMessage(), [
            'type' => $this->type,
            'user_id' => $this->userId,
        ]);
    }
}
