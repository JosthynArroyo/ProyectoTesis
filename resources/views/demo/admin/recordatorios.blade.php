@extends('layouts.demo')
@section('title', 'Recordatorios | Demo')
@section('header-title', 'Recordatorios')
@section('header-subtitle', 'Seguimiento de avisos previos a la cita')

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
                        <th>Telefono</th>
                        <th>Cita</th>
                        <th>Canal</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recordatorios as $reminder)
                        <tr>
                            <td data-label="Paciente">{{ $reminder['patient'] }}</td>
                            <td data-label="Telefono">{{ $reminder['phone'] }}</td>
                            <td data-label="Cita">{{ $reminder['appointment'] }}</td>
                            <td data-label="Canal">{{ $reminder['channel'] }}</td>
                            <td data-label="Estado"><span class="badge {{ $reminder['status_tone'] }}">{{ $reminder['status'] }}</span></td>
                            <td data-label="Acciones">
                                <div class="table-actions">
                                    <button class="btn btn-primary btn-sm demo-action-blocked">Enviar</button>
                                    <button class="btn btn-outline btn-sm demo-action-blocked">Revisar</button>
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
