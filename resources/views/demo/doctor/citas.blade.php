@extends('layouts.demo')
@section('title', 'Mis citas doctor | Demo')
@section('header-title', 'Mis citas')
@section('header-subtitle', 'Gestion diaria del consultorio')

@section('sidebar')
    @include('demo.partials.sidebar-doctor-demo')
@endsection

@section('main')
<div class="space-y-6">
    <div class="panel-action-bar flex flex-wrap items-center justify-between gap-4">
        <div class="flex rounded-lg bg-gray-100 p-1">
            <button class="rounded-md bg-white px-4 py-1.5 text-sm font-medium text-gray-900 demo-action-blocked">Hoy</button>
            <button class="px-4 py-1.5 text-sm font-medium text-gray-500 demo-action-blocked">Semana</button>
            <button class="px-4 py-1.5 text-sm font-medium text-gray-500 demo-action-blocked">Mes</button>
        </div>
        <div class="flex flex-wrap gap-2">
            <button class="btn btn-outline demo-action-blocked"><i class="ri-download-line"></i> Exportar</button>
            <button class="btn btn-primary demo-action-blocked"><i class="ri-add-line"></i> Agregar turno extra</button>
        </div>
    </div>

    <section class="card p-0">
        <div class="table-shell table-responsive-cards">
            <table class="table">
                <thead>
                    <tr>
                        <th>Hora</th>
                        <th>Paciente</th>
                        <th>Motivo</th>
                        <th>Prioridad</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($citasHoy as $appointment)
                        <tr>
                            <td data-label="Hora">{{ $appointment['time'] }}</td>
                            <td data-label="Paciente">
                                <p class="font-medium text-gray-900">{{ $appointment['patient'] }}</p>
                                <p class="text-xs text-gray-500">{{ $appointment['age'] }} anios, {{ $appointment['sex'] }}</p>
                            </td>
                            <td data-label="Motivo">{{ $appointment['reason'] }}</td>
                            <td data-label="Prioridad"><span class="inline-flex items-center gap-1.5 rounded-full {{ $appointment['priority_badge'] }} px-2 py-0.5 text-xs font-medium">{{ $appointment['priority_label'] }}</span></td>
                            <td data-label="Estado"><span class="badge {{ $appointment['status_tone'] }}">{{ $appointment['status'] }}</span></td>
                            <td data-label="Acciones">
                                <div class="table-actions">
                                    <button class="btn btn-primary btn-sm demo-action-blocked">Atender</button>
                                    <button class="btn btn-ghost btn-sm demo-action-blocked">Ausente</button>
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
