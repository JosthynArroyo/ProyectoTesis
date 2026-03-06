@extends('layouts.admin')

@section('title','Panel administrativo - Clínica Don Bosco')
@section('header-title','Panel administrativo')
@section('header-subtitle','Visión general de la operación')

@section('main')
  <div class="space-y-6">
    {{-- Hero card --}}
    <section class="card p-6">
      <div class="page-header">
        <div class="page-header__info">
          <p class="text-xs uppercase tracking-widest text-slate-500">Resumen del día</p>
          <h1 class="mt-2 text-2xl font-semibold text-slate-900">Panel de control</h1>
          <p class="text-slate-600">Visión general de pacientes, doctores y actividad reciente.</p>
        </div>
        <div class="page-header__actions">
          <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
            <i class="ri-calendar-line text-slate-400"></i>
            <input type="date" aria-label="Seleccionar fecha" value="{{ now()->toDateString() }}" class="bg-transparent text-sm text-slate-600">
          </div>
        </div>
      </div>
    </section>

    {{-- Stats row 1 --}}
    <section class="stat-grid">
      <a class="block" href="{{ route('admin.usuarios.index', ['role' => 'paciente']) }}">
        <x-ui.stat label="Total de pacientes" :value="$totalPacientes" tone="teal">
          <x-slot:icon><i class="ri-group-line"></i></x-slot:icon>
        </x-ui.stat>
      </a>
      <a class="block" href="{{ route('admin.usuarios.index', ['role' => 'doctor']) }}">
        <x-ui.stat label="Total de doctores" :value="$totalDoctores" tone="sky">
          <x-slot:icon><i class="ri-stethoscope-line"></i></x-slot:icon>
        </x-ui.stat>
      </a>
      <a class="block" href="{{ route('admin.usuarios.index') }}">
        <x-ui.stat label="Usuarios activos hoy" :value="$usuariosActivosHoy" tone="amber">
          <x-slot:icon><i class="ri-flashlight-line"></i></x-slot:icon>
        </x-ui.stat>
      </a>
    </section>

    {{-- Stats row 2 --}}
    <section class="stat-grid">
      <a class="block" href="{{ route('admin.dashboard') }}">
        <x-ui.stat label="Citas totales" :value="$totalCitas" tone="sky">
          <x-slot:icon><i class="ri-calendar-event-line"></i></x-slot:icon>
        </x-ui.stat>
      </a>
      <a class="block" href="{{ route('admin.dashboard', ['prioridad' => 'all']) }}">
        <x-ui.stat label="Citas pendientes" :value="$totalCitasPendientes" tone="amber">
          <x-slot:icon><i class="ri-hourglass-line"></i></x-slot:icon>
        </x-ui.stat>
      </a>
      <a class="block" href="{{ route('admin.dashboard', ['prioridad' => 'ALTA']) }}">
        <x-ui.stat label="Pendientes alta prioridad" :value="$totalCitasPendientesAlta" tone="rose">
          <x-slot:icon><i class="ri-error-warning-line"></i></x-slot:icon>
        </x-ui.stat>
      </a>
      <a class="block" href="{{ route('admin.dashboard') }}">
        <x-ui.stat label="Citas realizadas" :value="$totalCitasRealizadas" tone="teal">
          <x-slot:icon><i class="ri-checkbox-circle-line"></i></x-slot:icon>
        </x-ui.stat>
      </a>
      <a class="block" href="{{ route('admin.dashboard') }}">
        <x-ui.stat label="Citas canceladas" :value="$totalCitasCanceladas" tone="rose">
          <x-slot:icon><i class="ri-close-circle-line"></i></x-slot:icon>
        </x-ui.stat>
      </a>
    </section>

    {{-- Table + Sidebar --}}
    <div class="grid gap-6 lg:grid-cols-[1.5fr_0.5fr]">
      <section class="card p-6">
        <div class="page-header">
          <div class="page-header__info">
            <h2>Citas recientes</h2>
            <p>Últimas 50 citas registradas.</p>
          </div>
          <div class="page-header__actions">
            <form method="GET" action="{{ route('admin.dashboard') }}" class="flex items-center gap-2">
              <select name="prioridad" class="form-select">
                <option value="all" @selected(($prioridad ?? '') === '')>Todas las prioridades</option>
                <option value="ALTA" @selected(($prioridad ?? '') === 'ALTA')>ALTA</option>
                <option value="MEDIA" @selected(($prioridad ?? '') === 'MEDIA')>MEDIA</option>
                <option value="BAJA" @selected(($prioridad ?? '') === 'BAJA')>BAJA</option>
              </select>
              <button type="submit" class="btn btn-outline btn-sm">
                <i class="ri-filter-3-line"></i> Filtrar
              </button>
              @if(($prioridad ?? '') !== '')
                <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost btn-sm">Limpiar</a>
              @endif
            </form>
            <form id="exportForm" action="{{ route('admin.citas.export') }}" method="GET">
              <button type="submit" class="btn btn-outline btn-sm">
                <i class="ri-download-2-line"></i> Exportar
              </button>
            </form>
          </div>
        </div>

        <div class="mt-4 table-shell table-responsive-cards">
          <table class="table">
            <thead>
              <tr>
                <th>Paciente</th>
                <th>Doctor</th>
                <th>Estado</th>
                <th>Prioridad</th>
                <th>Horario</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody id="citasBody">
              @php use Illuminate\Support\Carbon; @endphp
              @forelse($citas as $cita)
                @php
                  $priorityTone = match($cita->prioridad_nivel) {
                    'ALTA' => 'danger',
                    'MEDIA' => 'warning',
                    default => 'neutral',
                  };
                  $redirectTo = route('admin.citas.prioridad.edit', $cita).'?redirect_to='.urlencode(request()->fullUrl());
                @endphp
                <tr>
                  <td data-label="Paciente">{{ optional($cita->paciente)->name ?? 'Sin paciente' }}</td>
                  <td data-label="Doctor">{{ optional($cita->doctor)->name ?? 'Sin asignar' }}</td>
                  <td data-label="Estado">
                    <x-ui.badge :tone="$cita->estado === 'cancelada' ? 'danger' : ($cita->estado === 'pendiente' ? 'warning' : 'success')">
                      {{ $cita->estado === 'no_se_presento' ? 'No se presento' : ucfirst($cita->estado) }}
                    </x-ui.badge>
                  </td>
                  <td data-label="Prioridad">
                    <x-ui.badge :tone="$priorityTone">{{ $cita->prioridad_nivel ?? 'BAJA' }}</x-ui.badge>
                    @if($cita->prioridad_red_flag)
                      <span class="badge danger">Red flag</span>
                    @endif
                  </td>
                  <td data-label="Horario">{{ Carbon::parse($cita->fecha)->format('Y-m-d') }} {{ Carbon::parse($cita->hora)->format('H:i') }}</td>
                  <td data-label="Acciones">
                    <a href="{{ $redirectTo }}" class="btn btn-outline btn-sm">Ajustar prioridad</a>
                  </td>
                </tr>
              @empty
                <tr><td colspan="6">No hay citas recientes.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
          <button type="button" id="btnShowLess" class="btn btn-ghost btn-sm" style="display:none;">Mostrar menos</button>
          <button type="button" id="btnShowMore" class="btn btn-ghost btn-sm">Mostrar más</button>
        </div>
      </section>

      <aside class="card p-6">
        <h3 class="text-lg font-semibold text-slate-900">Resumen de citas</h3>
        <div class="mt-4 space-y-4">
          <div>
            <p class="text-xs uppercase tracking-widest text-slate-500">Agendadas</p>
            <p id="kpi-agendadas" class="text-2xl font-semibold text-slate-900">{{ $totalCitas }}</p>
          </div>
          <div>
            <p class="text-xs uppercase tracking-widest text-slate-500">Completadas</p>
            <p id="kpi-completadas" class="text-2xl font-semibold text-slate-900">{{ $totalCitasRealizadas }}</p>
          </div>
          <div>
            <p class="text-xs uppercase tracking-widest text-slate-500">Canceladas</p>
            <p id="kpi-canceladas" class="text-2xl font-semibold text-slate-900">{{ $totalCitasCanceladas }}</p>
          </div>
        </div>
        <p class="mt-4 text-xs text-slate-500">Actualización en tiempo real.</p>
      </aside>
    </div>
  </div>
@endsection
