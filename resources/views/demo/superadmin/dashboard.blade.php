@extends('layouts.demo')
@section('title', 'Superadmin | Demo')
@section('header-title', 'Panel global')
@section('header-subtitle', 'Metricas y control centralizado')

@section('sidebar')
    @include('demo.partials.sidebar-superadmin-demo')
@endsection

@section('main')
<div class="space-y-6">
    <div class="panel-action-bar">
        <a class="btn btn-outline btn-full-mobile" href="{{ route('demo.superadmin.administradores') }}">
            <i class="ri-shield-user-line"></i> Administradores
        </a>
        <a class="btn btn-primary btn-full-mobile" href="{{ route('demo.superadmin.personalizacion') }}">
            <i class="ri-palette-line"></i> Personalizacion
        </a>
    </div>

    <section class="stat-grid">
        <a class="block" href="{{ route('demo.superadmin.usuarios') }}">
            <x-ui.stat label="Usuarios totales" :value="$kpis['total_usuarios']" tone="slate">
                <x-slot:icon><i class="ri-group-line"></i></x-slot:icon>
            </x-ui.stat>
        </a>
        <div>
            <x-ui.stat label="Pacientes" :value="$kpis['pacientes']" tone="teal">
                <x-slot:icon><i class="ri-heart-pulse-line"></i></x-slot:icon>
            </x-ui.stat>
        </div>
        <div>
            <x-ui.stat label="Doctores" :value="$kpis['doctores']" tone="sky">
                <x-slot:icon><i class="ri-stethoscope-line"></i></x-slot:icon>
            </x-ui.stat>
        </div>
        <a class="block" href="{{ route('demo.superadmin.administradores') }}">
            <x-ui.stat label="Administradores" :value="$kpis['total_admins']" tone="amber">
                <x-slot:icon><i class="ri-shield-user-line"></i></x-slot:icon>
            </x-ui.stat>
        </a>
    </section>

    <section class="stat-grid">
        <div>
            <x-ui.stat label="Laboratorio" :value="$kpis['laboratorios']" tone="rose">
                <x-slot:icon><i class="ri-test-tube-line"></i></x-slot:icon>
            </x-ui.stat>
        </div>
        <div>
            <x-ui.stat label="Citas totales" :value="$kpis['citas_totales']" tone="slate">
                <x-slot:icon><i class="ri-calendar-check-line"></i></x-slot:icon>
            </x-ui.stat>
        </div>
        <div>
            <x-ui.stat label="Citas hoy" :value="$kpis['citas_hoy']" tone="teal">
                <x-slot:icon><i class="ri-calendar-todo-line"></i></x-slot:icon>
                <div class="mt-2 text-sm text-gray-500">Pendientes: {{ $kpis['pendientes_hoy'] }}</div>
            </x-ui.stat>
        </div>
    </section>

    <section class="card p-6">
        <div class="page-header">
            <div class="page-header__info">
                <p class="text-xs uppercase tracking-widest text-gray-500">Actividad</p>
                <h2>Citas y usuarios por dia</h2>
                <p>Ultimos 7 dias de actividad del sistema.</p>
            </div>
        </div>
        <div class="activity-strip mt-4">
            @foreach($activity as $item)
                <div class="activity-card">
                    <p class="activity-card__label">{{ $item['label'] }}</p>
                    <div class="activity-card__track">
                        <div class="flex h-full items-end">
                            <div class="activity-card__bar" style="height: {{ $item['height'] }}%"></div>
                        </div>
                    </div>
                    <div class="activity-card__stats">
                        <p class="activity-card__stat">
                            <span>Citas</span>
                            <strong>{{ $item['citas'] }}</strong>
                        </p>
                        <p class="activity-card__stat activity-card__stat--muted">
                            <span>Usuarios</span>
                            <strong>{{ $item['usuarios'] }}</strong>
                        </p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="card p-6">
        <div class="page-header">
            <div class="page-header__info">
                <p class="text-xs uppercase tracking-widest text-gray-500">Solicitudes</p>
                <h2>Personalizacion</h2>
                <p>Revisa accesos pendientes de administradores.</p>
            </div>
            <div class="text-right">
                <p class="text-3xl font-semibold text-gray-900">{{ $pendingPersonalizacion }}</p>
                <p class="text-xs text-gray-500">Pendientes</p>
            </div>
        </div>
        <div class="mt-4">
            <a class="btn btn-outline btn-full-mobile" href="{{ route('demo.superadmin.solicitudes') }}">
                <i class="ri-notification-4-line"></i> Ver solicitudes
            </a>
        </div>
    </section>
</div>
@endsection
