<?php

$configuredProxies = preg_split(
    '/[\s,;]+/',
    trim((string) env('TRUSTED_PROXIES', '')),
    -1,
    PREG_SPLIT_NO_EMPTY
) ?: [];

$isValidProxy = static function (string $proxy): bool {
    if (filter_var($proxy, FILTER_VALIDATE_IP)) {
        return true;
    }

    if (! str_contains($proxy, '/')) {
        return false;
    }

    [$ip, $prefix] = explode('/', $proxy, 2);

    if (! ctype_digit($prefix)) {
        return false;
    }

    $prefix = (int) $prefix;

    return match (true) {
        filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false => $prefix <= 32,
        filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false => $prefix <= 128,
        default => false,
    };
};

foreach ($configuredProxies as $proxy) {
    if (! $isValidProxy($proxy)) {
        throw new InvalidArgumentException(
            "TRUSTED_PROXIES contiene una IP o CIDR no valida: [{$proxy}]."
        );
    }
}

return [
    // Empty by default: forwarded headers are ignored unless REMOTE_ADDR is
    // explicitly covered by one of these IPs/CIDRs.
    'proxies' => array_values($configuredProxies),
];
