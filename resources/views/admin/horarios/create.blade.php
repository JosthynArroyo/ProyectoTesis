{{-- resources/views/admin/horarios/create.blade.php --}}
@extends('layouts.admin')
@section('title','Crear horario')
@section('header-title','Nuevo horario')
@section('header-subtitle','Define rangos y franjas semanales')

@section('main')
<div class="space-y-6">
  <form action="{{ route('admin.horarios.store') }}" method="POST" class="card p-6" id="form-horario">
    @csrf

    @php
      $dias = [1 => 'Lun', 2 => 'Mar', 3 => 'Mie', 4 => 'Jue', 5 => 'Vie', 6 => 'Sab', 7 => 'Dom'];
    @endphp

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
                @foreach($doctores as $d)
                  <option value="{{ $d->id }}" {{ old('doctor_id') == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                @endforeach
              </select>
            </div>
            @error('doctor_id')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
          </div>

          <div>
            <label class="form-label" for="fecha_inicio">Desde</label>
            <div class="relative mt-1">
              <input class="form-input form-input-native-date mt-0 pr-11" type="date" id="fecha_inicio" name="fecha_inicio" value="{{ old('fecha_inicio') }}" min="{{ now()->toDateString() }}" required>
              <button id="admin-horario-fecha-inicio-trigger" type="button" class="field-action-button" data-native-date-open="#fecha_inicio" aria-label="Abrir calendario para fecha inicial">
                <i class="ri-calendar-line"></i>
              </button>
            </div>
            @error('fecha_inicio')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
          </div>

          <div>
            <label class="form-label" for="fecha_fin">Hasta</label>
            <div class="relative mt-1">
              <input class="form-input form-input-native-date mt-0 pr-11" type="date" id="fecha_fin" name="fecha_fin" value="{{ old('fecha_fin') }}" required>
              <button id="admin-horario-fecha-fin-trigger" type="button" class="field-action-button" data-native-date-open="#fecha_fin" aria-label="Abrir calendario para fecha final">
                <i class="ri-calendar-line"></i>
              </button>
            </div>
            @error('fecha_fin')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
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
            @php $checked = in_array($num, (array) old('dias', [1,2,3,4,5])); @endphp
            <label class="day-chip rounded-full border border-gray-200 px-3 py-2 text-xs font-semibold {{ $checked ? 'active bg-gray-100 text-gray-900 font-medium' : 'text-gray-500' }}">
              <input type="checkbox" name="dias[]" value="{{ $num }}" {{ $checked ? 'checked' : '' }}>
              <span>{{ $lbl }}</span>
            </label>
          @endforeach
        </div>
        @error('dias')<div class="mt-2 text-xs text-rose-600">{{ $message }}</div>@enderror

        <div class="mt-4 flex flex-wrap gap-2">
          <button type="button" class="btn-mini btn btn-outline" data-preset="lv">L-V</button>
          <button type="button" class="btn-mini btn btn-outline" data-preset="ld">L-D</button>
          <button type="button" class="btn-mini btn btn-outline" data-preset="sd">S-D</button>
          <button type="button" class="btn-mini btn btn-outline" data-preset="none">Ninguno</button>
        </div>

        <label class="mt-4 flex items-center gap-2 text-sm text-gray-600" for="misma_franja">
          <input type="hidden" name="misma_franja" value="0">
          <input type="checkbox" id="misma_franja" name="misma_franja" value="1" {{ old('misma_franja',1) ? 'checked' : '' }}>
          Usar la misma franja para todos los dias marcados
        </label>
        @error('misma_franja')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
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

        <div id="franja-global" class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="form-label" for="hora_inicio">Hora inicio</label>
            <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2">
              <i class="ri-time-line text-gray-400"></i>
              <input class="w-full bg-transparent text-sm" type="time" id="hora_inicio" name="hora_inicio" step="1800" value="{{ old('hora_inicio') }}" required>
            </div>
            @error('hora_inicio')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
          </div>
          <div>
            <label class="form-label" for="hora_fin">Hora fin</label>
            <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2">
              <i class="ri-time-line text-gray-400"></i>
              <input class="w-full bg-transparent text-sm" type="time" id="hora_fin" name="hora_fin" step="1800" value="{{ old('hora_fin') }}" required>
            </div>
            @error('hora_fin')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
          </div>
        </div>

        <div id="franjas-por-dia" class="mt-4 space-y-3" style="display:none">
          <div class="text-xs text-gray-500">Define horas por cada dia marcado.</div>
          @foreach($dias as $num => $lbl)
            @php $row = old("horas.$num", ['inicio'=>null,'fin'=>null]); @endphp
            <div class="row-dia flex flex-wrap items-center gap-2 rounded-2xl border border-gray-200 bg-white/90 px-3 py-2" data-dia="{{ $num }}">
              <div class="row-dia__label text-sm font-semibold text-gray-600">{{ $lbl }}</div>
              <input class="form-input" type="time" name="horas[{{ $num }}][inicio]" step="1800" value="{{ $row['inicio'] }}" placeholder="hh:mm" required>
              <span class="row-dia__sep text-xs text-gray-400">a</span>
              <input class="form-input" type="time" name="horas[{{ $num }}][fin]" step="1800" value="{{ $row['fin'] }}" placeholder="hh:mm" required>
            </div>
          @endforeach
        </div>

        <p class="mt-4 text-sm text-gray-500" id="kpi"></p>
      </section>
    </div>

    <div class="mt-6">
      <x-ui.form-actions>
        <x-slot:left>
          <a class="btn btn-ghost" href="{{ route('admin.horarios.index') }}">
            <i class="ri-arrow-left-line"></i> Volver
          </a>
        </x-slot>
        <button class="btn btn-primary" type="submit">
          <i class="ri-save-line"></i> Guardar
        </button>
      </x-ui.form-actions>
    </div>
  </form>
</div>
@endsection

@push('scripts')
  @vite('resources/js/admin/horarios/create.js')
@endpush
