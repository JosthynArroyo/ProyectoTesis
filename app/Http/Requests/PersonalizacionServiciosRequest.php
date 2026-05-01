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
            'services_title' => ['nullable', 'string', 'max:120'],
            'services_subtitle' => ['nullable', 'string', 'max:240'],
            'services_cta_text' => ['nullable', 'string', 'max:60'],
            'services_hero_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
            'services_hero_image_path' => ['nullable', 'string', 'max:180'],

            'especialidades' => ['nullable', 'array'],
            'especialidades.*.nombre' => ['nullable', 'string', 'max:120'],
            'especialidades.*.descripcion' => ['nullable', 'string', 'max:240'],
            'especialidades.*.icono' => ['nullable', 'string', 'max:80', Rule::in($allowedIcons)],
            'especialidades.*.activo' => ['nullable', 'boolean'],
            'especialidades.*.orden' => ['nullable', 'integer', 'min:0', 'max:999'],
            'especialidades.*.image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
            'especialidades.*.image_path' => ['nullable', 'string', 'max:180'],

            'nuevas' => ['nullable', 'array'],
            'nuevas.*.nombre' => ['nullable', 'string', 'max:120'],
            'nuevas.*.descripcion' => ['nullable', 'string', 'max:240'],
            'nuevas.*.icono' => ['nullable', 'string', 'max:80', Rule::in($allowedIcons)],
            'nuevas.*.activo' => ['nullable', 'boolean'],
            'nuevas.*.orden' => ['nullable', 'integer', 'min:0', 'max:999'],
            'nuevas.*.image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
            'nuevas.*.image_path' => ['nullable', 'string', 'max:180'],
        ];
    }
}
