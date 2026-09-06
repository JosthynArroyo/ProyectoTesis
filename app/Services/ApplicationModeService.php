<?php

namespace App\Services;

class ApplicationModeService
{
    public const MODE_PRODUCTION = 'production';

    public const MODE_DEMO = 'demo';

    public const VALID_MODES = [
        self::MODE_PRODUCTION,
        self::MODE_DEMO,
    ];

    /**
     * Get the validated current application mode.
     *
     * Falls back safely to 'production' if the configured value is invalid or unset.
     */
    public function getMode(): string
    {
        $configured = config('app.mode', self::MODE_PRODUCTION);

        if (! is_string($configured)) {
            return self::MODE_PRODUCTION;
        }

        $normalized = strtolower(trim($configured));

        if (in_array($normalized, self::VALID_MODES, true)) {
            return $normalized;
        }

        return self::MODE_PRODUCTION;
    }

    /**
     * Determine if the application is running in demo mode.
     */
    public function isDemo(): bool
    {
        return $this->getMode() === self::MODE_DEMO;
    }

    /**
     * Determine if the application is running in production mode.
     */
    public function isProduction(): bool
    {
        return $this->getMode() === self::MODE_PRODUCTION;
    }

    /**
     * Determine whether persistent database writes should be allowed.
     */
    public function shouldPersist(): bool
    {
        return ! $this->isDemo();
    }

    /**
     * Determine whether external side effects (e.g. SMTP emails, external webhooks) are allowed.
     */
    public function allowExternalEffects(): bool
    {
        return ! $this->isDemo();
    }

    /**
     * Get the isolated session cookie name for the current application mode.
     */
    public function sessionCookieName(): string
    {
        $baseCookie = (string) config('session.cookie_base', '');

        if ($baseCookie === '') {
            $baseCookie = (string) config('session.cookie', '');
        }

        if ($baseCookie === '') {
            $baseCookie = \Illuminate\Support\Str::snake((string) config('app.name', 'laravel')) . '_session';
        }

        if ($this->isDemo()) {
            if (str_ends_with($baseCookie, '_demo_session')) {
                return $baseCookie;
            }
            if (str_ends_with($baseCookie, '_session')) {
                return substr($baseCookie, 0, -strlen('_session')) . '_demo_session';
            }

            return $baseCookie . '_demo';
        }

        if (str_ends_with($baseCookie, '_demo_session')) {
            return substr($baseCookie, 0, -strlen('_demo_session')) . '_session';
        }

        return $baseCookie;
    }

    /**
     * Get the resolved database connection name for the current application mode.
     */
    public function databaseConnectionName(?string $baseConnection = null): string
    {
        $base = $baseConnection ?: (string) config('database.default', 'mysql');

        if ($this->isDemo()) {
            if ($base === 'mysql') {
                return 'mysql_demo';
            }
            if (! str_ends_with($base, '_demo')) {
                return $base.'_demo';
            }

            return $base;
        }

        if (str_ends_with($base, '_demo')) {
            return substr($base, 0, -strlen('_demo'));
        }

        return $base;
    }

    /**
     * Fail-closed validation to guarantee demo mode never runs against unconfigured or production database.
     *
     * @throws \RuntimeException
     */
    public function assertDemoDatabaseIsolation(): void
    {
        if (! $this->isDemo()) {
            return;
        }

        $activeConnection = $this->databaseConnectionName();
        $demoConfig = config("database.connections.{$activeConnection}");

        if (! is_array($demoConfig)) {
            throw new \RuntimeException(
                "Configuración insegura: La conexión demo [{$activeConnection}] no está definida en database.connections."
            );
        }

        $demoDatabase = trim((string) ($demoConfig['database'] ?? ''));

        if ($demoDatabase === '') {
            throw new \RuntimeException(
                "Configuración insegura: APP_MODE=demo requiere 'DB_DEMO_DATABASE' configurado y no puede estar vacío."
            );
        }

        $prodConnection = config('database.connections.mysql') ?? [];
        $prodDatabase = trim((string) ($prodConnection['database'] ?? ''));
        $demoHost = trim((string) ($demoConfig['host'] ?? '127.0.0.1'));
        $prodHost = trim((string) ($prodConnection['host'] ?? '127.0.0.1'));

        if ($prodDatabase !== '' && $demoDatabase === $prodDatabase && $demoHost === $prodHost) {
            throw new \RuntimeException(
                "Violación crítica de seguridad: DB_DEMO_DATABASE ('{$demoDatabase}') no puede ser igual a DB_DATABASE ('{$prodDatabase}'). La demo debe utilizar una base de datos aislada."
            );
        }
    }
}

