@extends('layouts.doctor')
@section('title', 'Nueva Orden de Laboratorio')
@section('activeSidebar', 'citas')
@section('header-title','Crear orden de laboratorio')
@section('header-subtitle','Agenda la cita y registra el examen')

@push('scripts')
  @vite('resources/js/doctor/laboratorio-create.js')
@endpush

@section('main')
<section class="space-y-6">
  <div class="card p-6">
    <div>
      <p class="text-xs uppercase tracking-widest text-slate-500">Laboratorio</p>
      <h1 class="mt-2 text-2xl font-semibold text-slate-900">Crear orden</h1>
      <p class="text-slate-600">Agenda la cita de laboratorio y registra los detalles del examen.</p>
    </div>
  </div>


  <div class="card p-6">
    <form
      method="POST"
      action="{{ route('doctor.laboratorio.store') }}"
      class="space-y-4"
      data-slots-template="{{ url('/api/doctor/DOC_ID/fecha/FECHA/slots') }}"
      data-old-hora="{{ old('hora') }}"
    >
      @csrf

      <div class="grid gap-4 md:grid-cols-2">
        <div>
          <label for="paciente_id" class="form-label">Paciente</label>
          <select id="paciente_id" name="paciente_id" required class="form-select">
            <option value="">Seleccionar paciente</option>
            @foreach($pacientes as $paciente)
              <option value="{{ $paciente->id }}" {{ (string) old('paciente_id', $prefPaciente) === (string) $paciente->id ? 'selected' : '' }}>
                {{ $paciente->name }}{{ $paciente->email ? ' - '.$paciente->email : '' }}
              </option>
            @endforeach
          </select>
          @error('paciente_id')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <div>
          <label for="doctor_id" class="form-label">Laboratorio</label>
          @if($doctoresLab->count() === 1 && $defaultLabId)
            <input type="hidden" id="doctor_id" name="doctor_id" value="{{ $defaultLabId }}">
            <input id="doctor_label" type="text" value="{{ $defaultLabName ?? 'Laboratorio Clinico' }}" disabled class="form-input">
          @else
            <select id="doctor_id" name="doctor_id" required class="form-select">
              <option value="">Seleccionar laboratorio</option>
              @foreach($doctoresLab as $doctor)
                <option value="{{ $doctor->id }}" {{ (string) old('doctor_id', $defaultLabId) === (string) $doctor->id ? 'selected' : '' }}>
                  {{ $doctor->name }}
                </option>
              @endforeach
            </select>
            <div class="text-xs text-slate-500">Si creas un usuario de laboratorio llamado "Laboratorio Clinico" se autoselecciona.</div>
          @endif
          @error('doctor_id')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
          @if($labId && $doctoresLab->isEmpty())
            <div class="text-xs text-rose-600">No hay usuarios de laboratorio con esa especialidad.</div>
          @endif
        </div>

        <div>
          <label for="fecha" class="form-label">Fecha</label>
          <input
            id="fecha"
            type="date"
            name="fecha"
            value="{{ old('fecha') }}"
            min="{{ \Carbon\Carbon::now('America/Guayaquil')->toDateString() }}"
            required
            class="form-input"
          >
          @error('fecha')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <div>
          <label for="hora" class="form-label">Hora</label>
          <select id="hora" name="hora" required disabled class="form-select">
            <option value="">{{ old('doctor_id', $defaultLabId) && old('fecha') ? 'Cargando horarios...' : 'Seleccione laboratorio y fecha' }}</option>
          </select>
          <div id="horaHelp" class="text-xs text-slate-500">Se muestran solo los horarios configurados y realmente disponibles.</div>
          @error('hora')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <div class="md:col-span-2">
          <label for="motivo_consulta" class="form-label">Motivo de consulta</label>
          <input
            id="motivo_consulta"
            type="text"
            name="motivo_consulta"
            value="{{ old('motivo_consulta') }}"
            required
            minlength="3"
            maxlength="80"
            class="form-input"
            placeholder="Ej: dolor de garganta"
          >
          @error('motivo_consulta')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
          <div class="mt-1 text-xs text-slate-500">Campo obligatorio, breve y en una sola linea (3-80 caracteres).</div>
          <div class="mt-2 flex flex-wrap gap-2" data-motivo-chip-group data-target="#motivo_consulta">
            @foreach(['Fiebre','Dolor de garganta','Dolor abdominal','Tos','Dolor de cabeza','Nauseas','Diarrea','Malestar general'] as $chip)
              <button type="button" class="chip" data-motivo-chip="{{ $chip }}">{{ $chip }}</button>
            @endforeach
          </div>
        </div>

        <div class="md:col-span-2">
          <label for="tipo_examen" class="form-label">Tipo de examen</label>
          <input id="tipo_examen" type="text" name="tipo_examen" value="{{ old('tipo_examen') }}" placeholder="Ej: Hemograma completo" required class="form-input">
          @error('tipo_examen')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <div>
          <label for="prioridad" class="form-label">Prioridad</label>
          <select id="prioridad" name="prioridad" required class="form-select">
            <option value="">Seleccionar prioridad</option>
            <option value="normal" {{ old('prioridad') === 'normal' ? 'selected' : '' }}>Normal</option>
            <option value="urgente" {{ old('prioridad') === 'urgente' ? 'selected' : '' }}>Urgente</option>
          </select>
          @error('prioridad')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <div class="md:col-span-2">
          <label for="preparacion" class="form-label">Preparacion previa</label>
          <textarea id="preparacion" name="preparacion" rows="3" required class="form-textarea">{{ old('preparacion') }}</textarea>
          @error('preparacion')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <div class="md:col-span-2">
          <label for="indicaciones" class="form-label">Indicaciones</label>
          <textarea id="indicaciones" name="indicaciones" rows="3" required class="form-textarea">{{ old('indicaciones') }}</textarea>
          @error('indicaciones')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
      </div>

      <x-ui.form-actions>
        <x-slot:left>
          <a class="btn btn-ghost" href="{{ route('doctor.citas') }}">Cancelar</a>
        </x-slot>
        <button type="submit" class="btn btn-primary">
          <i class="ri-save-line"></i> Crear orden
        </button>
      </x-ui.form-actions>
    </form>
  </div>
</section>
@endsection

