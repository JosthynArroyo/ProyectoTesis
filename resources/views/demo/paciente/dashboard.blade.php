@extends('layouts.demo')
@section('title', 'Portal paciente | Demo')
@section('header-title', 'Panel del paciente')
@section('header-subtitle', 'Resumen de citas y laboratorio')

@section('sidebar')
    @include('demo.partials.sidebar-paciente-demo')
@endsection

@section('main')
<div class="space-y-6">
    <section class="stat-grid">
        <x-ui.stat label="Citas agendadas" :value="$totalCitas" tone="teal">
            <x-slot:icon><i class="ri-calendar-check-line"></i></x-slot:icon>
            <p class="text-xs text-gray-500">Este mes</p>
        </x-ui.stat>
        <x-ui.stat label="Completadas" :value="$totalCitasRealizadas" tone="sky">
            <x-slot:icon><i class="ri-checkbox-circle-line"></i></x-slot:icon>
            <p class="text-xs text-gray-500">Historial</p>
        </x-ui.stat>
        <x-ui.stat label="Pendientes" :value="$totalCitasPendientes" tone="amber">
            <x-slot:icon><i class="ri-timer-line"></i></x-slot:icon>
            <p class="text-xs text-gray-500">Proximas</p>
        </x-ui.stat>
        <x-ui.stat label="Ordenes de cobro" :value="$totalPagos" tone="slate">
            <x-slot:icon><i class="ri-wallet-3-line"></i></x-slot:icon>
            <p class="text-xs text-gray-500">Pagadas: {{ $pagosPagados }}</p>
        </x-ui.stat>
    </section>

    <div class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
        <section class="card p-6">
            <div class="page-header">
                <div class="page-header__info">
                    <h2>Mis proximas citas</h2>
                    <p>Las citas mas cercanas del paciente demo.</p>
                </div>
                <div class="page-header__actions">
                    <a class="btn btn-ghost" href="{{ route('demo.paciente.citas') }}">
                        <i class="ri-arrow-right-line"></i> Ver todas
                    </a>
                </div>
            </div>

            <div class="mt-4 table-shell table-responsive-cards">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Especialidad</th>
                            <th>Doctor</th>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($citas as $appointment)
                            <tr>
                                <td data-label="Especialidad">{{ $appointment['specialty'] }}</td>
                                <td data-label="Doctor">{{ $appointment['doctor'] }}</td>
                                <td data-label="Fecha">{{ $appointment['date'] }}</td>
                                <td data-label="Hora">{{ $appointment['time'] }}</td>
                                <td data-label="Estado"><span class="badge {{ $appointment['status_tone'] }}">{{ $appointment['status'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="space-y-6">
            <section class="card p-6">
                <h3 class="text-lg font-semibold text-gray-900">Examenes de laboratorio</h3>
                <div class="mt-4 space-y-3">
                    @foreach($labOrders as $order)
                        <div class="rounded-2xl border border-gray-200 bg-white/90 p-4">
                            <p class="text-sm font-semibold text-gray-900">{{ $order['exam'] }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ $order['origin'] }}</p>
                            <div class="mt-3">
                                <span class="badge {{ $order['status_tone'] }}">{{ $order['status'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="card p-6">
                <h3 class="text-lg font-semibold text-gray-900">Acciones rapidas</h3>
                <div class="mt-4 space-y-3">
                    <a href="{{ route('demo.paciente.agendar-cita') }}" class="btn btn-primary w-full">Agendar cita</a>
                    <a href="{{ route('demo.paciente.solicitar-examen') }}" class="btn btn-outline w-full">Solicitar examen</a>
                    <a href="{{ route('demo.paciente.resultados') }}" class="btn btn-outline w-full">Ver resultados</a>
                </div>
            </section>
        </aside>
    </div>
</div>
@endsection
