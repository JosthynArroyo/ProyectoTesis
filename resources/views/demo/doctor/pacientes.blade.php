@extends('layouts.demo')
@section('title', 'Pacientes doctor | Demo')
@section('header-title', 'Pacientes')
@section('header-subtitle', 'Consulta rapida de historiales e informacion')

@section('sidebar')
    @include('demo.partials.sidebar-doctor-demo')
@endsection

@section('main')
<div class="space-y-6">
    <div class="panel-action-bar">
        <div class="inline-control-shell max-w-sm flex-1">
            <i class="ri-search-line text-gray-400"></i>
            <input type="text" value="" placeholder="Buscar por nombre o documento..." readonly>
        </div>
    </div>

    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        @foreach($patients as $patient)
            <section class="card p-0 transition-all hover:-translate-y-1 hover:shadow-md">
                <div class="border-b border-gray-100 p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 text-lg font-semibold text-blue-700">
                                {{ strtoupper(substr($patient['name'], 0, 1)) }}
                            </div>
                            <div>
                                <h2 class="text-base font-semibold text-gray-900">{{ $patient['name'] }}</h2>
                                <p class="text-sm text-gray-500">C.I: {{ $patient['document'] }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 grid gap-2 sm:grid-cols-3">
                        <div class="rounded-xl bg-gray-50 p-2 text-center">
                            <span class="block text-xs text-gray-500">Edad</span>
                            <span class="font-semibold text-gray-900">{{ $patient['age'] }}</span>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-2 text-center">
                            <span class="block text-xs text-gray-500">Sangre</span>
                            <span class="font-semibold text-rose-600">{{ $patient['blood_type'] }}</span>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-2 text-center">
                            <span class="block text-xs text-gray-500">Ultima cita</span>
                            <span class="font-semibold text-gray-900">{{ $patient['last_visit'] }}</span>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3">
                    <button class="text-sm font-medium text-blue-700 demo-action-blocked">Ver historial <i class="ri-arrow-right-line align-middle"></i></button>
                </div>
            </section>
        @endforeach
    </div>
</div>
@endsection
