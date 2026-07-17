<?php

namespace App\Services;

class WhatsAppService
{
    /**
     * Normaliza un número de teléfono al formato estándar (E.164).
     */
    public function normalizePhone(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($raw, '+')) {
            return $this->isValidE164($digits) ? '+'.$digits : null;
        }

        $country = strtoupper((string) config('services.whatsapp.default_country', 'EC'));
        if ($country === 'EC') {
            if (str_starts_with($digits, '5930') && strlen($digits) === 13) {
                return '+593'.substr($digits, 4);
            }
            if (str_starts_with($digits, '593') && strlen($digits) === 12) {
                return '+'.$digits;
            }
            if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
                return '+593'.substr($digits, 1);
            }
            if (strlen($digits) === 9) {
                return '+593'.$digits;
            }
        }

        return $this->isValidE164($digits) ? '+'.$digits : null;
    }

    /**
     * Valida si la longitud de los dígitos del teléfono cumple con el estándar E.164.
     */
    private function isValidE164(string $digits): bool
    {
        $len = strlen($digits);

        return $len >= 10 && $len <= 15;
    }
}
