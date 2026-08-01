<?php

namespace App\Services;

use App\Models\CaptchaImage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class CaptchaImageSynchronizer
{
    private const CACHE_SIGNATURE_KEY = 'captcha.images.signature';
    private const CACHE_COUNTS_KEY = 'captcha.images.counts';
    private const CACHE_TOTAL_KEY = 'captcha.images.total';
    private const BOOTSTRAP_LOCK_KEY = 'captcha.images.bootstrap';
    private const BOOTSTRAP_LOCK_SECONDS = 120;

    /**
     * Ensure the CAPTCHA image index is synchronized with the AI dataset.
     */
    public function ensureSynchronized(?callable $logCallback = null): int
    {
        $classes = $this->classes();
        $signature = $this->datasetSignature($classes);
        return Cache::lock(self::BOOTSTRAP_LOCK_KEY, self::BOOTSTRAP_LOCK_SECONDS)->block(10, function () use ($classes, $signature, $logCallback): int {
            $cachedSignature = Cache::get(self::CACHE_SIGNATURE_KEY);
            $cachedCounts = Cache::get(self::CACHE_COUNTS_KEY, []);
            $cachedTotal = (int) Cache::get(self::CACHE_TOTAL_KEY, 0);

            if ($cachedSignature === $signature && is_array($cachedCounts) && $cachedCounts !== []) {
                $currentCounts = $this->currentCounts($classes);

                if ($currentCounts === $cachedCounts) {
                    return $cachedTotal > 0 ? $cachedTotal : array_sum($cachedCounts);
                }
            }

            $databaseCounts = $this->currentCounts($classes);
            if ($databaseCounts !== [] && count($databaseCounts) >= 4) {
                return $this->primeCacheFromDatabase($signature, $databaseCounts);
            }

            return $this->synchronize($logCallback, $signature);
        });
    }

    /**
     * Synchronizes CAPTCHA images from the AI dataset to storage and database.
     *
     * @param callable|null $logCallback Optional callback to log progress (e.g. CLI output)
     * @param string|null $signature Optional precomputed dataset signature
     * @return int Total number of synchronized images
     * @throws \RuntimeException If no source directory is found or no images exist.
     */
    public function synchronize(?callable $logCallback = null, ?string $signature = null): int
    {
        $classes = $this->classes();

        $privateDir = \Illuminate\Support\Facades\Storage::disk('local')->path('captcha_animals');
        File::ensureDirectoryExists($privateDir);

        $log = function (string $msg, string $type = 'info') use ($logCallback) {
            Log::channel('single')->$type("CaptchaImageSynchronizer: {$msg}");
            if ($logCallback) {
                $logCallback($msg, $type);
            }
        };

        $this->syncPrivateDirectoryFromDataset($privateDir, $classes, $log);

        $rows = [];
        $paths = [];
        $now = now();

        foreach ($classes as $classKey) {
            $classDir = "{$privateDir}/{$classKey}";
            if (! File::isDirectory($classDir)) {
                $log("Directorio de clase no encontrado: {$classDir}", 'warning');
                continue;
            }

            foreach (File::files($classDir) as $file) {
                $ext = strtolower($file->getExtension());
                if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    continue;
                }

                $path = "captcha_animals/{$classKey}/{$file->getFilename()}";
                $paths[] = $path;
                $rows[] = [
                    'class_key' => $classKey,
                    'dataset_split' => 'public',
                    'image_path' => $path,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $total = count($rows);
        if ($total === 0) {
            throw new \RuntimeException('No se encontraron imagenes validas en el almacenamiento privado (storage/app/captcha_animals).');
        }

        CaptchaImage::query()
            ->whereIn('class_key', $classes)
            ->where('dataset_split', 'public')
            ->whereNotIn('image_path', $paths)
            ->delete();

        $chunks = array_chunk($rows, 500);
        foreach ($chunks as $chunk) {
            CaptchaImage::query()->upsert(
                $chunk,
                ['image_path'],
                ['class_key', 'dataset_split', 'updated_at']
            );
        }

        $counts = array_count_values(array_column($rows, 'class_key'));
        ksort($counts);
        Cache::forever(self::CACHE_SIGNATURE_KEY, $signature ?? $this->datasetSignature($classes));
        Cache::forever(self::CACHE_COUNTS_KEY, $counts);
        Cache::forever(self::CACHE_TOTAL_KEY, $total);

        $log("Sincronizacion de CAPTCHA exitosa: {$total} imagenes registradas.", 'info');

        return $total;
    }

    /**
     * Rebuilds the cache snapshot from the already indexed database rows.
     */
    private function primeCacheFromDatabase(string $signature, array $counts): int
    {
        ksort($counts);
        $total = array_sum($counts);

        Cache::forever(self::CACHE_SIGNATURE_KEY, $signature);
        Cache::forever(self::CACHE_COUNTS_KEY, $counts);
        Cache::forever(self::CACHE_TOTAL_KEY, $total);

        Log::channel('single')->info('CaptchaImageSynchronizer: Caché de CAPTCHA rehidratada desde el índice de base de datos.');

        return $total;
    }

    /**
     * Copies animal images from the AI dataset to storage.
     */
    private function syncPrivateDirectoryFromDataset(string $privateDir, array $classes, callable $log): void
    {
        $datasetDir = base_path('ai/dataset/val');
        if (! File::isDirectory($datasetDir)) {
            $log("El dataset original en {$datasetDir} no existe. No se pueden inicializar las imagenes.", 'error');
            throw new \RuntimeException("Origen de imagenes de CAPTCHA no disponible. Asegurese de que exista 'ai/dataset/val'.");
        }

        $log("Sincronizando imagenes del dataset original 'ai/dataset/val' a 'storage/app/captcha_animals'...", 'info');

        foreach ($classes as $classKey) {
            $sourceClassDir = "{$datasetDir}/{$classKey}";
            $destClassDir = "{$privateDir}/{$classKey}";
            File::ensureDirectoryExists($destClassDir);

            if (! File::isDirectory($sourceClassDir)) {
                $log("La clase de animal '{$classKey}' no existe en el dataset: {$sourceClassDir}", 'warning');
                continue;
            }

            $sourceFiles = [];
            foreach (File::files($sourceClassDir) as $file) {
                $ext = strtolower($file->getExtension());
                if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    continue;
                }

                $sourceFiles[$file->getFilename()] = true;

                $destPath = "{$destClassDir}/{$file->getFilename()}";
                if (! File::exists($destPath) || File::lastModified($destPath) < $file->getMTime()) {
                    File::copy($file->getPathname(), $destPath);
                }
            }

            foreach (File::files($destClassDir) as $file) {
                if (! isset($sourceFiles[$file->getFilename()])) {
                    File::delete($file->getPathname());
                }
            }
        }
    }

    private function classes(): array
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

        return is_array($classes) ? array_values($classes) : [];
    }

    private function datasetSignature(array $classes): string
    {
        $datasetDir = base_path('ai/dataset/val');
        $parts = [$datasetDir];

        foreach ($classes as $classKey) {
            $classDir = "{$datasetDir}/{$classKey}";
            $parts[] = $classKey . ':' . (File::isDirectory($classDir) ? File::lastModified($classDir) : 0);
        }

        return hash('sha256', implode('|', $parts));
    }

    private function currentCounts(array $classes): array
    {
        $counts = CaptchaImage::query()
            ->whereIn('class_key', $classes)
            ->where('dataset_split', 'public')
            ->selectRaw('class_key, COUNT(*) as total')
            ->groupBy('class_key')
            ->pluck('total', 'class_key')
            ->map(static fn ($value) => (int) $value)
            ->all();

        ksort($counts);

        return $counts;
    }
}
