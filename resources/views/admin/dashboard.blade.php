@extends('layouts.admin')

@section('title','Panel Administrativo - Clínica Los Ángeles')

@push('head')
  <meta name="dashboard-resumen-url" content="{{ route('admin.dashboard.resumen') }}">
@endpush

@section('main')
  <h1>Panel de Control</h1>
  <div class="date"><input type="date"></div>

  <div class="insights">
    <div class="sales">
      <span class="material-symbols-sharp">monitor_heart</span>
      <div class="middle">
        <div class="left">
          <h3>Citas Totales</h3>
          <h1>{{ $totalCitas }}</h1>
        </div>
        <div class="progress"><svg><circle r="30" cx="40" cy="40"></circle></svg></div>
      </div>
      <small>Acumulado</small>
    </div>

    <div class="expenses">
      <span class="material-symbols-sharp">event_note</span>
      <div class="middle">
        <div class="left">
          <h3>Citas Pendientes</h3>
          <h1>{{ $totalCitasPendientes }}</h1>
        </div>
        <div class="progress"><svg><circle r="30" cx="40" cy="40"></circle></svg></div>
      </div>
      <small>Acumulado</small>
    </div>

    <div class="income">
      <span class="material-symbols-sharp">check_circle</span>
      <div class="middle">
        <div class="left">
          <h3>Citas Completadas</h3>
          <h1>{{ $totalCitasRealizadas }}</h1>
        </div>
        <div class="progress"><svg><circle r="30" cx="40" cy="40"></circle></svg></div>
      </div>
      <small>Acumulado</small>
    </div>
  </div>

  <div class="recent_order">
    <h1>Citas Recientes</h1>

    <form action="{{ route('admin.citas.export') }}" method="GET" style="margin-bottom:15px;text-align:right;">
      <button type="submit" class="btn-export">
        <span class="material-symbols-outlined">download</span> Exportar a Excel
      </button>
    </form>

    @php use Illuminate\Support\Carbon; @endphp
    <table id="tabla-citas">
      <thead>
        <tr>
          <th>Paciente</th><th>Doctor</th><th>Estado</th><th>Horario</th>
        </tr>
      </thead>
      <tbody id="citasBody">
        @forelse($citas as $cita)
          <tr>
            <td>{{ $cita->paciente->name ?? 'Sin paciente' }}</td>
            <td>{{ $cita->doctor->name ?? 'Sin asignar' }}</td>
            <td class="{{ $cita->estado === 'pendiente' ? 'warning' : ($cita->estado === 'realizada' ? 'success' : ($cita->estado === 'confirmada' ? 'info' : 'danger')) }}">
              {{ ucfirst($cita->estado) }}
            </td>
            <td>{{ Carbon::parse($cita->fecha)->format('Y-m-d') }} {{ Carbon::parse($cita->hora)->format('H:i') }}</td>
          </tr>
        @empty
          <tr><td colspan="4">No hay citas recientes.</td></tr>
        @endforelse
      </tbody>
    </table>

    <div class="table-actions" style="display:flex;gap:.6rem;justify-content:flex-end;margin-top:.8rem;">
      <button type="button" id="btnShowLess" class="btn-outline" style="display:none;">Mostrar menos</button>
      <button type="button" id="btnShowMore" class="btn-outline">Mostrar más</button>
    </div>
  </div>
@endsection

@section('right')
  <div class="recent_updates">
    <h2>Últimas actividades</h2>
    <div class="updates"></div>
  </div>

  <div class="sales_analytics">
    <h2>Resumen de citas</h2>
    <div class="item online">
      <div class="icon"><span class="material-symbols-sharp">calendar_month</span></div>
      <div class="right_text">
        <div class="info"><h3>Citas agendadas</h3><small class="text-muted">Total</small></div>
        <h3 id="kpi-agendadas">{{ $totalCitas }}</h3>
      </div>
    </div>

    <div class="item online">
      <div class="icon"><span class="material-symbols-sharp">task_alt</span></div>
      <div class="right_text">
        <div class="info"><h3>Citas completadas</h3><small class="text-muted">Total</small></div>
        <h3 id="kpi-completadas">{{ $totalCitasRealizadas }}</h3>
      </div>
    </div>

    <div class="item online">
      <div class="icon"><span class="material-symbols-sharp">cancel</span></div>
      <div class="right_text">
        <div class="info"><h3>Citas canceladas</h3><small class="text-muted">Total</small></div>
        <h3 id="kpi-canceladas">{{ $totalCitasCanceladas }}</h3>
      </div>
    </div>
  </div>
@endsection
