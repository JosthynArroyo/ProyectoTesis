@extends('layouts.doctor')
@section('title', 'Certificado medico')
@section('activeSidebar', 'citas')
@section('header-title','Certificado medico')
@section('header-subtitle','Documento emitido asociado a la cita')

@section('main')
<div class="space-y-6">
  @if (session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif
  @if (session('info'))
    <x-ui.alert tone="warning">{{ session('info') }}</x-ui.alert>
  @endif

  @if($certificado->isReemplazado())
    <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-900">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p class="font-semibold text-sm flex items-center gap-1.5 text-rose-900">
            <i class="ri-close-circle-line text-lg text-rose-600"></i> DOCUMENTO REEMPLAZADO (Versión {{ $certificado->version ?: 1 }})
          </p>
          <p class="mt-1 text-xs text-rose-800 leading-relaxed">
            Este certificado fue sustituido por una versión posterior el {{ $certificado->fecha_correccion?->format('d/m/Y H:i') ?? '-' }}.
          </p>
          @if($certificado->motivo_correccion)
            <p class="mt-1 text-xs text-rose-800 font-medium">
              Motivo de la corrección: {{ $certificado->motivo_correccion }}
            </p>
          @endif
        </div>
        @if($certificado->reemplazadoPor || $certificado->cita?->certificadoMedico)
          <a href="{{ route('doctor.certificados.show', $certificado->reemplazadoPor ?: $certificado->cita->certificadoMedico) }}" class="btn btn-sm btn-outline text-xs">
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
      <h3 class="text-base font-semibold text-gray-900 flex items-center gap-2">
        <i class="ri-history-line text-teal-600"></i> Historial de correcciones
      </h3>
      <div class="divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
        @foreach($historial as $item)
          <div class="p-4 flex flex-wrap items-center justify-between gap-3 {{ $item->id === $certificado->id ? 'bg-teal-50/40' : '' }}">
            <div class="space-y-1">
              <div class="flex items-center gap-2">
                <span class="font-bold text-sm text-gray-900">Versión {{ $item->version ?: 1 }}</span>
                <span class="badge {{ $item->isReemplazado() ? 'danger' : 'success' }} text-xs">
                  {{ $item->isReemplazado() ? 'Reemplazado' : 'Vigente' }}
                </span>
                <span class="text-xs text-gray-500">({{ $item->codigo }})</span>
              </div>
              <p class="text-xs text-gray-600">
                Emitido: {{ $item->fecha_emision?->format('d/m/Y H:i') }}
                @if($item->isReemplazado() && $item->fecha_correccion)
                  | Reemplazado: {{ $item->fecha_correccion->format('d/m/Y H:i') }}
                @endif
              </p>
              @if($item->isReemplazado() && $item->motivo_correccion)
                <p class="text-xs text-gray-700 italic">Motivo: "{{ $item->motivo_correccion }}"</p>
              @endif
            </div>
            <div>
              @if($item->id !== $certificado->id)
                <a href="{{ route('doctor.certificados.show', $item) }}" class="btn btn-ghost btn-sm text-xs">
                  Ver esta versión
                </a>
              @else
                <span class="text-xs text-gray-400 font-medium">Viendo versión actual</span>
              @endif
            </div>
          </div>
        @endforeach
      </div>
    </section>
  @endif

  <section class="card p-6">
    <x-ui.form-actions>
      <x-slot:left>
        <a href="{{ route('doctor.citas') }}" class="btn btn-ghost">Volver a citas</a>
      </x-slot>
      @if(in_array($certificado->envio_estado, ['failed', 'queued', 'sending'], true))
        <form method="POST" action="{{ route('doctor.certificados.resend', $certificado) }}">
          @csrf
          <button type="submit" class="btn btn-outline">
            <i class="ri-mail-send-line"></i> Reenviar por correo
          </button>
        </form>
      @endif
      @if($certificado->isVigente())
        <a href="{{ route('doctor.certificados.corregir', $certificado) }}" class="btn btn-outline">
          <i class="ri-edit-line"></i> Corregir certificado
        </a>
      @endif
      <a href="{{ route('doctor.certificados.download', $certificado) }}" class="btn btn-primary" download data-action-lock-ignore data-skip-page-loader>
        <i class="ri-download-2-line"></i> Descargar certificado
      </a>
    </x-ui.form-actions>
  </section>
</div>
@endsection
