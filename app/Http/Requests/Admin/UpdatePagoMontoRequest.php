<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePagoMontoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return (bool) $user && ($user->hasRole('administrador') || $user->hasRole('superadmin'));
    }

    public function rules(): array
    {
        return [
            'monto' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'moneda' => ['nullable', 'string', 'size:3'],
        ];
    }
}
