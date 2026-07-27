<?php

namespace App\Support;

use App\Models\Dependiente;
use App\Models\User;
use App\Rules\GlobalUniqueCedulaRule;
use Closure;
use Illuminate\Validation\Rule;

class ValidationRules
{
    public static function passwordRequired(): array
    {
        return [
            'required',
            'string',
            'min:8',
            'confirmed',
            'regex:/^(?=.*[A-Za-z])(?=.*\\d)(?=.*[^A-Za-z0-9]).{8,}$/',
        ];
    }

    public static function passwordOptional(): array
    {
        return [
            'nullable',
            'string',
            'min:8',
            'confirmed',
            'regex:/^(?=.*[A-Za-z])(?=.*\\d)(?=.*[^A-Za-z0-9]).{8,}$/',
        ];
    }

    public static function emailUnique(string $table = 'users', ?int $ignoreId = null, string $column = 'email'): array
    {
        $rule = Rule::unique($table, $column);
        if ($ignoreId) {
            $rule->ignore($ignoreId);
        }

        return ['required', 'email', 'max:255', $rule];
    }

    public static function cedulaUnique(mixed $ignoreTypeOrTable = User::class, mixed $ignoreId = null, string $action = 'guardar_usuario', string $module = 'usuarios', ?int $userId = null): array
    {
        $ignoreType = is_string($ignoreTypeOrTable) && class_exists($ignoreTypeOrTable) ? $ignoreTypeOrTable : User::class;
        return ['required', new GlobalUniqueCedulaRule($ignoreType, $ignoreId, $action, $module, $userId)];
    }

    public static function documentoValidationRules(mixed $ignoreTypeOrTable = User::class, mixed $ignoreId = null, string $action = 'guardar_usuario', string $module = 'usuarios', ?int $userId = null): array
    {
        $ignoreType = is_string($ignoreTypeOrTable) && class_exists($ignoreTypeOrTable) ? $ignoreTypeOrTable : User::class;
        return [
            'tipo_documento' => ['nullable', 'in:cedula,pasaporte'],
            'nacionalidad' => ['required_if:tipo_documento,pasaporte', 'nullable', 'string', function ($attribute, $value, $fail) {
                if (request('tipo_documento') === 'pasaporte' && (! $value || ! \App\Support\CountryCatalog::isValidCode($value))) {
                    $fail('La nacionalidad es obligatoria cuando el documento es pasaporte.');
                }
            }],
            'dni' => ['required', new GlobalUniqueCedulaRule($ignoreType, $ignoreId, $action, $module, $userId)],
        ];
    }

    public static function telefono(): array
    {
        return ['required', 'digits:10'];
    }

    public static function birthDate(): array
    {
        return [
            'required',
            'date_format:Y-m-d',
            static function (string $attribute, mixed $value, Closure $fail): void {
                $parts = explode('-', (string) $value);

                if (count($parts) !== 3) {
                    $fail('Ingresa una fecha de nacimiento valida.');

                    return;
                }

                [$year, $month, $day] = array_map('intval', $parts);

                if (! checkdate($month, $day, $year)) {
                    $fail('Ingresa una fecha de nacimiento valida.');
                }
            },
            'before:today',
        ];
    }

    public static function motivoConsulta(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'min:3',
            'max:80',
            'regex:/^[^\r\n]+$/u',
            static function (string $attribute, mixed $value, \Closure $fail): void {
                $normalized = mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $value) ?? ''));

                if (in_array($normalized, ['no', 'ninguno', 'ninguna', 'n/a', 'na', 'sin motivo', 'omitir'], true)) {
                    $fail('Ingresa un motivo breve para la cita, por ejemplo fiebre, dolor de cabeza o tos.');
                }
            },
        ];
    }

    public static function dependentAge(): array
    {
        return [
            'required',
            'date',
            'before:today',
            static function (string $attribute, mixed $value, \Closure $fail): void {
                try {
                    $age = \Carbon\Carbon::parse($value)->age;
                    if ($age >= 18 && $age <= 65) {
                        $fail('Aviso: El paciente ingresado es mayor de edad. Por políticas del sistema, las personas entre 18 y 65 años deben registrar y gestionar su propia cuenta principal.');
                    }
                } catch (\Throwable) {
                    $fail('La fecha de nacimiento no es válida.');
                }
            }
        ];
    }

    public static function dependiente(bool $isUpdate = false, mixed $ignoreId = null, ?int $userId = null): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'tipo_documento' => ['nullable', 'in:cedula,pasaporte'],
            'nacionalidad' => ['required_if:tipo_documento,pasaporte', 'nullable', 'string', function ($attribute, $value, $fail) {
                if (request('tipo_documento') === 'pasaporte' && (! $value || ! \App\Support\CountryCatalog::isValidCode($value))) {
                    $fail('La nacionalidad es obligatoria cuando el documento es pasaporte.');
                }
            }],
            'dni' => ['required', new GlobalUniqueCedulaRule(Dependiente::class, $ignoreId, $isUpdate ? 'actualizar_dependiente' : 'crear_dependiente', 'dependientes', $userId)],
            'fecha_nacimiento' => self::dependentAge(),
            'sexo' => ['nullable', 'in:Masculino,Femenino,Otro'],
            'parentesco' => ['required', 'in:' . implode(',', \App\Models\Dependiente::PARENTESCOS)],
            'telefono_emergencia' => ['nullable', 'string', 'max:20'],
            'notas' => ['nullable', 'string'],
        ];
    }
}
