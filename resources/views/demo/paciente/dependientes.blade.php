@extends('layouts.demo')
@section('title', 'Mis Dependientes - Demo')
@section('activeSidebar', 'dependientes')
@section('header-title', 'Mis Dependientes')
@section('header-subtitle', 'Gestiona los sub-perfiles de tus familiares (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-paciente-demo')
@endsection

@php
    $total = count($dependientes);
    $activos = collect($dependientes)->filter(fn($d) => ($d['status'] ?? 'active') === 'active');
    $inactivos = collect($dependientes)->filter(fn($d) => ($d['status'] ?? 'active') !== 'active');
@endphp

@section('main')
<div class="space-y-6">
    <div class="panel-action-bar flex items-center justify-between">
        <p class="text-sm text-gray-500">
            Registros visibles: <strong>{{ $total }} / 4</strong>
        </p>
        <a href="{{ route('demo.paciente.dependientes.create') }}" class="btn btn-primary">
            <i class="ri-add-line"></i> Agregar dependiente
        </a>
    </div>

    @if(session('success'))
        <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
    @endif

    @if($total === 0)
        <div class="card p-12 text-center bg-white">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-teal-50 p-4 text-teal-600">
                <i class="ri-parent-line text-3xl"></i>
            </div>
            <h3 class="mt-4 text-lg font-semibold text-gray-900">No tienes dependientes registrados</h3>
            <p class="mt-2 text-sm text-gray-500 max-w-md mx-auto">
                Registra a tus hijos menores de edad o padres adultos mayores para poder agendar citas médicas a su nombre y gestionar sus historiales clínicos de forma independiente.
            </p>
            <div class="mt-6">
                <a href="{{ route('demo.paciente.dependientes.create') }}" class="btn btn-primary">
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
                <span class="badge success">{{ count($activos) }} activo(s)</span>
            </div>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($activos as $dep)
                    @php
                      $birth = \Carbon\Carbon::parse($dep['birth_date']);
                      $age = $birth->age;
                      $isMenor = $age < 18;
                    @endphp
                    <div class="card p-6 flex flex-col justify-between space-y-4 bg-white">
                        <div>
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl {{ $isMenor ? 'bg-blue-50 text-blue-600' : 'bg-amber-50 text-amber-600' }}">
                                        @if($isMenor)
                                            <i class="ri-user-smile-line text-2xl"></i>
                                        @else
                                            <i class="ri-user-star-line text-2xl"></i>
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <h4 class="truncate font-semibold text-gray-900 leading-tight">{{ $dep['name'] }}</h4>
                                        <span class="mt-1 inline-flex badge neutral text-xs">{{ $dep['relationship'] }}</span>
                                    </div>
                                </div>
                                <span class="badge success">Activo</span>
                            </div>

                            <div class="mt-4 space-y-2 text-sm text-gray-650">
                                <div class="flex items-center gap-2">
                                    <i class="ri-id-card-line text-gray-400"></i>
                                    <span>Cédula: <strong>{{ $dep['dni'] }}</strong></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <i class="ri-calendar-event-line text-gray-400"></i>
                                    <span>Edad: <strong>{{ $age }} años</strong> ({{ $birth->format('d/m/Y') }})</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 pt-4 border-t border-gray-100">
                            <a href="{{ route('demo.paciente.dependientes.edit', $dep['id']) }}" class="btn btn-outline btn-sm flex-1 text-center justify-center">
                                <i class="ri-edit-line"></i> Editar
                            </a>
                            <form action="{{ route('demo.paciente.dependientes.destroy', $dep['id']) }}" method="POST" onsubmit="return confirm('¿Deseas eliminar este familiar de la simulación?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger"><i class="ri-delete-bin-line"></i> Eliminar</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        @if(count($inactivos) > 0)
            <section class="space-y-4 mt-6">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Dependientes inactivos</h3>
                    </div>
                    <span class="badge neutral">{{ count($inactivos) }} inactivo(s)</span>
                </div>

                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($inactivos as $dep)
                        <!-- Render similarly if needed, currently all are active by default in mock -->
                    @endforeach
                </div>
            </section>
        @endif
    @endif
</div>
@endsection
