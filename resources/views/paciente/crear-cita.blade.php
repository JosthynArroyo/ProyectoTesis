@extends('layouts.paciente')
@section('title', 'Agendar cita')
@section('body-class', 'paciente-body--crear-cita')
@section('header-title','Agendar cita')
@section('header-subtitle','Selecciona especialidad y horario')

@push('scripts')
    @vite('resources/js/paciente/crear-cita.js')
@endpush

@section('main')
<div class="space-y-6">
    <section class="card p-4">
        <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-semibold text-gray-900">Progreso del formulario</p>
            <span class="text-xs text-gray-500" data-step-summary>Paso 1 de 4</span>
        </div>
        <ol class="mt-3 grid gap-2 sm:grid-cols-4" data-cita-stepper>
            <li class="rounded-xl border border-gray-200 bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-700" data-step-item="1">1. Especialidad</li>
            <li class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-500" data-step-item="2">2. Doctor</li>
            <li class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-500" data-step-item="3">3. Fecha y hora</li>
            <li class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-500" data-step-item="4">4. Motivo y confirmar</li>
        </ol>
    </section>

    @php($selectedEsp = old('especialidad_id', $prefEspecialidad ?? ''))
    @php($labExamenes = $labExamenes ?? [])

    <div class="card p-6">
        @if ($errors->has('error'))
            <x-ui.alert tone="error">{{ $errors->first('error') }}</x-ui.alert>
        @endif

        <form method="POST" action="{{ route('paciente.crear-cita.store') }}"
              data-endpoint-template="{{ route('especialidades.doctores', ['especialidad' => 'ESP_ID']) }}"
              data-old-esp="{{ $selectedEsp }}"
              data-old-doc="{{ old('doctor_id') }}"
              data-old-hora="{{ old('hora') }}"
              data-old-hold-token="{{ old('hold_token') }}"
              data-tarifa-template="{{ route('api.tarifa.doctor.show', ['id' => 'DOC_ID']) }}"
              data-slots-template="{{ url('/api/doctor/DOC_ID/fecha/FECHA/slots') }}"
              data-slot-hold-url="{{ route('api.slot-holds.store') }}"
              data-laboratorio-id="{{ $laboratorioId ?? '' }}"
              class="space-y-5">
            @csrf
            <input type="hidden" name="hold_token" value="{{ old('hold_token') }}">

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

                <div class="grid gap-4 md:grid-cols-2 p-4 bg-gray-50/50 rounded-2xl border border-gray-200 para-quien-cita-container">
                    <div class="flex items-center gap-2">
                        <input type="radio" name="tipo_paciente" id="paciente_titular" value="titular" checked class="h-4 w-4 text-teal-600 focus:ring-teal-500 border-gray-300">
                        <label for="paciente_titular" class="text-sm font-medium text-gray-700 cursor-pointer">Para mí ({{ Auth::user()->name }})</label>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="radio" name="tipo_paciente" id="paciente_dependiente" value="dependiente" class="h-4 w-4 text-teal-600 focus:ring-teal-500 border-gray-300">
                        <label for="paciente_dependiente" class="text-sm font-medium text-gray-700 cursor-pointer">Para un familiar (Dependiente)</label>
                    </div>

                    <div class="md:col-span-2 hidden" id="select_dependiente_container">
                        <label for="dependiente_id" class="form-label">Seleccionar Familiar</label>
                        <select name="dependiente_id" id="dependiente_id" class="form-select">
                            <option value="">-- Selecciona un familiar --</option>
                            @foreach($dependientes as $dep)
                                <option value="{{ $dep->id }}" {{ old('dependiente_id') == $dep->id ? 'selected' : '' }}>{{ $dep->nombre }} ({{ ucfirst($dep->parentesco) }})</option>
                            @endforeach
                        </select>
                        @error('dependiente_id')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                        <p class="mt-2 text-xs text-gray-500">
                            ¿No aparece tu familiar? <a href="{{ route('paciente.dependientes.create') }}" class="text-teal-600 font-semibold underline hover:text-teal-700">Registra un nuevo familiar aquí</a>.
                        </p>
                    </div>
                </div>
            </section>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const radioTitular = document.getElementById('paciente_titular');
                const radioDependiente = document.getElementById('paciente_dependiente');
                const container = document.getElementById('select_dependiente_container');
                const selectDep = document.getElementById('dependiente_id');

                function toggleContainer() {
                    if (radioDependiente.checked) {
                        container.classList.remove('hidden');
                        selectDep.setAttribute('required', 'required');
                    } else {
                        container.classList.add('hidden');
                        selectDep.removeAttribute('required');
                        selectDep.value = '';
                    }
                }

                radioTitular.addEventListener('change', toggleContainer);
                radioDependiente.addEventListener('change', toggleContainer);

                // Initialize state in case of redirect/old input
                if (document.getElementById('dependiente_id').value !== '') {
                    radioDependiente.checked = true;
                    toggleContainer();
                }
            });
            </script>

            <section class="panel-form-section">
                <div class="panel-form-section__header">
                    <div class="panel-form-section__heading">
                        <h2 class="panel-form-section__title">
                            <span class="panel-form-section__icon"><i class="ri-stethoscope-line"></i></span>
                            Selección de atención
                        </h2>
                        <p class="panel-form-section__hint">Primero elige la especialidad y luego el profesional disponible para esa atencion.</p>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label for="especialidad_id" class="form-label">Especialidad</label>
                        <select id="especialidad_id" name="especialidad_id" required class="form-select" data-step-field="1">
                            <option value="">Seleccione una especialidad</option>
                            @foreach($especialidades as $esp)
                                <option value="{{ $esp->id }}" {{ (string) $selectedEsp === (string) $esp->id ? 'selected' : '' }}>
                                    {{ $esp->nombre }}
                                </option>
                            @endforeach
                        </select>
                        @error('especialidad_id')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                    </div>

                    <div class="md:col-span-2">
                        <label for="doctor_id" class="form-label">Doctor</label>
                        <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                            <select id="doctor_id" name="doctor_id" required disabled class="form-select" data-step-field="2">
                                <option value="">{{ old('especialidad_id') ? 'Cargando...' : 'Seleccione una especialidad primero' }}</option>
                            </select>
                            <button id="doctorProfileButton" type="button" class="btn btn-outline w-full sm:w-auto" data-doctor-profile-open disabled>
                                <i class="ri-user-search-line"></i> Ver perfil
                            </button>
                        </div>
                        @error('doctor_id')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror

                        <div id="tarifaPanel" class="mt-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2 text-sm text-gray-600" aria-live="polite" hidden>
                            <span id="tarifaLabel">Tarifa: -</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="panel-form-section">
                <div class="panel-form-section__header">
                    <div class="panel-form-section__heading">
                        <h2 class="panel-form-section__title">
                            <span class="panel-form-section__icon"><i class="ri-calendar-check-line"></i></span>
                            Disponibilidad
                        </h2>
                        <p class="panel-form-section__hint">Solo se muestran fechas validas y horarios realmente disponibles para el profesional elegido.</p>
                    </div>
                    <x-ui.context-pill label="Horario">
                        <x-slot:icon><i class="ri-time-line"></i></x-slot:icon>
                        24 h
                    </x-ui.context-pill>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="fecha" class="form-label">Fecha</label>
                        <div class="relative mt-1">
                            <input id="fecha" type="date" name="fecha"
                                   value="{{ old('fecha') }}"
                                   required
                                   class="form-input form-input-native-date mt-0 pr-11"
                                   data-step-field="3"
                                   min="{{ \Carbon\Carbon::now('America/Guayaquil')->toDateString() }}"
                                   placeholder="AAAA-MM-DD"
                                   autocomplete="off">
                            <button id="paciente-cita-fecha-trigger" type="button" class="field-action-button" data-native-date-open="#fecha" aria-label="Abrir calendario para la fecha de la cita">
                                <i class="ri-calendar-line"></i>
                            </button>
                        </div>
                        @error('fecha')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                        <div class="text-xs text-gray-500">Solo se permiten fechas a partir de hoy.</div>
                    </div>

                    <div>
                        <label for="hora" class="form-label">Hora</label>
                        <select id="hora" name="hora" required disabled class="form-select" data-step-field="3">
                            <option value="">{{ old('doctor_id') && old('fecha') ? 'Cargando horarios...' : 'Seleccione doctor y fecha' }}</option>
                        </select>
                        @error('hora')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                        <div id="horaHelp" class="text-xs text-gray-500">Se listan solo horarios disponibles y en formato de 24 horas.</div>
                    </div>
                </div>
            </section>

            <section class="panel-form-section">
                <div class="panel-form-section__header">
                    <div class="panel-form-section__heading">
                        <h2 class="panel-form-section__title">
                            <span class="panel-form-section__icon"><i class="ri-file-text-line"></i></span>
                            Motivo de consulta
                        </h2>
                        <p class="panel-form-section__hint">Describe el motivo en una sola linea. Puedes usar sugerencias rapidas si te ayudan.</p>
                    </div>
                </div>

                <div>
                    <label for="motivo_consulta" class="form-label">Motivo de consulta</label>
                    <input id="motivo_consulta"
                           type="text"
                           name="motivo_consulta"
                           value="{{ old('motivo_consulta') }}"
                           required
                           minlength="3"
                           maxlength="80"
                           class="form-input"
                           data-step-field="4"
                           placeholder="Ej: dolor de garganta">
                    @error('motivo_consulta')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                    <div class="mt-1 text-xs text-gray-500">Campo obligatorio, breve y en una sola linea (3-80 caracteres).</div>
                    <div class="mt-2 flex flex-wrap gap-2" data-motivo-chip-group data-target="#motivo_consulta">
                        @foreach(['Fiebre','Dolor de garganta','Dolor abdominal','Tos','Dolor de cabeza','Nauseas','Diarrea','Malestar general'] as $chip)
                            <button type="button" class="chip" data-motivo-chip="{{ $chip }}">{{ $chip }}</button>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="panel-form-section lab-section" id="labSection" hidden>
                <div class="panel-form-section__header">
                    <div class="panel-form-section__heading">
                        <h2 class="panel-form-section__title">
                            <span class="panel-form-section__icon"><i class="ri-flask-line"></i></span>
                            Examen de laboratorio
                        </h2>
                        <p class="panel-form-section__hint">Completa estos datos solo si la especialidad corresponde a laboratorio.</p>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="tipo_examen" class="form-label">Tipo de examen</label>
                        <select id="tipo_examen" name="tipo_examen" data-lab-required data-prep-empty="Selecciona un examen para ver la preparacion." class="form-select">
                            <option value="">Seleccionar examen</option>
                            @foreach($labExamenes as $examen)
                                @php($value = $examen['value'] ?? '')
                                @php($prep = $examen['prep'] ?? [])
                                <option value="{{ $value }}"
                                    {{ old('tipo_examen') === $value ? 'selected' : '' }}
                                    data-prep-ayuno="{{ $prep['ayuno'] ?? '' }}"
                                    data-prep-agua="{{ $prep['agua'] ?? '' }}"
                                    data-prep-horario="{{ $prep['horario'] ?? '' }}">
                                    {{ $examen['label'] ?? $value }}
                                </option>
                            @endforeach
                        </select>
                        @error('tipo_examen')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                    </div>

                    <div>
                        <label for="prioridad" class="form-label">Prioridad</label>
                        <select id="prioridad" name="prioridad" data-lab-required class="form-select">
                            <option value="normal" {{ old('prioridad', 'normal') === 'normal' ? 'selected' : '' }}>Normal</option>
                            <option value="urgente" {{ old('prioridad') === 'urgente' ? 'selected' : '' }}>Urgente</option>
                        </select>
                        @error('prioridad')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                    </div>

                    <div class="md:col-span-2 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl border border-gray-200 bg-white/90 p-3">
                            <h3 class="text-xs font-semibold text-gray-700">Preparacion previa</h3>
                            <ul class="mt-2 space-y-1 text-xs text-gray-500">
                                <li><strong>Ayuno:</strong> <span data-lab-prep="ayuno">Selecciona un examen para ver la preparacion.</span></li>
                                <li><strong>Agua:</strong> <span data-lab-prep="agua">Selecciona un examen para ver la preparacion.</span></li>
                                <li><strong>Horario recomendado:</strong> <span data-lab-prep="horario">Selecciona un examen para ver la preparacion.</span></li>
                            </ul>
                        </div>

                        <div class="rounded-2xl border border-gray-200 bg-white/90 p-3">
                            <h3 class="text-xs font-semibold text-gray-700">Indicaciones del medico</h3>
                            <p class="mt-2 text-xs text-gray-500" data-lab-indicaciones>Sin indicaciones adicionales.</p>
                        </div>
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

@push('modals')
<div id="doctorProfileModal" class="modal" data-doctor-profile-modal aria-hidden="true">
    <div class="modal-backdrop" data-doctor-profile-close></div>
    <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="doctorProfileTitle" tabindex="-1">
        <article class="card w-full max-w-3xl overflow-hidden">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 p-4 sm:p-6">
                <div class="flex min-w-0 items-center gap-4">
                    <div class="flex-none">
                        <img
                            id="doctorProfileAvatarImg"
                            data-doctor-profile-avatar
                            src=""
                            alt="Foto del doctor"
                            class="doctor-avatar-photo h-20 w-20 rounded-lg border border-gray-200 object-cover sm:h-24 sm:w-24"
                            loading="lazy"
                            decoding="async">
                        <div
                            id="doctorProfileAvatarFallback"
                            data-doctor-profile-fallback
                            class="hidden h-20 w-20 flex-none items-center justify-center rounded-lg bg-teal-100 text-2xl font-bold text-teal-800 border border-teal-200 sm:h-24 sm:w-24">
                            D
                        </div>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-widest text-gray-500">Perfil profesional</p>
                        <h2 id="doctorProfileTitle" class="mt-1 break-words text-xl font-semibold text-gray-900 sm:text-2xl" data-doctor-profile-name>Doctor</h2>
                        <p class="mt-1 text-sm text-gray-500" data-doctor-profile-role>Especialista activo</p>
                    </div>
                </div>
                <button type="button" class="btn btn-ghost px-3" data-doctor-profile-close aria-label="Cerrar perfil del doctor">
                    <i class="ri-close-line"></i>
                </button>
            </div>

            <div class="grid gap-4 p-4 sm:grid-cols-2 sm:p-6">
                <div class="rounded-xl border border-gray-200 bg-gray-50/80 p-4">
                    <div class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                        <i class="ri-stethoscope-line text-teal-600"></i>
                        Especialidades
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2" data-doctor-profile-specialties></div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50/80 p-4">
                    <div class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                        <i class="ri-money-dollar-circle-line text-teal-600"></i>
                        Tarifa
                    </div>
                    <p class="mt-3 text-sm text-gray-600" data-doctor-profile-price>Tarifa no configurada</p>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <div class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                        <i class="ri-phone-line text-teal-600"></i>
                        Contacto
                    </div>
                    <p class="mt-3 break-words text-sm text-gray-600" data-doctor-profile-phone>No registrado</p>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <div class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                        <i class="ri-map-pin-line text-teal-600"></i>
                        Ubicacion
                    </div>
                    <p class="mt-3 break-words text-sm text-gray-600" data-doctor-profile-address>No registrada</p>
                </div>
            </div>

            <div class="border-t border-gray-200 bg-gray-50/80 px-4 py-3 text-sm text-gray-500 sm:px-6">
                Selecciona fecha y hora despues de revisar la disponibilidad del profesional.
            </div>
        </article>
    </div>
</div>
@endpush
