@extends('layouts.laboratorio')
@section('title','Mi horario | Laboratorio')
@section('activeSidebar','horario')
@section('header-title','Mi horario')
@section('header-subtitle','Configura y revisa tus bloques de atención')

@push('head')
  @vite('resources/css/panel/weekly-schedule.css')
@endpush

@php
  $prevWeek = $weekStart->copy()->subWeek()->toDateString();
  $nextWeek = $weekStart->copy()->addWeek()->toDateString();
  $currentWeek = now()->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();
  $legend = [
    ['label' => 'Bloque de atención', 'tone' => 'slate', 'variant' => 'soft'],
    ['label' => 'Cita programada', 'tone' => 'blue'],
    ['label' => 'Muestra tomada / análisis', 'tone' => 'amber'],
    ['label' => 'Resultado listo', 'tone' => 'emerald'],
    ['label' => 'Cancelado / ausente', 'tone' => 'rose'],
  ];
@endphp

@section('main')
  <div class="space-y-6">
    @if(session('success'))
      <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
    @endif

    <x-ui.weekly-schedule
      :calendar="$calendar"
      eyebrow="Panel laboratorio"
      title="Weekly schedule"
      subtitle="Tus bloques de atención y las órdenes programadas conviven en una misma grilla semanal."
      :legend="$legend"
      empty-title="Sin actividad semanal"
      empty-message="Crea un bloque o cambia de semana para revisar la carga de trabajo del laboratorio."
    >
      <x-slot:toolbar>
        <div class="flex flex-wrap items-center gap-2">
          <a class="btn btn-outline btn-sm" href="{{ route('laboratorio.horario.index', ['week' => $prevWeek, 'desde' => $prevWeek, 'hasta' => \Carbon\Carbon::parse($prevWeek)->endOfWeek(\Carbon\Carbon::SUNDAY)->toDateString()]) }}">
            <i class="ri-arrow-left-s-line"></i> Semana anterior
          </a>
          <a class="btn btn-outline btn-sm" href="{{ route('laboratorio.horario.index', ['week' => $currentWeek, 'desde' => $currentWeek, 'hasta' => \Carbon\Carbon::parse($currentWeek)->endOfWeek(\Carbon\Carbon::SUNDAY)->toDateString()]) }}">
            <i class="ri-calendar-line"></i> Semana actual
          </a>
          <a class="btn btn-outline btn-sm" href="{{ route('laboratorio.horario.index', ['week' => $nextWeek, 'desde' => $nextWeek, 'hasta' => \Carbon\Carbon::parse($nextWeek)->endOfWeek(\Carbon\Carbon::SUNDAY)->toDateString()]) }}">
            Siguiente semana <i class="ri-arrow-right-s-line"></i>
          </a>
        </div>

        <span class="badge neutral">{{ $calendar['range_label'] ?? '' }}</span>
      </x-slot:toolbar>
    </x-ui.weekly-schedule>

    <section class="card p-6">
      <div class="page-header">
        <div class="page-header__info">
          <h2>Filtros</h2>
          <p>Acota el rango visible de la tabla sin perder la vista semanal de referencia.</p>
        </div>
      </div>

      <form method="GET" class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <input type="hidden" name="week" value="{{ request('week', $weekStart->toDateString()) }}">
        <div>
          <label class="form-label" for="lab-horario-filtro-desde">Desde</label>
          <div class="relative mt-1">
            <input id="lab-horario-filtro-desde" type="date" name="desde" value="{{ $desde }}" class="form-input form-input-native-date mt-0 pr-11">
            <button id="lab-horario-filtro-desde-trigger" type="button" data-native-date-open="#lab-horario-filtro-desde" class="field-action-button" aria-label="Abrir calendario para fecha inicial">
              <i class="ri-calendar-line"></i>
            </button>
          </div>
        </div>
        <div>
          <label class="form-label" for="lab-horario-filtro-hasta">Hasta</label>
          <div class="relative mt-1">
            <input id="lab-horario-filtro-hasta" type="date" name="hasta" value="{{ $hasta }}" class="form-input form-input-native-date mt-0 pr-11">
            <button id="lab-horario-filtro-hasta-trigger" type="button" data-native-date-open="#lab-horario-filtro-hasta" class="field-action-button" aria-label="Abrir calendario para fecha final">
              <i class="ri-calendar-line"></i>
            </button>
          </div>
        </div>
        <div class="xl:col-span-2 flex items-end gap-2">
          <button class="btn btn-primary w-full sm:w-auto" type="submit">
            <i class="ri-filter-3-line"></i> Aplicar
          </button>
          <a href="{{ route('laboratorio.horario.index', ['week' => $weekStart->toDateString(), 'desde' => $weekStart->toDateString(), 'hasta' => $weekEnd->toDateString()]) }}" class="btn btn-ghost btn-full-mobile">
            <i class="ri-refresh-line"></i> Limpiar
          </a>
        </div>
      </form>
    </section>

    <details class="panel-accordion card p-6" open>
      <summary class="panel-accordion__summary">
        <div class="panel-accordion__meta">
          <span class="panel-form-section__icon"><i class="ri-add-circle-line"></i></span>
          <div class="panel-form-section__heading">
            <h2 class="panel-form-section__title">Crear horario de un día</h2>
            <p class="panel-form-section__hint">Usa este bloque cuando solo necesites registrar una fecha puntual.</p>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <span class="badge info">1 día</span>
          <span class="panel-accordion__chevron"><i class="ri-arrow-down-s-line"></i></span>
        </div>
      </summary>

      <div class="panel-accordion__body">
        <form method="POST" action="{{ route('laboratorio.horario.store') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          @csrf
          <div>
            <label class="form-label" for="lab-horario-fecha">Fecha</label>
            <div class="relative mt-1">
              <input id="lab-horario-fecha" type="date" name="fecha" class="form-input form-input-native-date mt-0 pr-11" required>
              <button id="lab-horario-fecha-trigger" type="button" data-native-date-open="#lab-horario-fecha" class="field-action-button" aria-label="Abrir calendario para el horario del laboratorio">
                <i class="ri-calendar-line"></i>
              </button>
            </div>
          </div>
          <div>
            <label class="form-label">Inicio</label>
            <input type="time" name="hora_inicio" required class="form-input">
          </div>
          <div>
            <label class="form-label">Fin</label>
            <input type="time" name="hora_fin" required class="form-input">
          </div>
          <input type="hidden" name="intervalo_minutos" value="30">
          <div>
            <label class="form-label">Intervalo</label>
            <div class="mt-2 inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">30 min</div>
          </div>
          <div class="md:col-span-4">
            <button class="btn btn-primary" type="submit">
              <i class="ri-save-line"></i> Crear horario
            </button>
          </div>
        </form>
      </div>
    </details>

    <details class="panel-accordion card p-6">
      <summary class="panel-accordion__summary">
        <div class="panel-accordion__meta">
          <span class="panel-form-section__icon"><i class="ri-calendar-2-line"></i></span>
          <div class="panel-form-section__heading">
            <h2 class="panel-form-section__title">Generar por rango</h2>
            <p class="panel-form-section__hint">Ideal para cargar varios días de una sola vez sin repetir el mismo formulario.</p>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <span class="badge warning">Rango</span>
          <span class="panel-accordion__chevron"><i class="ri-arrow-down-s-line"></i></span>
        </div>
      </summary>

      <div class="panel-accordion__body">
        <form method="POST" action="{{ route('laboratorio.horario.generar') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          @csrf
          <div>
            <label class="form-label" for="lab-horario-rango-desde">Desde</label>
            <div class="relative mt-1">
              <input id="lab-horario-rango-desde" type="date" name="desde" class="form-input form-input-native-date mt-0 pr-11" required>
              <button id="lab-horario-rango-desde-trigger" type="button" data-native-date-open="#lab-horario-rango-desde" class="field-action-button" aria-label="Abrir calendario para fecha inicial del rango">
                <i class="ri-calendar-line"></i>
              </button>
            </div>
          </div>
          <div>
            <label class="form-label" for="lab-horario-rango-hasta">Hasta</label>
            <div class="relative mt-1">
              <input id="lab-horario-rango-hasta" type="date" name="hasta" class="form-input form-input-native-date mt-0 pr-11" required>
              <button id="lab-horario-rango-hasta-trigger" type="button" data-native-date-open="#lab-horario-rango-hasta" class="field-action-button" aria-label="Abrir calendario para fecha final del rango">
                <i class="ri-calendar-line"></i>
              </button>
            </div>
          </div>

          <div class="md:col-span-4">
            <label class="form-label">Días</label>
            @php($dias=[1=>'Lun',2=>'Mar',3=>'Mie',4=>'Jue',5=>'Vie',6=>'Sab',7=>'Dom'])
            <div class="mt-2 flex flex-wrap gap-2">
              @foreach($dias as $k => $v)
                <label class="flex items-center gap-2 rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600">
                  <input type="checkbox" name="dias[]" value="{{ $k }}" class="h-4 w-4 rounded border-gray-300 text-teal-600 focus:ring-teal-500" {{ $k <= 5 ? 'checked' : '' }}>
                  <span>{{ $v }}</span>
                </label>
              @endforeach
            </div>
          </div>

          <div>
            <label class="form-label">Inicio</label>
            <input type="time" name="hora_inicio" required class="form-input">
          </div>
          <div>
            <label class="form-label">Fin</label>
            <input type="time" name="hora_fin" required class="form-input">
          </div>
          <input type="hidden" name="intervalo_minutos" value="30">
          <div>
            <label class="form-label">Intervalo</label>
            <div class="mt-2 inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">30 min</div>
          </div>
          <div class="md:col-span-4">
            <label class="flex items-center gap-2 text-sm text-gray-600">
              <input type="hidden" name="sobrescribir" value="0">
              <input type="checkbox" name="sobrescribir" value="1" class="h-4 w-4 rounded border-gray-300 text-teal-600 focus:ring-teal-500">
              <span>Sobrescribir días existentes</span>
            </label>
          </div>
          <div class="md:col-span-4">
            <button class="btn btn-primary" type="submit">
              <i class="ri-calendar-check-line"></i> Generar horarios
            </button>
          </div>
        </form>
      </div>
    </details>

    <section class="card p-6">
      <div class="page-header">
        <div class="page-header__info">
          <h2>Mis horarios</h2>
          <p>Bloques registrados en el rango activo.</p>
        </div>
      </div>
      <div class="mt-4 table-shell table-responsive-cards">
        <table class="table">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Inicio</th>
              <th>Fin</th>
              <th>Intervalo</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            @forelse($items as $horario)
              <tr>
                <td data-label="Fecha">{{ optional($horario->fecha)->format('d/m/Y') }}</td>
                <td data-label="Inicio">{{ substr((string) $horario->hora_inicio, 0, 5) }}</td>
                <td data-label="Fin">{{ substr((string) $horario->hora_fin, 0, 5) }}</td>
                <td data-label="Intervalo"><x-ui.badge tone="info">30 min</x-ui.badge></td>
                <td data-label="Acciones">
                  <div class="table-actions table-actions--start">
                    <a class="btn btn-ghost btn-sm" href="{{ route('laboratorio.horario.edit', $horario) }}">
                      <i class="ri-edit-line"></i> Editar
                    </a>
                    <form action="{{ route('laboratorio.horario.destroy', $horario) }}" method="POST" data-confirm-title="Eliminar horario" data-confirm-message="¿Estás seguro de que deseas eliminar este bloque de horario de laboratorio?" data-confirm-action="eliminar" data-confirm-btn="Sí, eliminar">
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
              <tr><td colspan="5">Sin horarios en el rango.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>
  </div>
@endsection

@push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const openNativePicker = (input) => {
        if (!(input instanceof HTMLInputElement)) {
          return;
        }

        input.focus({ preventScroll: true });

        if (typeof input.showPicker === 'function') {
          try {
            input.showPicker();
            return;
          } catch (_error) {}
        }

        input.click();
      };

      document.querySelectorAll('[data-native-date-open]').forEach((trigger) => {
        const selector = trigger.getAttribute('data-native-date-open');
        const input = selector ? document.querySelector(selector) : null;

        if (!input) {
          return;
        }

        trigger.addEventListener('click', (event) => {
          event.preventDefault();
          openNativePicker(input);
        });
      });
    });
  </script>
@endpush
