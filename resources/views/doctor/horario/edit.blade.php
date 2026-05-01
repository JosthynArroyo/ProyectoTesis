@extends('layouts.doctor')
@section('title','Editar horario | Doctor')
@section('activeSidebar','horario')
@section('header-title','Editar horario')
@section('header-subtitle','Actualiza este bloque')

@section('main')
  <div class="space-y-6">
    <div class="card p-6">
      <h3 class="text-lg font-semibold text-gray-900">Editar horario</h3>
      <form method="POST" action="{{ route('doctor.horario.update',$h) }}" class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @csrf @method('PUT')
        <div>
          <label class="form-label" for="doctor-horario-edit-fecha">Fecha</label>
          <div class="relative mt-1">
            <input class="form-input form-input-native-date mt-0 pr-11" id="doctor-horario-edit-fecha" type="date" name="fecha" value="{{ $h->fecha }}" placeholder="AAAA-MM-DD" autocomplete="off" required>
            <button id="doctor-horario-edit-fecha-trigger" type="button" class="field-action-button" data-native-date-open="#doctor-horario-edit-fecha" aria-label="Abrir calendario para editar el horario">
              <i class="ri-calendar-line"></i>
            </button>
          </div>
          @error('fecha')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Inicio</label>
          <input class="form-input" type="time" name="hora_inicio" value="{{ substr($h->hora_inicio,0,5) }}" required>
          @error('hora_inicio')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Fin</label>
          <input class="form-input" type="time" name="hora_fin" value="{{ substr($h->hora_fin,0,5) }}" required>
          @error('hora_fin')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <input type="hidden" name="intervalo_minutos" value="30">
        <div class="flex items-end">
          <span class="badge neutral">Intervalo 30 min</span>
        </div>

        <div class="sm:col-span-4">
          <x-ui.form-actions>
            <x-slot:left>
              <a class="btn btn-ghost" href="{{ route('doctor.horario.index') }}">Volver</a>
            </x-slot>
            <button class="btn btn-primary">Guardar</button>
          </x-ui.form-actions>
        </div>
      </form>
    </div>
  </div>
@endsection
