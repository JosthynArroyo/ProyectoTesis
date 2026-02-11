@extends('layouts.admin')

@section('title','Panel administrativo - Clínica Don Bosco')
@section('header-title','Panel administrativo')
@section('header-subtitle','Vision general de la operacion')

@section('main')
  <div class="space-y-6">
    <section class="card p-6">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p class="text-xs uppercase tracking-widest text-slate-500">Resumen del dia</p>
          <h1 class="mt-2 text-2xl font-semibold text-slate-900">Panel de control</h1>
          <p class="text-slate-600">Vision general de pacientes, doctores y actividad reciente.</p>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
          <i class="ri-calendar-line text-slate-400"></i>
          <input type="date" aria-label="Seleccionar fecha" value="{{ now()->toDateString() }}" class="bg-transparent text-sm text-slate-600">
        </div>
      </div>
    </section>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      <x-ui.stat label="Total de pacientes" :value="$totalPacientes" tone="teal">
        <x-slot:icon><i class="ri-group-line"></i></x-slot:icon>
      </x-ui.stat>
      <x-ui.stat label="Total de doctores" :value="$totalDoctores" tone="sky">
        <x-slot:icon><i class="ri-stethoscope-line"></i></x-slot:icon>
      </x-ui.stat>
      <x-ui.stat label="Usuarios activos hoy" :value="$usuariosActivosHoy" tone="amber">
        <x-slot:icon><i class="ri-flashlight-line"></i></x-slot:icon>
      </x-ui.stat>
    </section>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
      <x-ui.stat label="Citas totales" :value="$totalCitas" tone="sky">
        <x-slot:icon><i class="ri-calendar-event-line"></i></x-slot:icon>
      </x-ui.stat>
      <x-ui.stat label="Citas pendientes" :value="$totalCitasPendientes" tone="amber">
        <x-slot:icon><i class="ri-hourglass-line"></i></x-slot:icon>
      </x-ui.stat>
      <x-ui.stat label="Pendientes criticas" :value="$totalCitasPendientesCriticas" tone="rose">
        <x-slot:icon><i class="ri-error-warning-line"></i></x-slot:icon>
      </x-ui.stat>
      <x-ui.stat label="Citas realizadas" :value="$totalCitasRealizadas" tone="teal">
        <x-slot:icon><i class="ri-checkbox-circle-line"></i></x-slot:icon>
      </x-ui.stat>
      <x-ui.stat label="Citas canceladas" :value="$totalCitasCanceladas" tone="rose">
        <x-slot:icon><i class="ri-close-circle-line"></i></x-slot:icon>
      </x-ui.stat>
    </section>

    <div class="grid gap-6 lg:grid-cols-[1.5fr_0.5fr]">
      <section class="card p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">Citas recientes</h2>
            <p class="text-sm text-slate-500">Últimas 50 citas registradas.</p>
          </div>
          <form id="exportForm" action="{{ route('admin.citas.export') }}" method="GET">
            <button type="submit" class="btn btn-outline">
              <i class="ri-download-2-line"></i> Exportar
            </button>
          </form>
        </div>

        <div class="mt-4 table-shell">
          <table class="table">
            <thead>
              <tr>
                <th>Paciente</th>
                <th>Doctor</th>
                <th>Estado</th>
                <th>Horario</th>
              </tr>
            </thead>
            <tbody id="citasBody">
              @php use Illuminate\Support\Carbon; @endphp
              @forelse($citas as $cita)
                <tr>
                  <td>{{ optional($cita->paciente)->name ?? 'Sin paciente' }}</td>
                  <td>{{ optional($cita->doctor)->name ?? 'Sin asignar' }}</td>
                  <td>
                    <x-ui.badge :tone="$cita->estado === 'cancelada' ? 'danger' : ($cita->estado === 'pendiente' ? 'warning' : 'success')">
                      {{ ucfirst($cita->estado) }}
                    </x-ui.badge>
                  </td>
                  <td>{{ Carbon::parse($cita->fecha)->format('Y-m-d') }} {{ Carbon::parse($cita->hora)->format('H:i') }}</td>
                </tr>
              @empty
                <tr><td colspan="4">No hay citas recientes.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
          <button type="button" id="btnShowLess" class="btn btn-ghost" style="display:none;">Mostrar menos</button>
          <button type="button" id="btnShowMore" class="btn btn-ghost">Mostrar más</button>
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
