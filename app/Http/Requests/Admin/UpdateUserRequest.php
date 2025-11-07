<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('user')->id ?? null;

        return [
            'name'             => ['required','string','max:120'],
            'email'            => ['required','email','max:160',"unique:users,email,$id"],
            'telefono'         => ['nullable','string','max:40'],
            'dni'              => ['nullable','string','max:30'],
            'direccion'        => ['nullable','string','max:255'],
            'fecha_nacimiento' => ['nullable','date'],
            'sexo'             => ['nullable','in:M,F,O'],
            'precio_consulta'  => ['nullable','numeric','min:0','max:999999.99'],
            'moneda'           => ['nullable','string','max:10'],
            'status'           => ['required','in:active,inactive,blocked'],
            'role_id'          => ['nullable','integer','exists:roles,id'],
        ];
    }
}
