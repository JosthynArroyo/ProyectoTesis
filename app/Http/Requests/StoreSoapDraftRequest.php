<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSoapDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'subjetivo_motivo' => $this->motivoDesdeCita($this->input('subjetivo_motivo')),
            'sv_talla' => $this->normalizeTalla($this->input('sv_talla')),
        ]);
    }

    public function rules(): array
    {
        return [
            'subjetivo_motivo' => ['nullable', 'string', 'max:5000'],
            'subjetivo_hpi' => ['nullable', 'string', 'max:8000'],
            'subjetivo_ros' => ['nullable', 'string', 'max:8000'],
            'subjetivo_notas' => ['nullable', 'string', 'max:8000'],

            'examen_fisico' => ['nullable', 'string', 'max:8000'],
            'notas_objetivas' => ['nullable', 'string', 'max:8000'],
            'assessment' => ['nullable', 'string', 'max:8000'],
            'plan_general' => ['nullable', 'string', 'max:8000'],
            'plan_seguimiento' => ['nullable', 'string', 'max:8000'],
            'follow_up_date' => ['nullable', 'date'],
            'follow_up_notes' => ['nullable', 'string', 'max:8000'],
            'plan_notas' => ['nullable', 'string', 'max:8000'],

            'sv_ta' => ['nullable', 'string', 'max:20'],
            'sv_fc' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'sv_fr' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sv_temp' => ['nullable', 'numeric', 'min:30', 'max:45'],
            'sv_spo2' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sv_peso' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'sv_talla' => ['nullable', 'numeric', 'min:0', 'max:300'],

            'diagnosticos' => ['nullable', 'array'],
            'diagnosticos.*.tipo' => ['nullable', 'in:principal,secundario,diferencial'],
            'diagnosticos.*.texto' => ['nullable', 'string', 'max:255'],
            'diagnosticos.*.cie10' => ['nullable', 'string', 'max:20'],
        ];
    }

    private function normalizeTalla(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $normalized = str_replace(',', '.', trim((string) $value));
        if (! is_numeric($normalized)) {
            return $value;
        }

        $height = (float) $normalized;

        return $height > 0 && $height <= 3
            ? (string) round($height * 100)
            : $normalized;
    }

    private function motivoDesdeCita(mixed $value): mixed
    {
        if (filled($value)) {
            return $value;
        }

        $cita = $this->route('cita');

        return is_object($cita) && filled($cita->motivo_consulta)
            ? $cita->motivo_consulta
            : $value;
    }
}
