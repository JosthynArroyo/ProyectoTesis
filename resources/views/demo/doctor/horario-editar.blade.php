@extends('layouts.demo')
@section('title','Editar horario - Demo')
@section('activeSidebar','horario')
@section('header-title','Editar horario')
@section('header-subtitle','Actualiza este bloque (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-doctor-demo')
@endsection

@section('main')
  <div class="space-y-6">
    <div class="card p-6">
      <h3 class="text-lg font-semibold text-gray-900">Editar horario</h3>
      <form method="POST" action="{{ route('demo.doctor.horario.update', $horario->id) }}" class="mt-4 grid gap-4 md:grid-cols-2">
        @csrf
        @method('PUT')

        <div>
          <label class="form-label" for="range">Rango de horario (Ej: 08:00 - 16:00)</label>
          <input class="form-input" id="range" name="range" value="{{ $horario->range }}" required>
        </div>

        <div class="md:col-span-2">
          <x-ui.form-actions>
            <x-slot:left>
              <a class="btn btn-ghost" href="{{ route('demo.doctor.horario.index') }}">Volver</a>
            </x-slot>
            <button class="btn btn-primary">Guardar</button>
          </x-ui.form-actions>
        </div>
      </form>
    </div>
  </div>
@endsection
