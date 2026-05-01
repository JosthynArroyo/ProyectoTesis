@extends('layouts.demo')
@section('title', 'Panel medico | Demo')
@section('header-title', 'Panel medico')
@section('header-subtitle', 'Resumen del dia y agenda activa')

@section('sidebar')
    @include('demo.partials.sidebar-doctor-demo')
@endsection

@section('main')
<div class="space-y-6">
    <section class="stat-grid">
        <x-ui.stat label="Pacientes atendidos" :value="$estadisticas['pacientes_atendidos']" tone="sky">
            <x-slot:icon><i class="ri-user-heart-line"></i></x-slot:icon>
        </x-ui.stat>
        <x-ui.stat label="Citas pendientes" :value="$estadisticas['citas_pendientes']" tone="amber">
            <x-slot:icon><i class="ri-calendar-todo-line"></i></x-slot:icon>
        </x-ui.stat>
        <x-ui.stat label="Calificacion" :value="$estadisticas['calificacion']" tone="teal">
            <x-slot:icon><i class="ri-star-fill"></i></x-slot:icon>
        </x-ui.stat>
        <x-ui.stat label="Citas hoy" :value="$estadisticas['citas_hoy']" tone="slate">
            <x-slot:icon><i class="ri-calendar-check-line"></i></x-slot:icon>
        </x-ui.stat>
    </section>

    <div class="grid gap-6 lg:grid-cols-[1.3fr_0.7fr]">
        <section class="card p-6">
            <div class="page-header">
                <div class="page-header__info">
                    <p class="text-xs uppercase tracking-widest text-gray-500">Agenda</p>
                    <h2>Citas recientes</h2>
                    <p>Actualizadas en tiempo real dentro de la demo.</p>
                </div>
                <div class="page-header__actions">
                    <a href="{{ route('demo.doctor.citas') }}" class="btn btn-outline btn-sm">Ver agenda completa</a>
                </div>
            </div>
            <div class="mt-4 space-y-3">
                @foreach($citasHoy as $appointment)
                    <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-white/90 p-4">
                        <div class="flex items-center gap-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-100 text-sm font-semibold text-blue-700">{{ $appointment['time'] }}</div>
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900">{{ $appointment['patient'] }}</h3>
                                <p class="text-xs text-gray-500">{{ $appointment['age'] }} anios, {{ $appointment['sex'] }}</p>
                                <p class="mt-1 text-sm text-gray-600">{{ $appointment['reason'] }}</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-full {{ $appointment['priority_badge'] }} px-2 py-0.5 text-xs font-medium">{{ $appointment['priority_label'] }}</span>
                            <span class="badge {{ $appointment['status_tone'] }}">{{ $appointment['status'] }}</span>
                            <button class="btn btn-primary btn-sm demo-action-blocked">Atender</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <aside class="space-y-6">
            <section class="card p-6">
                <h3 class="text-lg font-semibold text-gray-900">Acciones rapidas</h3>
                <div class="mt-4 space-y-3">
                    <button class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-left text-sm font-medium text-gray-900 transition hover:bg-gray-50 demo-action-blocked">
                        <i class="ri-file-add-line mr-2 text-blue-600"></i> Nueva receta medica
                    </button>
                    <button class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-left text-sm font-medium text-gray-900 transition hover:bg-gray-50 demo-action-blocked">
                        <i class="ri-flask-line mr-2 text-teal-600"></i> Orden de laboratorio
                    </button>
                    <button class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-left text-sm font-medium text-gray-900 transition hover:bg-gray-50 demo-action-blocked">
                        <i class="ri-calendar-event-line mr-2 text-purple-600"></i> Agendar control
                    </button>
                </div>
            </section>
        </aside>
    </div>
</div>
@endsection
