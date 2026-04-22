<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RejectPagoRequest extends FormRequest
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
            'observacion_admin.required' => 'La observación es obligatoria para rechazar un pago.',
            'observacion_admin.min' => 'La observación debe tener al menos 5 caracteres.',
        ];
    }
}
