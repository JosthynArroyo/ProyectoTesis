<?php

namespace App\Http\Requests\Admin;

use App\Models\Pago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePagoMetodoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) $user && ($user->hasRole('administrador') || $user->hasRole('superadmin'));
    }

    public function rules(): array
    {
        /** @var Pago|null $pago */
        $pago = $this->route('pago');
        $nuevoMetodo = (string) $this->input('metodo_pago');

        $requiereObservacion = $pago
            && $pago->metodo_pago !== null
            && $pago->metodo_pago !== $nuevoMetodo;

        return [
            'metodo_pago' => ['required', Rule::in(Pago::METODOS)],
            'observacion_admin' => [
                $requiereObservacion ? 'required' : 'nullable',
                'string',
                'min:5',
                'max:1500',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'metodo_pago.required' => 'Debe seleccionar un metodo de pago.',
            'metodo_pago.in' => 'El metodo de pago seleccionado no es valido.',
            'observacion_admin.required' => 'Debe indicar una observacion al cambiar un metodo ya definido.',
            'observacion_admin.min' => 'La observacion debe tener al menos 5 caracteres.',
        ];
    }
}
