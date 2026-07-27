@extends('layouts.paciente')
@section('title', $dependiente->exists ? 'Editar Dependiente' : 'Registrar Dependiente')
@section('header-title', $dependiente->exists ? 'Editar Dependiente' : 'Registrar Dependiente')
@section('header-subtitle', 'Completa la información del sub-perfil')

@section('main')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="card p-6">
        <div>
            <p class="text-xs uppercase tracking-widest text-gray-500">Formulario de registro</p>
            <h3 class="mt-2 text-lg font-semibold text-gray-900">Información del Familiar</h3>
            <p class="text-sm text-gray-500">Recuerda que solo se permiten registrar menores de 18 años o adultos mayores de 65 años.</p>
        </div>

        @if($errors->any())
            <x-ui.alert tone="error" class="mt-4">
                <ul class="list-disc list-inside text-sm">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif

        <!-- Alerta Dinámica de Edad en JS -->
        <div id="ageAlert" class="mt-4 hidden">
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-800">
                <div class="flex gap-3">
                    <i class="ri-alert-line text-lg"></i>
                    <div>
                        <p class="font-semibold" id="ageAlertTitle">Registro no permitido</p>
                        <p class="mt-1 text-sm" id="ageAlertText"></p>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" 
              action="{{ $dependiente->exists ? route('paciente.dependientes.update', $dependiente->id) : route('paciente.dependientes.store') }}" 
              class="mt-6 space-y-6"
              id="dependienteForm">
            @csrf
            @if($dependiente->exists)
                @method('PUT')
            @endif

            <div class="grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="form-label" for="nombre">Nombre Completo <span class="text-rose-500">*</span></label>
                    <input class="form-input" type="text" name="nombre" id="nombre" value="{{ old('nombre', $dependiente->nombre) }}" placeholder="Ej. Juan Pérez" required>
                    @error('nombre')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                </div>

                <x-ui.document-fields :model="$dependiente" />

                <div>
                    <label class="form-label" for="fecha_nacimiento">Fecha de Nacimiento <span class="text-rose-500">*</span></label>
                    <input class="form-input" type="date" name="fecha_nacimiento" id="fecha_nacimiento" value="{{ old('fecha_nacimiento', $dependiente->fecha_nacimiento ? $dependiente->fecha_nacimiento->format('Y-m-d') : '') }}" max="{{ date('Y-m-d') }}" required>
                    @error('fecha_nacimiento')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label class="form-label" for="sexo">Sexo</label>
                    <select class="form-select" name="sexo" id="sexo">
                        <option value="">Seleccione sexo</option>
                        <option value="Masculino" {{ old('sexo', $dependiente->sexo) === 'Masculino' ? 'selected' : '' }}>Masculino</option>
                        <option value="Femenino" {{ old('sexo', $dependiente->sexo) === 'Femenino' ? 'selected' : '' }}>Femenino</option>
                        <option value="Otro" {{ old('sexo', $dependiente->sexo) === 'Otro' ? 'selected' : '' }}>Otro</option>
                    </select>
                    @error('sexo')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label class="form-label" for="parentesco">Parentesco <span class="text-rose-500">*</span></label>
                    <select class="form-select" name="parentesco" id="parentesco" required>
                        <option value="">Seleccione parentesco</option>
                        @foreach(\App\Models\Dependiente::PARENTESCOS as $p)
                            <option value="{{ $p }}" {{ old('parentesco', $dependiente->parentesco) === $p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
                        @endforeach
                    </select>
                    @error('parentesco')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                </div>

                <div class="md:col-span-2">
                    <label class="form-label" for="telefono_emergencia">Teléfono de Emergencia</label>
                    <input class="form-input" type="tel" name="telefono_emergencia" id="telefono_emergencia" value="{{ old('telefono_emergencia', $dependiente->telefono_emergencia) }}" placeholder="Ej. 0991234567">
                    @error('telefono_emergencia')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                </div>

                <div class="md:col-span-2">
                    <label class="form-label" for="notas">Notas médicas / Alergias conocidas</label>
                    <textarea class="form-input min-h-[100px] py-2 resize-y" name="notas" id="notas" placeholder="Escribe aquí alergias o condiciones crónicas si las tiene.">{{ old('notas', $dependiente->notas) }}</textarea>
                    @error('notas')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                <a href="{{ route('paciente.dependientes.index') }}" class="btn btn-outline">Cancelar</a>
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="ri-save-line"></i> Guardar familiar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dobInput = document.getElementById('fecha_nacimiento');
    const submitBtn = document.getElementById('submitBtn');
    const ageAlert = document.getElementById('ageAlert');
    const ageAlertText = document.getElementById('ageAlertText');
    const form = document.getElementById('dependienteForm');

    function calculateAge(birthday) {
        const today = new Date();
        const birthDate = new Date(birthday);
        let age = today.getFullYear() - birthDate.getFullYear();
        const m = today.getMonth() - birthDate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        return age;
    }

    function validateAge() {
        if (!dobInput.value) {
            ageAlert.classList.add('hidden');
            submitBtn.disabled = false;
            submitBtn.classList.remove('opacity-60', 'cursor-not-allowed');
            return true;
        }

        const age = calculateAge(dobInput.value);

        if (age >= 18 && age <= 65) {
            ageAlertText.textContent = 'Aviso: El paciente ingresado es mayor de edad (' + age + ' años). Por políticas del sistema, las personas entre 18 y 65 años deben registrar y gestionar su propia cuenta principal.';
            ageAlert.classList.remove('hidden');
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-60', 'cursor-not-allowed');
            return false;
        } else {
            ageAlert.classList.add('hidden');
            submitBtn.disabled = false;
            submitBtn.classList.remove('opacity-60', 'cursor-not-allowed');
            return true;
        }
    }

    dobInput.addEventListener('change', validateAge);
    dobInput.addEventListener('input', validateAge);

    form.addEventListener('submit', function(e) {
        if (!validateAge()) {
            e.preventDefault();
        }
    });

    // Run initially in case of validation back with old input
    validateAge();
});
</script>
@endsection
