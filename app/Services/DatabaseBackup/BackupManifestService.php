<?php

namespace App\Services\DatabaseBackup;

use Carbon\Carbon;
use Symfony\Component\Process\Process;

class BackupManifestService
{
    /**
     * Generate the sidecar manifest array for a backup object.
     */
    public function generateManifest(
        string $uuid,
        string $type,
        string $status,
        string $databaseName,
        int $fileSize,
        string $sha256,
        float $durationSeconds,
        string $r2Key
    ): array {
        $nowGuayaquil = Carbon::now('America/Guayaquil')->toIso8601String();

        return [
            'uuid' => $uuid,
            'type' => $type,
            'status' => $status,
            'database_name' => $databaseName,
            'timestamp' => $nowGuayaquil,
            'file_size' => $fileSize,
            'sha256' => $sha256,
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'git_commit' => $this->getGitCommit(),
            'duration_seconds' => round($durationSeconds, 2),
            'r2_key' => $r2Key,
            'format_version' => '1.0',
        ];
    }

    /**
     * Get the sidecar manifest key corresponding to a backup R2 key.
     */
    public function getManifestKeyForBackupKey(string $backupR2Key): string
    {
        if (str_ends_with($backupR2Key, '.zip')) {
            return substr($backupR2Key, 0, -4) . '.manifest.json';
        }

        return $backupR2Key . '.manifest.json';
    }

    /**
     * Validate structural integrity of a manifest array.
     */
    public function validateManifest(array $manifest): bool
    {
        $requiredKeys = [
            'uuid',
            'type',
            'status',
            'database_name',
            'timestamp',
            'file_size',
            'sha256',
            'php_version',
            'laravel_version',
            'duration_seconds',
            'r2_key',
            'format_version',
        ];

        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $manifest)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Safely attempt to fetch current git commit hash.
     */
    private function getGitCommit(): ?string
    {
        try {
            $process = new Process(['git', 'rev-parse', 'HEAD']);
            $process->run();
            if ($process->isSuccessful()) {
                return trim($process->getOutput());
            }
        } catch (\Throwable) {
            // Fail gracefully if git command is not available
        }

        return null;
    }
}
