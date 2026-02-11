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
    <section class="card p-6">
        <div>
            <p class="text-xs uppercase tracking-widest text-slate-500">Agendar</p>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">Agendar nueva cita</h1>
            <p class="text-slate-600">Elige especialidad, doctor, fecha y hora disponibles.</p>
        </div>
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
              data-tarifa-template="{{ route('api.tarifa.doctor.show', ['id' => 'DOC_ID']) }}"
              data-slots-template="{{ url('/api/doctor/DOC_ID/fecha/FECHA/slots') }}"
              data-laboratorio-id="{{ $laboratorioId ?? '' }}" class="space-y-6">
            @csrf

            <div class="grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label for="especialidad_id" class="form-label">Especialidad</label>
                    <select id="especialidad_id" name="especialidad_id" required class="form-select">
                        <option value="">Seleccione una especialidad</option>
                        @foreach($especialidades as $esp)
                            <option value="{{ $esp->id }}" {{ (string)$selectedEsp === (string)$esp->id ? 'selected' : '' }}>
                                {{ $esp->nombre }}
                            </option>
                        @endforeach
                    </select>
                    @error('especialidad_id')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                </div>

                <div class="md:col-span-2">
                    <label for="doctor_id" class="form-label">Doctor</label>
                    <select id="doctor_id" name="doctor_id" required disabled class="form-select">
                        <option value="">{{ old('especialidad_id') ? 'Cargando…' : 'Seleccione una especialidad primero' }}</option>
                    </select>
                    @error('doctor_id')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror

                    <div id="tarifaPanel" class="mt-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2 text-sm text-slate-600" aria-live="polite" hidden>
                        <span id="tarifaLabel">Tarifa: —</span>
                    </div>
                </div>

                <div>
                    <label for="fecha" class="form-label">Fecha</label>
                    <input id="fecha" type="date" name="fecha"
                           value="{{ old('fecha') }}"
                           min="{{ \Carbon\Carbon::now('America/Guayaquil')->toDateString() }}" required class="form-input">
                    @error('fecha')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                    <div class="text-xs text-slate-500">Solo se permiten fechas a partir de hoy.</div>
                </div>

                <div>
                    <label for="hora" class="form-label">Hora</label>
                    <select id="hora" name="hora" required disabled class="form-select">
                        <option value="">{{ old('doctor_id') && old('fecha') ? 'Cargando horarios…' : 'Seleccione doctor y fecha' }}</option>
                    </select>
                    @error('hora')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                    <div id="horaHelp" class="text-xs text-slate-500">Formato de 24 horas. Se listan solo los horarios disponibles.</div>
                </div>

                <div class="md:col-span-2 lab-section" id="labSection" hidden>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900">Examen de laboratorio</h3>
                                <p class="text-xs text-slate-500">Selecciona el examen y confirma la solicitud.</p>
                            </div>
                        </div>
                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <div>
                                <label for="tipo_examen" class="form-label">Tipo de examen</label>
                                <select id="tipo_examen" name="tipo_examen" data-lab-required data-prep-empty="Selecciona un examen para ver la preparación." class="form-select">
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
                                <label for="prioridad" class="form-label">Prioridad (si aplica)</label>
                                <select id="prioridad" name="prioridad" data-lab-required class="form-select">
                                    <option value="normal" {{ old('prioridad', 'normal') === 'normal' ? 'selected' : '' }}>Normal</option>
                                    <option value="urgente" {{ old('prioridad') === 'urgente' ? 'selected' : '' }}>Urgente</option>
                                </select>
                                @error('prioridad')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                            </div>

                            <div class="md:col-span-2 grid gap-3 sm:grid-cols-2">
                                <div class="rounded-2xl border border-slate-200 bg-white/90 p-3">
                                    <h4 class="text-xs font-semibold text-slate-700">Preparación previa</h4>
                                    <ul class="mt-2 space-y-1 text-xs text-slate-500">
                                        <li><strong>Ayuno:</strong> <span data-lab-prep="ayuno">Selecciona un examen para ver la preparación.</span></li>
                                        <li><strong>Agua:</strong> <span data-lab-prep="agua">Selecciona un examen para ver la preparación.</span></li>
                                        <li><strong>Horario recomendado:</strong> <span data-lab-prep="horario">Selecciona un examen para ver la preparación.</span></li>
                                    </ul>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-white/90 p-3">
                                    <h4 class="text-xs font-semibold text-slate-700">Indicaciones del médico</h4>
                                    <p class="mt-2 text-xs text-slate-500" data-lab-indicaciones>Sin indicaciones adicionales.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <x-ui.form-actions>
                <x-slot:left>
                    <a href="{{ route('paciente.citas') }}" class="btn btn-ghost">Cancelar</a>
                </x-slot>
                <button type="submit" class="btn btn-primary">Registrar cita</button>
            </x-ui.form-actions>
        </form>
    </div>
</div>
@endsection
