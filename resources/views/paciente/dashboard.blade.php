@extends('layouts.paciente')
@section('title', 'Panel Paciente - Clínica Los Ángeles')
@push('head')
    @vite('resources/js/paciente/dashboard-paciente.js')
@endpush
@section('main')
    <h1>Mi Panel</h1>
    <div class="date">
        <input type="date" value="{{ \Carbon\Carbon::now()->format('Y-m-d') }}">
    </div>
    @if(session('success'))
        <div class="alert success">{{ session('success') }}</div>
    @endif
    <div class="insights">
        <div class="sales">
            <span class="material-symbols-sharp">calendar_month</span>
            <div class="middle">
                <div class="left">
                    <h3>Citas Agendadas</h3>
                    <h1>{{ $totalCitas }}</h1>
                </div>
                <div class="progress">
                    <svg><circle r="30" cx="40" cy="40"></circle></svg>
                </div>
            </div>
            <small>Este mes</small>
        </div>
        <div class="expenses">
            <span class="material-symbols-sharp">check_circle</span>
            <div class="middle">
                <div class="left">
                    <h3>Completadas</h3>
                    <h1>{{ $totalCitasRealizadas }}</h1>
                </div>
                <div class="progress">
                    <svg><circle r="30" cx="40" cy="40"></circle></svg>
                </div>
            </div>
            <small>Historial</small>
        </div>
        <div class="income">
            <span class="material-symbols-sharp">pending</span>
            <div class="middle">
                <div class="left">
                    <h3>Pendientes</h3>
                    <h1>{{ $totalCitas - $totalCitasRealizadas }}</h1>
                </div>
                <div class="progress">
                    <svg><circle r="30" cx="40" cy="40"></circle></svg>
                </div>
            </div>
            <small>Este mes</small>
        </div>
    </div>
    <div class="recent_order">
        <h1>Mis Próximas Citas</h1>
        <table>
            <thead>
            <tr>
                <th>Doctor</th>
                <th>Especialidad</th>
                <th>Fecha</th>
                <th>Hora</th>
                <th>Estado</th>
            </tr>
            </thead>
            <tbody>
            @forelse($citas as $cita)
                <tr>
                    <td>{{ $cita->doctor->name ?? 'Sin asignar' }}</td>
                    <td>{{ $cita->especialidad->nombre ?? '—' }}</td>
                    <td>{{ \Carbon\Carbon::parse($cita->fecha)->format('Y/m/d') }}</td>
                    <td>{{ \Carbon\Carbon::parse($cita->hora)->format('H:i') }}</td>
                    <td>
                        <span class="badge {{ $cita->estado === 'pendiente' ? 'warning' : ($cita->estado === 'realizada' ? 'success' : ($cita->estado === 'confirmada' ? 'info' : 'danger')) }}">
                            {{ ucfirst($cita->estado) }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No tienes próximas citas.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
@section('right')
    <div class="recent_updates">
        <h2>Actualizaciones</h2>
        <div class="updates"></div>
    </div>
    <div class="sales_analytics">
        <h2>Resumen</h2>
        <div class="item online">
            <div class="icon"><span class="material-symbols-sharp">calendar_month</span></div>
            <div class="right_text">
                <div class="info"><h3>Agendadas</h3><small class="text-muted">Este mes</small></div>
                <h3>{{ $totalCitas }}</h3>
            </div>
        </div>
        <div class="item online">
            <div class="icon"><span class="material-symbols-sharp">task_alt</span></div>
            <div class="right_text">
                <div class="info"><h3>Completadas</h3><small class="text-muted">Este mes</small></div>
                <h3>{{ $totalCitasRealizadas }}</h3>
            </div>
        </div>
        <div class="item online">
            <div class="icon"><span class="material-symbols-sharp">pending_actions</span></div>
            <div class="right_text">
                <div class="info"><h3>Pendientes</h3><small class="text-muted">Este mes</small></div>
                <h3>{{ $totalCitas - $totalCitasRealizadas }}</h3>
            </div>
        </div>
    </div>
@endsection
