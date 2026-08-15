@extends('layouts.navbar')

@section('title', 'Verificación de informe de laboratorio')

@section('main')
<div class="flex min-h-[calc(100vh-4.5rem)] flex-col justify-between bg-[radial-gradient(circle_at_top,_var(--accent-soft,_#f1f5f9)_0,_#f8fafc_35%,_#ffffff_100%)]">
  <div class="mx-auto flex w-full max-w-5xl flex-1 items-center px-4 py-12 sm:px-6 lg:px-8">
    <div class="grid w-full gap-8 rounded-[2rem] border border-slate-200/80 bg-white/90 p-6 shadow-[0_25px_70px_rgba(15,23,42,0.08)] backdrop-blur md:grid-cols-[1.05fr_0.95fr] md:p-10">
      <div class="space-y-6">
        <div class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-xs font-semibold uppercase tracking-[0.25em] text-slate-700">
          Verificación de informe
        </div>
        <div class="space-y-3">
          <h1 class="text-4xl font-bold tracking-tight text-slate-900 sm:text-5xl">
            Informe de laboratorio verificado.
          </h1>
          <p class="max-w-xl text-base leading-7 text-slate-600">
            Este código confirma la existencia y el estado de la versión emitida. No muestra el contenido clínico completo sin autenticación.
          </p>
        </div>

        <div class="grid gap-3 text-sm text-slate-600 sm:grid-cols-2">
          <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <div class="font-semibold text-slate-900">Estado</div>
            <div class="mt-1">{{ ucfirst(str_replace('_', ' ', $metadata['estado'] ?? 'borrador')) }}</div>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <div class="font-semibold text-slate-900">Versión</div>
            <div class="mt-1">V{{ $metadata['version'] ?? '-' }}</div>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <div class="font-semibold text-slate-900">Informe</div>
            <div class="mt-1">#INF-{{ $metadata['pedido_id'] ?? '-' }}-V{{ $metadata['version'] ?? '-' }}</div>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <div class="font-semibold text-slate-900">Paciente</div>
            <div class="mt-1">Pedido #{{ $metadata['pedido_id'] ?? '-' }}</div>
          </div>
        </div>

        @if(($metadata['estado'] ?? '') === 'reemplazado')
          <x-ui.alert tone="warning">Esta versión fue reemplazada por una corrección posterior.</x-ui.alert>
        @elseif(($metadata['estado'] ?? '') === 'anulado')
          <x-ui.alert tone="error">Esta versión fue anulada.</x-ui.alert>
        @elseif(($metadata['estado'] ?? '') === 'borrador')
          <x-ui.alert tone="info">Este documento es un borrador interno y no debe usarse como resultado final.</x-ui.alert>
        @else
          <x-ui.alert tone="success">La versión verificada es válida.</x-ui.alert>
        @endif
      </div>

      <div class="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-6 shadow-inner">
        <div class="space-y-4 text-sm text-slate-600">
          <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <div class="text-xs font-semibold uppercase tracking-widest text-slate-500">Documento</div>
            <div class="mt-1 text-base font-semibold text-slate-900">{{ $documento['titulo'] ?? 'Informe de laboratorio' }}</div>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <div class="text-xs font-semibold uppercase tracking-widest text-slate-500">Fecha de emisión</div>
            <div class="mt-1 text-base font-semibold text-slate-900">
              {{ isset($metadata['publicado_at']) && $metadata['publicado_at'] ? $metadata['publicado_at']->format('d/m/Y H:i') : '-' }}
            </div>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <div class="text-xs font-semibold uppercase tracking-widest text-slate-500">Responsable</div>
            <div class="mt-1 text-base font-semibold text-slate-900">Laboratorio clínico</div>
          </div>

          @if($downloadUrl)
            <a href="{{ $downloadUrl }}" class="btn btn-primary flex w-full items-center justify-center gap-2 rounded-2xl px-4 py-3 text-base font-semibold transition">
              <i class="ri-download-line"></i>
              Ir al documento seguro
            </a>
          @endif
        </div>
      </div>
    </div>
  </div>
  @include('partials.footer')
</div>
@endsection
