@extends('layouts.demo')
@section('title', 'Mensajes de contacto | Demo')
@section('header-title', 'Mensajes de contacto')
@section('header-subtitle', 'Solicitudes enviadas desde el formulario publico')

@section('sidebar')
    @include('demo.partials.sidebar-admin-demo')
@endsection

@section('main')
<div class="space-y-6">
    <div class="panel-action-bar">
        <span class="badge info">{{ count($contactMessages) }} mensajes demo</span>
    </div>

    <section class="card p-0">
        <div class="table-shell table-responsive-cards">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Correo</th>
                        <th>Telefono</th>
                        <th>Asunto</th>
                        <th>Estado</th>
                        <th>Recibido</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($contactMessages as $message)
                        <tr>
                            <td data-label="Nombre">{{ $message['name'] }}</td>
                            <td data-label="Correo">{{ $message['email'] }}</td>
                            <td data-label="Telefono">{{ $message['phone'] }}</td>
                            <td data-label="Asunto">{{ $message['subject'] }}</td>
                            <td data-label="Estado"><span class="badge {{ $message['status_tone'] }}">{{ $message['status'] }}</span></td>
                            <td data-label="Recibido">{{ $message['received_at'] }}</td>
                            <td data-label="Acciones">
                                <button class="btn btn-outline btn-sm demo-action-blocked">Ver detalle</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
