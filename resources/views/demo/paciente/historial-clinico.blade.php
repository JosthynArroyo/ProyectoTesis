@extends('layouts.demo')
@section('title', 'Historial clinico | Demo')
@section('header-title', 'Historial clinico')
@section('header-subtitle', 'Resumen longitudinal del paciente')

@section('sidebar')
    @include('demo.partials.sidebar-paciente-demo')
@endsection

@section('main')
<div class="space-y-6">
    @foreach($historyEntries as $entry)
        <section class="card p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-widest text-gray-500">{{ $entry['specialty'] }}</p>
                    <h2 class="mt-2 text-lg font-semibold text-gray-900">{{ $entry['doctor'] }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ $entry['date'] }}</p>
                </div>
                <button class="btn btn-outline btn-sm demo-action-blocked">Ver detalle</button>
            </div>
            <p class="mt-4 text-sm leading-6 text-gray-600">{{ $entry['summary'] }}</p>
        </section>
    @endforeach
</div>
@endsection
