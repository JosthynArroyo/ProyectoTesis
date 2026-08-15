@extends('layouts.paciente')
@section('title', 'Certificado medico')
@section('header-title','Certificado medico')
@section('header-subtitle','Documento emitido por tu doctor tratante')

@section('main')
<div class="space-y-6">
  @if($certificado->isReemplazado())
    <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-900 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-200">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p class="font-semibold text-sm flex items-center gap-1.5 text-rose-900 dark:text-rose-200">
            <i class="ri-close-circle-line text-lg text-rose-600 dark:text-rose-400"></i> DOCUMENTO REEMPLAZADO (Versión {{ $certificado->version ?: 1 }})
          </p>
          <p class="mt-1 text-xs text-rose-800 dark:text-rose-300 leading-relaxed">
            Este certificado fue sustituido por una versión posterior el {{ $certificado->fecha_correccion?->format('d/m/Y H:i') ?? '-' }}.
          </p>
          @if($certificado->motivo_correccion)
            <p class="mt-1 text-xs text-rose-800 dark:text-rose-300 font-medium">
              Motivo de la corrección: {{ $certificado->motivo_correccion }}
            </p>
          @endif
        </div>
        @if($certificado->reemplazadoPor || $certificado->cita?->certificadoMedico)
          <a href="{{ route('paciente.certificados.show', $certificado->reemplazadoPor ?: $certificado->cita->certificadoMedico) }}" class="btn btn-sm btn-outline text-xs border-rose-300 text-rose-800 dark:border-rose-700 dark:text-rose-200 hover:bg-rose-100 dark:hover:bg-rose-900/50">
            <i class="ri-arrow-right-line"></i> Ver certificado vigente
          </a>
        @endif
      </div>
    </div>
  @endif

  @include('certificados._detalle', ['certificado' => $certificado])

  @php
    $historial = $certificado->getHistorialCadena();
  @endphp

  @if($historial->count() > 1)
    <section class="card p-6 space-y-4">
      <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
        <i class="ri-history-line text-teal-600 dark:text-teal-400"></i> Historial de correcciones
      </h3>
      <div class="divide-y divide-gray-100 dark:divide-gray-700 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
        @foreach($historial as $item)
          <div class="p-4 flex flex-wrap items-center justify-between gap-3 {{ $item->id === $certificado->id ? 'bg-teal-50/40 dark:bg-teal-950/30' : '' }}">
            <div class="space-y-1">
              <div class="flex items-center gap-2">
                <span class="font-bold text-sm text-gray-900 dark:text-gray-100">Versión {{ $item->version ?: 1 }}</span>
                <span class="badge {{ $item->isReemplazado() ? 'danger' : 'success' }} text-xs">
                  {{ $item->isReemplazado() ? 'Reemplazado' : 'Vigente' }}
                </span>
                @if($item->id === $certificado->id)
                  <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">(Viendo actualmente)</span>
                @endif
              </div>
              <p class="text-xs text-gray-600 dark:text-gray-400">Código: {{ $item->codigo }}</p>
              @if($item->motivo_correccion)
                <p class="text-xs text-gray-500 dark:text-gray-400">Motivo: {{ $item->motivo_correccion }}</p>
              @endif
            </div>
            @if($item->id !== $certificado->id)
              <div class="flex items-center gap-2">
                <a href="{{ route('paciente.certificados.show', $item) }}" class="btn btn-xs btn-outline">Ver</a>
                <a href="{{ route('paciente.certificados.download', $item) }}" class="btn btn-xs btn-primary" download>Descargar</a>
              </div>
            @endif
          </div>
        @endforeach
      </div>
    </section>
  @endif

  <section class="card p-6">
    <x-ui.form-actions>
      <x-slot:left>
        <a href="{{ route('paciente.historial') }}" class="btn btn-ghost">Volver al historial</a>
      </x-slot>
      <a href="{{ route('paciente.certificados.download', $certificado) }}" class="btn btn-primary" download data-action-lock-ignore data-skip-page-loader>
        <i class="ri-download-2-line"></i> Descargar certificado
      </a>
    </x-ui.form-actions>
  </section>
</div>
@endsection
