@extends('layouts.demo')
@section('title', 'Agenda semanal - Demo')
@section('activeSidebar','agenda')
@section('header-title', 'Agenda semanal')
@section('header-subtitle', 'Consulta disponibilidad y citas de la semana (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-doctor-demo')
@endsection

@section('main')
<section class="space-y-6">
  <div class="panel-action-bar">
    <a class="btn btn-outline btn-sm" href="{{ route('demo.doctor.horario.index') }}">
      <i class="ri-time-line"></i> Mi horario
    </a>
  </div>

  <div class="card p-6">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6 border-b border-gray-100 pb-4">
      <div class="flex flex-wrap items-center gap-2">
        <button class="btn btn-outline btn-sm demo-action-blocked">
          <i class="ri-arrow-left-s-line"></i> Semana anterior
        </button>
        <button class="btn btn-outline btn-sm demo-action-blocked">
          <i class="ri-calendar-line"></i> Semana actual
        </button>
        <button class="btn btn-outline btn-sm demo-action-blocked">
          Siguiente semana <i class="ri-arrow-right-s-line"></i>
        </button>
      </div>

      <div class="flex items-center gap-2">
        <input type="date" value="{{ now()->toDateString() }}" class="form-input" readonly>
        <button class="btn btn-primary btn-sm demo-action-blocked">
          <i class="ri-filter-3-line"></i> Ir
        </button>
      </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-5">
      @foreach($weeklyAgenda as $day)
        <section class="card border border-gray-150 p-5 bg-gray-50/50">
          <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">{{ $day['day'] }}</p>
          <h2 class="mt-2 text-lg font-bold text-gray-900">{{ $day['date'] }}</h2>
          <div class="mt-4 space-y-3">
            @forelse($day['blocks'] as $block)
              <div class="rounded-2xl border border-gray-200 bg-white px-4 py-3 text-xs text-gray-700 font-medium">
                {{ $block }}
              </div>
            @empty
              <div class="text-xs text-gray-400">Sin bloques registrados.</div>
            @endforelse
          </div>
        </section>
      @endforeach
    </div>
  </div>
</section>
@endsection
