<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();
        if (!$u) return false;
        return $u->roles()->where('name','administrador')->exists();
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $id   = $user?->id;

        return [
            'name'             => ['required','string','max:255'],
            'email'            => ['required','email','max:255', Rule::unique('users','email')->ignore($id)],
            'password'         => [$id ? 'nullable' : 'required','string','min:8'],
            'telefono'         => ['nullable','string','max:30'],
            'dni'              => ['nullable','string','max:20', Rule::unique('users','dni')->ignore($id)],
            'direccion'        => ['nullable','string','max:255'],
            'fecha_nacimiento' => ['nullable','date','before:today'],
            'sexo'             => ['nullable','in:Masculino,Femenino,Otro'],
            'precio_consulta'  => ['nullable','numeric','min:0','max:99999999.99'],
            'moneda'           => ['nullable','in:USD'],
            'status'           => ['nullable','in:active,inactive,blocked'],
            'role_id'          => ['required','exists:roles,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'    => 'El nombre es obligatorio.',
            'email.required'   => 'El correo es obligatorio.',
            'email.unique'     => 'Este correo ya está registrado.',
            'password.min'     => 'La contraseña debe tener al menos 8 caracteres.',
            'role_id.required' => 'Selecciona un rol.',
        ];
    }
}
