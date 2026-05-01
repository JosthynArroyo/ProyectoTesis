@extends('layouts.admin')
@section('title','Horarios de doctores')
@section('header-title','Horarios')
@section('header-subtitle','Planificacion semanal de doctores')

@push('head')
  @vite('resources/css/panel/weekly-schedule.css')
@endpush

@php
  $prevWeek = $weekStart->copy()->subWeek()->toDateString();
  $nextWeek = $weekStart->copy()->addWeek()->toDateString();
  $currentWeek = now()->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();
  $activeDoctorId = (string) ($doctorId ?? 'all');
  $showAllDoctors = $showAllDoctors ?? $activeDoctorId === 'all';
  $legend = [
    ['label' => 'Bloque disponible', 'tone' => 'slate', 'variant' => 'soft'],
    ['label' => 'Pendiente', 'tone' => 'amber'],
    ['label' => 'Confirmada', 'tone' => 'blue'],
    ['label' => 'Realizada', 'tone' => 'emerald'],
    ['label' => 'Cancelada / ausente', 'tone' => 'rose'],
  ];
@endphp

@section('main')
  <div class="space-y-6">
    <div class="panel-action-bar">
      <a class="btn btn-primary btn-full-mobile" href="{{ route('admin.horarios.create') }}">
        <i class="ri-add-line"></i> Nuevo horario
      </a>
    </div>

    @if (session('success'))
      <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
    @endif

    <form method="GET" action="{{ route('admin.horarios.index') }}" class="card p-6">
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div>
          <label class="form-label" for="doctor_id">Doctor</label>
          <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2">
            <i class="ri-stethoscope-line text-gray-400"></i>
            <select id="doctor_id" name="doctor_id" class="w-full bg-transparent text-sm">
              <option value="all" {{ $showAllDoctors ? 'selected' : '' }}>Todos los doctores</option>
              @foreach($doctores as $doctor)
                <option value="{{ $doctor->id }}" {{ (string) $activeDoctorId === (string) $doctor->id ? 'selected' : '' }}>
                  {{ $doctor->name }}
                </option>
              @endforeach
            </select>
          </div>
        </div>

        <div>
          <label class="form-label" for="week">Semana</label>
          <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2">
            <i class="ri-calendar-line text-gray-400"></i>
            <input id="week" class="w-full bg-transparent text-sm" type="date" name="week" value="{{ request('week', $weekStart->toDateString()) }}" required>
          </div>
        </div>

        <div class="xl:col-span-2 flex items-end gap-2">
          <button class="btn btn-primary w-full sm:w-auto" type="submit">
            <i class="ri-filter-3-line"></i> Aplicar filtros
          </button>
          <a class="btn btn-ghost btn-full-mobile" href="{{ route('admin.horarios.index', ['doctor_id' => $activeDoctorId, 'week' => $currentWeek]) }}">
            <i class="ri-refresh-line"></i> Semana actual
          </a>
        </div>
      </div>
    </form>

    <x-ui.weekly-schedule
      class="weekly-schedule--admin-compact"
      :calendar="$calendar"
      title=""
      subtitle=""
      :legend="$legend"
      empty-title="Sin bloques para esta semana"
      empty-message="{{ $showAllDoctors ? 'No hay bloques ni citas registradas para los doctores en esta semana.' : 'Crea horarios o navega a otra semana para revisar la disponibilidad del profesional.' }}"
    >
      <x-slot:actions>
        <a class="btn btn-outline btn-sm" href="{{ route('admin.citas.override.create') }}">
          <i class="ri-calendar-check-line"></i> Agendar override
        </a>
      </x-slot:actions>

      <x-slot:toolbar>
        <div class="flex flex-wrap items-center gap-2">
          <a class="btn btn-outline btn-sm" href="{{ route('admin.horarios.index', ['doctor_id' => $activeDoctorId, 'week' => $prevWeek]) }}">
            <i class="ri-arrow-left-s-line"></i> Semana anterior
          </a>
          <a class="btn btn-outline btn-sm" href="{{ route('admin.horarios.index', ['doctor_id' => $activeDoctorId, 'week' => $currentWeek]) }}">
            <i class="ri-calendar-line"></i> Semana actual
          </a>
          <a class="btn btn-outline btn-sm" href="{{ route('admin.horarios.index', ['doctor_id' => $activeDoctorId, 'week' => $nextWeek]) }}">
            Siguiente semana <i class="ri-arrow-right-s-line"></i>
          </a>
        </div>

        <span class="badge neutral">{{ $horarios->count() }} bloques · {{ $citas->count() }} citas</span>
      </x-slot:toolbar>
    </x-ui.weekly-schedule>

    <section class="card p-6">
      <div class="page-header">
        <div class="page-header__info">
          <h2>Bloques registrados</h2>
          <p>Edicion rapida de los horarios visibles en la semana seleccionada.</p>
        </div>
      </div>

      <div class="mt-4 table-shell table-responsive-cards">
        <table class="table">
          <thead>
            <tr>
              @if($showAllDoctors)
                <th>Doctor</th>
              @endif
              <th>Fecha</th>
              <th>Inicio</th>
              <th>Fin</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            @forelse($horarios as $horario)
              <tr>
                @if($showAllDoctors)
                  <td data-label="Doctor">{{ $horario->doctor?->name ?? 'Sin doctor' }}</td>
                @endif
                <td data-label="Fecha">{{ optional($horario->fecha)->format('d/m/Y') }}</td>
                <td data-label="Inicio">{{ substr((string) $horario->hora_inicio, 0, 5) }}</td>
                <td data-label="Fin">{{ substr((string) $horario->hora_fin, 0, 5) }}</td>
                <td data-label="Acciones">
                  <div class="table-actions table-actions--start">
                    <a class="btn btn-ghost btn-sm" href="{{ route('admin.horarios.edit', $horario) }}">
                      <i class="ri-edit-line"></i> Editar
                    </a>
                    <form action="{{ route('admin.horarios.destroy', $horario) }}" method="POST" onsubmit="return confirm('Eliminar este horario');">
                      @csrf
                      @method('DELETE')
                      <button class="btn btn-danger btn-sm" type="submit">
                        <i class="ri-delete-bin-line"></i> Eliminar
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="{{ $showAllDoctors ? 5 : 4 }}">No hay horarios para la semana seleccionada.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>
  </div>
@endsection
