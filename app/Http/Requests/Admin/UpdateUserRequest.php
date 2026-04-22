<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use App\Support\DateField;
use App\Support\ValidationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        DateField::mergeIntoRequest($this, 'fecha_nacimiento');
    }

    public function rules(): array
    {
        $id = $this->route('user')?->id;
        $roleId = (int) $this->input('role_id');
        $roleName = $roleId ? Role::where('id', $roleId)->value('name') : null;
        $isDoctor = $roleName === 'doctor';
        $isLaboratorio = $roleName === 'laboratorio';

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ValidationRules::emailUnique('users', $id),
            'telefono' => ValidationRules::telefono(),
            'dni' => ValidationRules::cedulaUnique('users', $id),
            'direccion' => ['required', 'string', 'max:255'],
            'fecha_nacimiento' => ValidationRules::birthDate(),
            'sexo' => ['required', 'in:Masculino,Femenino,Otro'],
            'especialidad_id' => ['nullable', Rule::requiredIf($isDoctor), 'integer', 'exists:especialidades,id'],
            'precio_consulta' => ['nullable', Rule::requiredIf($isDoctor || $isLaboratorio), 'numeric', 'min:0', 'max:999999.99'],
            'moneda' => ['nullable', Rule::requiredIf($isDoctor || $isLaboratorio), 'in:USD'],
            'status' => ['required', 'in:active,inactive,blocked'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ];
    }
}
