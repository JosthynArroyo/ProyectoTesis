@extends('layouts.laboratorio')
@section('title', 'Citas y resultados de laboratorio')
@section('activeSidebar', 'ordenes')
@section('header-title','Citas y resultados')
@section('header-subtitle','Gestiona citas, muestras y resultados')

@section('main')
<section class="space-y-6">
  <header class="card p-6">
    <form class="flex flex-wrap items-end gap-3" method="GET" action="{{ route('laboratorio.ordenes.index') }}">
      <div>
        <label class="form-label" for="estado">Filtrar por estado</label>
        <select id="estado" name="estado" class="form-select">
          <option value="all" @selected(($estado ?? 'all') === 'all')>Todos</option>
          <option value="orden_creada" @selected(($estado ?? '') === 'orden_creada')>Solo legacy pendiente</option>
          <option value="cita_programada" @selected(($estado ?? '') === 'cita_programada')>Pendiente de toma</option>
          <option value="muestra_tomada" @selected(($estado ?? '') === 'muestra_tomada')>Muestra tomada / analisis</option>
          <option value="resultado_disponible" @selected(($estado ?? '') === 'resultado_disponible')>Resultado listo</option>
        </select>
      </div>
      <button class="btn btn-outline btn-sm" type="submit">
        <i class="ri-filter-3-line"></i> Aplicar
      </button>
      @if(($estado ?? 'all') !== 'all')
        <a class="btn btn-ghost btn-sm" href="{{ route('laboratorio.ordenes.index') }}">
          <i class="ri-refresh-line"></i> Limpiar
        </a>
      @endif
    </form>
  </header>

  @if (session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  @if ($errors->any())
    <x-ui.alert tone="error">{{ $errors->first() }}</x-ui.alert>
  @endif

  <div class="grid gap-6">
    @forelse($ordenes as $orden)
      <article class="card p-6" id="{{ $orden->uid }}">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <h2 class="text-lg font-semibold text-slate-900">{{ $orden->title }}</h2>
              <x-ui.badge :tone="$orden->badge_tone">{{ $orden->status_label }}</x-ui.badge>
            </div>
            <p class="mt-1 text-sm text-slate-500">Paciente: {{ $orden->patient_name }}</p>
            <p class="text-xs uppercase tracking-widest text-slate-400">{{ $orden->source_label }}</p>
          </div>
        </div>

        <div class="mt-4 grid gap-3 text-sm text-slate-600 sm:grid-cols-2">
          <div>
            <span class="text-slate-500">Fecha:</span>
            {{ $orden->date_label }}
          </div>
          <div>
            <span class="text-slate-500">Prioridad:</span>
            {{ $orden->priority_label }}
          </div>
        </div>

        @if($orden->preparation)
          <div class="mt-3 rounded-xl border border-amber-100 bg-amber-50/80 px-3 py-2 text-sm text-amber-800">
            <strong>Preparacion:</strong> {{ $orden->preparation }}
          </div>
        @endif
        @if($orden->notes)
          <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
            <strong>Indicaciones:</strong> {{ $orden->notes }}
          </div>
        @endif
        @if($orden->result_summary)
          <div class="mt-3 rounded-xl border border-emerald-100 bg-emerald-50/80 px-3 py-2 text-sm text-emerald-800">
            <strong>Resumen:</strong> {{ $orden->result_summary }}
          </div>
        @endif

        <div class="mt-5 flex flex-wrap gap-3">
          @if($orden->download_url)
            <a class="btn btn-outline" href="{{ $orden->download_url }}">
              <i class="ri-download-line"></i> Descargar resultado
            </a>
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
              <label for="resultado_pdf_{{ $orden->uid }}" class="form-label">Resultado PDF</label>
              <label class="mt-1 block rounded-xl border-2 border-dashed border-slate-300 bg-slate-50/70 px-3 py-4 text-center text-sm text-slate-500 hover:border-teal-300 hover:text-slate-700" data-dropzone>
                <span data-dropzone-text>Arrastra el PDF aqui o haz clic para seleccionar.</span>
                <input id="resultado_pdf_{{ $orden->uid }}" type="file" name="resultado_pdf" accept="application/pdf" required class="sr-only" data-dropzone-input>
              </label>
            </div>
            <div>
              <label for="resultado_resumen_{{ $orden->uid }}" class="form-label">Resumen</label>
              <textarea id="resultado_resumen_{{ $orden->uid }}" name="resultado_resumen" rows="3" required class="form-textarea"></textarea>
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
      <x-ui.empty-state
        title="No hay ordenes registradas"
        message="Cuando tengas ordenes asignadas o solicitudes directas disponibles apareceran aqui."
      />
    @endforelse
  </div>

  <x-ui.pagination :paginator="$ordenes" />
</section>

@push('scripts')
  @vite('resources/js/laboratorio/ordenes.js')
@endpush
@endsection
