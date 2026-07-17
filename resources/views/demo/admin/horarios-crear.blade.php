@extends('layouts.demo')
@section('title','Crear horario - Demo')
@section('header-title','Nuevo horario')
@section('header-subtitle','Define rangos y franjas semanales (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-admin-demo')
@endsection

@push('head')
  @vite('resources/css/panel/weekly-schedule.css')
@endpush

@php
  $doctoresList = collect($usuarios)->filter(fn($u) => strtolower($u['role']) === 'doctor')->map(function($u) {
      $userObj = new \App\Models\User($u);
      $userObj->id = $u['id'];
      return $userObj;
  });
  $dias = [1 => 'Lun', 2 => 'Mar', 3 => 'Mie', 4 => 'Jue', 5 => 'Vie', 6 => 'Sab', 7 => 'Dom'];
@endphp

@section('main')
<div class="space-y-6">
  <form action="{{ route('demo.admin.horarios.store') }}" method="POST" class="card p-6" id="form-horario">
    @csrf

    <div class="space-y-5">
      <section class="panel-form-section">
        <div class="panel-form-section__header">
          <div class="panel-form-section__heading">
            <h2 class="panel-form-section__title">
              <span class="panel-form-section__icon"><i class="ri-user-heart-line"></i></span>
              Profesional y rango
            </h2>
            <p class="panel-form-section__hint">Selecciona el doctor y el periodo en el que se van a crear los bloques.</p>
          </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          <div class="md:col-span-3">
            <label class="form-label" for="doctor_id">Doctor</label>
            <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2">
              <i class="ri-stethoscope-line text-gray-400"></i>
              <select class="w-full bg-transparent text-sm" id="doctor_id" name="doctor_id" required>
                <option value="">Seleccione</option>
                @foreach($doctoresList as $d)
                  <option value="{{ $d->id }}">{{ $d->name }}</option>
                @endforeach
              </select>
            </div>
          </div>

          <div>
            <label class="form-label" for="fecha_inicio">Desde</label>
            <div class="relative mt-1">
              <input class="form-input form-input-native-date mt-0 pr-11" type="date" id="fecha_inicio" name="fecha_inicio" value="{{ now()->toDateString() }}" min="{{ now()->toDateString() }}" required>
            </div>
          </div>

          <div>
            <label class="form-label" for="fecha_fin">Hasta</label>
            <div class="relative mt-1">
              <input class="form-input form-input-native-date mt-0 pr-11" type="date" id="fecha_fin" name="fecha_fin" value="{{ now()->addDays(7)->toDateString() }}" required>
            </div>
          </div>
        </div>
      </section>

      <section class="panel-form-section">
        <div class="panel-form-section__header">
          <div class="panel-form-section__heading">
            <h2 class="panel-form-section__title">
              <span class="panel-form-section__icon"><i class="ri-repeat-line"></i></span>
              Dias y repeticion
            </h2>
            <p class="panel-form-section__hint">Marca los dias de trabajo y decide si todos usan la misma franja o una distinta por dia.</p>
          </div>
        </div>

        <div id="dias-wrap" class="flex flex-wrap gap-2">
          @foreach($dias as $num => $lbl)
            @php $checked = in_array($num, [1,2,3,4,5]); @endphp
            <label class="day-chip rounded-full border border-gray-200 px-3 py-2 text-xs font-semibold {{ $checked ? 'active bg-gray-100 text-gray-900 font-medium' : 'text-gray-500' }}">
              <input type="checkbox" name="dias[]" value="{{ $num }}" {{ $checked ? 'checked' : '' }}>
              <span>{{ $lbl }}</span>
            </label>
          @endforeach
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
          <button type="button" class="btn-mini btn btn-outline" data-preset="lv">L-V</button>
          <button type="button" class="btn-mini btn btn-outline" data-preset="ld">L-D</button>
          <button type="button" class="btn-mini btn btn-outline" data-preset="sd">S-D</button>
          <button type="button" class="btn-mini btn btn-outline" data-preset="none">Ninguno</button>
        </div>

        <label class="mt-4 flex items-center gap-2 text-sm text-gray-600" for="misma_franja">
          <input type="hidden" name="misma_franja" value="0">
          <input type="checkbox" id="misma_franja" name="misma_franja" value="1" checked>
          Usar la misma franja para todos los dias marcados
        </label>
      </section>

      <section class="panel-form-section">
        <div class="panel-form-section__header">
          <div class="panel-form-section__heading">
            <h2 class="panel-form-section__title">
              <span class="panel-form-section__icon"><i class="ri-time-line"></i></span>
              Franjas horarias
            </h2>
            <p class="panel-form-section__hint">Mantiene una sola franja o define horas especificas por dia marcado.</p>
          </div>
        </div>

        <input type="hidden" id="intervalo_minutos" value="30">

        <script id="clinica-horarios-config" type="application/json">
          {!! json_encode(collect(range(1, 7))->mapWithKeys(function($day) {
              return [$day => ['status' => 1, 'opening' => '08:00', 'closing' => '17:00']];
          })) !!}
        </script>

        <div id="franja-global" class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="form-label" for="hora_inicio_global">Hora inicio</label>
            <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2">
              <i class="ri-time-line text-gray-400"></i>
              <select class="w-full bg-transparent text-sm" id="hora_inicio_global" name="hora_inicio" required></select>
            </div>
          </div>
          <div>
            <label class="form-label" for="hora_fin_global">Hora fin</label>
            <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2">
              <i class="ri-time-line text-gray-400"></i>
              <select class="w-full bg-transparent text-sm" id="hora_fin_global" name="hora_fin" required></select>
            </div>
          </div>
          <div class="sm:col-span-2">
            <p class="text-xs text-teal-650 dark:text-teal-400 font-semibold" id="clinic-hours-info-global"></p>
          </div>
        </div>

        <div id="franjas-por-dia" class="mt-4 space-y-3" style="display:none">
          <div class="text-xs text-gray-500">Define horas por cada dia marcado.</div>
          @foreach($dias as $num => $lbl)
            <div class="row-dia flex flex-wrap items-center gap-2 rounded-2xl border border-gray-200 bg-white/90 px-3 py-2" data-dia="{{ $num }}">
              <div class="row-dia__label text-sm font-semibold text-gray-600">{{ $lbl }}</div>
              <select class="form-input" id="dias_{{ $num }}_hora_inicio" name="horas[{{ $num }}][inicio]" required></select>
              <span class="row-dia__sep text-xs text-gray-400">a</span>
              <select class="form-input" id="dias_{{ $num }}_hora_fin" name="horas[{{ $num }}][fin]" required></select>
            </div>
          @endforeach
        </div>

        <p class="mt-4 text-sm text-gray-500" id="kpi"></p>
      </section>
    </div>

    <div class="mt-6 flex justify-between">
      <a class="btn btn-ghost" href="{{ route('demo.admin.horarios.index') }}">
        <i class="ri-arrow-left-line"></i> Volver
      </a>
      <button class="btn btn-primary" type="submit">
        <i class="ri-save-line"></i> Guardar
      </button>
    </div>
  </form>
</div>
@endsection

@push('scripts')
  @vite('resources/js/admin/horarios/create.js')
@endpush
