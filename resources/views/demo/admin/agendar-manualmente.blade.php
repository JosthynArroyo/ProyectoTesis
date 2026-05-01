@extends('layouts.demo')
@section('title', 'Agendar manualmente | Demo')
@section('header-title', 'Agendar manualmente')
@section('header-subtitle', 'Disponibilidad simulada por doctor')

@section('sidebar')
    @include('demo.partials.sidebar-admin-demo')
@endsection

@section('main')
<div class="space-y-6">
    <section class="card p-6">
        <div class="grid gap-4 md:grid-cols-3">
            @foreach($manualBookingOptions as $option)
                <div class="rounded-3xl border border-gray-200 bg-white/90 p-5">
                    <p class="text-xs uppercase tracking-widest text-gray-500">{{ $option['specialty'] }}</p>
                    <h2 class="mt-3 text-lg font-semibold text-gray-900">{{ $option['doctor'] }}</h2>
                    <p class="mt-2 text-sm text-gray-600">{{ $option['slot'] }}</p>
                    <div class="mt-4 flex items-center justify-between">
                        <span class="badge {{ $option['state_tone'] }}">{{ $option['state'] }}</span>
                        <button class="btn btn-primary btn-sm demo-action-blocked">Reservar</button>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
</div>
@endsection
