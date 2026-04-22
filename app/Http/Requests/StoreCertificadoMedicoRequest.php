<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCertificadoMedicoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $dias = $this->input('dias_reposo');

        $this->merge([
            'dias_reposo' => $dias === null || $dias === '' ? 0 : $dias,
            'reposo_desde' => $this->emptyToNull($this->input('reposo_desde')),
            'reposo_hasta' => $this->emptyToNull($this->input('reposo_hasta')),
            'observaciones' => $this->emptyToNull($this->input('observaciones')),
        ]);
    }

    public function rules(): array
    {
        return [
            'texto_constancia' => ['required', 'string', 'max:5000'],
            'dias_reposo' => ['nullable', 'integer', 'min:0', 'max:365'],
            'reposo_desde' => ['nullable', 'date'],
            'reposo_hasta' => ['nullable', 'date', 'after_or_equal:reposo_desde'],
            'observaciones' => ['nullable', 'string', 'max:3000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $dias = (int) $this->input('dias_reposo', 0);
            $desde = $this->input('reposo_desde');
            $hasta = $this->input('reposo_hasta');

            if ($dias > 0 && (! $desde || ! $hasta)) {
                $v->errors()->add('reposo_desde', 'Indica el rango de reposo cuando registras dias de reposo.');
            }

            if ($dias === 0 && ($desde || $hasta)) {
                $v->errors()->add('dias_reposo', 'Indica dias de reposo mayor a cero o deja el rango vacio.');
            }

            if ($dias > 0 && $desde && $hasta) {
                try {
                    $rangeDays = Carbon::parse($desde)->diffInDays(Carbon::parse($hasta)) + 1;
                    if ($rangeDays < $dias) {
                        $v->errors()->add('reposo_hasta', 'El rango indicado no cubre los dias de reposo registrados.');
                    }
                } catch (\Throwable) {
                    // Las reglas date reportan el error principal.
                }
            }
        });
    }

    private function emptyToNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
