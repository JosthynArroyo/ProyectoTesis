@extends('layouts.doctor')
@section('title','Agenda semanal | Doctor')
@section('activeSidebar','agenda')
@section('header-title','Agenda semanal')
@section('header-subtitle','Consulta disponibilidad y citas de la semana')

@push('head')
  @vite('resources/css/panel/weekly-schedule.css')
@endpush

@php
  $prevWeek = $weekStart->copy()->subWeek()->toDateString();
  $nextWeek = $weekStart->copy()->addWeek()->toDateString();
  $currentWeek = now()->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();
  $toolbarInputValue = request('week', $weekStart->toDateString());
  $legend = [
    ['label' => 'Bloque disponible', 'tone' => 'slate', 'variant' => 'soft'],
    ['label' => 'Pendiente', 'tone' => 'amber'],
    ['label' => 'Confirmada', 'tone' => 'blue'],
    ['label' => 'Realizada', 'tone' => 'emerald'],
    ['label' => 'Cancelada / ausente', 'tone' => 'rose'],
  ];
@endphp

@section('main')
  <section class="space-y-6">
    <x-ui.weekly-schedule
      :calendar="$calendar"
      eyebrow="Panel médico"
      title="Horario semanal"
      subtitle="Una vista semanal con tus bloques de atención y las citas ya registradas."
      :legend="$legend"
      empty-title="Sin actividad en esta semana"
      empty-message="Configura bloques en Mi horario o avanza a otra semana para revisar tu agenda."
    >
      <x-slot:actions>
        <a class="btn btn-outline btn-sm" href="{{ route('doctor.horario.index') }}">
          <i class="ri-time-line"></i> Mi horario
        </a>
      </x-slot:actions>

      <x-slot:toolbar>
        <div class="flex flex-wrap items-center gap-2">
          <a class="btn btn-outline btn-sm" href="{{ route('doctor.agenda', ['week' => $prevWeek]) }}">
            <i class="ri-arrow-left-s-line"></i> Semana anterior
          </a>
          <a class="btn btn-outline btn-sm" href="{{ route('doctor.agenda', ['week' => $currentWeek]) }}">
            <i class="ri-calendar-line"></i> Semana actual
          </a>
          <a class="btn btn-outline btn-sm" href="{{ route('doctor.agenda', ['week' => $nextWeek]) }}">
            Siguiente semana <i class="ri-arrow-right-s-line"></i>
          </a>
        </div>

        <form method="GET" action="{{ route('doctor.agenda') }}" class="flex items-center gap-2">
          <label class="form-label sr-only" for="doctor-agenda-week">Semana</label>
          <input
            id="doctor-agenda-week"
            type="date"
            name="week"
            value="{{ $toolbarInputValue }}"
            class="form-input"
          >
          <button class="btn btn-primary btn-sm" type="submit">
            <i class="ri-filter-3-line"></i> Ir
          </button>
        </form>
      </x-slot:toolbar>
    </x-ui.weekly-schedule>
  </section>
@endsection
