<?php

namespace App\Services;

use Aws\S3\S3ClientInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use InvalidArgumentException;
use Throwable;

class ImageOptimizer
{
    private ?ImageManager $manager;

    private ?bool $avifSupported = null;

    public function __construct(?ImageManager $manager = null)
    {
        $this->manager = $manager;
    }

    public function optimizeAndStore(
        UploadedFile $file,
        string $folder,
        array $sizes = [],
        ?string $baseName = null,
        bool $generateAvif = true,
        ?string $profile = null,
        bool $storeOriginal = false
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
            extension: $extension,
            mime: $mime,
            sizes: $sizes,
            generateAvif: $generateAvif,
            profile: $profile,
            storeOriginal: $storeOriginal
        );
    }

    public function optimizeExistingPublicPath(
        string $publicPath,
        string $folder,
        array $sizes = [],
        ?string $baseName = null,
        ?string $profile = null,
        bool $storeOriginal = false
    ): ?string {
        $path = $this->normalizeStoredPath($publicPath);
        if (! $path || $this->isExternalPath($path)) {
            return null;
        }

        $absolutePath = Storage::disk($this->disk())->path($path);

        return $this->optimizeAbsolutePath(
            absolutePath: $absolutePath,
            folder: $folder,
            sizes: $sizes,
            baseName: $baseName ?? pathinfo($path, PATHINFO_FILENAME),
            profile: $profile,
            storeOriginal: $storeOriginal
        );
    }

    public function optimizeAbsolutePath(
        string $absolutePath,
        string $folder,
        array $sizes = [],
        ?string $baseName = null,
        ?string $profile = null,
        bool $storeOriginal = false
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

        return $this->optimizeRasterFromAbsolutePath(
            absolutePath: $absolutePath,
            folder: $folder,
            baseName: $baseName,
            extension: $extension,
            mime: $mime,
            sizes: $sizes,
            profile: $profile,
            storeOriginal: $storeOriginal
        );
    }

    public function deleteByStoredPath(?string $storedPath, ?string $folder = null): void
    {
        $this->deleteManyByStoredPaths([$storedPath], $folder);
    }

    /**
     * @param  iterable<int, string|null>  $storedPaths
     */
    public function deleteManyByStoredPaths(iterable $storedPaths, ?string $folder = null): void
    {
        $paths = [];

        foreach ($storedPaths as $storedPath) {
            $path = $this->normalizeStoredPath($storedPath);
            if (! $path || $this->isExternalPath($path)) {
                continue;
            }

            $paths[] = $path;

            [$resolvedFolder, $baseName] = $this->extractFolderAndBaseName($path, $folder);
            if (! $resolvedFolder || ! $baseName) {
                continue;
            }

            foreach (array_keys($this->sizes()) as $sizeName) {
                $paths[] = $this->buildVariantPath($resolvedFolder, $sizeName, $baseName, 'webp');
                $paths[] = $this->buildVariantPath($resolvedFolder, $sizeName, $baseName, 'avif');
            }

            foreach (['jpg', 'jpeg', 'png', 'webp'] as $originalExtension) {
                $paths[] = $this->buildOriginalRasterPath($resolvedFolder, $baseName, $originalExtension);
            }
            $paths[] = $this->buildOriginalSvgPath($resolvedFolder, $baseName);
        }

        $paths = array_values(array_unique(array_filter($paths)));

        if ($paths === []) {
            return;
        }

        $disk = Storage::disk($this->disk());

        if ($disk->getAdapter() instanceof AwsS3V3Adapter) {
            $this->deleteManyViaS3Objects($paths);

            return;
        }

        $disk->delete($paths);
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

    public function buildOriginalRasterPath(string $folder, string $baseName, string $extension): string
    {
        $folder = $this->sanitizeFolder($folder);
        $extension = $this->normalizeRasterExtension($extension);

        return trim($this->basePath(), '/')
            .'/'.$folder
            .'/original/'
            .$this->normalizeBaseName($baseName)
            .'.'.$extension;
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
        string $extension,
        string $mime,
        array $sizes = [],
        bool $generateAvif = true,
        ?string $profile = null,
        bool $storeOriginal = false
    ): string {
        $manager = $this->resolveManagerForRaster($mime, $extension);
        if (! $manager) {
            return $this->storeOriginalRaster(
                absolutePath: $absolutePath,
                folder: $folder,
                baseName: $baseName,
                extension: $extension,
                reason: $this->unsupportedRasterReason($mime, $extension),
                storeOriginal: true
            );
        }

        $definitions = $this->resolveSizes($sizes, $profile);
        $profileDefinition = $this->resolveProfileDefinition($profile);
        $generateAvif = array_key_exists('generate_avif', $profileDefinition)
            ? (bool) $profileDefinition['generate_avif']
            : $generateAvif;
        $storeOriginal = $storeOriginal || (bool) ($profileDefinition['store_original'] ?? false);

        if ($definitions === []) {
            return $this->storeOriginalRaster(
                absolutePath: $absolutePath,
                folder: $folder,
                baseName: $baseName,
                extension: $extension,
                reason: 'profile_without_sizes',
                storeOriginal: true
            );
        }

        if ($storeOriginal) {
            $this->storeOriginalRaster(
                absolutePath: $absolutePath,
                folder: $folder,
                baseName: $baseName,
                extension: $extension,
                reason: 'profile_original_fallback',
                storeOriginal: true
            );
        }

        $image = $manager->read($absolutePath)->orient();
        $preferredSize = $this->preferredSizeForProfile($profile, array_keys($definitions));

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

        return $this->buildVariantPath(
            $folder,
            $preferredSize ?? (array_key_last($definitions) ?: 'large'),
            $baseName,
            'webp'
        );
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

    private function storeOriginalRaster(
        string $absolutePath,
        string $folder,
        string $baseName,
        string $extension,
        string $reason,
        bool $storeOriginal = false
    ): string {
        $path = $this->buildOriginalRasterPath($folder, $baseName, $extension);
        $contents = @file_get_contents($absolutePath);

        if ($contents === false) {
            throw new InvalidArgumentException('Unable to read image contents.');
        }

        Storage::disk($this->disk())->put($path, $contents);

        Log::warning('Image optimization skipped because raster support is unavailable.', [
            'folder' => $folder,
            'path' => $path,
            'reason' => $reason,
        ]);

        return $path;
    }

    private function extractFolderAndBaseName(string $path, ?string $folderOverride = null): array
    {
        $folder = $folderOverride ? $this->sanitizeFolder($folderOverride) : null;
        $baseName = null;

        if (preg_match('#^'.preg_quote(trim($this->basePath(), '/'), '#').'/([^/]+)/(thumb|medium|large)/([^/.]+)\.(webp|avif)$#i', $path, $matches)) {
            $folder = $this->sanitizeFolder((string) $matches[1]);
            $baseName = $this->normalizeBaseName((string) $matches[3]);
        } elseif (preg_match('#^'.preg_quote(trim($this->basePath(), '/'), '#').'/([^/]+)/original/([^/.]+)\.(svg|jpg|jpeg|png|webp)$#i', $path, $matches)) {
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

    private function resolveSizes(array $sizes, ?string $profile = null): array
    {
        $defaults = $profile ? $this->profileSizes($profile) : $this->sizes();
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

    public function profileSizes(?string $profile): array
    {
        $definition = $this->resolveProfileDefinition($profile);

        return (array) ($definition['sizes'] ?? []);
    }

    public function profileGenerateAvif(?string $profile): bool
    {
        $definition = $this->resolveProfileDefinition($profile);

        return (bool) ($definition['generate_avif'] ?? config('image_optimization.generate_avif', true));
    }

    public function profileStoresOriginal(?string $profile): bool
    {
        $definition = $this->resolveProfileDefinition($profile);

        return (bool) ($definition['store_original'] ?? false);
    }

    public function defaultSizes(): array
    {
        return $this->sizes();
    }

    public function profilePreferredSize(?string $profile): ?string
    {
        $definition = $this->resolveProfileDefinition($profile);
        $preferred = trim((string) ($definition['preferred_size'] ?? ''));

        return $preferred !== '' ? $preferred : null;
    }

    private function resolveProfileDefinition(?string $profile): array
    {
        $profile = trim((string) $profile);
        if ($profile === '') {
            return [];
        }

        $profiles = (array) config('image_optimization.profiles', []);
        $definition = $profiles[$profile] ?? [];

        return is_array($definition) ? $definition : [];
    }

    private function preferredSizeForProfile(?string $profile, array $sizeNames): ?string
    {
        if ($sizeNames === []) {
            return null;
        }

        $preferred = $this->profilePreferredSize($profile);
        if ($preferred && in_array($preferred, $sizeNames, true)) {
            return $preferred;
        }

        return $sizeNames[0] ?? null;
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

    private function resolveManagerForRaster(string $mime, string $extension): ?ImageManager
    {
        if ($this->manager instanceof ImageManager) {
            return $this->manager;
        }

        if ($this->imagickCanOptimizeRaster($extension)) {
            return new ImageManager(new ImagickDriver);
        }

        if ($this->gdCanOptimizeRaster($mime, $extension)) {
            return new ImageManager(new GdDriver);
        }

        return null;
    }

    private function imagickCanOptimizeRaster(string $extension): bool
    {
        if (! extension_loaded('imagick') || ! class_exists(\Imagick::class)) {
            return false;
        }

        $sourceFormat = $this->imagickFormatForExtension($extension);
        if ($sourceFormat === null) {
            return false;
        }

        return $this->imagickSupportsFormat($sourceFormat)
            && $this->imagickSupportsFormat('WEBP');
    }

    private function gdCanOptimizeRaster(string $mime, string $extension): bool
    {
        if (! extension_loaded('gd')) {
            return false;
        }

        return match ($this->normalizeRasterExtension($extension)) {
            'jpg' => $this->gdSupportsJpeg(),
            'png' => $this->gdSupportsPng(),
            'webp' => $this->gdSupportsWebp(),
            default => in_array($mime, ['image/jpeg', 'image/pjpeg', 'image/png', 'image/webp'], true)
                && false,
        };
    }

    private function gdSupportsJpeg(): bool
    {
        $info = $this->gdInfo();

        return function_exists('imagejpeg')
            && function_exists('imagecreatefromjpeg')
            && (bool) ($info['JPEG Support'] ?? false)
            && $this->gdCanEncodeWebp();
    }

    private function gdSupportsPng(): bool
    {
        $info = $this->gdInfo();

        return function_exists('imagepng')
            && function_exists('imagecreatefrompng')
            && (bool) ($info['PNG Support'] ?? false)
            && $this->gdCanEncodeWebp();
    }

    private function gdSupportsWebp(): bool
    {
        return function_exists('imagecreatefromwebp')
            && $this->gdCanEncodeWebp();
    }

    private function gdCanEncodeWebp(): bool
    {
        $info = $this->gdInfo();

        return function_exists('imagewebp')
            && (bool) ($info['WebP Support'] ?? false);
    }

    private function gdInfo(): array
    {
        return function_exists('gd_info') ? (array) gd_info() : [];
    }

    private function imagickSupportsFormat(string $format): bool
    {
        try {
            return \Imagick::queryFormats($format) !== [];
        } catch (Throwable) {
            return false;
        }
    }

    private function imagickFormatForExtension(string $extension): ?string
    {
        return match ($this->normalizeRasterExtension($extension)) {
            'jpg' => 'JPEG',
            'png' => 'PNG',
            'webp' => 'WEBP',
            default => null,
        };
    }

    private function unsupportedRasterReason(string $mime, string $extension): string
    {
        $driverAvailability = [
            'imagick' => extension_loaded('imagick'),
            'gd' => extension_loaded('gd'),
        ];

        return sprintf(
            'source=%s mime=%s imagick=%s gd=%s',
            $this->normalizeRasterExtension($extension),
            $mime !== '' ? $mime : 'unknown',
            $driverAvailability['imagick'] ? 'on' : 'off',
            $driverAvailability['gd'] ? 'on' : 'off'
        );
    }

    private function normalizeRasterExtension(string $extension): string
    {
        $extension = strtolower(trim($extension));

        return match ($extension) {
            'jpeg' => 'jpg',
            default => $extension,
        };
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

    public function disk(): string
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

    /**
     * @param  array<int, string>  $paths
     */
    private function deleteManyViaS3Objects(array $paths): bool
    {
        $disk = Storage::disk($this->disk());
        $adapter = $disk->getAdapter();

        if (! $adapter instanceof AwsS3V3Adapter) {
            return false;
        }

        try {
            $client = $this->readAdapterProperty($adapter, 'client');
            $bucket = $this->readAdapterProperty($adapter, 'bucket');
            $prefixer = $this->readAdapterProperty($adapter, 'prefixer');

            if (! $client instanceof S3ClientInterface || ! is_string($bucket) || ! is_object($prefixer) || ! method_exists($prefixer, 'prefixPath')) {
                return false;
            }

            $objects = array_map(
                fn (string $path) => ['Key' => $prefixer->prefixPath($path)],
                $paths
            );

            $client->deleteObjects([
                'Bucket' => $bucket,
                'Delete' => [
                    'Objects' => $objects,
                    'Quiet' => true,
                ],
            ]);

            return true;
        } catch (Throwable $exception) {
            Log::warning('Batch delete of optimized images failed.', [
                'disk' => $this->disk(),
                'count' => count($paths),
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function readAdapterProperty(object $adapter, string $property): mixed
    {
        $reflection = new \ReflectionClass($adapter);

        if (! $reflection->hasProperty($property)) {
            return null;
        }

        $reflectionProperty = $reflection->getProperty($property);
        $reflectionProperty->setAccessible(true);

        return $reflectionProperty->getValue($adapter);
    }
}
