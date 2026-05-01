@extends('layouts.demo')
@section('title', 'Panel administrativo | Demo')
@section('header-title', 'Panel administrativo')
@section('header-subtitle', 'Vision general de la operacion')

@section('sidebar')
    @include('demo.partials.sidebar-admin-demo')
@endsection

@section('main')
<div class="space-y-6">
    <section class="stat-grid">
        <x-ui.stat label="Total de pacientes" :value="$kpis['total_pacientes']" tone="teal">
            <x-slot:icon><i class="ri-group-line"></i></x-slot:icon>
        </x-ui.stat>
        <x-ui.stat label="Total de doctores" :value="$kpis['total_doctores']" tone="sky">
            <x-slot:icon><i class="ri-stethoscope-line"></i></x-slot:icon>
        </x-ui.stat>
        <x-ui.stat label="Usuarios activos hoy" :value="$kpis['usuarios_activos']" tone="amber">
            <x-slot:icon><i class="ri-flashlight-line"></i></x-slot:icon>
        </x-ui.stat>
        <x-ui.stat label="Citas del mes" :value="$kpis['citas_mes']" tone="slate">
            <x-slot:icon><i class="ri-calendar-event-line"></i></x-slot:icon>
        </x-ui.stat>
    </section>

    <div class="grid gap-6 lg:grid-cols-[1.35fr_0.65fr]">
        <section class="card p-6">
            <div class="page-header">
                <div class="page-header__info">
                    <h2>Citas recientes</h2>
                    <p>Replica visual del tablero operativo con agenda del dia.</p>
                </div>
            </div>

            <div class="mt-4 table-shell table-responsive-cards">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Hora</th>
                            <th>Paciente</th>
                            <th>Doctor</th>
                            <th>Especialidad</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentAppointments as $appointment)
                            <tr>
                                <td data-label="Hora">{{ $appointment['time'] }}</td>
                                <td data-label="Paciente">{{ $appointment['patient'] }}</td>
                                <td data-label="Doctor">{{ $appointment['doctor'] }}</td>
                                <td data-label="Especialidad">{{ $appointment['specialty'] }}</td>
                                <td data-label="Estado"><span class="badge {{ $appointment['status_tone'] }}">{{ $appointment['status'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <button class="btn btn-outline btn-sm demo-action-blocked w-full">Ver todas las citas</button>
            </div>
        </section>

        <aside class="card p-6">
            <h3 class="text-lg font-semibold text-gray-900">Pagos recientes</h3>
            <div class="mt-4 space-y-3">
                @foreach($recentPayments as $payment)
                    <div class="flex items-center justify-between rounded-2xl border border-gray-200 bg-white/90 px-4 py-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ $payment['reference'] }}</p>
                            <p class="text-xs text-gray-500">{{ $payment['patient'] }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-gray-900">{{ $payment['amount'] }}</p>
                            <p class="text-xs text-gray-500">{{ $payment['age'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-4">
                <a href="{{ route('demo.admin.gestion-pagos') }}" class="btn btn-outline btn-sm w-full">Ver todos los pagos</a>
            </div>
        </aside>
    </div>
</div>
@endsection
