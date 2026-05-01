<?php

namespace App\Services;

use App\Support\ImageUrl;
use Illuminate\Support\Facades\Storage;

class ClinicIdentityService
{
    public function __construct(
        private readonly SiteSettingsService $settings,
        private readonly ImageUrl $imageUrl,
    ) {
    }

    public function name(): string
    {
        $value = trim((string) $this->settings->get('branding.name', ''));

        return $value !== '' ? $value : 'Nombre de la clínica';
    }

    public function slogan(): string
    {
        $value = trim((string) $this->settings->get('branding.navbar_text', ''));

        return $value !== '' ? $value : 'Sistema web de gestión médica';
    }

    public function institutionalName(): string
    {
        $value = trim((string) $this->settings->get('branding.institutional_name', ''));

        return $value !== '' ? $value : $this->name();
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
        return $this->optionalValue('branding.logo');
    }

    public function faviconPath(): ?string
    {
        return $this->optionalValue('branding.favicon');
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

        return $path ? $this->imageUrl->url($path, 'branding', 'banner', $size) : null;
    }

    public function faviconUrl(): string
    {
        $path = $this->faviconPath();

        return $path
            ? $this->imageUrl->url($path, 'branding', 'banner', 'thumb')
            : asset('img/placeholders/default.svg');
    }

    public function logoBase64(): ?string
    {
        $path = $this->absolutePath($this->logoPath());
        if (! $path || ! is_file($path)) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/png';
        $data = base64_encode(file_get_contents($path) ?: '');

        return $data === '' ? null : "data:{$mime};base64,{$data}";
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
}

