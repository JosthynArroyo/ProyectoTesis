@extends('layouts.demo')
@section('title', 'Citas y resultados de laboratorio - Demo')
@section('activeSidebar', 'citas-resultados')
@section('header-title','Citas y resultados')
@section('header-subtitle','Gestiona citas, muestras y resultados (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-laboratorio-demo')
@endsection

@php
  $estadoFiltro = request('estado', 'all');

  $mappedOrdenes = collect($ordenes)->map(function($o) {
      $orden = new \stdClass();
      $orden->uid = $o['id'];
      $orden->title = $o['exam'];
      $orden->patient_name = $o['patient'];
      $orden->source_label = 'Con orden médica';
      $orden->date_label = $o['date'];
      $orden->priority_label = 'Normal';
      $orden->preparation = 'Ayuno de 8 a 12 horas.';
      $orden->notes = 'Indicación médica.';
      
      $status = strtolower($o['status']);
      $orden->status_label = $o['status'];
      $orden->badge_tone = match ($status) {
          'pendiente toma', 'orden_creada', 'cita_programada' => 'warning',
          'en proceso', 'muestra_tomada' => 'info',
          'completado', 'resultado_disponible' => 'success',
          default => 'neutral'
      };
      
      $orden->can_mark_sample = in_array($status, ['pendiente toma', 'orden_creada', 'cita_programada']);
      $orden->can_upload_result = in_array($status, ['en proceso', 'muestra_tomada']);
      $orden->download_url = in_array($status, ['completado', 'resultado_disponible', 'completado (simulado)']) ? '#' : null;
      $orden->result_summary = in_array($status, ['completado', 'resultado_disponible', 'completado (simulado)']) 
          ? 'Valores dentro de rangos normales de control.' : null;
      
      $orden->mark_sample_url = route('demo.laboratorio.ordenes.muestra', $o['id']);
      $orden->upload_result_url = route('demo.laboratorio.ordenes.resultado', $o['id']);
      return $orden;
  });
@endphp

@section('main')
<section class="space-y-6">
  <header class="card p-6 bg-white">
    <form class="flex flex-wrap items-end gap-3" method="GET" action="{{ route('demo.laboratorio.citas-resultados') }}">
      <div>
        <label class="form-label" for="estado">Filtrar por estado</label>
        <select id="estado" name="estado" class="form-select">
          <option value="all" selected>Todos</option>
          <option value="orden_creada">Solo orden creada</option>
          <option value="cita_programada">Pendiente de toma</option>
          <option value="muestra_tomada">Muestra tomada / análisis</option>
          <option value="resultado_disponible">Resultado listo</option>
        </select>
      </div>
      <button class="btn btn-outline btn-sm" type="submit">
        <i class="ri-filter-3-line"></i> Aplicar
      </button>
    </form>
  </header>

  @if (session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  <div class="grid gap-6">
    @forelse($mappedOrdenes as $orden)
      <article class="card p-6 bg-white" id="order-card-{{ $orden->uid }}">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <h2 class="text-lg font-bold text-gray-900">{{ $orden->title }}</h2>
              <x-ui.badge :tone="$orden->badge_tone">{{ $orden->status_label }}</x-ui.badge>
            </div>
            <p class="mt-1 text-sm text-gray-500">Paciente: {{ $orden->patient_name }}</p>
            <p class="text-xs uppercase tracking-widest text-gray-400 font-semibold">{{ $orden->source_label }}</p>
          </div>
        </div>

        <div class="mt-4 grid gap-3 text-sm text-gray-650 sm:grid-cols-2">
          <div>
            <span class="text-gray-500 font-medium">Fecha:</span>
            {{ $orden->date_label }}
          </div>
          <div>
            <span class="text-gray-500 font-medium">Prioridad:</span>
            {{ $orden->priority_label }}
          </div>
        </div>

        @if($orden->preparation)
          <div class="mt-3 rounded-xl border border-amber-100 bg-amber-50/80 px-3 py-2 text-xs text-amber-800 leading-relaxed">
            <strong>Preparación:</strong> {{ $orden->preparation }}
          </div>
        @endif
        @if($orden->notes)
          <div class="mt-3 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-600 leading-relaxed">
            <strong>Indicaciones:</strong> {{ $orden->notes }}
          </div>
        @endif
        @if($orden->result_summary)
          <div class="mt-3 rounded-xl border border-gray-200 bg-gray-100/80 px-3 py-2 text-xs text-gray-800 leading-relaxed">
            <strong>Resumen:</strong> {{ $orden->result_summary }}
          </div>
        @endif

        <div class="mt-5 flex flex-wrap gap-3">
          @if($orden->download_url)
            <button class="btn btn-outline demo-action-blocked">
              <i class="ri-download-line"></i> Descargar resultado
            </button>
          @endif

          @if($orden->can_mark_sample)
            <form method="POST" action="{{ $orden->mark_sample_url }}">
              @csrf
              <button class="btn btn-outline" type="submit">
                <i class="ri-test-tube-line"></i> Marcar muestra tomada
              </button>
            </form>
          @endif
        </div>

        @if($orden->can_upload_result)
          <form class="mt-5 grid gap-4 md:grid-cols-2" method="POST" action="{{ $orden->upload_result_url }}" enctype="multipart/form-data">
            @csrf
            <div>
              <label class="form-label">Resultado PDF</label>
              <input type="file" name="resultado_pdf" accept="application/pdf" required class="form-input">
            </div>
            <div>
              <label class="form-label">Resumen de resultados</label>
              <textarea name="resultado_resumen" rows="2" required class="form-textarea" placeholder="Ej: Glucosa basal: 85 mg/dL. Rango de referencia: 70 - 100 mg/dL."></textarea>
            </div>
            <div class="md:col-span-2">
              <button class="btn btn-primary" type="submit">
                <i class="ri-upload-2-line"></i> Subir resultados
              </button>
            </div>
          </form>
        @endif
      </article>
    @empty
      <x-ui.empty-state title="No hay ordenes registradas" />
    @endforelse
  </div>
</section>
@endsection
