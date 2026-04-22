<?php

namespace App\Support;

use App\Services\ImageOptimizer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageUrl
{
    public function __construct(
        private readonly ImageOptimizer $optimizer
    ) {
    }

    public function variants(?string $value, ?string $folder = null, string $entity = 'default'): array
    {
        $path = $this->optimizer->normalizeStoredPath($value);
        if (! $path) {
            return $this->placeholderVariants($entity);
        }

        if ($this->isExternalPath($path)) {
            return $this->singleUrlVariants($path);
        }

        if ($this->isSvgPath($path)) {
            if ($this->pathExists($path)) {
                return $this->singleUrlVariants($this->toUrl($path));
            }

            return $this->placeholderVariants($entity);
        }

        [$resolvedFolder, $baseName] = $this->resolveFolderAndBaseName($path, $folder);
        if ($resolvedFolder && $baseName) {
            $thumbPath = $this->optimizer->buildVariantPath($resolvedFolder, 'thumb', $baseName, 'webp');
            $mediumPath = $this->optimizer->buildVariantPath($resolvedFolder, 'medium', $baseName, 'webp');
            $largePath = $this->optimizer->buildVariantPath($resolvedFolder, 'large', $baseName, 'webp');

            $thumbUrl = $this->pathExists($thumbPath) ? $this->toUrl($thumbPath) : null;
            $mediumUrl = $this->pathExists($mediumPath) ? $this->toUrl($mediumPath) : null;
            $largeUrl = $this->pathExists($largePath) ? $this->toUrl($largePath) : null;

            if ($thumbUrl || $mediumUrl || $largeUrl) {
                $thumb = $thumbUrl ?? $mediumUrl ?? $largeUrl;
                $medium = $mediumUrl ?? $largeUrl ?? $thumbUrl;
                $large = $largeUrl ?? $mediumUrl ?? $thumbUrl;
                $srcset = $this->buildSrcset($thumb, $medium, $large);

                return [
                    'thumb' => $thumb,
                    'medium' => $medium,
                    'large' => $large,
                    'src' => $thumb,
                    'srcset' => $srcset,
                    'is_placeholder' => false,
                ];
            }
        }

        if ($this->pathExists($path)) {
            return $this->singleUrlVariants($this->toUrl($path));
        }

        return $this->placeholderVariants($entity);
    }

    public function url(?string $value, ?string $folder = null, string $entity = 'default', string $size = 'large'): string
    {
        $variants = $this->variants($value, $folder, $entity);
        $size = strtolower(trim($size));

        return $variants[$size] ?? $variants['src'];
    }

    public function fallback(string $entity = 'default'): string
    {
        $fallbacks = (array) config('image_optimization.fallbacks', []);
        $entity = $this->normalizeEntityKey($entity);
        $path = $fallbacks[$entity] ?? $fallbacks['default'] ?? 'img/placeholders/default.svg';

        return asset(ltrim((string) $path, '/'));
    }

    private function resolveFolderAndBaseName(string $path, ?string $folder = null): array
    {
        $folderFromPath = null;
        $baseName = null;

        if (preg_match('#^images/([^/]+)/(thumb|medium|large)/([^/.]+)\.(webp|avif)$#i', $path, $matches)) {
            $folderFromPath = (string) $matches[1];
            $baseName = (string) $matches[3];
        } elseif (preg_match('#^images/([^/]+)/original/([^/.]+)\.svg$#i', $path, $matches)) {
            $folderFromPath = (string) $matches[1];
            $baseName = (string) $matches[2];
        } else {
            $folderFromPath = $folder ?: $this->optimizer->mapLegacyFolder($path);
            $baseName = pathinfo($path, PATHINFO_FILENAME);
        }

        if (! $folderFromPath || ! $baseName) {
            return [null, null];
        }

        return [$folderFromPath, $this->optimizer->normalizeBaseName($baseName)];
    }

    private function buildSrcset(string $thumb, string $medium, string $large): ?string
    {
        $items = [
            $thumb.' 150w',
            $medium.' 600w',
            $large.' 1200w',
        ];

        $unique = array_values(array_unique($items));
        if (count($unique) < 2) {
            return null;
        }

        return implode(', ', $unique);
    }

    private function singleUrlVariants(string $url): array
    {
        return [
            'thumb' => $url,
            'medium' => $url,
            'large' => $url,
            'src' => $url,
            'srcset' => null,
            'is_placeholder' => false,
        ];
    }

    private function placeholderVariants(string $entity): array
    {
        $url = $this->fallback($entity);

        return [
            'thumb' => $url,
            'medium' => $url,
            'large' => $url,
            'src' => $url,
            'srcset' => null,
            'is_placeholder' => true,
        ];
    }

    private function toUrl(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        if ($this->isExternalPath($path)) {
            return $path;
        }

        if (Str::startsWith($path, 'storage/')) {
            $storedPath = substr($path, 8);

            return $this->withVersion(asset($path), $this->storageVersion($storedPath));
        }

        if (Storage::disk('public')->exists($path)) {
            return $this->withVersion(asset('storage/'.$path), $this->storageVersion($path));
        }

        if (is_file(public_path($path))) {
            return $this->withVersion(asset($path), $this->publicVersion($path));
        }

        return asset('storage/'.$path);
    }

    private function withVersion(string $url, ?int $version): string
    {
        if (! $version) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'v='.$version;
    }

    private function storageVersion(string $path): ?int
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        try {
            if (Storage::disk('public')->exists($path)) {
                return Storage::disk('public')->lastModified($path);
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private function publicVersion(string $path): ?int
    {
        $absolutePath = public_path(ltrim(str_replace('\\', '/', $path), '/'));

        if (! is_file($absolutePath)) {
            return null;
        }

        $modifiedAt = filemtime($absolutePath);

        return $modifiedAt !== false ? $modifiedAt : null;
    }

    private function pathExists(string $path): bool
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        if ($this->isExternalPath($path)) {
            return true;
        }

        if (Str::startsWith($path, 'storage/')) {
            $path = substr($path, 8);
        }

        if (Storage::disk('public')->exists($path)) {
            return true;
        }

        return is_file(public_path($path));
    }

    private function isExternalPath(string $path): bool
    {
        return Str::startsWith($path, ['http://', 'https://', 'data:']);
    }

    private function isSvgPath(string $path): bool
    {
        return Str::endsWith(strtolower($path), '.svg');
    }

    private function normalizeEntityKey(string $entity): string
    {
        $entity = strtolower(trim($entity));

        return match ($entity) {
            'paciente', 'patient' => 'patient',
            'doctor', 'medico' => 'doctor',
            'usuario', 'user', 'avatar' => 'user',
            'banner', 'slide', 'hero' => 'banner',
            default => 'default',
        };
    }
}
