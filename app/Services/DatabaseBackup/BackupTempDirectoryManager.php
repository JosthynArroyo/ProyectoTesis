<?php

namespace App\Services\DatabaseBackup;

use RuntimeException;

class BackupTempDirectoryManager
{
    /**
     * Resolve a secure operation-specific temporary directory outside base_path() and storage_path().
     *
     * @param string $uuid Operation unique identifier.
     * @return string Absolute path to the resolved system temporary directory.
     */
    public static function getTempDir(string $uuid): string
    {
        $sysTemp = rtrim(sys_get_temp_dir(), '/\\');
        $tempDir = $sysTemp . DIRECTORY_SEPARATOR . 'clinica-database-backups' . DIRECTORY_SEPARATOR . $uuid;

        // Defensive validation: ensure resolved path is outside base_path() and storage_path()
        $realBase = realpath(base_path()) ?: base_path();
        $realStorage = realpath(storage_path()) ?: storage_path();

        $normTemp = strtolower(str_replace('\\', '/', $tempDir));
        $normBase = strtolower(str_replace('\\', '/', $realBase));
        $normStorage = strtolower(str_replace('\\', '/', $realStorage));

        if (str_starts_with($normTemp, $normBase) || str_starts_with($normTemp, $normStorage)) {
            throw new RuntimeException("SEGURIDAD DE ALMACENAMIENTO: El directorio temporal [{$tempDir}] está ubicado dentro del directorio del proyecto.");
        }

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0700, true);
        }

        @chmod($tempDir, 0700);

        return $tempDir;
    }

    /**
     * Recursively delete an entire operation temporary directory.
     */
    public static function deleteTempDir(?string $dirPath): void
    {
        if (! $dirPath || ! is_dir($dirPath)) {
            return;
        }

        $items = glob(rtrim($dirPath, '/\\') . '/{,.}[!.]*', GLOB_BRACE);
        if ($items) {
            foreach ($items as $item) {
                if (is_dir($item)) {
                    self::deleteTempDir($item);
                } else {
                    @unlink($item);
                }
            }
        }

        @rmdir($dirPath);
    }
}
