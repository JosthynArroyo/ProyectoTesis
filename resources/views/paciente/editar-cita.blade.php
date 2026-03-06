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
        <section class="card p-6">
            <div>
                <p class="text-xs uppercase tracking-widest text-slate-500">Citas</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">Reagendar cita</h1>
                <p class="text-slate-600">Modifica la fecha y hora de tu cita según tu disponibilidad.</p>
            </div>
        </section>

        <div class="card p-6">
            @if ($errors->has('error'))
                <x-ui.alert tone="error">{{ $errors->first('error') }}</x-ui.alert>
            @endif

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div class="card p-4">
                    <div class="text-xs uppercase tracking-widest text-slate-400">Doctor</div>
                    <div class="text-sm font-semibold text-slate-900">{{ optional($cita->doctor)->name ?? 'Sin asignar' }}</div>
                </div>
                <div class="card p-4">
                    <div class="text-xs uppercase tracking-widest text-slate-400">Especialidad</div>
                    <div class="text-sm font-semibold text-slate-900">{{ optional($cita->especialidad)->nombre ?? '—' }}</div>
                </div>
                <div class="card p-4">
                    <div class="text-xs uppercase tracking-widest text-slate-400">Estado</div>
                    <div class="text-sm">
                        <span class="badge {{ $cita->estado === 'pendiente' ? 'warning' : ($cita->estado === 'confirmada' ? 'info' : ($cita->estado === 'realizada' ? 'success' : 'danger')) }}">
                            {{ $cita->estado === 'pendiente' ? 'En revisión' : ($cita->estado === 'no_se_presento' ? 'No se presentó' : ucfirst($cita->estado)) }}
                        </span>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('paciente.editar-cita.update', $cita->id) }}"
                  data-slots-url="{{ route('api.doctor.slots',['doctor'=>$cita->doctor_id,'fecha'=>'__FECHA__']) }}"
                  data-doctor="{{ $cita->doctor_id }}"
                  data-old-hora="{{ old('hora', \Carbon\Carbon::parse($cita->hora)->format('H:i')) }}"
                  class="mt-6 grid gap-4 md:grid-cols-2">
                @csrf
                @method('PUT')

                <div>
                    <label for="fecha" class="form-label">Nueva fecha</label>
                    <input id="fecha" type="date" name="fecha"
                           value="{{ old('fecha', $cita->fecha ? $cita->fecha->format('Y-m-d') : '') }}"
                           min="{{ \Carbon\Carbon::now('America/Guayaquil')->toDateString() }}"
                           required class="form-input">
                    @error('fecha')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                    <div class="text-xs text-slate-500">Solo fechas futuras o la actual.</div>
                </div>

                <div>
                    <label for="hora" class="form-label">Nueva Hora</label>
                    <select id="hora" name="hora" required class="form-select">
                        <option value="">Selecciona una hora</option>
                        <option value="{{ old('hora', \Carbon\Carbon::parse($cita->hora)->format('H:i')) }}" selected>
                            {{ old('hora', \Carbon\Carbon::parse($cita->hora)->format('H:i')) }}
                        </option>
                    </select>
                    @error('hora')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                    <div class="text-xs text-slate-500" id="horaHelp">Formato 24 horas. Se muestran solo horarios disponibles.</div>
                </div>

                <div class="md:col-span-2">
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
                    <div class="mt-1 text-xs text-slate-500">Campo obligatorio, breve y en una sola linea (3-80 caracteres).</div>
                    <div class="mt-2 flex flex-wrap gap-2" data-motivo-chip-group data-target="#motivo_consulta">
                        @foreach(['Fiebre','Dolor de garganta','Dolor abdominal','Tos','Dolor de cabeza','Nauseas','Diarrea','Malestar general'] as $chip)
                            <button type="button" class="chip" data-motivo-chip="{{ $chip }}">{{ $chip }}</button>
                        @endforeach
                    </div>
                </div>

                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
@endsection
