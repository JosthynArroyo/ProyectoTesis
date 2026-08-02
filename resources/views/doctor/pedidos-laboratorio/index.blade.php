@extends('layouts.doctor')
@section('title', 'Pedidos de laboratorio')
@section('activeSidebar', 'citas')
@section('header-title', 'Pedidos de laboratorio')
@section('header-subtitle', 'Consulta órdenes emitidas y resultados publicados')

@section('main')
<section class="space-y-6">
  @php
    $catalogoExamenes = app(\App\Services\LabTestCatalogService::class);
  @endphp
  @if(session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  @if($errors->any())
    <x-ui.alert tone="error">{{ $errors->first() }}</x-ui.alert>
  @endif

  <div class="grid gap-6">
    @forelse($pedidos as $pedido)
      @php
        $resultadoPublicado = $pedido->resultados->firstWhere('estado', 'publicado');
        $statusTone = match ($pedido->estado) {
            'pendiente_toma' => 'warning',
            'muestra_tomada' => 'info',
            'resultado_listo' => 'success',
            default => 'neutral'
        };
        $statusLabel = match ($pedido->estado) {
            'pendiente_toma' => 'Pendiente',
            'muestra_tomada' => 'En proceso',
            'resultado_listo' => 'Resultados publicados',
            default => ucfirst(str_replace('_', ' ', $pedido->estado))
        };
      @endphp

      <article class="card p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <h2 class="text-lg font-semibold text-gray-900">Pedido #{{ $pedido->id }}</h2>
              <x-ui.badge :tone="$statusTone">{{ $statusLabel }}</x-ui.badge>
            </div>
            <p class="mt-1 text-sm text-gray-500">
              Paciente: {{ $pedido->nombrePacienteReal() }}
              @if($pedido->representanteNombre())
                | Representante: {{ $pedido->representanteNombre() }}
              @endif
            </p>
            <p class="text-xs uppercase tracking-widest text-gray-400">Creado: {{ $pedido->created_at?->format('d/m/Y H:i') }}</p>
          </div>
        </div>

        <div class="mt-4 grid gap-3 text-sm text-gray-600 sm:grid-cols-2">
          <div>
            <span class="text-gray-500">Exámenes:</span>
            {{ implode(', ', array_map(fn ($key) => $catalogoExamenes->label($key), (array) $pedido->examenes)) }}
          </div>
          <div>
            <span class="text-gray-500">Última versión:</span>
            {{ $resultadoPublicado?->version ?? '-' }}
          </div>
        </div>

        @if($resultadoPublicado?->observaciones_generales)
          <div class="mt-3 rounded-xl border border-gray-200 bg-gray-100/80 px-3 py-2 text-sm text-gray-800">
            <strong>Observaciones:</strong> {{ $resultadoPublicado->observaciones_generales }}
          </div>
        @endif

        <div class="mt-5 flex flex-wrap gap-3">
          @if($pedido->pdf_path)
            <a class="btn btn-outline" href="{{ route('doctor.pedidos-laboratorio.download', $pedido) }}" download data-action-lock-ignore data-skip-page-loader>
              <i class="ri-file-shield-line"></i> Ver orden firmada
            </a>
          @endif

          @if($resultadoPublicado && $resultadoPublicado->pdf_path)
            <a
              class="btn btn-outline"
              href="{{ route('doctor.pedidos-laboratorio.resultado.download', $pedido) }}?disposition=inline"
              target="_blank"
              rel="noopener"
              data-action-lock-ignore
              data-skip-page-loader
            >
              <i class="ri-file-pdf-line"></i> Ver informe
            </a>
            <a
              class="btn btn-primary"
              href="{{ route('doctor.pedidos-laboratorio.resultado.download', $pedido) }}?disposition=attachment"
              download
              data-action-lock-ignore
              data-skip-page-loader
            >
              <i class="ri-download-line"></i> Descargar informe
            </a>
            @if($resultadoPublicado->csv)
              <a
                class="btn btn-ghost"
                href="{{ route('documentos.verificar.show', $resultadoPublicado->csv) }}"
                target="_blank"
                rel="noopener"
                data-action-lock-ignore
                data-skip-page-loader
              >
                <i class="ri-qr-code-line"></i> Verificación pública
              </a>
            @endif
          @else
            <span class="rounded-full bg-gray-100 px-3 py-2 text-sm text-gray-500">
              El informe aún no está publicado.
            </span>
          @endif
        </div>
      </article>
    @empty
      <x-ui.empty-state
        title="No hay pedidos registrados"
        message="Cuando emitas pedidos de laboratorio aparecerán aquí."
      />
    @endforelse
  </div>

  <x-ui.pagination :paginator="$pedidos" />
</section>
@endsection
