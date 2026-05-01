@extends('layouts.demo')
@section('title', 'Panel laboratorio | Demo')
@section('header-title', 'Panel laboratorio')
@section('header-subtitle', 'Controla ordenes, resultados y agenda diaria')

@section('sidebar')
    @include('demo.partials.sidebar-laboratorio-demo')
@endsection

@section('main')
<div class="space-y-6">
    <section class="stat-grid">
        <x-ui.stat label="Ordenes pendientes" :value="$estadisticas['pendientes']" tone="amber">
            <x-slot:icon><i class="ri-flask-line"></i></x-slot:icon>
        </x-ui.stat>
        <x-ui.stat label="Muestras hoy" :value="$estadisticas['muestras_hoy']" tone="sky">
            <x-slot:icon><i class="ri-test-tube-line"></i></x-slot:icon>
        </x-ui.stat>
        <x-ui.stat label="Resultados publicados" :value="$estadisticas['resultados_publicados']" tone="teal">
            <x-slot:icon><i class="ri-checkbox-circle-line"></i></x-slot:icon>
        </x-ui.stat>
    </section>

    <div class="grid gap-6 lg:grid-cols-[1.3fr_0.7fr]">
        <section class="card p-6">
            <div class="page-header">
                <div class="page-header__info">
                    <p class="text-xs uppercase tracking-widest text-gray-500">Citas y resultados</p>
                    <h2>Ultimas ordenes</h2>
                    <p>Ordenes asignadas al laboratorio demo.</p>
                </div>
                <div class="page-header__actions">
                    <a class="btn btn-outline btn-sm" href="{{ route('demo.laboratorio.citas-resultados') }}">Gestionar resultados</a>
                </div>
            </div>
            <div class="mt-4 table-shell table-responsive-cards">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Paciente</th>
                            <th>Examen</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th>Accion</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ordenes as $order)
                            <tr>
                                <td data-label="Paciente">{{ $order['patient'] }}</td>
                                <td data-label="Examen">{{ $order['exam'] }}</td>
                                <td data-label="Estado"><span class="badge {{ $order['status_tone'] }}">{{ $order['status'] }}</span></td>
                                <td data-label="Fecha">{{ $order['date'] }}</td>
                                <td data-label="Accion">
                                    <button class="btn btn-outline btn-sm demo-action-blocked">Ver detalle</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="card p-6">
            <h3 class="text-lg font-semibold text-gray-900">Busqueda rapida</h3>
            <div class="mt-4">
                <input type="text" class="form-input" value="LAB-2026-101" readonly>
            </div>
            <div class="mt-4 space-y-3">
                <button class="btn btn-primary w-full demo-action-blocked">Recibir muestra</button>
                <button class="btn btn-outline w-full demo-action-blocked">Subir resultados</button>
            </div>
        </aside>
    </div>
</div>
@endsection
