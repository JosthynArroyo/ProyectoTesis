<?php

namespace App\Services\DatabaseBackup;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BackupStorageService
{
    private function disk(): Filesystem
    {
        $diskName = (string) config('database_backups.disk', 'r2_backups');
        return Storage::disk($diskName);
    }

    public function uploadBackup(string $localPath, string $r2Key): bool
    {
        if (! file_exists($localPath)) {
            throw new RuntimeException("El archivo local a subir no existe.");
        }

        $stream = fopen($localPath, 'r');
        if (! $stream) {
            throw new RuntimeException("No se pudo abrir el stream del archivo local.");
        }

        try {
            $success = $this->disk()->put($r2Key, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $success) {
            throw new RuntimeException("Fallo al subir el archivo de respaldo a R2 [{$r2Key}].");
        }

        return true;
    }

    public function uploadManifest(array $manifestData, string $r2ManifestKey): bool
    {
        $json = json_encode($manifestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException("Fallo al codificar el manifiesto en formato JSON.");
        }

        $success = $this->disk()->put($r2ManifestKey, $json);
        if (! $success) {
            throw new RuntimeException("Fallo al subir el manifiesto a R2 [{$r2ManifestKey}].");
        }

        return true;
    }

    public function exists(string $r2Key): bool
    {
        return $this->disk()->exists($r2Key);
    }

    public function size(string $r2Key): int
    {
        return (int) $this->disk()->size($r2Key);
    }

    public function delete(string $r2Key): bool
    {
        if ($this->disk()->exists($r2Key)) {
            return $this->disk()->delete($r2Key);
        }
        return true;
    }

    public function download(string $r2Key, string $targetLocalPath): string
    {
        if (! $this->disk()->exists($r2Key)) {
            throw new RuntimeException("El objeto no existe en el almacenamiento R2 [{$r2Key}].");
        }

        $targetDir = dirname($targetLocalPath);
        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $content = $this->disk()->get($r2Key);
        if ($content === null) {
            throw new RuntimeException("No se pudo leer el contenido del objeto R2 [{$r2Key}].");
        }

        if (file_put_contents($targetLocalPath, $content) === false) {
            throw new RuntimeException("Fallo al escribir el objeto R2 descargado en la ruta local [{$targetLocalPath}].");
        }

        return $targetLocalPath;
    }

    public function listManifests(): array
    {
        $allFiles = $this->disk()->allFiles('database');
        $manifestKeys = array_filter($allFiles, fn ($file) => str_ends_with($file, '.manifest.json'));
        $manifests = [];

        foreach ($manifestKeys as $key) {
            try {
                $content = $this->disk()->get($key);
                if (! $content) {
                    continue;
                }
                $data = json_decode($content, true);
                if (is_array($data)) {
                    $manifests[] = $data;
                }
            } catch (\Throwable) {
                // Ignore broken manifests during listing
            }
        }

        return $manifests;
    }

    public function getManifest(string $r2ManifestKey): ?array
    {
        if (! $this->disk()->exists($r2ManifestKey)) {
            return null;
        }

        $content = $this->disk()->get($r2ManifestKey);
        if (! $content) {
            return null;
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : null;
    }
}
