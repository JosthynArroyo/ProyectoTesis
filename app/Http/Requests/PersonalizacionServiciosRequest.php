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
            'especialidades' => ['required', 'array'],
            'especialidades.*.nombre' => ['required', 'string', 'max:120'],
            'especialidades.*.descripcion' => ['required', 'string', 'max:240'],
            'especialidades.*.icono' => ['required', 'string', 'max:80', Rule::in($allowedIcons)],
            'especialidades.*.activo' => ['required', 'boolean'],
            'especialidades.*.orden' => ['required', 'integer', 'min:0', 'max:999'],

            'nuevas' => ['nullable', 'array'],
            'nuevas.*.nombre' => ['required', 'string', 'max:120'],
            'nuevas.*.descripcion' => ['required', 'string', 'max:240'],
            'nuevas.*.icono' => ['required', 'string', 'max:80', Rule::in($allowedIcons)],
            'nuevas.*.activo' => ['required', 'boolean'],
            'nuevas.*.orden' => ['required', 'integer', 'min:0', 'max:999'],
        ];
    }
}
