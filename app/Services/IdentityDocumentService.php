<?php

namespace App\Services;

use App\Models\Dependiente;
use App\Models\IdentityDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IdentityDocumentService
{
    public static function normalize(?string $value, string $tipoDocumento = 'cedula'): string
    {
        if ($value === null) {
            return '';
        }

        $tipo = strtolower(trim($tipoDocumento));
        if ($tipo === 'pasaporte') {
            return strtoupper(trim((string) $value));
        }

        return preg_replace('/[^0-9]/', '', trim((string) $value));
    }

    public static function isValidEcuadorianCedula(?string $value): bool
    {
        $normalized = self::normalize($value, 'cedula');
        return preg_match('/^\d{10}$/', $normalized) === 1;
    }

    public static function isValidPassportFormat(?string $value): bool
    {
        $normalized = self::normalize($value, 'pasaporte');
        return preg_match('/^[A-Z0-9-]{5,20}$/', $normalized) === 1;
    }

    public function isUnique(
        string $documento,
        string $tipoDocumento = 'cedula',
        ?string $nacionalidad = null,
        ?string $ignoreType = null,
        mixed $ignoreId = null
    ): bool {
        $tipo = strtolower(trim($tipoDocumento)) === 'pasaporte' ? 'PASAPORTE' : 'CEDULA';
        $normalized = self::normalize($documento, $tipo);

        if ($normalized === '') {
            return true;
        }

        $pais = $tipo === 'PASAPORTE' ? strtoupper(trim($nacionalidad ?? 'EC')) : 'EC';

        // 1. Check central identity_documents table
        $queryDoc = IdentityDocument::query()
            ->where('tipo_documento', $tipo)
            ->where('pais', $pais)
            ->where('numero_documento', $normalized);

        if ($ignoreType && $ignoreId) {
            $queryDoc->whereNot(function ($q) use ($ignoreType, $ignoreId) {
                $q->where('documentable_type', $ignoreType)
                  ->where('documentable_id', $ignoreId);
            });
        }

        if ($queryDoc->exists()) {
            return false;
        }

        // 2. Cross-check users table
        $userQuery = DB::table('users')
            ->whereNotNull('dni')
            ->where('dni', '!=', '');

        if ($tipo === 'PASAPORTE') {
            $userQuery->where(function ($q) use ($normalized, $pais) {
                $q->where('dni', $normalized)
                  ->where(function ($sq) use ($pais) {
                      $sq->where('nacionalidad', $pais)
                        ->orWhereNull('nacionalidad');
                  });
            });
        } else {
            $userQuery->whereRaw("REGEXP_REPLACE(dni, '[^0-9]', '') = ?", [$normalized]);
        }

        if ($ignoreType === User::class || $ignoreType === 'users' || is_subclass_of($ignoreType, User::class)) {
            if ($ignoreId) {
                $userQuery->where('id', '!=', $ignoreId);
            }
        }

        if ($userQuery->exists()) {
            return false;
        }

        // 3. Cross-check dependientes table
        $depQuery = DB::table('dependientes')
            ->whereNotNull('dni')
            ->where('dni', '!=', '');

        if ($tipo === 'PASAPORTE') {
            $depQuery->where(function ($q) use ($normalized, $pais) {
                $q->where('dni', $normalized)
                  ->where(function ($sq) use ($pais) {
                      $sq->where('nacionalidad', $pais)
                        ->orWhereNull('nacionalidad');
                  });
            });
        } else {
            $depQuery->whereRaw("REGEXP_REPLACE(dni, '[^0-9]', '') = ?", [$normalized]);
        }

        if ($ignoreType === Dependiente::class || $ignoreType === 'dependientes' || is_subclass_of($ignoreType, Dependiente::class)) {
            if ($ignoreId) {
                $depQuery->where('id', '!=', $ignoreId);
            }
        }

        if ($depQuery->exists()) {
            return false;
        }

        return true;
    }

    public function sync(Model $model, ?string $documento, string $tipoDocumento = 'cedula', ?string $nacionalidad = null): void
    {
        $tipo = strtolower(trim($tipoDocumento)) === 'pasaporte' ? 'PASAPORTE' : 'CEDULA';
        $normalized = self::normalize($documento, $tipo);
        $pais = $tipo === 'PASAPORTE' ? strtoupper(trim($nacionalidad ?? 'EC')) : 'EC';

        if ($normalized === '') {
            IdentityDocument::query()
                ->where('documentable_type', get_class($model))
                ->where('documentable_id', $model->getKey())
                ->delete();
            return;
        }

        IdentityDocument::updateOrCreate(
            [
                'documentable_type' => get_class($model),
                'documentable_id'   => $model->getKey(),
            ],
            [
                'pais'             => $pais,
                'tipo_documento'   => $tipo,
                'numero_documento' => $normalized,
            ]
        );
    }

    public static function auditDuplicateAttempt(string $action, string $module, ?int $userId = null): void
    {
        Log::warning('Auditoria: Intento de registro rechazado por documento duplicado', [
            'accion' => $action,
            'modulo' => $module,
            'fecha_hora' => now()->toIso8601String(),
            'usuario_id' => $userId,
            'motivo' => 'documento_duplicado',
        ]);
    }
}
