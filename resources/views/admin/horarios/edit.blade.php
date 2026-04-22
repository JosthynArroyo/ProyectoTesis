{{-- resources/views/admin/horarios/edit.blade.php --}}
@extends('layouts.admin')
@section('title','Editar horario')
@section('header-title','Editar horario')
@section('header-subtitle','Actualiza la franja seleccionada')

@section('main')
<div class="space-y-6">
  <div class="panel-action-bar">
    <x-ui.context-pill label="Bloque actual">
      <x-slot:icon><i class="ri-time-line"></i></x-slot:icon>
      {{ \Carbon\Carbon::parse($horario->hora_inicio)->format('H:i') }} - {{ \Carbon\Carbon::parse($horario->hora_fin)->format('H:i') }}
    </x-ui.context-pill>
  </div>

  <form action="{{ route('admin.horarios.update', $horario) }}" method="POST" class="card p-6" id="form-horario-edit">
    @csrf
    @method('PUT')

    <section class="panel-form-section">
      <div class="panel-form-section__header">
        <div class="panel-form-section__heading">
          <h2 class="panel-form-section__title">
            <span class="panel-form-section__icon"><i class="ri-edit-2-line"></i></span>
            Datos del bloque
          </h2>
          <p class="panel-form-section__hint">Cambia la fecha y el horario sin perder el contexto del bloque actual.</p>
        </div>
      </div>

      <div class="grid gap-4 md:grid-cols-2">
        <div class="md:col-span-2">
          <label class="form-label" for="doctor_id">Doctor</label>
          <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
            <i class="ri-stethoscope-line text-slate-400"></i>
            <select class="w-full bg-transparent text-sm" id="doctor_id" name="doctor_id" required>
              @foreach($doctores as $d)
                <option value="{{ $d->id }}" {{ old('doctor_id', $horario->doctor_id) == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
              @endforeach
            </select>
          </div>
          @error('doctor_id')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <div>
          <label class="form-label" for="fecha">Fecha</label>
          <div class="relative mt-1">
            <input class="form-input form-input-native-date mt-0 pr-11" type="date" id="fecha" name="fecha" value="{{ old('fecha', \Carbon\Carbon::parse($horario->fecha)->format('Y-m-d')) }}" required>
            <button id="admin-horario-edit-fecha-trigger" type="button" class="field-action-button" data-native-date-open="#fecha" aria-label="Abrir calendario para editar la fecha">
              <i class="ri-calendar-line"></i>
            </button>
          </div>
          @error('fecha')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <div>
          <label class="form-label" for="hora_inicio">Hora inicio</label>
          <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
            <i class="ri-time-line text-slate-400"></i>
            <input class="w-full bg-transparent text-sm" type="time" id="hora_inicio" name="hora_inicio" step="1800" value="{{ old('hora_inicio', \Carbon\Carbon::parse($horario->hora_inicio)->format('H:i')) }}" required>
          </div>
          @error('hora_inicio')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <div>
          <label class="form-label" for="hora_fin">Hora fin</label>
          <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
            <i class="ri-time-line text-slate-400"></i>
            <input class="w-full bg-transparent text-sm" type="time" id="hora_fin" name="hora_fin" step="1800" value="{{ old('hora_fin', \Carbon\Carbon::parse($horario->hora_fin)->format('H:i')) }}" required>
          </div>
          @error('hora_fin')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
      </div>
    </section>

    <div class="mt-6">
      <x-ui.form-actions>
        <x-slot:left>
          <a class="btn btn-ghost" href="{{ route('admin.horarios.index') }}">
            <i class="ri-arrow-left-line"></i> Volver
          </a>
        </x-slot>
        <button class="btn btn-primary" type="submit">
          <i class="ri-save-line"></i> Actualizar
        </button>
      </x-ui.form-actions>
    </div>
  </form>
</div>
@endsection

@push('scripts')
  @vite('resources/js/admin/horarios/edit.js')
@endpush
