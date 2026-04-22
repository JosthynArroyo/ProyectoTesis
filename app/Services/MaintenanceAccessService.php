<?php

namespace App\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

class MaintenanceAccessService
{
    private const FORWARDED_IP_HEADERS = [
        'X-Forwarded-For',
        'X-Real-IP',
        'CF-Connecting-IP',
        'True-Client-IP',
    ];

    public function __construct(private SiteSettingsService $settings)
    {
    }

    public function enabled(): bool
    {
        return $this->settings->getBool('maintenance.enabled', false);
    }

    public function userOrIpCanBypass(Request $request, ?Authenticatable $user = null): bool
    {
        if ($user && method_exists($user, 'hasRole') && $user->hasRole('superadmin')) {
            return true;
        }

        return $this->requestIpIsAllowlisted($request);
    }

    public function requestIpIsAllowlisted(Request $request, ?string $allowlist = null): bool
    {
        $allowedIps = $this->parseAllowlist($allowlist ?? $this->settings->get('maintenance.allow_ips', ''));

        if ($allowedIps === []) {
            return false;
        }

        foreach ($this->requestIps($request) as $requestIp) {
            if (IpUtils::checkIp($requestIp, $allowedIps)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function requestIps(Request $request): array
    {
        $ips = [];
        $this->pushIp($ips, $request->ip());

        foreach ($request->ips() as $ip) {
            $this->pushIp($ips, $ip);
        }

        foreach (self::FORWARDED_IP_HEADERS as $header) {
            foreach (explode(',', (string) $request->headers->get($header, '')) as $ip) {
                $this->pushIp($ips, $ip);
            }
        }

        $forwarded = (string) $request->headers->get('Forwarded', '');
        if ($forwarded !== '') {
            preg_match_all('/for="?([^";,]+)"?/i', $forwarded, $matches);

            foreach ($matches[1] ?? [] as $ip) {
                $this->pushIp($ips, $ip);
            }
        }

        return array_values(array_unique($ips));
    }

    /**
     * @return list<string>
     */
    private function parseAllowlist(?string $allowlist): array
    {
        $items = preg_split('/[\s,;]+/', (string) $allowlist, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $validItems = [];

        foreach ($items as $item) {
            $item = trim($item);

            if ($item !== '' && $this->isValidIpOrCidr($item)) {
                $validItems[] = $item;
            }
        }

        return array_values(array_unique($validItems));
    }

    private function pushIp(array &$ips, ?string $ip): void
    {
        $normalizedIp = $this->normalizeIp($ip);

        if ($normalizedIp !== null) {
            $ips[] = $normalizedIp;
        }
    }

    private function normalizeIp(?string $ip): ?string
    {
        $ip = trim((string) $ip);

        if ($ip === '') {
            return null;
        }

        if (str_starts_with($ip, '[') && str_contains($ip, ']')) {
            $ip = substr($ip, 1, strpos($ip, ']') - 1);
        }

        if (preg_match('/^\d{1,3}(?:\.\d{1,3}){3}:\d+$/', $ip)) {
            $ip = substr($ip, 0, strrpos($ip, ':'));
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
    }

    private function isValidIpOrCidr(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return true;
        }

        if (! str_contains($value, '/')) {
            return false;
        }

        [$ip, $prefix] = explode('/', $value, 2);

        if (! ctype_digit($prefix)) {
            return false;
        }

        $prefix = (int) $prefix;

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $prefix >= 0 && $prefix <= 32;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $prefix >= 0 && $prefix <= 128;
        }

        return false;
    }
}
