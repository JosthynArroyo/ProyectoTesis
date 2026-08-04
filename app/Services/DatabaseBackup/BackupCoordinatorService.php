<?php

namespace App\Services\DatabaseBackup;

use App\Models\DatabaseBackup;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class BackupCoordinatorService
{
    public function __construct(
        private readonly MySqlDumpService $dumpService,
        private readonly BackupEncryptionService $encryptionService,
        private readonly BackupIntegrityService $integrityService,
        private readonly BackupStorageService $storageService,
        private readonly BackupManifestService $manifestService,
        private readonly BackupRetentionService $retentionService
    ) {}

    /**
     * Coordinate full database backup execution.
     */
    public function performBackup(string $type = DatabaseBackup::TYPE_MANUAL, ?int $userId = null): DatabaseBackup
    {
        $startTime = microtime(true);
        $uuid = (string) Str::uuid();

        $backup = DatabaseBackup::create([
            'uuid' => $uuid,
            'type' => $type,
            'status' => DatabaseBackup::STATUS_PENDING,
            'disk' => config('database_backups.disk', 'r2_backups'),
            'user_id' => $userId,
            'attempts' => 1,
        ]);

        $tempDir = BackupTempDirectoryManager::getTempDir($uuid);
        $rawSqlPath = $tempDir . DIRECTORY_SEPARATOR . 'dump.sql';
        $zipPath = $tempDir . DIRECTORY_SEPARATOR . 'backup.zip';
        $uploadedBackupKey = null;

        try {
            $backup->update([
                'status' => DatabaseBackup::STATUS_PROCESSING,
                'started_at' => now(),
            ]);

            // 1. Check encryption mechanism availability
            if (! $this->encryptionService->isAvailable()) {
                throw new RuntimeException("Mecanismo de cifrado AES-256 no disponible o falta BACKUP_ARCHIVE_PASSWORD.");
            }

            // 2. Dump MySQL database safely using temp cnf options file
            $connection = config('database.default', 'mysql');
            $dbName = config("database.connections.{$connection}.database");
            $this->dumpService->dump($rawSqlPath, $connection);

            // 3. Compress and Encrypt SQL file
            $this->encryptionService->encrypt($rawSqlPath, $zipPath);

            // 4. Validate encrypted zip package locally
            $this->integrityService->verifyLocalPackage($zipPath);

            // 5. Compute SHA-256
            $sha256 = $this->integrityService->computeSha256($zipPath);
            $fileSize = filesize($zipPath);

            // 6. Generate R2 Storage Keys
            $now = Carbon::now('America/Guayaquil');
            $category = ($type === DatabaseBackup::TYPE_MANUAL) ? 'manual' : "automatic/{$type}";
            $r2Key = sprintf('database/%s/%s/%s/backup-%s-%s.zip',
                $category,
                $now->format('Y'),
                $now->format('m'),
                $now->format('Ymd-His'),
                $uuid
            );
            $r2ManifestKey = $this->manifestService->getManifestKeyForBackupKey($r2Key);

            // 7. Upload Encrypted Archive to R2
            $this->storageService->uploadBackup($zipPath, $r2Key);
            $uploadedBackupKey = $r2Key;

            // Confirm Zip Upload
            if (! $this->storageService->exists($r2Key) || $this->storageService->size($r2Key) === 0) {
                throw new RuntimeException("Fallo al confirmar la subida del paquete cifrado a R2.");
            }

            // 8. Generate & Upload Manifest JSON
            $durationSeconds = microtime(true) - $startTime;
            $manifestData = $this->manifestService->generateManifest(
                $uuid,
                $type,
                DatabaseBackup::STATUS_VERIFIED,
                (string) $dbName,
                $fileSize,
                $sha256,
                $durationSeconds,
                $r2Key
            );

            try {
                $this->storageService->uploadManifest($manifestData, $r2ManifestKey);
                if (! $this->storageService->exists($r2ManifestKey)) {
                    throw new RuntimeException("Fallo al confirmar la subida del manifiesto a R2.");
                }
            } catch (\Throwable $manifestException) {
                // Clean up orphaned zip object if manifest upload failed
                if ($uploadedBackupKey) {
                    $this->storageService->delete($uploadedBackupKey);
                }
                throw $manifestException;
            }

            // 9. Update Database Record
            $backup->update([
                'status' => DatabaseBackup::STATUS_VERIFIED,
                'file_path' => $r2Key,
                'manifest_path' => $r2ManifestKey,
                'file_size' => $fileSize,
                'sha256' => $sha256,
                'completed_at' => now(),
                'duration_seconds' => round($durationSeconds, 2),
                'last_verified_at' => now(),
                'error_message' => null,
            ]);

            // 10. Run Retention Policy
            $this->retentionService->prune($type);

            return $backup->fresh();

        } catch (\Throwable $e) {
            $sanitizedError = $this->dumpService->sanitizeLogOutput($e->getMessage());

            $backup->update([
                'status' => DatabaseBackup::STATUS_FAILED,
                'error_message' => $sanitizedError,
                'completed_at' => now(),
                'duration_seconds' => round(microtime(true) - $startTime, 2),
            ]);

            Log::error("ERROR RESPALDO BASE DE DATOS: " . $sanitizedError, [
                'uuid' => $uuid,
                'type' => $type,
            ]);

            $this->notifySuperadminsOfFailure($backup, $sanitizedError);

            throw new RuntimeException("Error en respaldo de base de datos: " . $sanitizedError, 0, $e);

        } finally {
            // Guarantee OS temp directory and raw SQL/ZIP cleanup
            BackupTempDirectoryManager::deleteTempDir($tempDir);
        }
    }

    /**
     * Verify an existing backup by UUID.
     */
    public function verifyBackup(string $uuid): DatabaseBackup
    {
        $backup = DatabaseBackup::where('uuid', $uuid)->first();
        if (! $backup) {
            throw new RuntimeException("No se encontró el registro de respaldo con UUID [{$uuid}].");
        }

        if (! $backup->file_path) {
            throw new RuntimeException("El registro de respaldo no contiene la ruta de archivo en R2.");
        }

        $tempDir = BackupTempDirectoryManager::getTempDir('verify_' . $uuid);
        $tempZipPath = $tempDir . DIRECTORY_SEPARATOR . 'downloaded.zip';

        try {
            $this->storageService->download($backup->file_path, $tempZipPath);
            $downloadedSize = filesize($tempZipPath);

            if ($backup->file_size && $downloadedSize !== (int) $backup->file_size) {
                throw new RuntimeException("El tamaño del archivo descargado ({$downloadedSize} B) no coincide con el registrado ({$backup->file_size} B).");
            }

            $this->integrityService->verifyLocalPackage($tempZipPath, $backup->sha256);

            $backup->update([
                'status' => DatabaseBackup::STATUS_VERIFIED,
                'last_verified_at' => now(),
            ]);

            return $backup->fresh();

        } finally {
            BackupTempDirectoryManager::deleteTempDir($tempDir);
        }
    }

    /**
     * Discover backups from R2 manifests.
     */
    public function discoverFromR2(): array
    {
        $manifests = $this->storageService->listManifests();
        $discovered = [];

        foreach ($manifests as $manifest) {
            if ($this->manifestService->validateManifest($manifest)) {
                $discovered[] = $manifest;
            }
        }

        return $discovered;
    }

    /**
     * Rebuild local database catalog from R2 manifests without restoring DB data.
     */
    public function rebuildCatalogFromR2(): int
    {
        $manifests = $this->discoverFromR2();
        $count = 0;

        foreach ($manifests as $m) {
            $exists = DatabaseBackup::where('uuid', $m['uuid'])->exists();
            if (! $exists) {
                DatabaseBackup::create([
                    'uuid' => $m['uuid'],
                    'type' => $m['type'],
                    'status' => $m['status'],
                    'disk' => config('database_backups.disk', 'r2_backups'),
                    'file_path' => $m['r2_key'],
                    'manifest_path' => $this->manifestService->getManifestKeyForBackupKey($m['r2_key']),
                    'file_size' => $m['file_size'],
                    'sha256' => $m['sha256'],
                    'completed_at' => Carbon::parse($m['timestamp']),
                    'duration_seconds' => $m['duration_seconds'] ?? null,
                    'last_verified_at' => now(),
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Safely restore a backup into a target database.
     */
    public function restoreBackup(string $uuid, string $targetDatabase, bool $isTestEnvironment = false): void
    {
        $activeDb = (string) config('database.connections.' . config('database.default', 'mysql') . '.database');

        if (strtolower($targetDatabase) === strtolower($activeDb)) {
            throw new RuntimeException("RESTAURACIÓN RECHAZADA: Está prohibido restaurar directamente sobre la base activa [{$activeDb}].");
        }

        if ($isTestEnvironment && ! str_ends_with(strtolower($targetDatabase), '_test')) {
            throw new RuntimeException("RESTAURACIÓN RECHAZADA: Durante pruebas, el destino debe terminar en '_test'. Base especificada: [{$targetDatabase}].");
        }

        $backup = DatabaseBackup::where('uuid', $uuid)->first();
        $r2Key = $backup ? $backup->file_path : null;

        if (! $r2Key) {
            $manifests = $this->discoverFromR2();
            foreach ($manifests as $m) {
                if ($m['uuid'] === $uuid) {
                    $r2Key = $m['r2_key'];
                    break;
                }
            }
        }

        if (! $r2Key) {
            throw new RuntimeException("No se encontró la clave R2 para el respaldo con UUID [{$uuid}].");
        }

        $tempDir = BackupTempDirectoryManager::getTempDir('restore_' . $uuid);
        $tempZipPath = $tempDir . DIRECTORY_SEPARATOR . 'downloaded.zip';

        try {
            $this->storageService->download($r2Key, $tempZipPath);
            $this->integrityService->verifyLocalPackage($tempZipPath, $backup?->sha256);

            $extractedSql = $this->encryptionService->decrypt($tempZipPath, $tempDir);

            // Import into target database safely via options file
            $this->importSqlIntoDatabase($extractedSql, $targetDatabase);

            Log::info("AUDIT RESTORE: Respaldo [{$uuid}] restaurado con éxito en la base de datos [{$targetDatabase}].");

        } finally {
            BackupTempDirectoryManager::deleteTempDir($tempDir);
        }
    }

    /**
     * Execute SQL import safely into a target database using a temporary credentials options file in OS temp dir.
     */
    private function importSqlIntoDatabase(string $sqlFilePath, string $targetDatabase): void
    {
        $connectionName = config('database.default', 'mysql');
        $dbConfig = config("database.connections.{$connectionName}");

        $host = $dbConfig['host'] ?? '127.0.0.1';
        $port = (string) ($dbConfig['port'] ?? 3306);
        $username = $dbConfig['username'] ?? '';
        $password = (string) ($dbConfig['password'] ?? '');

        $cnfPath = $this->dumpService->createTempOptionsFile($host, $port, $username, $password);

        try {
            $cmd = [
                'mysql',
                "--defaults-extra-file={$cnfPath}",
                $targetDatabase,
            ];

            $process = new Process($cmd, null, null, file_get_contents($sqlFilePath), 1800);
            $process->run();

            if (! $process->isSuccessful()) {
                $rawError = $process->getErrorOutput() ?: $process->getOutput();
                $sanitizedError = $this->dumpService->sanitizeLogOutput($rawError, $password);
                throw new RuntimeException("Fallo al importar el SQL en la base de datos [{$targetDatabase}]: " . $sanitizedError);
            }
        } finally {
            $this->dumpService->removeTempFile($cnfPath);
        }
    }

    /**
     * Notify superadministrators of backup failures.
     */
    private function notifySuperadminsOfFailure(DatabaseBackup $backup, string $errorMessage): void
    {
        try {
            $superadmins = User::query()
                ->whereHas('roles', fn ($q) => $q->where('name', 'superadmin'))
                ->get();

            foreach ($superadmins as $admin) {
                if ($admin->email) {
                    Mail::to($admin->email)->send(new \App\Mail\DatabaseBackupFailedMail($backup, $errorMessage));
                }
            }
        } catch (\Throwable $mailEx) {
            Log::warning("Fallo al enviar correo de notificación de respaldo fallido: " . $mailEx->getMessage());
        }
    }
}
