@extends('layouts.demo')
@section('title', 'Historial clinico | Demo')
@section('header-title', 'Historiales clinicos')
@section('header-subtitle', 'Acceso administrativo al expediente longitudinal')

@section('sidebar')
    @include('demo.partials.sidebar-admin-demo')
@endsection

@section('main')
<div class="space-y-6">
    <section class="card p-6">
        <div class="flex flex-wrap items-end gap-3">
            <div class="inline-control-shell flex-1">
                <i class="ri-search-line text-gray-400"></i>
                <input type="text" value="" placeholder="Buscar por paciente, documento o correo..." readonly>
            </div>
            <button class="btn btn-outline demo-action-blocked">Filtrar</button>
        </div>
    </section>

    <section class="card p-0">
        <div class="table-shell table-responsive-cards">
            <table class="table">
                <thead>
                    <tr>
                        <th>Paciente</th>
                        <th>Doctor</th>
                        <th>Especialidad</th>
                        <th>Fecha</th>
                        <th class="text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($historialEntries as $entry)
                        <tr>
                            <td data-label="Paciente">{{ $entry['patient'] }}</td>
                            <td data-label="Doctor">{{ $entry['doctor'] }}</td>
                            <td data-label="Especialidad">{{ $entry['specialty'] }}</td>
                            <td data-label="Fecha">{{ $entry['date'] }}</td>
                            <td data-label="Acciones">
                                <div class="table-actions">
                                    <button class="btn btn-outline btn-sm demo-action-blocked">Ver historial</button>
                                    <button class="btn btn-ghost btn-sm demo-action-blocked">Notas firmadas</button>
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
