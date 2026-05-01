@extends('layouts.demo')
@section('title', 'Citas y resultados | Demo')
@section('header-title', 'Citas y resultados')
@section('header-subtitle', 'Gestion de ordenes de laboratorio')

@section('sidebar')
    @include('demo.partials.sidebar-laboratorio-demo')
@endsection

@section('main')
<div class="space-y-6">
    <div class="panel-action-bar flex flex-wrap items-center justify-between gap-4">
        <div class="flex rounded-lg bg-gray-100 p-1">
            <button class="rounded-md bg-white px-4 py-1.5 text-sm font-medium text-gray-900 demo-action-blocked">Pendientes</button>
            <button class="px-4 py-1.5 text-sm font-medium text-gray-500 demo-action-blocked">En proceso</button>
            <button class="px-4 py-1.5 text-sm font-medium text-gray-500 demo-action-blocked">Finalizados</button>
        </div>
        <div class="inline-control-shell max-w-xs flex-1">
            <i class="ri-search-line text-gray-400"></i>
            <input type="text" value="" placeholder="Buscar orden o paciente..." readonly>
        </div>
    </div>

    <section class="card p-0">
        <div class="table-shell table-responsive-cards">
            <table class="table">
                <thead>
                    <tr>
                        <th>Orden</th>
                        <th>Paciente</th>
                        <th>Examenes</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($ordenes as $order)
                        <tr>
                            <td data-label="Orden">{{ $order['code'] }}</td>
                            <td data-label="Paciente">
                                <p class="font-medium text-gray-900">{{ $order['patient'] }}</p>
                                <p class="text-xs text-gray-500">{{ $order['document'] }}</p>
                            </td>
                            <td data-label="Examenes">{{ $order['exam'] }}</td>
                            <td data-label="Estado"><span class="badge {{ $order['status_tone'] }}">{{ $order['status'] }}</span></td>
                            <td data-label="Fecha">{{ $order['date'] }}</td>
                            <td data-label="Acciones">
                                <div class="table-actions">
                                    <button class="btn btn-primary btn-sm demo-action-blocked">Recibir</button>
                                    <button class="btn btn-outline btn-sm demo-action-blocked">Resultados</button>
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
