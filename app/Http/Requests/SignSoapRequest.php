<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SignSoapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subjetivo_motivo' => ['required', 'string', 'max:5000'],
            'subjetivo_hpi' => ['required', 'string', 'max:8000'],
            'subjetivo_ros' => ['required', 'string', 'max:8000'],
            'subjetivo_notas' => ['required', 'string', 'max:8000'],

            'examen_fisico' => ['required', 'string', 'max:8000'],
            'notas_objetivas' => ['required', 'string', 'max:8000'],
            'assessment' => ['required', 'string', 'max:8000'],
            'plan_general' => ['required', 'string', 'max:8000'],
            'plan_seguimiento' => ['required', 'string', 'max:8000'],
            'plan_notas' => ['required', 'string', 'max:8000'],

            'sv_ta' => ['required', 'string', 'max:20'],
            'sv_fc' => ['required', 'numeric', 'min:0', 'max:300'],
            'sv_fr' => ['required', 'numeric', 'min:0', 'max:100'],
            'sv_temp' => ['required', 'numeric', 'min:30', 'max:45'],
            'sv_spo2' => ['required', 'numeric', 'min:0', 'max:100'],
            'sv_peso' => ['required', 'numeric', 'min:0', 'max:500'],
            'sv_talla' => ['required', 'numeric', 'min:0', 'max:300'],

            'diagnosticos' => ['required', 'array', 'min:1'],
            'diagnosticos.*.tipo' => ['nullable', 'in:principal,secundario,diferencial'],
            'diagnosticos.*.texto' => ['nullable', 'string', 'max:255'],
            'diagnosticos.*.cie10' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $diagnosticos = $this->input('diagnosticos', []);
            $tienePrincipal = false;
            $tieneDiagnosticos = false;

            foreach ($diagnosticos as $index => $diag) {
                $tipo = trim((string) ($diag['tipo'] ?? ''));
                $texto = trim((string) ($diag['texto'] ?? ''));
                $cie10 = trim((string) ($diag['cie10'] ?? ''));

                if ($tipo === '' && $texto === '' && $cie10 === '') {
                    continue;
                }

                $tieneDiagnosticos = true;

                if ($tipo === '') {
                    $v->errors()->add("diagnosticos.$index.tipo", 'Selecciona el tipo de diagnostico.');
                }

                if ($texto === '') {
                    $v->errors()->add("diagnosticos.$index.texto", 'Ingresa el detalle del diagnostico.');
                }

                if ($tipo === 'principal' && $texto !== '') {
                    $tienePrincipal = true;
                }
            }

            if (! $tieneDiagnosticos) {
                $v->errors()->add('diagnosticos', 'Debes registrar al menos un diagnostico.');
            }

            if (! $tienePrincipal) {
                $v->errors()->add('diagnosticos', 'Debes registrar al menos un diagnostico principal.');
            }
        });
    }
}
