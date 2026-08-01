<?php

namespace App\Http\Requests;

use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rule;

class PersonalizacionServiciosRequest extends FormRequest
{
    private const SUPERADMIN_MAX_FILE_KB = 10240;

    private const SUPERADMIN_MAX_TOTAL_BYTES = 52428800;

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

        $isSuperadminServicesUpdate = $this->routeIs('superadmin.personalizacion.servicios.update');
        $maxFileKb = $isSuperadminServicesUpdate ? self::SUPERADMIN_MAX_FILE_KB : 4096;

        return [
            'services_title' => ['nullable', 'string', 'max:120'],
            'services_subtitle' => ['nullable', 'string', 'max:240'],
            'services_cta_text' => ['nullable', 'string', 'max:60'],
            'services_hero_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:'.$maxFileKb],
            'services_hero_image_path' => ['nullable', 'string', 'max:180'],

            'especialidades' => ['nullable', 'array'],
            'especialidades.*.nombre' => ['nullable', 'string', 'max:120'],
            'especialidades.*.descripcion' => ['nullable', 'string', 'max:240'],
            'especialidades.*.icono' => ['nullable', 'string', 'max:80', Rule::in($allowedIcons)],
            'especialidades.*.activo' => ['nullable', 'boolean'],
            'especialidades.*.orden' => ['nullable', 'integer', 'min:0', 'max:999'],
            'especialidades.*.image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:'.$maxFileKb],
            'especialidades.*.image_path' => ['nullable', 'string', 'max:180'],

            'nuevas' => ['nullable', 'array'],
            'nuevas.*.nombre' => ['nullable', 'string', 'max:120'],
            'nuevas.*.descripcion' => ['nullable', 'string', 'max:240'],
            'nuevas.*.icono' => ['nullable', 'string', 'max:80', Rule::in($allowedIcons)],
            'nuevas.*.activo' => ['nullable', 'boolean'],
            'nuevas.*.orden' => ['nullable', 'integer', 'min:0', 'max:999'],
            'nuevas.*.image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:'.$maxFileKb],
            'nuevas.*.image_path' => ['nullable', 'string', 'max:180'],
        ];
    }

    public function messages(): array
    {
        if (! $this->routeIs('superadmin.personalizacion.servicios.update')) {
            return [];
        }

        return [
            'services_hero_image.max' => 'La imagen principal debe pesar como maximo 10 MB.',
            'especialidades.*.image.max' => 'Cada imagen de especialidad debe pesar como maximo 10 MB.',
            'nuevas.*.image.max' => 'Cada imagen nueva debe pesar como maximo 10 MB.',
            'services_hero_image.image' => 'La imagen principal debe ser una imagen valida.',
            'especialidades.*.image.image' => 'Cada imagen de especialidad debe ser una imagen valida.',
            'nuevas.*.image.image' => 'Cada imagen nueva debe ser una imagen valida.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        if (! $this->routeIs('superadmin.personalizacion.servicios.update')) {
            return;
        }

        $validator->after(function (Validator $validator): void {
            $totalBytes = array_sum(array_map(
                fn (UploadedFile $file) => (int) $file->getSize(),
                $this->uploadedImageFiles()
            ));

            if ($totalBytes > self::SUPERADMIN_MAX_TOTAL_BYTES) {
                $validator->errors()->add(
                    'services_upload_total',
                    'Las imagenes seleccionadas superan el limite total permitido de 50 MB. Reduce el tamano o selecciona menos imagenes.'
                );
            }
        });
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function uploadedImageFiles(): array
    {
        $files = [];

        $this->appendUploadedFile($files, $this->file('services_hero_image'));

        foreach ((array) $this->file('especialidades', []) as $payload) {
            if (is_array($payload)) {
                $this->appendUploadedFile($files, $payload['image'] ?? null);
            }
        }

        foreach ((array) $this->file('nuevas', []) as $payload) {
            if (is_array($payload)) {
                $this->appendUploadedFile($files, $payload['image'] ?? null);
            }
        }

        return $files;
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    private function appendUploadedFile(array &$files, mixed $file): void
    {
        if ($file instanceof UploadedFile) {
            $files[] = $file;
        }
    }
}
