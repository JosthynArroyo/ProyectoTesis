@extends('layouts.demo')
@section('title', 'Mis citas | Demo')
@section('header-title', 'Mis citas')
@section('header-subtitle', 'Agenda del paciente en solo lectura')

@section('sidebar')
    @include('demo.partials.sidebar-paciente-demo')
@endsection

@section('main')
<div class="space-y-6">
    <div class="panel-action-bar">
        <a class="btn btn-primary btn-full-mobile" href="{{ route('demo.paciente.agendar-cita') }}">
            <i class="ri-add-circle-line"></i> Agendar cita
        </a>
    </div>

    <section class="card p-0">
        <div class="table-shell table-responsive-cards">
            <table class="table">
                <thead>
                    <tr>
                        <th>Especialidad</th>
                        <th>Doctor</th>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Prioridad</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($citas as $appointment)
                        <tr>
                            <td data-label="Especialidad">{{ $appointment['specialty'] }}</td>
                            <td data-label="Doctor">{{ $appointment['doctor'] }}</td>
                            <td data-label="Fecha">{{ $appointment['date'] }}</td>
                            <td data-label="Hora">{{ $appointment['time'] }}</td>
                            <td data-label="Prioridad"><span class="inline-flex items-center gap-1.5 rounded-full {{ $appointment['priority_badge'] }} px-2 py-0.5 text-xs font-medium">{{ $appointment['priority_label'] }}</span></td>
                            <td data-label="Estado"><span class="badge {{ $appointment['status_tone'] }}">{{ $appointment['status'] }}</span></td>
                            <td data-label="Acciones">
                                <div class="table-actions">
                                    <button class="btn btn-outline btn-sm demo-action-blocked">Reagendar</button>
                                    <button class="btn btn-ghost btn-sm demo-action-blocked text-rose-600">Cancelar</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
