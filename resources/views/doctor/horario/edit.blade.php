@extends('layouts.doctor')
@section('title','Editar horario | Doctor')
@section('activeSidebar','horario')
@section('header-title','Editar horario')
@section('header-subtitle','Actualiza este bloque')

@section('main')
  <div class="space-y-6">
    <div class="card p-6">
      <h3 class="text-lg font-semibold text-gray-900">Editar horario</h3>
      <form method="POST" action="{{ route('doctor.horario.update',$h) }}" class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @csrf @method('PUT')
        @if (session('horario_conflicts'))
          <div class="card p-4 border-2 border-amber-500 bg-amber-50 dark:bg-amber-950/20 text-amber-800 dark:text-amber-200 mb-6 space-y-3 col-span-4">
            <div class="flex items-start gap-3">
              <i class="ri-alert-line text-lg text-amber-600 dark:text-amber-400"></i>
              <div>
                <h4 class="font-semibold">Confirmación requerida</h4>
                <p class="text-sm mt-1">Este cambio dejará <strong>{{ session('horario_conflicts') }} cita(s) futura(s) activa(s)</strong> sin cobertura horaria para ti.</p>
              </div>
            </div>
            <label class="flex items-center gap-2 text-sm font-semibold cursor-pointer">
              <input type="hidden" name="confirmar_conflictos" value="0">
              <input type="checkbox" name="confirmar_conflictos" value="1" required class="rounded border-gray-300 text-teal-650 focus:ring-teal-550 dark:border-gray-700 dark:bg-gray-800">
              Confirmar que deseo proceder y guardar los cambios.
            </label>
          </div>
        @endif

        <div>
          <label class="form-label" for="fecha_single">Fecha</label>
          <div class="relative mt-1">
            <input class="form-input form-input-native-date mt-0 pr-11" id="fecha_single" type="date" name="fecha" value="{{ $h->fecha->toDateString() }}" placeholder="AAAA-MM-DD" autocomplete="off" required>
            <button id="doctor-horario-edit-fecha-trigger" type="button" class="field-action-button" data-native-date-open="#fecha_single" aria-label="Abrir calendario para editar el horario">
              <i class="ri-calendar-line"></i>
            </button>
          </div>
          @error('fecha')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Inicio</label>
          <select class="form-input" id="hora_inicio_single" name="hora_inicio" data-old="{{ old('hora_inicio', substr($h->hora_inicio,0,5)) }}" required></select>
          @error('hora_inicio')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Fin</label>
          <select class="form-input" id="hora_fin_single" name="hora_fin" data-old="{{ old('hora_fin', substr($h->hora_fin,0,5)) }}" required></select>
          @error('hora_fin')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <input type="hidden" id="intervalo_minutos" name="intervalo_minutos" value="{{ $h->intervalo_minutos ?? 30 }}">
        <div class="flex items-end">
          <span class="badge neutral">Intervalo {{ $h->intervalo_minutos ?? 30 }} min</span>
        </div>

        <div class="sm:col-span-4">
          <p class="text-xs text-teal-650 dark:text-teal-400 font-semibold" id="clinic-hours-info-single"></p>
        </div>

        <div class="sm:col-span-4">
          <x-ui.form-actions>
            <x-slot:left>
              <a class="btn btn-ghost" href="{{ route('doctor.horario.index') }}">Volver</a>
            </x-slot>
            <button class="btn btn-primary">Guardar</button>
          </x-ui.form-actions>
        </div>
      </form>
    </div>
  </div>

  <script id="clinica-horarios-config" type="application/json">
    {!! json_encode(collect(range(1, 7))->mapWithKeys(function($day) {
        return [$day => app(App\Services\ProfessionalScheduleService::class)->getClinicHours($day)];
    })) !!}
  </script>
@endsection

@push('scripts')
  @vite('resources/js/doctor/horario.js')
@endpush
