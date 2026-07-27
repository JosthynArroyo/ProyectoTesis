<?php

namespace App\Rules;

use App\Services\IdentityDocumentService;
use App\Support\CountryCatalog;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class GlobalUniqueCedulaRule implements ValidationRule
{
    public function __construct(
        private ?string $ignoreType = null,
        private mixed $ignoreId = null,
        private string $action = 'guardar_persona',
        private string $module = 'general',
        private ?int $userId = null,
        private ?string $tipoDocumento = null,
        private ?string $nacionalidad = null
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $tipo = strtolower(trim($this->tipoDocumento ?? request('tipo_documento', 'cedula')));
        if ($tipo !== 'pasaporte') {
            $tipo = 'cedula';
        }

        $nacionalidad = $this->nacionalidad ?? request('nacionalidad');

        if ($tipo === 'cedula') {
            $normalized = IdentityDocumentService::normalize($value, 'cedula');

            if (strlen($normalized) !== 10 || preg_match('/^\d{10}$/', $normalized) !== 1) {
                $fail('La cédula debe contener exactamente 10 dígitos.');
                return;
            }

            $service = app(IdentityDocumentService::class);
            if (! $service->isUnique($normalized, 'cedula', null, $this->ignoreType, $this->ignoreId)) {
                IdentityDocumentService::auditDuplicateAttempt($this->action, $this->module, $this->userId);
                $fail('Este documento ya está registrado para otra persona.');
            }
        } else {
            // Pasaporte
            $normalized = IdentityDocumentService::normalize($value, 'pasaporte');

            if ($normalized === '') {
                $fail('El número de pasaporte es obligatorio.');
                return;
            }

            if (strlen($normalized) < 5 || strlen($normalized) > 20 || preg_match('/^[A-Z0-9-]{5,20}$/', $normalized) !== 1) {
                $fail('El pasaporte debe contener entre 5 y 20 caracteres, utilizando letras, números o guion.');
                return;
            }

            if (! $nacionalidad || ! CountryCatalog::isValidCode($nacionalidad)) {
                $fail('La nacionalidad es obligatoria cuando el documento es pasaporte.');
                return;
            }

            $service = app(IdentityDocumentService::class);
            if (! $service->isUnique($normalized, 'pasaporte', $nacionalidad, $this->ignoreType, $this->ignoreId)) {
                IdentityDocumentService::auditDuplicateAttempt($this->action, $this->module, $this->userId);
                $fail('Este documento ya está registrado para otra persona.');
            }
        }
    }
}
