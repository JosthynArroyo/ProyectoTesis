{{-- resources/views/paciente/editar-cita.blade.php --}}
@extends('layouts.paciente')
@section('title', 'Reagendar cita')
@section('body-class', 'paciente-body--editar-cita')
@section('header-title','Reagendar cita')
@section('header-subtitle','Ajusta la fecha y hora')

@push('scripts')
    @vite('resources/js/paciente/editar-cita.js')
@endpush

@section('main')
    <div class="space-y-6">
        <div class="panel-action-bar">
            <x-ui.context-pill label="Cita">
                <x-slot:icon><i class="ri-calendar-check-line"></i></x-slot:icon>
                #{{ $cita->id }}
            </x-ui.context-pill>
        </div>

        <div class="card p-6">
            @if ($errors->has('error'))
                <x-ui.alert tone="error">{{ $errors->first('error') }}</x-ui.alert>
            @endif

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div class="card p-4">
                    <div class="text-xs uppercase tracking-widest text-gray-400">Doctor</div>
                    <div class="text-sm font-semibold text-gray-900">{{ optional($cita->doctor)->name ?? 'Sin asignar' }}</div>
                </div>
                <div class="card p-4">
                    <div class="text-xs uppercase tracking-widest text-gray-400">Especialidad</div>
                    <div class="text-sm font-semibold text-gray-900">{{ optional($cita->especialidad)->nombre ?? '-' }}</div>
                </div>
                <div class="card p-4">
                    <div class="text-xs uppercase tracking-widest text-gray-400">Estado</div>
                    <div class="text-sm">
                        <span class="badge {{ $cita->estado === 'pendiente' ? 'warning' : ($cita->estado === 'confirmada' ? 'info' : ($cita->estado === 'realizada' ? 'success' : 'danger')) }}">
                            {{ $cita->estado === 'pendiente' ? 'En revision' : ($cita->estado === 'no_se_presento' ? 'No se presento' : ucfirst($cita->estado)) }}
                        </span>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('paciente.editar-cita.update', $cita->id) }}"
                  data-slots-url="{{ route('api.doctor.slots',['doctor'=>$cita->doctor_id,'fecha'=>'__FECHA__']) }}"
                  data-doctor="{{ $cita->doctor_id }}"
                  data-old-hora="{{ old('hora', \Carbon\Carbon::parse($cita->hora)->format('H:i')) }}"
                  class="mt-6 space-y-5">
                @csrf
                @method('PUT')

                <section class="panel-form-section">
                    <div class="panel-form-section__header">
                        <div class="panel-form-section__heading">
                            <h2 class="panel-form-section__title">
                                <span class="panel-form-section__icon"><i class="ri-calendar-line"></i></span>
                                Nueva disponibilidad
                            </h2>
                            <p class="panel-form-section__hint">Selecciona una nueva fecha y una hora disponible para el mismo profesional.</p>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="fecha" class="form-label">Nueva fecha</label>
                            <div class="relative mt-1">
                                <input id="fecha" type="date" name="fecha"
                                       value="{{ old('fecha', $cita->fecha ? $cita->fecha->format('Y-m-d') : '') }}"
                                       required
                                       class="form-input form-input-native-date mt-0 pr-11"
                                       min="{{ \Carbon\Carbon::now('America/Guayaquil')->toDateString() }}"
                                       placeholder="AAAA-MM-DD"
                                       autocomplete="off">
                                <button id="paciente-reagenda-fecha-trigger" type="button" class="field-action-button" data-native-date-open="#fecha" aria-label="Abrir calendario para la nueva fecha">
                                    <i class="ri-calendar-line"></i>
                                </button>
                            </div>
                            @error('fecha')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                            <div class="text-xs text-gray-500">Solo fechas futuras o la actual.</div>
                        </div>

                        <div>
                            <label for="hora" class="form-label">Nueva hora</label>
                            <select id="hora" name="hora" required class="form-select">
                                <option value="">Selecciona una hora</option>
                                <option value="{{ old('hora', \Carbon\Carbon::parse($cita->hora)->format('H:i')) }}" selected>
                                    {{ old('hora', \Carbon\Carbon::parse($cita->hora)->format('H:i')) }}
                                </option>
                            </select>
                            @error('hora')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                            <div class="text-xs text-gray-500" id="horaHelp">Se muestran solo horarios disponibles en formato de 24 horas.</div>
                        </div>
                    </div>
                </section>

                <section class="panel-form-section">
                    <div class="panel-form-section__header">
                        <div class="panel-form-section__heading">
                            <h2 class="panel-form-section__title">
                                <span class="panel-form-section__icon"><i class="ri-file-text-line"></i></span>
                                Motivo actualizado
                            </h2>
                            <p class="panel-form-section__hint">Mantiene el motivo claro y breve. Puedes usar sugerencias rapidas si te sirven.</p>
                        </div>
                    </div>

                    <div>
                        <label for="motivo_consulta" class="form-label">Motivo de consulta</label>
                        <input id="motivo_consulta"
                               type="text"
                               name="motivo_consulta"
                               value="{{ old('motivo_consulta', $cita->motivo_consulta) }}"
                               required
                               minlength="3"
                               maxlength="80"
                               class="form-input"
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

                <x-ui.form-actions>
                    <x-slot:left>
                        <a href="{{ route('paciente.citas') }}" class="btn btn-ghost">
                            <i class="ri-arrow-left-line"></i> Cancelar
                        </a>
                    </x-slot>
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-save-line"></i> Guardar cambios
                    </button>
                </x-ui.form-actions>
            </form>
        </div>
    </div>
@endsection
