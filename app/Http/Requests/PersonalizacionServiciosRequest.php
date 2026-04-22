<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PersonalizacionServiciosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $allowedIcons = collect(config('iconos.especialidades', []))
            ->pluck('id')
            ->values()
            ->all();

        return [
            'especialidades' => ['nullable', 'array'],
            'especialidades.*.nombre' => ['nullable', 'string', 'max:120'],
            'especialidades.*.descripcion' => ['nullable', 'string', 'max:240'],
            'especialidades.*.icono' => ['nullable', 'string', 'max:80', Rule::in($allowedIcons)],
            'especialidades.*.activo' => ['nullable', 'boolean'],
            'especialidades.*.orden' => ['nullable', 'integer', 'min:0', 'max:999'],

            'nuevas' => ['nullable', 'array'],
            'nuevas.*.nombre' => ['nullable', 'string', 'max:120'],
            'nuevas.*.descripcion' => ['nullable', 'string', 'max:240'],
            'nuevas.*.icono' => ['nullable', 'string', 'max:80', Rule::in($allowedIcons)],
            'nuevas.*.activo' => ['nullable', 'boolean'],
            'nuevas.*.orden' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }
}
