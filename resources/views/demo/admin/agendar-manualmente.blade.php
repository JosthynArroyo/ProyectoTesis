@extends('layouts.demo')
@section('title', 'Agendar cita con excepcion - Demo')
@section('header-title', 'Agendar cita con excepcion')
@section('header-subtitle', 'Uso exclusivo para casos especiales (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-admin-demo')
@endsection

@php
  $pacientesList = collect($usuarios)->filter(fn($u) => strtolower($u['role']) === 'paciente')->map(function($u) {
      $userObj = new \App\Models\User($u);
      $userObj->id = $u['id'];
      return $userObj;
  });
  $especialidadesList = collect([
      new \App\Models\Especialidad(['id' => 1, 'nombre' => 'Pediatría']),
      new \App\Models\Especialidad(['id' => 2, 'nombre' => 'Medicina general']),
  ]);
  $doctoresList = collect($usuarios)->filter(fn($u) => strtolower($u['role']) === 'doctor')->map(function($u) {
      $userObj = new \App\Models\User($u);
      $userObj->id = $u['id'];
      return $userObj;
  });
@endphp

@section('main')
<div class="space-y-6">
  <div class="panel-action-bar">
    <a href="{{ route('demo.admin.pagos.index') }}" class="btn btn-outline">
      <i class="ri-wallet-3-line"></i> Ver pagos
    </a>
  </div>

  @if(session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  <x-ui.alert tone="warning">
    Este formulario debe usarse solo para casos especiales. Si se fuerza la creacion con pagos pendientes, el motivo queda registrado.
  </x-ui.alert>

  <section class="card p-6">
    <form
      method="POST"
      action="{{ route('demo.admin.citas.override.store') }}"
      class="space-y-5"
    >
      @csrf

      <section class="panel-form-section">
        <div class="panel-form-section__header">
          <div class="panel-form-section__heading">
            <h2 class="panel-form-section__title">
              <span class="panel-form-section__icon"><i class="ri-user-line"></i></span>
              Paciente y profesión
            </h2>
            <p class="panel-form-section__hint">Selecciona el paciente, la especialidad y el profesional antes de elegir fecha y hora.</p>
          </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <div class="md:col-span-2">
            <label class="form-label" for="paciente_id">Paciente</label>
            <select id="paciente_id" name="paciente_id" class="form-select" required>
              <option value="">Seleccione un paciente</option>
              @foreach($pacientesList as $paciente)
                <option value="{{ $paciente->id }}">
                  {{ $paciente->name }} - {{ $paciente->dni }} - {{ $paciente->email }}
                </option>
              @endforeach
            </select>
          </div>

          <div>
            <label class="form-label" for="especialidad_id">Especialidad</label>
            <select id="especialidad_id" name="especialidad_id" class="form-select" required>
              <option value="">Seleccione especialidad</option>
              @foreach($especialidadesList as $esp)
                <option value="{{ $esp->id }}">{{ $esp->nombre }}</option>
              @endforeach
            </select>
          </div>

          <div>
            <label class="form-label" for="doctor_id">Profesional</label>
            <select id="doctor_id" name="doctor_id" class="form-select" required>
              <option value="">Seleccione doctor</option>
              @foreach($doctoresList as $doc)
                <option value="{{ $doc->id }}">{{ $doc->name }}</option>
              @endforeach
            </select>
          </div>
        </div>
      </section>

      <section class="panel-form-section">
        <div class="panel-form-section__header">
          <div class="panel-form-section__heading">
            <h2 class="panel-form-section__title">
              <span class="panel-form-section__icon"><i class="ri-calendar-check-line"></i></span>
              Agenda de la cita
            </h2>
            <p class="panel-form-section__hint">Elige la fecha y el horario disponible.</p>
          </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <div>
            <label class="form-label" for="fecha">Fecha</label>
            <input
              id="fecha"
              type="date"
              name="fecha"
              value="{{ now()->toDateString() }}"
              class="form-input mt-0"
              required
            >
          </div>

          <div>
            <label class="form-label" for="hora">Hora disponible</label>
            <select id="hora" name="hora" class="form-select" required>
              <option value="08:00">08:00</option>
              <option value="09:00">09:00</option>
              <option value="10:00">10:00</option>
              <option value="11:00">11:00</option>
              <option value="14:00">14:00</option>
              <option value="15:00">15:00</option>
            </select>
          </div>

          <div class="md:col-span-2">
            <label class="form-label" for="motivo_consulta">Motivo de consulta</label>
            <input
              id="motivo_consulta"
              type="text"
              name="motivo_consulta"
              value="Fiebre y malestar general"
              required
              class="form-input"
            >
          </div>
        </div>
      </section>

      <div class="mt-6 flex justify-between">
        <a class="btn btn-ghost" href="{{ route('demo.admin.dashboard') }}">
          <i class="ri-arrow-left-line"></i> Volver
        </a>
        <button class="btn btn-primary" type="submit">
          <i class="ri-save-line"></i> Agendar cita
        </button>
      </div>
    </form>
  </section>
</div>
@endsection
