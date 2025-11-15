{{-- resources/views/admin/horarios/index.blade.php --}}
@extends('layouts.admin')
@section('title','Horarios de doctores')

@push('head')
  @vite('resources/css/admin/horarios/index.css')
@endpush

@section('main')
@php
  use Carbon\Carbon;
  $days=[]; $c=$weekStart->copy();
  for($i=0;$i<7;$i++){ $days[]=$c->copy(); $c->addDay(); }
  $fmt = fn($t)=>Carbon::parse($t)->format('H:i');
  $activeDoctorId = $doctorId ?? ($doctores->first()->id ?? null);
  $prev=$weekStart->copy()->subWeek()->toDateString();
  $next=$weekStart->copy()->addWeek()->toDateString();
  $byDoctor = collect($horarios)->groupBy('doctor_id');
@endphp

<div class="page">
  <h1 class="page-title">Horarios de doctores</h1>

  <div class="toolbar-wrap">
    <form method="GET" action="{{ route('admin.horarios.index') }}" class="toolbar">
      <div>
        <label class="small" for="doctor_id">Doctor</label>
        <select id="doctor_id" name="doctor_id" class="select">
          @foreach($doctores as $d)
            <option value="{{ $d->id }}" {{ (string)$activeDoctorId===(string)$d->id?'selected':'' }}>{{ $d->name }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="small">Semana</label>
        <input class="input" type="date" name="week" value="{{ request('week',$weekStart->toDateString()) }}">
      </div>
      <button class="btn btn-primary" type="submit">Aplicar</button>
      <a class="btn btn-outline" href="{{ route('admin.horarios.index',['doctor_id'=>$activeDoctorId,'week'=>$prev]) }}">⟵ Anterior</a>
      <a class="btn btn-outline" href="{{ route('admin.horarios.index',['doctor_id'=>$activeDoctorId,'week'=>Carbon::now()->toDateString()]) }}">Hoy</a>
      <a class="btn btn-outline" href="{{ route('admin.horarios.index',['doctor_id'=>$activeDoctorId,'week'=>$next]) }}">Siguiente ⟶</a>
      <a class="btn btn-primary new-btn" href="{{ route('admin.horarios.create') }}">+ Nuevo horario</a>
    </form>
  </div>

  <div class="range-card">
    <div class="range">📅 {{ $weekStart->format('d M Y') }} — {{ $weekEnd->format('d M Y') }}</div>
    <div class="muted">Selecciona un doctor en las pestañas para ver su semana.</div>
  </div>

  <div class="tabs">
    <div class="tab-scroller" id="doctor-tabs">
      @foreach($doctores as $d)
        <button class="chip {{ (string)$activeDoctorId===(string)$d->id?'active':'' }}" data-doctor="{{ $d->id }}" type="button" title="{{ $d->name }}">
          <span class="dot"></span><span>{{ $d->name }}</span>
        </button>
      @endforeach
    </div>
  </div>

  @php
    $activeItems = $byDoctor->get($activeDoctorId, collect());
    $byDate = $activeItems->groupBy(fn($h)=>Carbon::parse($h->fecha)->toDateString());
  @endphp

  <div class="board">
    <div class="days">
      @foreach($days as $d)
        @php
          $key = $d->toDateString();
          $slots = $byDate->get($key, collect())->sortBy(['hora_inicio','hora_fin']);
        @endphp
        <div class="day">
          <div class="day-head">
            <div class="day-name">{{ $d->isoFormat('ddd') }}</div>
            <div class="day-date">{{ $d->format('d/m') }}</div>
          </div>
          @forelse($slots as $h)
            <div class="slot">
              <div class="slot-time">🕐 {{ $fmt($h->hora_inicio) }} – {{ $fmt($h->hora_fin) }}</div>
              <div class="slot-actions">
                <a href="{{ route('admin.horarios.edit',$h) }}">Editar</a>
                <form action="{{ route('admin.horarios.destroy',$h) }}" method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar este horario?');">
                  @csrf @method('DELETE')
                  <button type="submit">Eliminar</button>
                </form>
              </div>
            </div>
          @empty
            <div class="empty">Sin horarios</div>
          @endforelse
        </div>
      @endforeach
    </div>
  </div>
</div>
@endsection

@push('scripts')
  @vite('resources/js/admin/horarios/index.js')
@endpush
