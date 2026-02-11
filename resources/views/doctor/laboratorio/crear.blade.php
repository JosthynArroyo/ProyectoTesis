@extends('layouts.doctor')
@section('title', 'Nueva Orden de Laboratorio')
@section('activeSidebar', 'citas')
@section('header-title','Crear orden de laboratorio')
@section('header-subtitle','Agenda la cita y registra el examen')

@section('content')
<section class="space-y-6">
  <div class="card p-6">
    <div>
      <p class="text-xs uppercase tracking-widest text-slate-500">Laboratorio</p>
      <h1 class="mt-2 text-2xl font-semibold text-slate-900">Crear orden</h1>
      <p class="text-slate-600">Agenda la cita de laboratorio y registra los detalles del examen.</p>
    </div>
  </div>

  @if ($errors->any())
    <x-ui.alert tone="error">{{ $errors->first() }}</x-ui.alert>
  @endif

  <div class="card p-6">
    <form method="POST" action="{{ route('doctor.laboratorio.store') }}" class="space-y-4">
      @csrf
      <div class="grid gap-4 md:grid-cols-2">
        <div>
          <label for="paciente_id" class="form-label">Paciente</label>
          <select id="paciente_id" name="paciente_id" required class="form-select">
            <option value="">Seleccionar paciente</option>
            @foreach($pacientes as $paciente)
              <option value="{{ $paciente->id }}" {{ (string)old('paciente_id', $prefPaciente) === (string)$paciente->id ? 'selected' : '' }}>
                {{ $paciente->name }} {{ $paciente->email ? ' - '.$paciente->email : '' }}
              </option>
            @endforeach
          </select>
          @error('paciente_id')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <div>
          <label for="doctor_id" class="form-label">Laboratorio</label>
          @if($doctoresLab->count() === 1 && $defaultLabId)
            <input type="hidden" name="doctor_id" value="{{ $defaultLabId }}">
            <input id="doctor_id" type="text" value="{{ $defaultLabName ?? 'Laboratorio Clínico' }}" disabled class="form-input">
          @else
            <select id="doctor_id" name="doctor_id" required class="form-select">
              <option value="">Seleccionar laboratorio</option>
              @foreach($doctoresLab as $doctor)
                <option value="{{ $doctor->id }}" {{ (string)old('doctor_id', $defaultLabId) === (string)$doctor->id ? 'selected' : '' }}>
                  {{ $doctor->name }}
                </option>
              @endforeach
            </select>
            <div class="text-xs text-slate-500">Si creas un usuario de laboratorio llamado "Laboratorio Clínico" se autoselecciona.</div>
          @endif
          @error('doctor_id')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
          @if($labId && $doctoresLab->isEmpty())
            <div class="text-xs text-rose-600">No hay usuarios de laboratorio con esa especialidad.</div>
          @endif
        </div>

        <div>
          <label for="fecha" class="form-label">Fecha</label>
          <input id="fecha" type="date" name="fecha" value="{{ old('fecha') }}" min="{{ \Carbon\Carbon::now('America/Guayaquil')->toDateString() }}" required class="form-input">
          @error('fecha')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <div>
          <label for="hora" class="form-label">Hora</label>
          <input id="hora" type="time" name="hora" step="900" min="08:00" max="17:45" value="{{ old('hora') }}" required class="form-input">
          <div class="text-xs text-slate-500">Horario 08:00 a 18:00. Intervalos de 15 minutos.</div>
          @error('hora')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
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
          <label for="preparacion" class="form-label">Preparación previa</label>
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
