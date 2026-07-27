@extends('layouts.app')

@section('title', 'Verificación de informe de laboratorio')

@section('content')
<div class="min-h-screen bg-[radial-gradient(circle_at_top,_#d1fae5_0,_#f8fafc_35%,_#ffffff_100%)]">
  <div class="mx-auto flex min-h-screen max-w-5xl items-center px-4 py-16 sm:px-6 lg:px-8">
    <div class="grid w-full gap-8 rounded-[2rem] border border-emerald-100 bg-white/90 p-6 shadow-[0_25px_70px_rgba(15,118,110,0.12)] backdrop-blur md:grid-cols-[1.05fr_0.95fr] md:p-10">
      <div class="space-y-6">
        <div class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-4 py-2 text-xs font-semibold uppercase tracking-[0.25em] text-emerald-700">
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
            <a href="{{ $downloadUrl }}" class="flex w-full items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-4 py-3 text-base font-semibold text-white transition hover:bg-emerald-700">
              <i class="ri-download-line"></i>
              Ir al documento seguro
            </a>
          @endif
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
