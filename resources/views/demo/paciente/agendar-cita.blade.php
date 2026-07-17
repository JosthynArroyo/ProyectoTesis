@extends('layouts.demo')
@section('title', 'Agendar cita - Demo')
@section('body-class', 'paciente-body--crear-cita')
@section('header-title','Agendar cita')
@section('header-subtitle','Selecciona especialidad y horario (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-paciente-demo')
@endsection

@section('main')
<div class="space-y-6">
    <section class="card p-4 bg-white">
        <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-semibold text-gray-900">Progreso del formulario</p>
            <span class="text-xs text-gray-500">Paso 1 de 4</span>
        </div>
        <ol class="mt-3 grid gap-2 sm:grid-cols-4">
            <li class="rounded-xl border border-gray-200 bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-700">1. Especialidad</li>
            <li class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-500">2. Doctor</li>
            <li class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-500">3. Fecha y hora</li>
            <li class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-500">4. Motivo y confirmar</li>
        </ol>
    </section>

    <div class="card p-6 bg-white">
        @if(session('error'))
            <x-ui.alert tone="error">{{ session('error') }}</x-ui.alert>
        @endif

        <form method="POST" action="{{ route('demo.paciente.crear-cita.store') }}" class="space-y-5">
            @csrf

            <!-- Para quién -->
            <section class="panel-form-section">
                <div class="panel-form-section__header">
                    <div class="panel-form-section__heading">
                        <h2 class="panel-form-section__title">
                            <span class="panel-form-section__icon"><i class="ri-user-line"></i></span>
                            ¿Para quién es la cita?
                        </h2>
                        <p class="panel-form-section__hint">Elige si la cita es para ti o para un familiar registrado en tu cuenta.</p>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2 p-4 bg-gray-50/50 rounded-2xl border border-gray-200">
                    <div class="flex items-center gap-2">
                        <input type="radio" name="tipo_paciente" id="paciente_titular" value="titular" checked class="h-4 w-4 text-teal-600 focus:ring-teal-500 border-gray-300">
                        <label for="paciente_titular" class="text-sm font-medium text-gray-700 cursor-pointer">Para mí (María Fernanda Vega)</label>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="radio" name="tipo_paciente" id="paciente_dependiente" value="dependiente" class="h-4 w-4 text-teal-600 focus:ring-teal-500 border-gray-300">
                        <label for="paciente_dependiente" class="text-sm font-medium text-gray-700 cursor-pointer">Para un familiar (Dependiente)</label>
                    </div>

                    <div class="md:col-span-2" id="select_dependiente_container">
                        <label for="dependiente_id" class="form-label">Seleccionar Familiar</label>
                        <select name="dependiente_id" id="dependiente_id" class="form-select">
                            <option value="">-- Selecciona un familiar --</option>
                            <option value="1">Lucía Vega (Hijo/a)</option>
                        </select>
                    </div>
                </div>
            </section>

            <!-- Selección de atención -->
            <section class="panel-form-section border-t border-gray-100 pt-5">
                <div class="panel-form-section__header">
                    <div class="panel-form-section__heading">
                        <h2 class="panel-form-section__title">
                            <span class="panel-form-section__icon"><i class="ri-stethoscope-line"></i></span>
                            Selección de atención
                        </h2>
                        <p class="panel-form-section__hint">Primero elige la especialidad y luego el profesional disponible para esa atención.</p>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label for="especialidad_id" class="form-label">Especialidad</label>
                        <select id="especialidad_id" name="especialidad_id" required class="form-select">
                            <option value="">Seleccione una especialidad</option>
                            <option value="1">Pediatría ($45.00)</option>
                            <option value="2">Medicina General ($35.00)</option>
                            <option value="3">Laboratorio clínico</option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label for="doctor_id" class="form-label">Doctor</label>
                        <select id="doctor_id" name="doctor_id" required class="form-select">
                            <option value="">Seleccione un doctor</option>
                            <option value="11">Dra. Sofía Cárdenas</option>
                            <option value="12">Dr. Andrés Molina</option>
                            <option value="13">Lic. Carlos Pérez</option>
                        </select>
                    </div>
                </div>
            </section>

            <!-- Disponibilidad -->
            <section class="panel-form-section border-t border-gray-100 pt-5">
                <div class="panel-form-section__header">
                    <div class="panel-form-section__heading">
                        <h2 class="panel-form-section__title">
                            <span class="panel-form-section__icon"><i class="ri-calendar-check-line"></i></span>
                            Disponibilidad
                        </h2>
                        <p class="panel-form-section__hint">Solo se muestran fechas válidas y horarios realmente disponibles.</p>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="fecha" class="form-label">Fecha</label>
                        <input id="fecha" type="date" name="fecha" value="{{ now()->addDay()->toDateString() }}" required class="form-input">
                    </div>

                    <div>
                        <label for="hora" class="form-label">Hora</label>
                        <select id="hora" name="hora" required class="form-select">
                            <option value="08:00">08:00</option>
                            <option value="09:00">09:00</option>
                            <option value="10:00">10:00</option>
                            <option value="11:00">11:00</option>
                            <option value="14:00">14:00</option>
                            <option value="15:00">15:00</option>
                        </select>
                    </div>
                </div>
            </section>

            <!-- Motivo de consulta -->
            <section class="panel-form-section border-t border-gray-100 pt-5">
                <div class="panel-form-section__header">
                    <div class="panel-form-section__heading">
                        <h2 class="panel-form-section__title">
                            <span class="panel-form-section__icon"><i class="ri-file-text-line"></i></span>
                            Motivo de consulta
                        </h2>
                        <p class="panel-form-section__hint">Describe el motivo en una sola línea.</p>
                    </div>
                </div>

                <div>
                    <label for="motivo_consulta" class="form-label">Motivo de consulta</label>
                    <input id="motivo_consulta" type="text" name="motivo_consulta" required class="form-input" placeholder="Ej: dolor de garganta y fiebre">
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach(['Fiebre','Dolor de garganta','Dolor abdominal','Tos','Dolor de cabeza','Malestar general'] as $chip)
                            <button type="button" class="chip" onclick="document.getElementById('motivo_consulta').value = '{{ $chip }}';">{{ $chip }}</button>
                        @endforeach
                    </div>
                </div>
            </section>

            <x-ui.form-actions>
                <button type="submit" class="btn btn-primary">
                    <i class="ri-calendar-check-line"></i> Registrar cita
                </button>
            </x-ui.form-actions>
        </form>
    </div>
</div>
@endsection
