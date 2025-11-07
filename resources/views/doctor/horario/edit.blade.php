@extends('layouts.doctor')
@section('title','Editar horario | Doctor')
@section('activeSidebar','horario')

@push('head')
  @vite('resources/css/doctor/horario.css')
@endpush

@section('content')
  <div class="card">
    <h3 class="card-title">Editar horario</h3>
    <form method="POST" action="{{ route('doctor.horario.update',$h) }}" class="grid">
      @csrf @method('PUT')
      <div><label>Fecha</label><input type="date" name="fecha" value="{{ $h->fecha }}" required></div>
      <div><label>Inicio</label><input type="time" name="hora_inicio" value="{{ substr($h->hora_inicio,0,5) }}" required></div>
      <div><label>Fin</label><input type="time" name="hora_fin" value="{{ substr($h->hora_fin,0,5) }}" required></div>

      {{-- Fijo a 30 minutos --}}
      <input type="hidden" name="intervalo_minutos" value="30">
      <div class="readonly">
        <label>Intervalo</label>
        <span class="pill">30 min</span>
      </div>

      <div class="full actions">
        <button class="btn btn-primary">Guardar</button>
        <a class="btn btn-ghost" href="{{ route('doctor.horario.index') }}">Volver</a>
      </div>
    </form>
  </div>
@endsection
