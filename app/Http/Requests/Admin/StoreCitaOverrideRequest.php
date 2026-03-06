<?php

namespace App\Http\Requests\Admin;

use App\Support\ValidationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCitaOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return (bool) $user && ($user->hasRole('administrador') || $user->hasRole('superadmin'));
    }

    public function rules(): array
    {
        return [
            'paciente_id' => ['required', 'exists:users,id'],
            'doctor_id' => ['required', 'exists:users,id'],
            'especialidad_id' => ['required', 'exists:especialidades,id'],
            'fecha' => ['required', 'date'],
            'hora' => ['required', 'date_format:H:i'],
            'motivo_consulta' => ValidationRules::motivoConsulta(),
            'forzar_bloqueo' => ['nullable', 'boolean'],
            'override_reason' => ['nullable', 'string', 'max:1500'],
            'tipo_examen' => ['nullable', 'string', 'max:255'],
            'prioridad' => ['nullable', Rule::in(['normal', 'urgente'])],
            'indicaciones' => ['nullable', 'string', 'max:2000'],
            'preparacion' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
