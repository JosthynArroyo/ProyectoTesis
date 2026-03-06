<?php

namespace App\Http\Requests\Paciente;

use App\Models\Pago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitPagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('paciente');
    }

    public function rules(): array
    {
        /** @var Pago|null $pago */
        $pago = $this->route('pago');
        $metodo = (string) $this->input('metodo_pago');

        $requiereComprobante = $metodo === Pago::METODO_TRANSFERENCIA
            && (
                !$pago
                || !$pago->comprobante_path
                || $pago->estado === Pago::ESTADO_RECHAZADO
            );

        $comprobanteRules = $metodo === Pago::METODO_EFECTIVO
            ? ['prohibited']
            : [
                $requiereComprobante ? 'required' : 'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:5120',
            ];

        return [
            'metodo_pago' => ['required', Rule::in(Pago::METODOS)],
            'referencia_transaccion' => ['nullable', 'string', 'max:120'],
            'comprobante' => $comprobanteRules,
        ];
    }

    public function messages(): array
    {
        return [
            'metodo_pago.required' => 'Seleccione un metodo de pago.',
            'metodo_pago.in' => 'El metodo de pago seleccionado no es valido.',
            'referencia_transaccion.max' => 'La referencia no debe superar 120 caracteres.',
            'comprobante.required' => 'Debe adjuntar el comprobante para transferencias.',
            'comprobante.file' => 'El comprobante debe ser un archivo valido.',
            'comprobante.mimes' => 'El comprobante debe ser JPG, JPEG, PNG, WEBP o PDF.',
            'comprobante.max' => 'El comprobante no debe superar 5 MB.',
            'comprobante.prohibited' => 'El pago en efectivo no requiere comprobante.',
        ];
    }
}
