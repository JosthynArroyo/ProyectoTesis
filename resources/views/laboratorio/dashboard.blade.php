@extends('layouts.laboratorio')
@section('title', 'Panel del laboratorio - '.$clinicIdentity->name())
@section('activeSidebar', 'dashboard')
@section('header-title','Panel laboratorio')
@section('header-subtitle','Controla ordenes, resultados y agenda diaria')

@section('main')
  <div class="space-y-6">
    @if(session('success'))
      <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
    @endif

    <section class="stat-grid">
      <x-ui.stat label="Citas de laboratorio" :value="$citasHoy" tone="sky">
        <p class="text-xs text-gray-500">Agenda del dia</p>
        <x-slot:icon><i class="ri-calendar-check-line"></i></x-slot:icon>
      </x-ui.stat>
      <x-ui.stat label="Ordenes pendientes" :value="$ordenesPendientes" tone="amber">
        <p class="text-xs text-gray-500">Legacy y auto-solicitudes</p>
        <x-slot:icon><i class="ri-flask-line"></i></x-slot:icon>
      </x-ui.stat>
      <x-ui.stat label="Resultados hoy" :value="$resultadosHoy" tone="teal">
        <p class="text-xs text-gray-500">Publicados</p>
        <x-slot:icon><i class="ri-checkbox-circle-line"></i></x-slot:icon>
      </x-ui.stat>
    </section>

    <section class="card p-6">
      <div class="page-header">
        <div class="page-header__info">
          <p class="text-xs uppercase tracking-widest text-gray-500">Citas y resultados</p>
          <h2>Ultimas ordenes</h2>
          <p>Ordenes con cita y solicitudes directas asignadas a tu cuenta.</p>
        </div>
        <div class="page-header__actions">
          <a class="btn btn-outline btn-full-mobile" href="{{ route('laboratorio.ordenes.index') }}">
            <i class="ri-file-list-3-line"></i> Gestionar resultados
          </a>
        </div>
      </div>

      <div class="mt-4 table-shell table-responsive-cards">
        <table class="table">
          <thead>
            <tr>
              <th>Paciente</th>
              <th>Examen</th>
              <th>Estado</th>
              <th>Fecha</th>
              <th>Accion</th>
            </tr>
          </thead>
          <tbody>
            @forelse($ordenesRecientes as $orden)
              <tr>
                <td data-label="Paciente">{{ $orden->patient_name }}</td>
                <td data-label="Examen">{{ $orden->exam_name }}</td>
                <td data-label="Estado"><x-ui.badge :tone="$orden->badge_tone">{{ $orden->status_label }}</x-ui.badge></td>
                <td data-label="Fecha">{{ $orden->date_label }}</td>
                <td data-label="Accion">
                  <a class="btn btn-ghost btn-sm" href="{{ $orden->action_url }}">
                    <i class="ri-eye-line"></i> {{ $orden->action_label }}
                  </a>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5">Sin ordenes recientes.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>
  </div>
@endsection
