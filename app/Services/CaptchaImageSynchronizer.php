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
    private const DATASET_RELATIVE_PATH = 'ai/dataset/val';
    private const DATASET_SPLIT = 'val';
    private const EXPECTED_CATEGORY_COUNT = 8;

    public function ensureSynchronized(?callable $logCallback = null): int
    {
        $classes = $this->classes();

        return Cache::lock(self::BOOTSTRAP_LOCK_KEY, self::BOOTSTRAP_LOCK_SECONDS)
            ->block(10, function () use ($classes, $logCallback): int {
                $signature = $this->datasetSignature($classes);
                $cachedCounts = Cache::get(self::CACHE_COUNTS_KEY, []);
                $cachedTotal = (int) Cache::get(self::CACHE_TOTAL_KEY, 0);

                if (
                    Cache::get(self::CACHE_SIGNATURE_KEY) === $signature
                    && $this->hasAllConfiguredCategories($cachedCounts, $classes)
                    && $this->currentCounts($classes) === $cachedCounts
                ) {
                    return $cachedTotal > 0 ? $cachedTotal : array_sum($cachedCounts);
                }

                return $this->synchronize($logCallback);
            });
    }

    public function synchronize(?callable $logCallback = null, ?string $signature = null): int
    {
        $classes = $this->classes();
        [$rows, $counts] = $this->datasetIndex($classes);
        $paths = array_column($rows, 'image_path');

        CaptchaImage::query()->whereNotIn('image_path', $paths)->delete();
        foreach (array_chunk($rows, 500) as $chunk) {
            CaptchaImage::query()->upsert(
                $chunk,
                ['image_path'],
                ['class_key', 'dataset_split', 'updated_at']
            );
        }

        ksort($counts);
        $total = count($rows);
        Cache::forever(self::CACHE_SIGNATURE_KEY, $this->datasetSignature($classes));
        Cache::forever(self::CACHE_COUNTS_KEY, $counts);
        Cache::forever(self::CACHE_TOTAL_KEY, $total);

        $message = 'Sincronizacion de CAPTCHA exitosa: '.$total.' imagenes de ai/dataset/val registradas.';
        Log::channel('single')->info('CaptchaImageSynchronizer: '.$message);
        if ($logCallback) {
            $logCallback($message, 'info');
        }

        return $total;
    }

    private function datasetIndex(array $classes): array
    {
        $datasetDir = base_path(self::DATASET_RELATIVE_PATH);
        if (! File::isDirectory($datasetDir)) {
            throw new \RuntimeException('Origen de imagenes de CAPTCHA no disponible. Asegurese de que exista ai/dataset/val.');
        }

        $rows = [];
        $counts = [];
        $now = now();

        foreach ($classes as $classKey) {
            $classDir = $datasetDir.DIRECTORY_SEPARATOR.$classKey;
            if (! File::isDirectory($classDir)) {
                throw new \RuntimeException('La categoria CAPTCHA configurada '.$classKey.' no existe en ai/dataset/val.');
            }

            $classCount = 0;
            foreach (File::files($classDir) as $file) {
                if (! $this->isSupportedImage($file->getExtension())) {
                    continue;
                }

                $rows[] = [
                    'class_key' => $classKey,
                    'dataset_split' => self::DATASET_SPLIT,
                    'image_path' => self::DATASET_RELATIVE_PATH.'/'.$classKey.'/'.$file->getFilename(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $classCount++;
            }

            if ($classCount === 0) {
                throw new \RuntimeException('La categoria CAPTCHA configurada '.$classKey.' no contiene imagenes validas en ai/dataset/val.');
            }
            $counts[$classKey] = $classCount;
        }

        return [$rows, $counts];
    }

    private function classes(): array
    {
        $configured = config('captcha.classes', []);
        $classes = is_array($configured)
            ? array_values(array_filter($configured, static fn ($class): bool => is_string($class) && $class !== ''))
            : [];

        if (count($classes) !== self::EXPECTED_CATEGORY_COUNT || count(array_unique($classes)) !== self::EXPECTED_CATEGORY_COUNT) {
            throw new \RuntimeException('El CAPTCHA debe tener exactamente 8 categorias configuradas y sin duplicados.');
        }

        return $classes;
    }

    private function datasetSignature(array $classes): string
    {
        $datasetDir = base_path(self::DATASET_RELATIVE_PATH);
        if (! File::isDirectory($datasetDir)) {
            throw new \RuntimeException('Origen de imagenes de CAPTCHA no disponible. Asegurese de que exista ai/dataset/val.');
        }

        $parts = [$datasetDir, (string) File::lastModified($datasetDir)];
        foreach ($classes as $classKey) {
            $classDir = $datasetDir.DIRECTORY_SEPARATOR.$classKey;
            if (! File::isDirectory($classDir)) {
                throw new \RuntimeException('La categoria CAPTCHA configurada '.$classKey.' no existe en ai/dataset/val.');
            }
            $parts[] = $classKey.':'.File::lastModified($classDir);
        }

        return hash('sha256', implode('|', $parts));
    }

    private function currentCounts(array $classes): array
    {
        $counts = CaptchaImage::query()
            ->whereIn('class_key', $classes)
            ->where('dataset_split', self::DATASET_SPLIT)
            ->selectRaw('class_key, COUNT(*) as total')
            ->groupBy('class_key')
            ->pluck('total', 'class_key')
            ->map(static fn ($value): int => (int) $value)
            ->all();
        ksort($counts);

        return $counts;
    }

    private function hasAllConfiguredCategories(mixed $counts, array $classes): bool
    {
        if (! is_array($counts) || count($counts) !== self::EXPECTED_CATEGORY_COUNT) {
            return false;
        }

        $expected = $classes;
        $actual = array_keys($counts);
        sort($expected);
        sort($actual);

        return $actual === $expected
            && collect($counts)->every(static fn ($count): bool => is_numeric($count) && (int) $count > 0);
    }

    private function isSupportedImage(string $extension): bool
    {
        return in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'webp'], true);
    }
}
