@extends('layouts.demo')
@section('title', 'Horarios | Demo')
@section('header-title', 'Horarios')
@section('header-subtitle', 'Bloques operativos y disponibilidad semanal')

@section('sidebar')
    @include('demo.partials.sidebar-admin-demo')
@endsection

@section('main')
<div class="space-y-6">
    <section class="card p-6">
        <div class="page-header">
            <div class="page-header__info">
                <h2>Resumen semanal</h2>
                <p>Replica de la vista de horarios administrativos y cobertura de doctores.</p>
            </div>
            <button class="btn btn-outline btn-sm demo-action-blocked">Editar bloques</button>
        </div>
        <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            @foreach($horarios as $slot)
                <div class="rounded-2xl border border-gray-200 bg-white/90 p-4">
                    <p class="text-xs uppercase tracking-widest text-gray-500">{{ $slot['day'] }}</p>
                    <p class="mt-2 text-base font-semibold text-gray-900">{{ $slot['range'] }}</p>
                    <p class="mt-2 text-sm text-gray-600">{{ $slot['note'] }}</p>
                </div>
            @endforeach
        </div>
    </section>
</div>
@endsection
