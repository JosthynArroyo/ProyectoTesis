<?php

namespace App\Services;

use App\Models\CaptchaImage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class CaptchaImageSynchronizer
{
    /**
     * Synchronizes CAPTCHA images from storage to database.
     *
     * @param callable|null $logCallback Optional callback to log progress (e.g. CLI output)
     * @return int Total number of synchronized images
     * @throws \RuntimeException If no source directory is found or no images exist.
     */
    public function synchronize(?callable $logCallback = null): int
    {
        $classes = config('captcha.classes', [
            'giraffe',
            'horse',
            'koala',
            'kangaroo',
            'rhinoceros',
            'dolphin',
            'blue_whale',
            'zebra',
        ]);

        $privateDir = \Illuminate\Support\Facades\Storage::disk('local')->path('captcha_animals');
        File::ensureDirectoryExists($privateDir);

        $log = function (string $msg, string $type = 'info') use ($logCallback) {
            Log::channel('single')->$type("CaptchaImageSynchronizer: {$msg}");
            if ($logCallback) {
                $logCallback($msg, $type);
            }
        };

        // 1. Copy from dataset if private dir is empty
        $this->ensurePrivateDirectoryPopulated($privateDir, $classes, $log);

        // 2. Scan and build batch rows
        $rows = [];
        $now = now();

        foreach ($classes as $classKey) {
            $classDir = "{$privateDir}/{$classKey}";
            if (!File::isDirectory($classDir)) {
                $log("Directorio de clase no encontrado: {$classDir}", 'warning');
                continue;
            }

            foreach (File::files($classDir) as $file) {
                $ext = strtolower($file->getExtension());
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    continue;
                }

                $rows[] = [
                    'class_key' => $classKey,
                    'dataset_split' => 'public',
                    'image_path' => "captcha_animals/{$classKey}/{$file->getFilename()}",
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $total = count($rows);
        if ($total === 0) {
            throw new \RuntimeException("No se encontraron imágenes válidas en el almacenamiento privado (storage/app/captcha_animals).");
        }

        // 3. Perform chunked upsert to DB
        $chunks = array_chunk($rows, 500);
        foreach ($chunks as $chunk) {
            CaptchaImage::query()->upsert(
                $chunk,
                ['image_path'],
                ['class_key', 'dataset_split', 'updated_at']
            );
        }

        $log("Sincronización de CAPTCHA exitosa: {$total} imágenes registradas.", 'info');

        return $total;
    }

    /**
     * Copies animal images from the AI dataset to storage if storage is empty.
     */
    private function ensurePrivateDirectoryPopulated(string $privateDir, array $classes, callable $log): void
    {
        $hasAnyImages = false;
        foreach ($classes as $classKey) {
            $classDir = "{$privateDir}/{$classKey}";
            if (File::isDirectory($classDir)) {
                $hasImages = collect(File::files($classDir))
                    ->contains(fn($file) => in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'webp'], true));
                if ($hasImages) {
                    $hasAnyImages = true;
                    break;
                }
            }
        }

        if ($hasAnyImages) {
            return;
        }

        $datasetDir = base_path('ai/dataset/val');
        if (!File::isDirectory($datasetDir)) {
            $log("El almacenamiento de CAPTCHA está vacío y el dataset original en {$datasetDir} no existe. No se pueden inicializar las imágenes.", 'error');
            throw new \RuntimeException("Origen de imágenes de CAPTCHA no disponible. Ejecute los pasos de instalación o asegúrese de que el dataset existe en 'ai/dataset/val'.");
        }

        $log("Copiando imágenes del dataset original 'ai/dataset/val' a 'storage/app/captcha_animals'...", 'info');

        foreach ($classes as $classKey) {
            $sourceClassDir = "{$datasetDir}/{$classKey}";
            $destClassDir = "{$privateDir}/{$classKey}";
            File::ensureDirectoryExists($destClassDir);

            if (!File::isDirectory($sourceClassDir)) {
                $log("La clase de animal '{$classKey}' no existe en el dataset: {$sourceClassDir}", 'warning');
                continue;
            }

            foreach (File::files($sourceClassDir) as $file) {
                $ext = strtolower($file->getExtension());
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    continue;
                }

                $destPath = "{$destClassDir}/{$file->getFilename()}";
                if (!File::exists($destPath)) {
                    File::copy($file->getPathname(), $destPath);
                }
            }
        }
    }
}
