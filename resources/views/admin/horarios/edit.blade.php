{{-- resources/views/admin/horarios/edit.blade.php --}}
@extends('layouts.admin')
@section('title','Editar horario')
@section('header-title','Editar horario')
@section('header-subtitle','Actualiza la franja seleccionada')

@section('main')
<div class="space-y-6">
  <section class="card p-6">
    <div>
      <p class="text-xs uppercase tracking-widest text-slate-500">EdiciÃ³n de horario</p>
      <h1 class="mt-2 text-2xl font-semibold text-slate-900">Editar horario #{{ $horario->id }}</h1>
      <p class="text-slate-600">Ajusta la fecha y la franja asignada con inputs claros.</p>
    </div>
  </section>


  <form action="{{ route('admin.horarios.update', $horario) }}" method="POST" class="card p-6" id="form-horario-edit">
    @csrf
    @method('PUT')

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
        <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
          <i class="ri-calendar-line text-slate-400"></i>
          <input class="w-full bg-transparent text-sm" type="date" id="fecha" name="fecha" value="{{ old('fecha', \Carbon\Carbon::parse($horario->fecha)->format('Y-m-d')) }}" required>
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

    <div class="mt-6">
      <x-ui.form-actions>
        <x-slot:left>
          <a class="btn btn-ghost" href="{{ route('admin.horarios.index') }}">Volver</a>
        </x-slot>
        <button class="btn btn-primary" type="submit">Actualizar</button>
      </x-ui.form-actions>
    </div>
  </form>
</div>
@endsection

@push('scripts')
  @vite('resources/js/admin/horarios/edit.js')
@endpush
