@extends('layouts.paciente')
@section('title', 'Mis Dependientes')
@section('header-title', 'Mis Dependientes')
@section('header-subtitle', 'Gestiona los sub-perfiles de tus familiares')

@php
    $activos = $dependientes->where('activo', true)->values();
    $inactivos = $dependientes->where('activo', false)->values();
    $total = $dependientes->count();
@endphp

@section('main')
<div class="space-y-6">
    <div class="panel-action-bar flex items-center justify-between">
        <p class="text-sm text-gray-500">
            Registros visibles: <strong>{{ $total }} / {{ \App\Models\Dependiente::MAX_POR_USUARIO }}</strong>
        </p>
        @if(!$limiteAlcanzado)
            <a href="{{ route('paciente.dependientes.create') }}" class="btn btn-primary">
                <i class="ri-add-line"></i> Agregar dependiente
            </a>
        @else
            <button class="btn btn-primary opacity-60 cursor-not-allowed" disabled title="Has alcanzado el límite máximo de dependientes">
                <i class="ri-lock-2-line"></i> Límite alcanzado
            </button>
        @endif
    </div>

    @if(session('success'))
        <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
    @endif

    @if(session('error'))
        <x-ui.alert tone="error">{{ session('error') }}</x-ui.alert>
    @endif

    @if($dependientes->isEmpty())
        <div class="card p-12 text-center">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-teal-50 p-4 text-teal-600">
                <i class="ri-parent-line text-3xl"></i>
            </div>
            <h3 class="mt-4 text-lg font-semibold text-gray-900">No tienes dependientes registrados</h3>
            <p class="mt-2 text-sm text-gray-500 max-w-md mx-auto">
                Registra a tus hijos menores de edad o padres adultos mayores para poder agendar citas médicas a su nombre y gestionar sus historiales clínicos de forma independiente.
            </p>
            <div class="mt-6">
                <a href="{{ route('paciente.dependientes.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Registrar primer dependiente
                </a>
            </div>
        </div>
    @else
        <section class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Dependientes activos</h3>
                    <p class="text-sm text-gray-500">Disponibles para agendar citas y continuar su historial clínico.</p>
                </div>
                <span class="badge success">{{ $activos->count() }} activo(s)</span>
            </div>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @forelse($activos as $dep)
                    @include('paciente.dependientes.partials.card', ['dep' => $dep])
                @empty
                    <div class="card p-6 text-sm text-gray-500 sm:col-span-2 lg:col-span-3">No hay dependientes activos.</div>
                @endforelse
            </div>
        </section>

        <section class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Dependientes inactivos</h3>
                    <p class="text-sm text-gray-500">Se conservan visibles para poder reactivarlos o eliminar los que no tengan historial asociado.</p>
                </div>
                <span class="badge neutral">{{ $inactivos->count() }} inactivo(s)</span>
            </div>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @forelse($inactivos as $dep)
                    @include('paciente.dependientes.partials.card', ['dep' => $dep])
                @empty
                    <div class="card p-6 text-sm text-gray-500 sm:col-span-2 lg:col-span-3">No hay dependientes inactivos.</div>
                @endforelse
            </div>
        </section>
    @endif
</div>
@endsection
