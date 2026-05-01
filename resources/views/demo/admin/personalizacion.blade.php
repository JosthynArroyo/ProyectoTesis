@extends('layouts.demo')
@section('title', 'Personalizacion admin | Demo')
@section('header-title', 'Personalizacion')
@section('header-subtitle', 'Replica segura de la configuracion publica')

@section('sidebar')
    @include('demo.partials.sidebar-admin-demo')
@endsection

@section('main')
<div class="grid gap-6 xl:grid-cols-3">
    @foreach($personalizationSections as $section)
        <section id="{{ strtolower($section['name']) }}" class="card p-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ $section['name'] }}</h2>
                    <p class="mt-2 text-sm text-gray-600">{{ $section['description'] }}</p>
                </div>
                <span class="badge {{ $section['status_tone'] }}">{{ $section['status'] }}</span>
            </div>
            <div class="mt-6 rounded-2xl border border-gray-200 bg-gray-50 p-4">
                <p class="text-xs uppercase tracking-widest text-gray-500">Vista demo</p>
                <p class="mt-2 text-sm text-gray-700">Este modulo conserva la estructura del panel real, pero cualquier guardado queda bloqueado en modo demostracion.</p>
            </div>
            <div class="mt-6 flex gap-3">
                <button class="btn btn-primary demo-action-blocked">Guardar</button>
                <button class="btn btn-outline demo-action-blocked">Solicitar cambios</button>
            </div>
        </section>
    @endforeach
</div>
@endsection
