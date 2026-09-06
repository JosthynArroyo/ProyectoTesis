<?php

namespace App\Services;

use App\Services\ApplicationModeService;
use App\Support\ImageUrl;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ClinicIdentityService
{
    private const LOGO_CACHE_SECONDS = 86400;

    public function __construct(
        private readonly SiteSettingsService $settings,
        private readonly ImageUrl $imageUrl,
        private readonly ApplicationModeService $appMode,
    ) {
    }

    public function name(): string
    {
        $value = trim((string) $this->settings->get('branding.name', ''));

        if ($value !== '') {
            return $value;
        }

        return $this->appMode->isDemo() ? 'Clínica Josthyn Arroyo' : 'Nombre de la clínica';
    }

    public function slogan(): string
    {
        $value = trim((string) $this->settings->get('branding.navbar_text', ''));

        return $value !== '' ? $value : 'Sistema web de gestión médica';
    }

    public function institutionalName(): string
    {
        $value = trim((string) $this->settings->get('branding.institutional_name', ''));

        if ($value !== '') {
            return $value;
        }

        return $this->appMode->isDemo() ? 'Clínica Josthyn Arroyo' : $this->name();
    }

    public function email(): ?string
    {
        $value = trim((string) $this->settings->get('contact.email', ''));

        if ($value !== '') {
            return $value;
        }

        $configured = trim((string) config('mail.contact_to', ''));

        if ($configured !== '' && ! str_contains($configured, 'example.com')) {
            return $configured;
        }

        return null;
    }

    public function phone(): ?string
    {
        return $this->optionalValue('contact.phone');
    }

    public function address(): ?string
    {
        return $this->optionalValue('contact.address');
    }

    public function schedule(): ?string
    {
        return $this->optionalValue('contact.hours');
    }

    public function logoPath(): ?string
    {
        $path = $this->optionalValue('branding.logo');
        if ($path !== null && $path !== '') {
            return $path;
        }

        return $this->appMode->isDemo() ? 'images/demo/logo-demo.png' : null;
    }

    public function faviconPath(): ?string
    {
        $path = $this->optionalValue('branding.favicon');
        if ($path !== null && $path !== '') {
            return $path;
        }

        return $this->appMode->isDemo() ? 'images/demo/favicon-demo.png' : null;
    }

    public function footerText(): string
    {
        $template = trim((string) $this->settings->get('branding.footer_text', ''));
        $template = $template !== '' ? $template : '© {year} - Todos los derechos reservados.';

        return str_replace(
            ['{brand}', '{year}'],
            [$this->institutionalName(), date('Y')],
            $template
        );
    }

    public function logoUrl(string $size = 'thumb'): ?string
    {
        $path = $this->logoPath();

        return $path ? $this->imageUrl->url($path, 'branding', 'banner', $size, 'branding_asset') : null;
    }

    public function faviconUrl(): string
    {
        $path = $this->faviconPath();

        return $path
            ? $this->imageUrl->url($path, 'branding', 'banner', 'thumb', 'branding_asset')
            : asset('img/placeholders/default.svg');
    }

    public function logoBase64(): ?string
    {
        $path = $this->logoPath();
        if (! $path) {
            return null;
        }

        $cacheKey = 'clinic-identity:logo:plain:'.sha1($path.':'.$this->appMode->getMode());

        return $this->rememberLogoDataUri($cacheKey, function () use ($path) {
            $logoData = $this->getLogoData($path);
            if (! $logoData) {
                return null;
            }

            return $this->buildDataUriFromContents($logoData['contents'], $logoData['mime']);
        });
    }

    public function logoBase64ForPdf(): ?string
    {
        $path = $this->logoPath() ?: 'img/placeholders/default.svg';
        $cacheKey = 'clinic-identity:logo:pdf:'.sha1($path.':'.$this->appMode->getMode());

        return $this->rememberLogoDataUri($cacheKey, function () use ($path) {
            $logoData = $this->getLogoData($path) ?? $this->getLogoData('img/placeholders/default.svg');

            if (! $logoData) {
                return null;
            }

            $mime = $logoData['mime'];
            $extension = $logoData['extension'];
            $contents = $logoData['contents'];

            if ($mime === 'image/webp' || $extension === 'webp') {
                if (function_exists('imagecreatefromwebp')) {
                    return $this->buildDataUriFromContents($contents, 'image/webp');
                }

                return $this->convertWebpContentsToPngDataUri($contents);
            }

            return $this->buildDataUriFromContents($contents, $mime !== '' ? $mime : null);
        });
    }

    private function getLogoData(?string $rawPath): ?array
    {
        $path = $rawPath ? ltrim(str_replace('\\', '/', trim($rawPath)), '/') : null;
        if (! $path) {
            return null;
        }

        $publicPath = public_path($path);
        if (is_file($publicPath)) {
            $contents = @file_get_contents($publicPath);
            if ($contents === false || $contents === '') {
                return null;
            }
            $extension = strtolower(pathinfo($publicPath, PATHINFO_EXTENSION));
            $mime = strtolower((string) (mime_content_type($publicPath) ?: $this->mimeFromExtension($extension)));
            $mtime = @filemtime($publicPath) ?: 0;

            return [
                'contents' => $contents,
                'mime' => $mime,
                'extension' => $extension,
                'cache_key_suffix' => sha1('public:'.$path.'|'.$mtime),
                'source' => 'public',
            ];
        }

        $storagePath = str_starts_with($path, 'storage/') ? substr($path, 8) : $path;
        $disk = (string) config('image_optimization.disk', 'public');

        try {
            if (Storage::disk($disk)->exists($storagePath)) {
                $contents = Storage::disk($disk)->get($storagePath);
                if ($contents === null || $contents === '') {
                    return null;
                }
                $extension = strtolower(pathinfo($storagePath, PATHINFO_EXTENSION));
                $mime = strtolower((string) (Storage::disk($disk)->mimeType($storagePath) ?: $this->mimeFromExtension($extension)));
                $mtime = 0;
                try {
                    $mtime = Storage::disk($disk)->lastModified($storagePath) ?: 0;
                } catch (Throwable) {
                }

                return [
                    'contents' => $contents,
                    'mime' => $mime,
                    'extension' => $extension,
                    'cache_key_suffix' => sha1($disk.':'.$storagePath.'|'.$mtime),
                    'source' => $disk,
                ];
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private function mimeFromExtension(string $extension): string
    {
        return match (strtolower($extension)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'avif' => 'image/avif',
            default => 'image/png',
        };
    }

    private function buildDataUriFromContents(string $contents, ?string $mime = null): ?string
    {
        $mime = $mime ?: 'image/png';
        $data = base64_encode($contents);

        return $data === '' ? null : "data:{$mime};base64,{$data}";
    }

    private function buildDataUri(string $path, ?string $mime = null): ?string
    {
        $mime = $mime ?: (mime_content_type($path) ?: 'image/png');
        $contents = file_get_contents($path);
        if ($contents === false || $contents === '') {
            return null;
        }

        $data = base64_encode($contents);

        return $data === '' ? null : "data:{$mime};base64,{$data}";
    }

    private function convertWebpContentsToPngDataUri(string $contents): ?string
    {
        if (extension_loaded('imagick') && class_exists(\Imagick::class)) {
            try {
                $image = new \Imagick();
                $image->readImageBlob($contents);
                $image->setImageFormat('png');
                $blob = $image->getImageBlob();
                $image->clear();
                $image->destroy();

                return $blob !== '' ? 'data:image/png;base64,'.base64_encode($blob) : null;
            } catch (Throwable) {
                return null;
            }
        }

        if (function_exists('imagecreatefromstring') && function_exists('imagepng')) {
            $resource = @imagecreatefromstring($contents);
            if ($resource) {
                ob_start();
                imagepng($resource);
                $png = (string) ob_get_clean();
                imagedestroy($resource);

                return $png !== '' ? 'data:image/png;base64,'.base64_encode($png) : null;
            }
        }

        return null;
    }

    private function convertWebpToPngDataUri(string $path): ?string
    {
        if (extension_loaded('imagick') && class_exists(\Imagick::class)) {
            try {
                $image = new \Imagick();
                $image->readImage($path);
                $image->setImageFormat('png');
                $blob = $image->getImageBlob();
                $image->clear();
                $image->destroy();

                return $blob !== '' ? 'data:image/png;base64,'.base64_encode($blob) : null;
            } catch (Throwable) {
                return null;
            }
        }

        if (! function_exists('imagecreatefromwebp') || ! function_exists('imagepng')) {
            return null;
        }

        $resource = @imagecreatefromwebp($path);
        if (! $resource) {
            return null;
        }

        ob_start();
        imagepng($resource);
        $png = (string) ob_get_clean();
        imagedestroy($resource);

        return $png !== '' ? 'data:image/png;base64,'.base64_encode($png) : null;
    }

    public function emailBranding(): array
    {
        return [
            'brand_name' => $this->name(),
            'brand_logo' => $this->logoPath(),
            'accent' => (string) $this->settings->get('branding.accent', '#0f766e'),
            'accent_strong' => (string) $this->settings->get('branding.accent_strong', '#14b8a6'),
            'accent_soft' => (string) $this->settings->get('branding.accent_soft', '#ccfbf1'),
            'contact_phone' => $this->phone() ?? '',
            'contact_email' => $this->email() ?? '',
            'contact_hours' => $this->schedule() ?? '',
            'contact_address' => $this->address() ?? '',
            'footer_text' => $this->footerText(),
        ];
    }

    public function subject(string $prefix): string
    {
        return trim($prefix).' - '.$this->name();
    }

    private function optionalValue(string $key): ?string
    {
        $value = trim((string) $this->settings->get($key, ''));

        return $value !== '' ? $value : null;
    }

    private function absolutePath(?string $path): ?string
    {
        $path = $path ? ltrim(str_replace('\\', '/', $path), '/') : null;
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'storage/')) {
            $publicPath = substr($path, 8);

            return Storage::disk('public')->exists($publicPath)
                ? Storage::disk('public')->path($publicPath)
                : null;
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->path($path);
        }

        $publicPath = public_path($path);

        return is_file($publicPath) ? $publicPath : null;
    }

    private function rememberLogoDataUri(string $cacheKey, callable $resolver): ?string
    {
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $data = $resolver();
        if (is_string($data) && $data !== '') {
            Cache::put($cacheKey, $data, now()->addSeconds(self::LOGO_CACHE_SECONDS));
        }

        return $data;
    }

    private function logoCacheKey(string $path, string $variant): string
    {
        $mtime = @filemtime($path) ?: 0;
        $size = @filesize($path) ?: 0;

        return 'clinic-identity:logo:'.$variant.':'.sha1($path.'|'.$mtime.'|'.$size);
    }
}

