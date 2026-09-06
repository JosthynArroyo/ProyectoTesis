<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;
use Illuminate\Http\Request;

class TrustHosts extends Middleware
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, $next)
    {
        if ($this->shouldSpecifyTrustedHosts()) {
            Request::setTrustedHosts(array_filter($this->hosts()));
            $request->getHost();
        }

        return $next($request);
    }

    /**
     * Get the host patterns that should be trusted.
     *
     * @return array<int, string|null>
     */
    public function hosts(): array
    {
        return array_values(array_filter([
            $this->exactApplicationUrlHost(),
            ...$this->additionalHosts(),
        ]));
    }

    /**
     * Get the exact canonical host pattern derived from the application URL.
     *
     * @return string|null
     */
    protected function exactApplicationUrlHost(): ?string
    {
        if ($host = parse_url($this->app['config']->get('app.url'), PHP_URL_HOST)) {
            return '^'.preg_quote($host, '#').'$';
        }

        return null;
    }

    /**
     * Determine additional trusted host patterns.
     *
     * @return array<int, string>
     */
    protected function additionalHosts(): array
    {
        $patterns = [];

        // Permitir localhost e interfaces locales en entornos de desarrollo y pruebas.
        if ($this->app->environment('local', 'testing')) {
            $patterns[] = '^localhost$';
            $patterns[] = '^127\.0\.0\.1$';
            $patterns[] = '^\[?::1\]?$';
        }

        // Hosts adicionales configurados explícitamente vía TRUSTED_HOSTS.
        $configured = config('app.trusted_hosts', []);
        if (is_string($configured)) {
            $configured = array_filter(array_map('trim', explode(',', $configured)));
        }

        foreach ($configured as $host) {
            $host = trim((string) $host);
            if ($host === '') {
                continue;
            }

            if (str_starts_with($host, '^') || str_ends_with($host, '$')) {
                $patterns[] = $host;
            } else {
                $patterns[] = '^'.preg_quote($host, '#').'$';
            }
        }

        return $patterns;
    }

    /**
     * Determine if the application should specify trusted hosts.
     *
     * @return bool
     */
    protected function shouldSpecifyTrustedHosts(): bool
    {
        return true;
    }
}
