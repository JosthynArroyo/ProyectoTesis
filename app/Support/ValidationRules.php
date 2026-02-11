<?php

namespace App\Support;

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
            'regex:/^(=.*[A-Za-z])(=.*\\d)(=.*[^A-Za-z0-9]).{8,}$/',
        ];
    }

    public static function passwordOptional(): array
    {
        return [
            'nullable',
            'string',
            'min:8',
            'confirmed',
            'regex:/^(=.*[A-Za-z])(=.*\\d)(=.*[^A-Za-z0-9]).{8,}$/',
        ];
    }

    public static function emailUnique(string $table = 'users', int $ignoreId = null, string $column = 'email'): array
    {
        $rule = Rule::unique($table, $column);
        if ($ignoreId) {
            $rule->ignore($ignoreId);
        }

        return ['required', 'email', 'max:255', $rule];
    }

    public static function cedulaUnique(string $table = 'users', int $ignoreId = null, string $column = 'dni'): array
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
}
