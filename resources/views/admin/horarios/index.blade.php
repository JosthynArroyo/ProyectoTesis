{{-- resources/views/admin/horarios/index.blade.php --}}
@extends('layouts.admin')
@section('title','Horarios de doctores')
@section('header-title','Horarios')
@section('header-subtitle','Planificación semanal de doctores')

@section('main')
@php
  use Carbon\Carbon;
  $days=[]; $c=$weekStart->copy();
  for($i=0;$i<7;$i++){ $days[]=$c->copy(); $c->addDay(); }
  $fmt = fn($t)=>Carbon::parse($t)->format('H:i');
  $activeDoctorId = $doctorId ?? optional($doctores->first())->id;
  $prev=$weekStart->copy()->subWeek()->toDateString();
  $next=$weekStart->copy()->addWeek()->toDateString();
  $byDoctor = collect($horarios)->groupBy('doctor_id');
@endphp

<div class="space-y-6">
  <section class="card p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Planificación semanal</p>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Horarios de doctores</h1>
        <p class="text-slate-600">Visualiza y controla los bloques semanales con una vista clara.</p>
      </div>
      <div class="flex items-center gap-2">
        <span class="badge info">Semana {{ $weekStart->format('d M') }} - {{ $weekEnd->format('d M') }}</span>
        <a class="btn btn-primary" href="{{ route('admin.horarios.create') }}">
          <i class="ri-add-line"></i> Nuevo horario
        </a>
      </div>
    </div>
  </section>

  @if (session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif
  @if ($errors->any())
    <x-ui.alert tone="error">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</x-ui.alert>
  @endif

  <form method="GET" action="{{ route('admin.horarios.index') }}" class="card p-6">
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      <div>
        <label class="form-label" for="doctor_id">Doctor</label>
        <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
          <i class="ri-stethoscope-line text-slate-400"></i>
          <select id="doctor_id" name="doctor_id" class="w-full bg-transparent text-sm" required>
            @foreach($doctores as $d)
              <option value="{{ $d->id }}" {{ (string)$activeDoctorId === (string)$d->id ? 'selected' : '' }}>{{ $d->name }}</option>
            @endforeach
          </select>
        </div>
        @error('doctor_id')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="form-label" for="week">Semana</label>
        <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
          <i class="ri-calendar-line text-slate-400"></i>
          <input class="w-full bg-transparent text-sm" id="week" type="date" name="week" value="{{ request('week',$weekStart->toDateString()) }}" required>
        </div>
        @error('week')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div class="lg:col-span-2">
        <label class="form-label">Navegación</label>
        <div class="flex flex-wrap gap-2">
          <a class="btn btn-outline" href="{{ route('admin.horarios.index',['doctor_id'=>$activeDoctorId,'week'=>$prev]) }}">
            <i class="ri-arrow-left-s-line"></i> Anterior
          </a>
          <a class="btn btn-outline" href="{{ route('admin.horarios.index',['doctor_id'=>$activeDoctorId,'week'=>Carbon::now()->toDateString()]) }}">Hoy</a>
          <a class="btn btn-outline" href="{{ route('admin.horarios.index',['doctor_id'=>$activeDoctorId,'week'=>$next]) }}">
            Siguiente <i class="ri-arrow-right-s-line"></i>
          </a>
          <button class="btn btn-primary" type="submit">Aplicar filtros</button>
        </div>
      </div>
    </div>
  </form>

  <div class="flex flex-wrap gap-2 md:hidden" aria-label="Seleccionar día (móvil)">
    @foreach($days as $d)
      <button class="day-chip rounded-full border border-slate-200 px-3 py-2 text-xs font-semibold {{ $loop->first ? 'active bg-teal-50 text-teal-700' : 'text-slate-500' }}" data-day-btn="{{ $d->toDateString() }}">
        {{ $d->isoFormat('ddd') }} {{ $d->format('d/m') }}
      </button>
    @endforeach
  </div>

  @php
    $activeItems = $byDoctor->get($activeDoctorId, collect());
    $byDate = $activeItems->groupBy(fn($h)=>Carbon::parse($h->fecha)->toDateString());
  @endphp

  <div class="card p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div class="flex items-center gap-3">
        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-teal-50 text-teal-700">
          <i class="ri-calendar-schedule-line"></i>
        </div>
        <div>
          <p class="text-xs uppercase tracking-widest text-slate-500">Semana completa</p>
          <strong class="text-base font-semibold text-slate-900">Bloques por día</strong>
        </div>
      </div>
      <div class="flex items-center gap-2" data-day-slider>
        <span class="hidden text-xs text-slate-500 md:inline">Desliza o usa las flechas para ver todos los días</span>
        <div class="flex gap-2">
          <button type="button" class="btn btn-ghost px-2" data-day-scroll="prev" aria-label="Desplazar a días anteriores">
            <i class="ri-arrow-left-s-line"></i>
          </button>
          <button type="button" class="btn btn-ghost px-2" data-day-scroll="next" aria-label="Desplazar a días siguientes">
            <i class="ri-arrow-right-s-line"></i>
          </button>
        </div>
      </div>
    </div>

    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3" data-day-track>
      @foreach($days as $d)
        @php
          $key = $d->toDateString();
          $slots = $byDate->get($key, collect())->sortBy(['hora_inicio','hora_fin']);
        @endphp
        <div class="day card p-4 {{ $loop->first ? 'is-active' : '' }}" data-day-panel="{{ $key }}">
          <div class="flex items-center justify-between">
            <div>
              <div class="text-xs uppercase tracking-widest text-slate-500">{{ $d->isoFormat('ddd') }}</div>
              <div class="text-sm font-semibold text-slate-900">{{ $d->format('d/m') }}</div>
            </div>
            <span class="badge neutral">{{ $slots->count() }} bloques</span>
          </div>
          <div class="mt-3 space-y-3">
            @forelse($slots as $h)
              <article class="slot rounded-2xl border border-slate-200 bg-white/90 p-3" tabindex="0">
                <div class="flex items-start justify-between gap-3">
                  <div class="flex items-center gap-2">
                    <i class="ri-time-line text-slate-400"></i>
                    <div>
                      <strong class="text-sm text-slate-900">{{ $fmt($h->hora_inicio) }} - {{ $fmt($h->hora_fin) }}</strong>
                      <p class="text-xs text-slate-500">Bloque activo para {{ $d->isoFormat('dddd') }}</p>
                    </div>
                  </div>
                  <div class="flex items-center gap-2">
                    <a class="btn btn-ghost px-2" href="{{ route('admin.horarios.edit',$h) }}" title="Editar">
                      <i class="ri-edit-line"></i>
                    </a>
                    <form action="{{ route('admin.horarios.destroy',$h) }}" method="POST" onsubmit="return confirm('Eliminar este horario');">
                      @csrf @method('DELETE')
                      <button type="submit" class="btn btn-ghost px-2" title="Eliminar">
                        <i class="ri-delete-bin-line"></i>
                      </button>
                    </form>
                    <button class="slot-more btn btn-ghost px-2" type="button" aria-label="Más acciones">
                      <i class="ri-more-2-fill"></i>
                    </button>
                  </div>
                </div>
              </article>
            @empty
              <div class="empty-state text-sm">Sin horarios</div>
            @endforelse
          </div>
        </div>
      @endforeach
    </div>
  </div>
</div>
@endsection

@push('scripts')
  @vite('resources/js/admin/horarios/index.js')
@endpush