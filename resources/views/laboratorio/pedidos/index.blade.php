@extends('layouts.laboratorio')
@section('title', 'Pedidos de laboratorio firmados - '.$clinicIdentity->name())
@section('activeSidebar', 'pedidos')
@section('header-title', 'Pedidos médicos')
@section('header-subtitle', 'Órdenes firmadas y resultados estructurados')

@section('main')
@php
  $estado = $estado ?? 'all';
  $catalogoExamenes = app(\App\Services\LabTestCatalogService::class);
@endphp

<section class="space-y-6">
  <header class="card p-6">
    <form class="flex flex-wrap items-end gap-3" method="GET" action="{{ route('laboratorio.pedidos.index') }}">
      <div>
        <label class="form-label" for="estado">Filtrar por estado</label>
        <select id="estado" name="estado" class="form-select">
          <option value="all" @selected($estado === 'all')>Todos</option>
          <option value="pendiente_toma" @selected($estado === 'pendiente_toma')>Pendiente</option>
          <option value="muestra_tomada" @selected($estado === 'muestra_tomada')>En proceso</option>
          <option value="resultado_listo" @selected($estado === 'resultado_listo')>Resultados publicados</option>
        </select>
      </div>
      <button class="btn btn-outline btn-sm" type="submit">
        <i class="ri-filter-3-line"></i> Aplicar
      </button>
      @if($estado !== 'all')
        <a class="btn btn-ghost btn-sm" href="{{ route('laboratorio.pedidos.index') }}">
          <i class="ri-refresh-line"></i> Limpiar
        </a>
      @endif
    </form>
  </header>

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
        $resultadoBorrador = $pedido->resultados->firstWhere('estado', 'borrador');
        $badgeTone = match ($pedido->estado) {
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
        $actionLabel = $resultadoPublicado ? 'Corregir resultados' : 'Registrar resultados';
      @endphp

      <article class="card p-6" id="pedido-{{ $pedido->id }}">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <h2 class="text-lg font-semibold text-gray-900">Pedido #{{ $pedido->id }}</h2>
              <x-ui.badge :tone="$badgeTone">{{ $statusLabel }}</x-ui.badge>
            </div>
            <p class="mt-1 text-sm text-gray-500">
              Paciente: <strong>{{ $pedido->nombrePacienteReal() }}</strong>
              @if($pedido->representanteNombre())
                | Representante: {{ $pedido->representanteNombre() }}
              @endif
            </p>
            <p class="text-xs uppercase tracking-widest text-gray-400">Solicitado por: Dr. {{ $pedido->doctor?->name ?? '-' }}</p>
          </div>
        </div>

        <div class="mt-4 grid gap-3 text-sm text-gray-600 sm:grid-cols-2 lg:grid-cols-4">
          <div><span class="text-gray-500">Orden creada:</span> {{ $pedido->created_at?->format('d/m/Y H:i') }}</div>
          <div><span class="text-gray-500">Última publicación:</span> {{ $pedido->resultado_publicado_at?->format('d/m/Y H:i') ?? '-' }}</div>
          <div><span class="text-gray-500">Versión:</span> {{ $resultadoPublicado?->version ?? $resultadoBorrador?->version ?? 1 }}</div>
          <div><span class="text-gray-500">Responsable:</span> {{ $resultadoPublicado?->laboratorio?->name ?? $resultadoBorrador?->laboratorio?->name ?? '-' }}</div>
        </div>

        <div class="mt-4">
          <strong class="text-sm text-gray-700 block mb-1.5">Exámenes solicitados</strong>
          <div class="flex flex-wrap gap-1.5">
            @foreach(($pedido->examenes ?? []) as $examKey)
              <span class="badge neutral text-xs">{{ $catalogoExamenes->label($examKey) }}</span>
            @endforeach
          </div>
        </div>

        @if($resultadoPublicado?->observaciones_generales)
          <div class="mt-3 rounded-xl border border-gray-200 bg-gray-100/80 px-3 py-2 text-sm text-gray-800">
            <strong>Observación general:</strong> {{ $resultadoPublicado->observaciones_generales }}
          </div>
        @elseif($resultadoBorrador?->observaciones_generales)
          <div class="mt-3 rounded-xl border border-amber-100 bg-amber-50/80 px-3 py-2 text-sm text-amber-900">
            <strong>Borrador:</strong> {{ $resultadoBorrador->observaciones_generales }}
          </div>
        @endif

        <div class="mt-5 flex flex-wrap gap-3">
          @if($pedido->estado === 'pendiente_toma')
            <form method="POST" action="{{ route('laboratorio.pedidos.muestra', $pedido->id) }}">
              @csrf
              <button class="btn btn-outline" type="submit">
                <i class="ri-test-tube-line"></i> Marcar en proceso
              </button>
            </form>
          @endif

          <a class="btn btn-primary" href="{{ route('laboratorio.pedidos.resultados.form', $pedido) }}">
            <i class="ri-file-list-3-line"></i> {{ $actionLabel }}
          </a>

          @if($resultadoPublicado && $resultadoPublicado->pdf_path)
            <a class="btn btn-outline" href="{{ route('laboratorio.pedidos.resultados.download', $pedido) }}">
              <i class="ri-download-line"></i> Descargar PDF
            </a>
            <form method="POST" action="{{ route('laboratorio.pedidos.resultados.resend', $pedido) }}">
              @csrf
              <button class="btn btn-outline" type="submit">
                <i class="ri-mail-send-line"></i> Reenviar correo
              </button>
            </form>
            @if($resultadoPublicado->csv)
              <a class="btn btn-ghost" href="{{ route('documentos.verificar.show', $resultadoPublicado->csv) }}" target="_blank" rel="noopener">
                <i class="ri-qr-code-line"></i> Verificación pública
              </a>
            @endif
          @endif
        </div>
      </article>
    @empty
      <x-ui.empty-state
        title="No hay pedidos registrados"
        message="Cuando los doctores emitan pedidos firmados digitalmente aparecerán aquí."
      />
    @endforelse
  </div>

  <x-ui.pagination :paginator="$pedidos" />
</section>
@endsection
