@extends('layouts.laboratorio')
@section('title', 'Citas y resultados de laboratorio')
@section('activeSidebar', 'ordenes')
@section('header-title','Citas y resultados')
@section('header-subtitle','Gestiona citas, muestras y resultados')

@section('content')
<section class="space-y-6">
  <header class="card p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Panel laboratorio</p>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Citas y resultados</h1>
        <p class="text-slate-600">Gestiona citas, muestras y resultados del laboratorio.</p>
      </div>
      <span class="badge info"><i class="ri-flask-line"></i> Órdenes activas</span>
    </div>
  </header>

  @if ($errors->any())
    <x-ui.alert tone="error">{{ $errors->first() }}</x-ui.alert>
  @endif
  @if (session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  <div class="grid gap-6">
    @forelse($ordenes as $orden)
      @php
        $estado = $orden->estado;
        $badge = match($estado) {
          'orden_creada' => 'warning',
          'cita_programada' => 'info',
          'muestra_tomada' => 'neutral',
          'resultado_disponible' => 'success',
          default => 'neutral',
        };
      @endphp
      <article class="card p-6" id="orden-{{ $orden->id }}">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">{{ $orden->tipo_examen }}</h2>
            <p class="text-sm text-slate-500">Paciente: {{ optional($orden->cita->paciente)->name ?? 'Paciente' }}</p>
          </div>
          <x-ui.badge :tone="$badge">{{ str_replace('_',' ', $estado) }}</x-ui.badge>
        </div>

        <div class="mt-4 grid gap-3 text-sm text-slate-600 sm:grid-cols-2">
          <div>
            <span class="text-slate-500">Fecha:</span>
            {{ optional($orden->cita->fecha)->format('Y/m/d') }}
            {{ $orden->cita->hora ? \Carbon\Carbon::parse($orden->cita->hora)->format('H:i') : '' }}
          </div>
          <div>
            <span class="text-slate-500">Prioridad:</span>
            {{ ucfirst($orden->prioridad) }}
          </div>
        </div>

        @if($orden->preparacion)
          <div class="mt-3 rounded-xl border border-amber-100 bg-amber-50/80 px-3 py-2 text-sm text-amber-800">
            <strong>Preparación:</strong> {{ $orden->preparacion }}
          </div>
        @endif
        @if($orden->indicaciones)
          <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
            <strong>Indicaciones:</strong> {{ $orden->indicaciones }}
          </div>
        @endif

        <div class="mt-5 flex flex-wrap gap-3">
          @if($orden->resultado_path)
            <a class="btn btn-outline" href="{{ route('laboratorio.ordenes.download', $orden->id) }}">
              <i class="ri-download-line"></i> Descargar resultado
            </a>
          @endif

          @if($estado !== 'resultado_disponible')
            <form method="POST" action="{{ route('laboratorio.ordenes.muestra', $orden->id) }}">
              @csrf
              <button class="btn btn-outline" type="submit">
                <i class="ri-test-tube-line"></i> Marcar muestra tomada
              </button>
            </form>
          @endif
        </div>

        @if($estado !== 'resultado_disponible')
          <form class="mt-5 grid gap-4 md:grid-cols-2" method="POST" action="{{ route('laboratorio.ordenes.resultado', $orden->id) }}" enctype="multipart/form-data">
            @csrf
            <div>
              <label for="resultado_pdf_{{ $orden->id }}" class="form-label">Resultado PDF</label>
              <input id="resultado_pdf_{{ $orden->id }}" type="file" name="resultado_pdf" accept="application/pdf" required class="form-input">
              @error('resultado_pdf')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
            </div>
            <div>
              <label for="resultado_resumen_{{ $orden->id }}" class="form-label">Resumen</label>
              <textarea id="resultado_resumen_{{ $orden->id }}" name="resultado_resumen" rows="3" required class="form-textarea"></textarea>
              @error('resultado_resumen')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
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
        title="No hay órdenes registradas"
        message="Cuando tengas órdenes asignadas aparecerán aquí."
      />
    @endforelse
  </div>

  <x-ui.pagination :paginator="$ordenes" />
</section>
@endsection
