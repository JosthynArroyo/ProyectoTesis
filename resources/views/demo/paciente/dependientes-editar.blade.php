@extends('layouts.demo')
@section('title', 'Editar Dependiente - Demo')
@section('activeSidebar', 'dependientes')
@section('header-title', 'Editar Dependiente')
@section('header-subtitle', 'Completa la información del sub-perfil (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-paciente-demo')
@endsection

@section('main')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="card p-6 bg-white">
        <div>
            <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">Formulario de registro</p>
            <h3 class="mt-2 text-lg font-semibold text-gray-900">Información del Familiar</h3>
            <p class="text-sm text-gray-500">Recuerda que solo se permiten registrar menores de 18 años o adultos mayores de 65 años.</p>
        </div>

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

        <form method="POST" action="{{ route('demo.paciente.dependientes.update', $dependiente->id) }}" class="mt-6 space-y-6" id="dependienteForm">
            @csrf
            @method('PUT')

            <div class="grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="form-label" for="nombre">Nombre Completo <span class="text-rose-500">*</span></label>
                    <input class="form-input" type="text" name="nombre" id="nombre" value="{{ $dependiente->name }}" required>
                </div>

                <div>
                    <label class="form-label" for="dni">Número de Cédula <span class="text-rose-500">*</span></label>
                    <input class="form-input" type="text" name="dni" id="dni" inputmode="numeric" pattern="\d{10}" minlength="10" maxlength="10" value="{{ $dependiente->dni }}" required>
                    <div class="text-xs text-gray-500 mt-1">Exactamente 10 dígitos.</div>
                </div>

                <div>
                    <label class="form-label" for="fecha_nacimiento">Fecha de Nacimiento <span class="text-rose-500">*</span></label>
                    <input class="form-input" type="date" name="fecha_nacimiento" id="fecha_nacimiento" value="{{ $dependiente->birth_date }}" required>
                </div>

                <div>
                    <label class="form-label" for="sexo">Sexo</label>
                    <select class="form-select" name="sexo" id="sexo">
                        <option value="Femenino" selected>Femenino</option>
                        <option value="Masculino">Masculino</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="parentesco">Parentesco <span class="text-rose-500">*</span></label>
                    <select class="form-select" name="parentesco" id="parentesco" required>
                        <option value="hijo/a" @selected(strtolower($dependiente->relationship) === 'hijo/a')>Hijo/a</option>
                        <option value="padre/madre" @selected(strtolower($dependiente->relationship) === 'padre/madre')>Padre/madre</option>
                        <option value="conyuge" @selected(strtolower($dependiente->relationship) === 'conyuge')>Conyuge</option>
                        <option value="otro" @selected(strtolower($dependiente->relationship) === 'otro')>Otro</option>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="form-label" for="telefono_emergencia">Teléfono de Emergencia</label>
                    <input class="form-input" type="tel" name="telefono_emergencia" id="telefono_emergencia" value="0995140927">
                </div>

                <div class="md:col-span-2">
                    <label class="form-label" for="notas">Notas médicas / Alergias conocidas</label>
                    <textarea class="form-input min-h-[100px] py-2 resize-y" name="notas" id="notas" placeholder="Escribe aquí alergias o condiciones crónicas si las tiene."></textarea>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                <a href="{{ route('demo.paciente.dependientes.index') }}" class="btn btn-outline">Cancelar</a>
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
            ageAlertText.textContent = 'Aviso: El familiar ingresado es mayor de edad (' + age + ' años). Por políticas del sistema, las personas entre 18 y 65 años deben registrar su propia cuenta principal.';
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

    validateAge();
});
</script>
@endsection
