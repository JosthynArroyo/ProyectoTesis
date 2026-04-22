<?php

namespace App\Support;

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

    public static function cedulaUnique(string $table = 'users', ?int $ignoreId = null, string $column = 'dni'): array
    {
        $rule = Rule::unique($table, $column);
        if ($ignoreId) {
            $rule->ignore($ignoreId);
        }

        return ['required', 'digits:10', $rule];
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
}
