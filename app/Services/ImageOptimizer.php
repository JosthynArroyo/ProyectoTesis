<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use InvalidArgumentException;
use Throwable;

class ImageOptimizer
{
    private ImageManager $manager;

    private ?bool $avifSupported = null;

    public function __construct(?ImageManager $manager = null)
    {
        $this->manager = $manager ?? $this->buildManager();
    }

    public function optimizeAndStore(
        UploadedFile $file,
        string $folder,
        array $sizes = [],
        ?string $baseName = null,
        bool $generateAvif = true
    ): string {
        $folder = $this->sanitizeFolder($folder);
        $baseName = $this->normalizeBaseName(
            $baseName ?? Str::uuid()->toString()
        );

        $mime = strtolower((string) $file->getMimeType());
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if ($this->isSvg($mime, $extension)) {
            $contents = $file->getContent();
            if ($contents === false) {
                throw new InvalidArgumentException('Unable to read SVG contents.');
            }

            return $this->storeSvgContents($contents, $folder, $baseName);
        }

        if (! $this->isSupportedRaster($mime, $extension)) {
            throw new InvalidArgumentException('Unsupported image type.');
        }

        return $this->optimizeRasterFromAbsolutePath(
            absolutePath: (string) $file->getRealPath(),
            folder: $folder,
            baseName: $baseName,
            sizes: $sizes,
            generateAvif: $generateAvif
        );
    }

    public function optimizeExistingPublicPath(
        string $publicPath,
        string $folder,
        array $sizes = [],
        ?string $baseName = null
    ): ?string {
        $path = $this->normalizeStoredPath($publicPath);
        if (! $path || $this->isExternalPath($path)) {
            return null;
        }

        if (! Storage::disk($this->disk())->exists($path)) {
            return null;
        }

        $absolutePath = Storage::disk($this->disk())->path($path);

        return $this->optimizeAbsolutePath(
            absolutePath: $absolutePath,
            folder: $folder,
            sizes: $sizes,
            baseName: $baseName ?? pathinfo($path, PATHINFO_FILENAME)
        );
    }

    public function optimizeAbsolutePath(
        string $absolutePath,
        string $folder,
        array $sizes = [],
        ?string $baseName = null
    ): ?string {
        if (! is_file($absolutePath)) {
            return null;
        }

        $folder = $this->sanitizeFolder($folder);
        $baseName = $this->normalizeBaseName(
            $baseName ?? pathinfo($absolutePath, PATHINFO_FILENAME)
        );

        $mime = strtolower((string) (mime_content_type($absolutePath) ?: ''));
        $extension = strtolower((string) pathinfo($absolutePath, PATHINFO_EXTENSION));

        if ($this->isSvg($mime, $extension)) {
            $contents = @file_get_contents($absolutePath);
            if ($contents === false) {
                return null;
            }

            return $this->storeSvgContents($contents, $folder, $baseName);
        }

        if (! $this->isSupportedRaster($mime, $extension)) {
            return null;
        }

        return $this->optimizeRasterFromAbsolutePath($absolutePath, $folder, $baseName, $sizes);
    }

    public function deleteByStoredPath(?string $storedPath, ?string $folder = null): void
    {
        $path = $this->normalizeStoredPath($storedPath);
        if (! $path || $this->isExternalPath($path)) {
            return;
        }

        Storage::disk($this->disk())->delete($path);

        [$resolvedFolder, $baseName] = $this->extractFolderAndBaseName($path, $folder);
        if (! $resolvedFolder || ! $baseName) {
            return;
        }

        foreach (array_keys($this->sizes()) as $sizeName) {
            Storage::disk($this->disk())->delete($this->buildVariantPath($resolvedFolder, $sizeName, $baseName, 'webp'));
            Storage::disk($this->disk())->delete($this->buildVariantPath($resolvedFolder, $sizeName, $baseName, 'avif'));
        }

        Storage::disk($this->disk())->delete($this->buildOriginalSvgPath($resolvedFolder, $baseName));
    }

    public function normalizeStoredPath(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        $normalized = str_replace('\\', '/', $path);
        $normalized = ltrim($normalized, '/');

        if (Str::startsWith($normalized, 'storage/')) {
            $normalized = substr($normalized, 8);
        }

        return $normalized !== '' ? $normalized : null;
    }

    public function normalizeBaseName(string $name): string
    {
        $name = trim(pathinfo($name, PATHINFO_FILENAME));
        $name = preg_replace('/[^A-Za-z0-9_-]+/', '-', $name) ?: '';
        $name = trim($name, '-_');

        if ($name === '') {
            $name = Str::uuid()->toString();
        }

        return Str::lower($name);
    }

    public function buildVariantPath(string $folder, string $sizeName, string $baseName, string $extension = 'webp'): string
    {
        $folder = $this->sanitizeFolder($folder);
        $sizeName = strtolower(trim($sizeName));
        $extension = strtolower(trim($extension));

        return trim($this->basePath(), '/')
            .'/'.$folder
            .'/'.$sizeName
            .'/'.$this->normalizeBaseName($baseName)
            .'.'.$extension;
    }

    public function buildOriginalSvgPath(string $folder, string $baseName): string
    {
        $folder = $this->sanitizeFolder($folder);

        return trim($this->basePath(), '/')
            .'/'.$folder
            .'/original/'
            .$this->normalizeBaseName($baseName)
            .'.svg';
    }

    public function mapLegacyFolder(string $path): ?string
    {
        $path = ltrim(str_replace('\\', '/', strtolower($path)), '/');

        foreach ((array) config('image_optimization.legacy_folder_map', []) as $prefix => $folder) {
            $normalizedPrefix = ltrim(strtolower((string) $prefix), '/');
            if (Str::startsWith($path, $normalizedPrefix)) {
                return $this->sanitizeFolder((string) $folder);
            }
        }

        return null;
    }

    private function optimizeRasterFromAbsolutePath(
        string $absolutePath,
        string $folder,
        string $baseName,
        array $sizes = [],
        bool $generateAvif = true
    ): string {
        $definitions = $this->resolveSizes($sizes);
        $image = $this->manager->read($absolutePath)->orient();

        foreach ($definitions as $sizeName => $definition) {
            $variant = clone $image;
            $variant = $this->applyResize($variant, $definition);

            $webpPath = $this->buildVariantPath($folder, (string) $sizeName, $baseName, 'webp');
            Storage::disk($this->disk())->put($webpPath, (string) $variant->toWebp($this->webpQuality()));

            if ($generateAvif && $this->shouldGenerateAvif()) {
                try {
                    $avifPath = $this->buildVariantPath($folder, (string) $sizeName, $baseName, 'avif');
                    Storage::disk($this->disk())->put($avifPath, (string) $variant->toAvif($this->avifQuality()));
                } catch (Throwable) {
                    $this->avifSupported = false;
                }
            }
        }

        return $this->buildVariantPath($folder, 'large', $baseName, 'webp');
    }

    private function applyResize(object $image, array $definition): object
    {
        $mode = strtolower((string) ($definition['mode'] ?? 'max_width'));
        $width = (int) ($definition['width'] ?? 0);
        $height = (int) ($definition['height'] ?? 0);

        if ($mode === 'cover') {
            if ($width > 0 && $height > 0) {
                $image->cover($width, $height, 'center');
            }

            return $image;
        }

        if ($width > 0) {
            $image->scaleDown($width);
        }

        return $image;
    }

    private function storeSvgContents(string $contents, string $folder, string $baseName): string
    {
        $path = $this->buildOriginalSvgPath($folder, $baseName);
        Storage::disk($this->disk())->put($path, $contents);

        return $path;
    }

    private function extractFolderAndBaseName(string $path, ?string $folderOverride = null): array
    {
        $folder = $folderOverride ? $this->sanitizeFolder($folderOverride) : null;
        $baseName = null;

        if (preg_match('#^'.preg_quote(trim($this->basePath(), '/'), '#').'/([^/]+)/(thumb|medium|large)/([^/.]+)\.(webp|avif)$#i', $path, $matches)) {
            $folder = $this->sanitizeFolder((string) $matches[1]);
            $baseName = $this->normalizeBaseName((string) $matches[3]);
        } elseif (preg_match('#^'.preg_quote(trim($this->basePath(), '/'), '#').'/([^/]+)/original/([^/.]+)\.svg$#i', $path, $matches)) {
            $folder = $this->sanitizeFolder((string) $matches[1]);
            $baseName = $this->normalizeBaseName((string) $matches[2]);
        }

        if (! $baseName) {
            $baseName = $this->normalizeBaseName(pathinfo($path, PATHINFO_FILENAME));
        }

        if (! $folder) {
            $folder = $this->mapLegacyFolder($path);
        }

        return [$folder, $baseName];
    }

    private function resolveSizes(array $sizes): array
    {
        $defaults = $this->sizes();
        if (empty($sizes)) {
            return $defaults;
        }

        $resolved = $defaults;
        foreach ($sizes as $sizeName => $definition) {
            if (! is_array($definition)) {
                continue;
            }
            $resolved[$sizeName] = array_merge($defaults[$sizeName] ?? [], $definition);
        }

        return $resolved;
    }

    private function sanitizeFolder(string $folder): string
    {
        $folder = str_replace('\\', '/', strtolower(trim($folder)));
        $folder = trim($folder, '/');
        $folder = preg_replace('#[^a-z0-9/_-]+#', '', $folder) ?: '';
        $folder = trim($folder, '/');

        return $folder !== '' ? $folder : 'misc';
    }

    private function isSupportedRaster(string $mime, string $extension): bool
    {
        $rasterMimes = ['image/jpeg', 'image/pjpeg', 'image/png', 'image/webp'];
        $rasterExtensions = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($extension, $rasterExtensions, true)) {
            return true;
        }

        return in_array($mime, $rasterMimes, true);
    }

    private function isSvg(string $mime, string $extension): bool
    {
        return $extension === 'svg' || $mime === 'image/svg+xml';
    }

    private function isExternalPath(string $path): bool
    {
        return Str::startsWith($path, ['http://', 'https://', 'data:']);
    }

    private function shouldGenerateAvif(): bool
    {
        if (! (bool) config('image_optimization.generate_avif', true)) {
            return false;
        }

        if ($this->avifSupported === false) {
            return false;
        }

        return true;
    }

    private function buildManager(): ImageManager
    {
        if (extension_loaded('imagick')) {
            return new ImageManager(new ImagickDriver);
        }

        return new ImageManager(new GdDriver);
    }

    private function disk(): string
    {
        return (string) config('image_optimization.disk', 'public');
    }

    private function basePath(): string
    {
        return (string) config('image_optimization.base_path', 'images');
    }

    private function webpQuality(): int
    {
        return max(1, min(100, (int) config('image_optimization.quality.webp', 80)));
    }

    private function avifQuality(): int
    {
        return max(1, min(100, (int) config('image_optimization.quality.avif', 60)));
    }

    private function sizes(): array
    {
        /** @var array<string, array<string, mixed>> $sizes */
        $sizes = config('image_optimization.sizes', []);

        return $sizes;
    }
}
