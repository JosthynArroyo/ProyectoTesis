@extends('layouts.laboratorio')
@section('title', 'Panel del laboratorio - Clínica Don Bosco')
@section('activeSidebar', 'dashboard')
@section('header-title','Panel laboratorio')
@section('header-subtitle','Controla órdenes, resultados y agenda diaria')

@section('content')
  <div class="space-y-6">
    <section class="card p-6">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p class="text-xs uppercase tracking-widest text-slate-500">Panel del laboratorio</p>
          <h1 class="mt-2 text-2xl font-semibold text-slate-900">Resumen de laboratorio</h1>
          <p class="text-slate-600">Controla órdenes, resultados y tu agenda diaria.</p>
        </div>
        <div class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white/90 px-3 py-2 text-sm text-slate-600">
          <i class="ri-calendar-line text-slate-400"></i>
          <input type="date" value="{{ now()->format('Y-m-d') }}" class="bg-transparent text-sm text-slate-700">
        </div>
      </div>
    </section>

    @if(session('success'))
      <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
    @endif

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      <x-ui.stat label="Citas de laboratorio" :value="$citasHoy" tone="sky">
        <p class="text-xs text-slate-500">Agenda del dia</p>
        <x-slot:icon><i class="ri-calendar-check-line"></i></x-slot:icon>
      </x-ui.stat>
      <x-ui.stat label="Órdenes pendientes" :value="$ordenesPendientes" tone="amber">
        <p class="text-xs text-slate-500">Por procesar</p>
        <x-slot:icon><i class="ri-flask-line"></i></x-slot:icon>
      </x-ui.stat>
      <x-ui.stat label="Resultados hoy" :value="$resultadosHoy" tone="teal">
        <p class="text-xs text-slate-500">Publicados</p>
        <x-slot:icon><i class="ri-checkbox-circle-line"></i></x-slot:icon>
      </x-ui.stat>
    </section>

    <section class="card p-6">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p class="text-xs uppercase tracking-widest text-slate-500">Citas y resultados</p>
          <h2 class="mt-2 text-lg font-semibold text-slate-900">Últimas órdenes</h2>
          <p class="text-sm text-slate-500">?rdenes y citas asignadas a tu cuenta.</p>
        </div>
        <a class="btn btn-outline" href="{{ route('laboratorio.ordenes.index') }}">Gestionar resultados</a>
      </div>

      <div class="mt-4 table-shell">
        <table class="table">
          <thead>
            <tr>
              <th>Paciente</th>
              <th>Examen</th>
              <th>Estado</th>
              <th>Fecha</th>
              <th>Acción</th>
            </tr>
          </thead>
          <tbody>
            @forelse($ordenesRecientes as $orden)
              @php
                $estado = $orden->estado;
                $badge = match($estado) {
                  'orden_creada' => 'warning',
                  'cita_programada' => 'info',
                  'muestra_tomada' => 'neutral',
                  'resultado_disponible' => 'success',
                  default => 'neutral',
                };
                $isResultado = $orden->resultado_path;
                $accionLabel = $isResultado ? 'Ver resultado' : 'Subir resultado';
                $accionUrl = route('laboratorio.ordenes.index') . '#orden-' . $orden->id;
              @endphp
              <tr>
                <td>{{ optional($orden->cita->paciente)->name ?? 'Paciente' }}</td>
                <td>{{ $orden->tipo_examen }}</td>
                <td><x-ui.badge :tone="$badge">{{ str_replace('_', ' ', $estado) }}</x-ui.badge></td>
                <td>{{ optional($orden->cita->fecha)->format('d/m/Y') }}</td>
                <td><a class="btn btn-ghost" href="{{ $accionUrl }}">{{ $accionLabel }}</a></td>
              </tr>
            @empty
              <tr>
                <td colspan="5">Sin órdenes recientes.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>
  </div>
@endsection
