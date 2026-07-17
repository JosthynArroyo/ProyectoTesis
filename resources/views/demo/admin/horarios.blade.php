@extends('layouts.demo')
@section('title','Horarios de doctores - Demo')
@section('header-title','Horarios')
@section('header-subtitle','Planificacion semanal de doctores (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-admin-demo')
@endsection

@php
  $horariosCollection = collect($horarios)->map(function($h) {
      $horarioObj = new \App\Models\Horario($h);
      $horarioObj->id = $h['id'];
      $horarioObj->exists = true;
      $horarioObj->fecha = \Carbon\Carbon::parse($h['fecha']);
      $horarioObj->hora_inicio = $h['hora_inicio'];
      $horarioObj->hora_fin = $h['hora_fin'];
      $horarioObj->setRelation('doctor', new \App\Models\User(['name' => $h['doctor']]));
      return $horarioObj;
  });

  $doctoresList = collect($usuarios)->filter(fn($u) => strtolower($u['role']) === 'doctor')->map(function($u) {
      $userObj = new \App\Models\User($u);
      $userObj->id = $u['id'];
      return $userObj;
  });

  $showAllDoctors = true;
  $activeDoctorId = 'all';
@endphp

@section('main')
  <div class="space-y-6">
    <div class="panel-action-bar">
      <a class="btn btn-primary btn-full-mobile" href="{{ route('demo.admin.horarios.create') }}">
        <i class="ri-add-line"></i> Nuevo horario
      </a>
    </div>

    @if (session('success'))
      <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
    @endif

    <form method="GET" action="{{ route('demo.admin.horarios.index') }}" class="card p-6">
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div>
          <label class="form-label" for="doctor_id">Doctor</label>
          <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2">
            <i class="ri-stethoscope-line text-gray-400"></i>
            <select id="doctor_id" name="doctor_id" class="w-full bg-transparent text-sm">
              <option value="all" selected>Todos los doctores</option>
              @foreach($doctoresList as $doctor)
                <option value="{{ $doctor->id }}">
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
            <input id="week" class="w-full bg-transparent text-sm" type="date" name="week" value="{{ now()->startOfWeek()->toDateString() }}" required>
          </div>
        </div>

        <div class="xl:col-span-2 flex items-end gap-2">
          <button class="btn btn-primary w-full sm:w-auto" type="submit">
            <i class="ri-filter-3-line"></i> Aplicar filtros
          </button>
          <a class="btn btn-ghost btn-full-mobile" href="{{ route('demo.admin.horarios.index') }}">
            <i class="ri-refresh-line"></i> Semana actual
          </a>
        </div>
      </div>
    </form>

    <section class="card p-6">
      <div class="page-header">
        <div class="page-header__info">
          <h2>Bloques registrados</h2>
          <p>Edición rápida de los horarios visibles en la semana seleccionada (Simulado).</p>
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
            @forelse($horariosCollection as $horario)
              <tr>
                @if($showAllDoctors)
                  <td data-label="Doctor">{{ $horario->doctor?->name ?? 'Sin doctor' }}</td>
                @endif
                <td data-label="Fecha">
                  {{ optional($horario->fecha)->format('d/m/Y') }}
                </td>
                <td data-label="Inicio">{{ substr((string) $horario->hora_inicio, 0, 5) }}</td>
                <td data-label="Fin">{{ substr((string) $horario->hora_fin, 0, 5) }}</td>
                <td data-label="Acciones">
                  <div class="table-actions table-actions--start">
                    <a class="btn btn-ghost btn-sm" href="{{ route('demo.admin.horarios.edit', $horario->id) }}">
                      <i class="ri-edit-line"></i> Editar
                    </a>
                    <form action="{{ route('demo.admin.horarios.destroy', $horario->id) }}" method="POST">
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
              <tr><td colspan="5">No hay horarios registrados.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>
  </div>
@endsection
