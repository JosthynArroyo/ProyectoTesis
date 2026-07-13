{{-- resources/views/admin/horarios/edit.blade.php --}}
@extends('layouts.admin')
@section('title','Editar horario')
@section('header-title','Editar horario')
@section('header-subtitle','Actualiza la franja seleccionada')

@section('main')
<div class="space-y-6">
  <div class="panel-action-bar">
    <x-ui.context-pill label="Bloque actual">
      <x-slot:icon><i class="ri-time-line"></i></x-slot:icon>
      {{ \Carbon\Carbon::parse($horario->hora_inicio)->format('H:i') }} - {{ \Carbon\Carbon::parse($horario->hora_fin)->format('H:i') }}
    </x-ui.context-pill>
  </div>

  <form action="{{ route('admin.horarios.update', $horario) }}" method="POST" class="card p-6" id="form-horario-edit">
    @csrf
    @method('PUT')

    <section class="panel-form-section">
      <div class="panel-form-section__header">
        <div class="panel-form-section__heading">
          <h2 class="panel-form-section__title">
            <span class="panel-form-section__icon"><i class="ri-edit-2-line"></i></span>
            Datos del bloque
          </h2>
          <p class="panel-form-section__hint">Cambia la fecha y el horario sin perder el contexto del bloque actual.</p>
        </div>
      </div>

      <input type="hidden" id="intervalo_minutos" value="{{ $horario->intervalo_minutos ?? 30 }}">

      <script id="clinica-horarios-config" type="application/json">
        {!! json_encode(collect(range(1, 7))->mapWithKeys(function($day) {
            return [$day => app(App\Services\ProfessionalScheduleService::class)->getClinicHours($day)];
        })) !!}
      </script>

      @if (session('horario_conflicts'))
        <div class="card p-4 border-2 border-amber-500 bg-amber-50 dark:bg-amber-950/20 text-amber-800 dark:text-amber-200 mb-6 space-y-3 col-span-2">
          <div class="flex items-start gap-3">
            <i class="ri-alert-line text-lg text-amber-600 dark:text-amber-400"></i>
            <div>
              <h4 class="font-semibold">Confirmación requerida</h4>
              <p class="text-sm mt-1">Este cambio dejará <strong>{{ session('horario_conflicts') }} cita(s) futura(s) activa(s)</strong> sin cobertura horaria para este doctor.</p>
            </div>
          </div>
          <label class="flex items-center gap-2 text-sm font-semibold cursor-pointer">
            <input type="hidden" name="confirmar_conflictos" value="0">
            <input type="checkbox" name="confirmar_conflictos" value="1" required class="rounded border-gray-300 text-teal-650 focus:ring-teal-550 dark:border-gray-700 dark:bg-gray-800">
            Confirmar que deseo proceder y guardar los cambios.
          </label>
        </div>
      @endif

      <div class="grid gap-4 md:grid-cols-2">
        <div class="md:col-span-2">
          <label class="form-label" for="doctor_id">Doctor</label>
          <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2">
            <i class="ri-stethoscope-line text-gray-400"></i>
            <select class="w-full bg-transparent text-sm" id="doctor_id" name="doctor_id" required>
              @foreach($doctores as $d)
                <option value="{{ $d->id }}" {{ old('doctor_id', $horario->doctor_id) == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
              @endforeach
            </select>
          </div>
          @error('doctor_id')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <div>
          <label class="form-label" for="fecha">Fecha</label>
          <div class="relative mt-1">
            <input class="form-input form-input-native-date mt-0 pr-11" type="date" id="fecha" name="fecha" value="{{ old('fecha', \Carbon\Carbon::parse($horario->fecha)->format('Y-m-d')) }}" required>
            <button id="admin-horario-edit-fecha-trigger" type="button" class="field-action-button" data-native-date-open="#fecha" aria-label="Abrir calendario para editar la fecha">
              <i class="ri-calendar-line"></i>
            </button>
          </div>
          @error('fecha')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <div>
          <label class="form-label" for="hora_inicio">Hora inicio</label>
          <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2">
            <i class="ri-time-line text-gray-400"></i>
            <select class="w-full bg-transparent text-sm" id="hora_inicio" name="hora_inicio" data-old="{{ old('hora_inicio', \Carbon\Carbon::parse($horario->hora_inicio)->format('H:i')) }}" required></select>
          </div>
          @error('hora_inicio')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <div>
          <label class="form-label" for="hora_fin">Hora fin</label>
          <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2">
            <i class="ri-time-line text-gray-400"></i>
            <select class="w-full bg-transparent text-sm" id="hora_fin" name="hora_fin" data-old="{{ old('hora_fin', \Carbon\Carbon::parse($horario->hora_fin)->format('H:i')) }}" required></select>
          </div>
          @error('hora_fin')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <div class="md:col-span-2">
          <p class="text-xs text-teal-650 dark:text-teal-400 font-semibold" id="clinic-hours-info"></p>
        </div>
      </div>
    </section>

    <div class="mt-6">
      <x-ui.form-actions>
        <x-slot:left>
          <a class="btn btn-ghost" href="{{ route('admin.horarios.index') }}">
            <i class="ri-arrow-left-line"></i> Volver
          </a>
        </x-slot>
        <button class="btn btn-primary" type="submit">
          <i class="ri-save-line"></i> Actualizar
        </button>
      </x-ui.form-actions>
    </div>
  </form>
</div>
@endsection

@push('scripts')
  @vite('resources/js/admin/horarios/edit.js')
@endpush
