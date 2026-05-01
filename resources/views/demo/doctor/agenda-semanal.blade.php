@extends('layouts.demo')
@section('title', 'Agenda semanal | Demo')
@section('header-title', 'Agenda semanal')
@section('header-subtitle', 'Distribucion semanal de consulta y seguimiento')

@section('sidebar')
    @include('demo.partials.sidebar-doctor-demo')
@endsection

@section('main')
<div class="grid gap-6 xl:grid-cols-5">
    @foreach($weeklyAgenda as $day)
        <section class="card p-5">
            <p class="text-xs uppercase tracking-widest text-gray-500">{{ $day['day'] }}</p>
            <h2 class="mt-2 text-lg font-semibold text-gray-900">{{ $day['date'] }}</h2>
            <div class="mt-4 space-y-3">
                @foreach($day['blocks'] as $block)
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
                        {{ $block }}
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
@endsection
