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

    public function variants(?string $value, ?string $folder = null, string $entity = 'default', ?string $profile = null): array
    {
        $path = $this->optimizer->normalizeStoredPath($value);
        if (! $path) {
            return $this->placeholderVariants($entity);
        }

        if ($this->isExternalPath($path)) {
            return $this->singleUrlVariants($path);
        }

        if ($this->isAvatarPath($path)) {
            return $this->avatarVariants($path, $entity);
        }

        if ($this->isSvgPath($path) || $this->isOriginalRasterPath($path) || $this->isPublicAssetPath($path)) {
            return $this->singleUrlVariants($this->toUrl($path));
        }

        [$resolvedFolder, $baseName] = $this->resolveFolderAndBaseName($path, $folder);
        if (! $resolvedFolder || ! $baseName) {
            return $this->singleUrlVariants($this->toUrl($path));
        }

        $sizeDefinitions = $profile
            ? $this->optimizer->profileSizes($profile)
            : $this->optimizer->profileSizes($this->inferProfileFromPath($path, $resolvedFolder));
        if ($profile === null && $sizeDefinitions === []) {
            $sizeDefinitions = $this->optimizer->defaultSizes();
        }

        if ($sizeDefinitions === []) {
            return $this->singleUrlVariants($this->toUrl($path));
        }

        $variants = [];
        foreach ($sizeDefinitions as $sizeName => $definition) {
            $variantPath = $this->optimizer->buildVariantPath($resolvedFolder, (string) $sizeName, $baseName, 'webp');
            $variants[$sizeName] = [
                'url' => $this->toUrl($variantPath),
                'width' => (int) ($definition['width'] ?? 0),
            ];
        }

        $preferredSize = $profile ? $this->optimizer->profilePreferredSize($profile) : null;
        if (! $preferredSize || ! array_key_exists($preferredSize, $variants)) {
            $preferredSize = array_key_first($variants);
        }

        $src = $variants[$preferredSize]['url'] ?? $this->toUrl($path);
        $thumb = $variants['thumb']['url'] ?? $src;
        $medium = $variants['medium']['url'] ?? $src;
        $large = $variants['large']['url'] ?? $medium ?? $src;

        $srcsetItems = [];
        foreach ($variants as $sizeName => $variant) {
            if ($variant['width'] <= 0) {
                continue;
            }
            $srcsetItems[] = $variant['url'].' '.$variant['width'].'w';
        }

        $srcset = $this->buildSrcset($srcsetItems);

        return [
            'thumb' => $thumb,
            'medium' => $medium,
            'large' => $large,
            'src' => $src,
            'srcset' => $srcset,
            'is_placeholder' => false,
        ];
    }

    public function url(?string $value, ?string $folder = null, string $entity = 'default', string $size = 'large', ?string $profile = null): string
    {
        $variants = $this->variants($value, $folder, $entity, $profile);
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
        } elseif (preg_match('#^images/([^/]+)/original/([^/.]+)\.(svg|jpg|jpeg|png|webp)$#i', $path, $matches)) {
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

    private function inferProfileFromPath(string $path, ?string $folder = null): ?string
    {
        $normalized = strtolower(ltrim(str_replace('\\', '/', $path), '/'));

        if (preg_match('#^images/([^/]+)/(thumb|medium|large)/#', $normalized, $matches)) {
            return match ($matches[1]) {
                'banners' => 'public_hero',
                'doctors' => 'public_doctor',
                'services' => (string) ($matches[2] === 'large' ? 'public_hero' : 'public_card'),
                default => null,
            };
        }

        return match ($folder) {
            'banners' => 'public_hero',
            'doctors' => 'public_doctor',
            'services' => 'public_card',
            default => null,
        };
    }

    private function isOriginalRasterPath(string $path): bool
    {
        return (bool) preg_match('#^images/[^/]+/original/[^/.]+\.(jpg|jpeg|png|webp)$#i', $path);
    }

    private function isPublicAssetPath(string $path): bool
    {
        return is_file(public_path($path));
    }

    /**
     * @param  array<int, string>  $items
     */
    private function buildSrcset(array $items): ?string
    {
        $items = array_values(array_filter(array_unique($items)));
        if (count($items) < 2) {
            return null;
        }

        return implode(', ', $items);
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

        if (is_file(public_path($path))) {
            return $this->withVersion(asset($path), $this->publicVersion($path));
        }

        $disk = $this->disk();

        if (Str::startsWith($path, 'storage/')) {
            $storedPath = substr($path, 8);

            if ($disk === 'public') {
                return asset($path);
            }

            return Storage::disk($disk)->url($storedPath);
        }

        if ($disk === 'public') {
            return asset('storage/'.$path);
        }

        return Storage::disk($disk)->url($path);
    }

    private function withVersion(string $url, ?int $version): string
    {
        if (! $version) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'v='.$version;
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

    private function isExternalPath(string $path): bool
    {
        return Str::startsWith($path, ['http://', 'https://', 'data:', 'blob:']);
    }

    private function disk(): string
    {
        return $this->optimizer->disk();
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

    private function isAvatarPath(string $path): bool
    {
        $normalized = str_replace('\\', '/', strtolower(trim($path)));

        return Str::startsWith($normalized, 'avatars/');
    }

    private function avatarVariants(string $path, string $entity): array
    {
        $normalized = str_replace('\\', '/', trim($path));

        if (preg_match('#^avatars/(\d+)/#i', $normalized, $matches)) {
            $userId = (int) $matches[1];
            $version = substr(md5($path), 0, 8);

            $thumb = route('media.avatars.show', ['user' => $userId, 'variant' => 'thumb', 'v' => $version]);
            $medium = route('media.avatars.show', ['user' => $userId, 'variant' => 'medium', 'v' => $version]);

            return [
                'thumb' => $thumb,
                'medium' => $medium,
                'large' => $medium,
                'src' => $medium,
                'srcset' => "{$thumb} 150w, {$medium} 600w",
                'is_placeholder' => false,
            ];
        }

        $user = \App\Models\User::where('avatar', $path)->first();
        if ($user) {
            return $this->avatarVariants("avatars/{$user->id}/legacy/original.png", $entity);
        }

        return $this->placeholderVariants($entity);
    }
}
