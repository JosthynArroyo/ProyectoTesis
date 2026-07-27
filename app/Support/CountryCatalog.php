<?php

namespace App\Support;

class CountryCatalog
{
    private static array $countries = [
        'AR' => ['code' => 'AR', 'country' => 'Argentina', 'demonym' => 'Argentina', 'aliases' => ['argentina', 'argentino', 'ar']],
        'BO' => ['code' => 'BO', 'country' => 'Bolivia', 'demonym' => 'Boliviana', 'aliases' => ['bolivia', 'boliviana', 'boliviano', 'bo']],
        'BR' => ['code' => 'BR', 'country' => 'Brasil', 'demonym' => 'Brasileña', 'aliases' => ['brasil', 'brazil', 'brasileña', 'brasileño', 'br']],
        'CA' => ['code' => 'CA', 'country' => 'Canadá', 'demonym' => 'Canadiense', 'aliases' => ['canada', 'canadá', 'canadiense', 'ca']],
        'CL' => ['code' => 'CL', 'country' => 'Chile', 'demonym' => 'Chilena', 'aliases' => ['chile', 'chilena', 'chileno', 'cl']],
        'CO' => ['code' => 'CO', 'country' => 'Colombia', 'demonym' => 'Colombiana', 'aliases' => ['colombia', 'colombiana', 'colombiano', 'co']],
        'CR' => ['code' => 'CR', 'country' => 'Costa Rica', 'demonym' => 'Costarricense', 'aliases' => ['costa rica', 'costarricense', 'cr']],
        'CU' => ['code' => 'CU', 'country' => 'Cuba', 'demonym' => 'Cubana', 'aliases' => ['cuba', 'cubana', 'cubano', 'cu']],
        'DE' => ['code' => 'DE', 'country' => 'Alemania', 'demonym' => 'Alemana', 'aliases' => ['alemania', 'alemana', 'alemán', 'aleman', 'de']],
        'DO' => ['code' => 'DO', 'country' => 'República Dominicana', 'demonym' => 'Dominicana', 'aliases' => ['republica dominicana', 'república dominicana', 'dominicana', 'dominicano', 'do']],
        'EC' => ['code' => 'EC', 'country' => 'Ecuador', 'demonym' => 'Ecuatoriana', 'aliases' => ['ecuador', 'ecuatoriana', 'ecuatoriano', 'ec']],
        'ES' => ['code' => 'ES', 'country' => 'España', 'demonym' => 'Española', 'aliases' => ['españa', 'espana', 'española', 'español', 'es']],
        'FR' => ['code' => 'FR', 'country' => 'Francia', 'demonym' => 'Francesa', 'aliases' => ['francia', 'francesa', 'francés', 'frances', 'fr']],
        'GB' => ['code' => 'GB', 'country' => 'Reino Unido', 'demonym' => 'Británica', 'aliases' => ['reino unido', 'inglaterra', 'britanica', 'británica', 'britanico', 'gb', 'uk']],
        'GT' => ['code' => 'GT', 'country' => 'Guatemala', 'demonym' => 'Guatemalteca', 'aliases' => ['guatemala', 'guatemalteca', 'guatemalteco', 'gt']],
        'HN' => ['code' => 'HN', 'country' => 'Honduras', 'demonym' => 'Hondureña', 'aliases' => ['honduras', 'hondureña', 'hondureño', 'hn']],
        'IT' => ['code' => 'IT', 'country' => 'Italia', 'demonym' => 'Italiana', 'aliases' => ['italia', 'italiana', 'italiano', 'it']],
        'JP' => ['code' => 'JP', 'country' => 'Japón', 'demonym' => 'Japonesa', 'aliases' => ['japon', 'japón', 'japonesa', 'japonés', 'japones', 'jp']],
        'MX' => ['code' => 'MX', 'country' => 'México', 'demonym' => 'Mexicana', 'aliases' => ['mexico', 'méxico', 'mexicana', 'mexicano', 'mx']],
        'NI' => ['code' => 'NI', 'country' => 'Nicaragua', 'demonym' => 'Nicaragüense', 'aliases' => ['nicaragua', 'nicaragüense', 'ni']],
        'PA' => ['code' => 'PA', 'country' => 'Panamá', 'demonym' => 'Panameña', 'aliases' => ['panama', 'panamá', 'panameña', 'panameño', 'pa']],
        'PE' => ['code' => 'PE', 'country' => 'Perú', 'demonym' => 'Peruana', 'aliases' => ['peru', 'perú', 'peruana', 'peruano', 'pe']],
        'PY' => ['code' => 'PY', 'country' => 'Paraguay', 'demonym' => 'Paraguaya', 'aliases' => ['paraguay', 'paraguaya', 'paraguayo', 'py']],
        'SV' => ['code' => 'SV', 'country' => 'El Salvador', 'demonym' => 'Salvadoreña', 'aliases' => ['el salvador', 'salvadoreña', 'salvadoreño', 'sv']],
        'UR' => ['code' => 'UY', 'country' => 'Uruguay', 'demonym' => 'Uruguaya', 'aliases' => ['uruguay', 'uruguaya', 'uruguayo', 'uy']],
        'US' => ['code' => 'US', 'country' => 'Estados Unidos', 'demonym' => 'Estadounidense', 'aliases' => ['estados unidos', 'eeuu', 'ee.uu.', 'estadounidense', 'us', 'usa']],
        'VE' => ['code' => 'VE', 'country' => 'Venezuela', 'demonym' => 'Venezolana', 'aliases' => ['venezuela', 'venezolana', 'venezolano', 've']],
    ];

    /**
     * Get sorted array of countries for select options.
     */
    public static function getSelectOptions(): array
    {
        $list = array_values(self::$countries);
        usort($list, fn ($a, $b) => strcmp($a['demonym'], $b['demonym']));

        return $list;
    }

    /**
     * Check if a code is valid.
     */
    public static function isValidCode(?string $code): bool
    {
        if (! $code) {
            return false;
        }
        $upper = strtoupper(trim($code));

        return isset(self::$countries[$upper]);
    }

    /**
     * Get country label (e.g. "Colombiana (Colombia)") by ISO code.
     */
    public static function getLabel(?string $code): string
    {
        if (! $code) {
            return '';
        }
        $upper = strtoupper(trim($code));
        if (isset(self::$countries[$upper])) {
            $item = self::$countries[$upper];
            return "{$item['demonym']} ({$item['country']})";
        }

        return $code;
    }

    /**
     * Get country demonym by ISO code.
     */
    public static function getDemonym(?string $code): string
    {
        if (! $code) {
            return '';
        }
        $upper = strtoupper(trim($code));
        return self::$countries[$upper]['demonym'] ?? $code;
    }

    /**
     * Get country name by ISO code.
     */
    public static function getCountryName(?string $code): string
    {
        if (! $code) {
            return '';
        }
        $upper = strtoupper(trim($code));
        return self::$countries[$upper]['country'] ?? $code;
    }

    /**
     * Find ISO code from user input string (used in Chatbot & input resolution).
     */
    public static function findCode(?string $input): ?string
    {
        if (! $input) {
            return null;
        }

        $normalized = self::normalizeString($input);
        if ($normalized === '') {
            return null;
        }

        foreach (self::$countries as $code => $item) {
            if ($normalized === strtolower($code)) {
                return $code;
            }

            if ($normalized === self::normalizeString($item['country'])) {
                return $code;
            }

            if ($normalized === self::normalizeString($item['demonym'])) {
                return $code;
            }

            foreach ($item['aliases'] as $alias) {
                if ($normalized === self::normalizeString($alias)) {
                    return $code;
                }
            }
        }

        return null;
    }

    /**
     * Strip accents and convert to lower case for insensitive matching.
     */
    public static function normalizeString(string $value): string
    {
        $str = mb_strtolower(trim($value));
        $unaccented = preg_replace(
            ['/[áàâä]/u', '/[éèêë]/u', '/[íìîï]/u', '/[óòôö]/u', '/[úùûü]/u', '/[ñ]/u'],
            ['a', 'e', 'i', 'o', 'u', 'n'],
            $str
        );

        return trim(preg_replace('/[^a-z0-9\s\.]/', '', $unaccented));
    }
}
