@extends('layouts.demo')
@section('title', 'Mantenimiento | Demo')
@section('header-title', 'Mantenimiento')
@section('header-subtitle', 'Estado operativo del sistema')

@section('sidebar')
    @include('demo.partials.sidebar-superadmin-demo')
@endsection

@section('main')
<div class="max-w-4xl space-y-6">
    <section class="card p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-widest text-gray-500">Estado actual</p>
                <h2 class="mt-2 text-xl font-semibold text-gray-900">Modo mantenimiento {{ $maintenance['enabled'] ? 'activo' : 'inactivo' }}</h2>
                <p class="mt-2 text-sm text-gray-600">La demo muestra el mismo panel de control, pero la accion queda bloqueada para evitar cambios reales.</p>
            </div>
            <span class="badge {{ $maintenance['enabled'] ? 'danger' : 'success' }}">{{ $maintenance['enabled'] ? 'Activo' : 'Operativo' }}</span>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                <p class="text-xs uppercase tracking-widest text-gray-500">Ventana</p>
                <p class="mt-2 text-sm font-semibold text-gray-900">{{ $maintenance['window'] }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                <p class="text-xs uppercase tracking-widest text-gray-500">Ultimo cambio</p>
                <p class="mt-2 text-sm font-semibold text-gray-900">{{ $maintenance['last_change'] }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                <p class="text-xs uppercase tracking-widest text-gray-500">Mensaje</p>
                <p class="mt-2 text-sm font-semibold text-gray-900">Personalizado</p>
            </div>
        </div>

        <div class="demo-maintenance-message mt-6 rounded-2xl border p-4 text-sm">
            {{ $maintenance['message'] }}
        </div>

        <div class="mt-6 flex flex-wrap gap-3">
            <button class="btn btn-primary demo-action-blocked">Activar mantenimiento</button>
            <button class="btn btn-outline demo-action-blocked">Guardar mensaje</button>
        </div>
    </section>
</div>
@endsection
