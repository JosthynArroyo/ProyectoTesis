<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AnularPagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return (bool) $user && ($user->hasRole('administrador') || $user->hasRole('superadmin'));
    }

    public function rules(): array
    {
        return [
            'observacion_admin' => ['required', 'string', 'min:5', 'max:1500'],
        ];
    }

    public function messages(): array
    {
        return [
            'observacion_admin.required' => 'Debe indicar el motivo de anulación.',
            'observacion_admin.min' => 'El motivo debe tener al menos 5 caracteres.',
        ];
    }
}
