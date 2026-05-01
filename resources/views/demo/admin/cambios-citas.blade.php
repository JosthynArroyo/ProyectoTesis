@extends('layouts.demo')
@section('title', 'Cambios de citas | Demo')
@section('header-title', 'Cambios de citas')
@section('header-subtitle', 'Solicitudes de reprogramacion y ajustes')

@section('sidebar')
    @include('demo.partials.sidebar-admin-demo')
@endsection

@section('main')
<div class="space-y-6">
    <section class="card p-0">
        <div class="table-shell table-responsive-cards">
            <table class="table">
                <thead>
                    <tr>
                        <th>Paciente</th>
                        <th>Doctor</th>
                        <th>Horario actual</th>
                        <th>Horario solicitado</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($appointmentChanges as $change)
                        <tr>
                            <td data-label="Paciente">{{ $change['patient'] }}</td>
                            <td data-label="Doctor">{{ $change['doctor'] }}</td>
                            <td data-label="Horario actual">{{ $change['from'] }}</td>
                            <td data-label="Horario solicitado">{{ $change['to'] }}</td>
                            <td data-label="Estado"><span class="badge {{ $change['status_tone'] }}">{{ $change['status'] }}</span></td>
                            <td data-label="Acciones">
                                <div class="table-actions">
                                    <button class="btn btn-primary btn-sm demo-action-blocked">Aceptar</button>
                                    <button class="btn btn-outline btn-sm demo-action-blocked">Rechazar</button>
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
