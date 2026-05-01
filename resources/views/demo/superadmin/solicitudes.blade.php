@extends('layouts.demo')
@section('title', 'Solicitudes | Demo')
@section('header-title', 'Solicitudes')
@section('header-subtitle', 'Solicitudes de personalizacion')

@section('sidebar')
    @include('demo.partials.sidebar-superadmin-demo')
@endsection

@section('main')
<div class="space-y-6">
    <section class="card p-0">
        <div class="table-shell table-responsive-cards">
            <table class="table demo-requests-table">
                <thead>
                    <tr>
                        <th>Administrador</th>
                        <th>Modulo</th>
                        <th>Motivo</th>
                        <th>Estado</th>
                        <th class="text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($solicitudes as $solicitud)
                        <tr>
                            <td data-label="Administrador">
                                <p class="text-sm font-semibold text-gray-900">{{ $solicitud['admin'] }}</p>
                                <p class="text-xs text-gray-500">{{ $solicitud['submitted_at'] }}</p>
                            </td>
                            <td data-label="Modulo"><span class="badge neutral">{{ $solicitud['module'] }}</span></td>
                            <td data-label="Motivo" class="wrap">{{ $solicitud['reason'] }}</td>
                            <td data-label="Estado"><span class="badge {{ $solicitud['status_tone'] }}">{{ $solicitud['status'] }}</span></td>
                            <td data-label="Acciones">
                                <div class="table-actions demo-requests-table__actions">
                                    <button class="btn btn-primary btn-sm demo-action-blocked">Aprobar</button>
                                    <button class="btn btn-outline btn-sm demo-action-blocked text-rose-600 border-rose-300">Rechazar</button>
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
