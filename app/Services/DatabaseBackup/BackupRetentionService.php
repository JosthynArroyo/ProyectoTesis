<?php

namespace App\Services\DatabaseBackup;

use App\Models\DatabaseBackup;
use Illuminate\Support\Facades\Log;

class BackupRetentionService
{
    public function __construct(
        private readonly BackupStorageService $storageService
    ) {}

    /**
     * Run retention cleanup for a given backup type.
     */
    public function prune(string $type): void
    {
        if ($type === DatabaseBackup::TYPE_MANUAL) {
            return; // Manual backups are kept forever
        }

        $limits = [
            DatabaseBackup::TYPE_DAILY => (int) config('database_backups.retention.daily', 30),
            DatabaseBackup::TYPE_WEEKLY => (int) config('database_backups.retention.weekly', 12),
            DatabaseBackup::TYPE_MONTHLY => (int) config('database_backups.retention.monthly', 12),
        ];

        $limit = $limits[$type] ?? null;
        if (! $limit || $limit <= 0) {
            return;
        }

        $backups = DatabaseBackup::query()
            ->where('type', $type)
            ->whereIn('status', [DatabaseBackup::STATUS_COMPLETED, DatabaseBackup::STATUS_VERIFIED])
            ->orderBy('created_at', 'desc')
            ->get();

        if ($backups->count() <= $limit) {
            return;
        }

        $toDelete = $backups->slice($limit);

        foreach ($toDelete as $backup) {
            try {
                if ($backup->file_path && str_starts_with($backup->file_path, 'database/')) {
                    $this->storageService->delete($backup->file_path);
                }

                if ($backup->manifest_path && str_starts_with($backup->manifest_path, 'database/')) {
                    $this->storageService->delete($backup->manifest_path);
                }

                $backup->delete();
            } catch (\Throwable $e) {
                Log::warning("Fallo al eliminar respaldo por política de retención: " . $e->getMessage(), [
                    'backup_uuid' => $backup->uuid,
                    'type' => $type,
                ]);
            }
        }
    }
}
