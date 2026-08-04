@extends('layouts.app')

@php
  $tipoIcono = match($tipo) {
    'receta'             => 'ri-medicine-bottle-line',
    'certificado_medico' => 'ri-award-line',
    'pedido_laboratorio' => 'ri-test-tube-line',
    'resultado_laboratorio' => 'ri-file-chart-line',
    'orden_cobro'        => 'ri-file-list-3-line',
    'recibo_pago'       => 'ri-shield-check-line',
    default              => 'ri-file-text-line',
  };
  $estadoTone = match($estado) {
    'Verificado', 'Publicado', 'Pagado' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
    'Borrador', 'Pendiente', 'En verificación' => 'bg-amber-50 text-amber-700 border-amber-200',
    'Reemplazado', 'Rechazado', 'Anulado' => 'bg-rose-50 text-rose-700 border-rose-200',
    default       => 'bg-emerald-50 text-emerald-700 border-emerald-200',
  };
  $estadoIcono = match($estado) {
    'Verificado', 'Publicado', 'Pagado' => 'ri-shield-check-line',
    'Borrador', 'Pendiente', 'En verificación' => 'ri-time-line',
    'Reemplazado', 'Rechazado', 'Anulado' => 'ri-close-circle-line',
    default       => 'ri-shield-check-line',
  };
@endphp

@section('title', 'Verificación · '.$titulo)

@section('content')
<div class="min-h-screen bg-[radial-gradient(ellipse_at_top,_#d1fae5_0,_#f0fdf4_30%,_#ffffff_70%)]">
  <div class="mx-auto flex min-h-screen max-w-5xl flex-col items-center justify-center px-4 py-16 sm:px-6 lg:px-8">

    {{-- Branding --}}
    <div class="mb-8 text-center">
      <p class="text-sm font-semibold uppercase tracking-widest text-emerald-700">{{ $clinica }}</p>
      <p class="mt-1 text-xs text-slate-400">Portal de verificación de documentos</p>
    </div>

    <div class="w-full rounded-[2rem] border border-emerald-100 bg-white/90 shadow-[0_25px_70px_rgba(15,118,110,0.10)] backdrop-blur">

      {{-- Top banner --}}
      <div class="flex flex-col items-center gap-3 rounded-t-[2rem] border-b border-emerald-100 bg-emerald-50/60 px-6 py-6 text-center">
        <div class="flex h-16 w-16 items-center justify-center rounded-full border border-emerald-200 bg-white shadow-sm">
          <i class="{{ $tipoIcono }} text-3xl text-emerald-600"></i>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-slate-900 sm:text-3xl">{{ $titulo }} verificado.</h1>
          <p class="mt-1 text-sm text-slate-500">Autenticidad confirmada por el sistema clínico</p>
        </div>
        <span class="inline-flex items-center gap-1.5 rounded-full border px-4 py-1.5 text-xs font-semibold {{ $estadoTone }}">
          <i class="{{ $estadoIcono }}"></i>
          {{ $estado }}
        </span>
      </div>

      {{-- Details grid --}}
      <div class="grid gap-4 p-6 sm:grid-cols-2 md:grid-cols-3 lg:p-8">

        {{-- CSV --}}
        <div class="col-span-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
          <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Código CSV</p>
          <p class="mt-1 font-mono text-lg font-bold text-slate-900">{{ $csv }}</p>
        </div>

        {{-- Tipo --}}
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
          <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Tipo de documento</p>
          <p class="mt-1 text-sm font-semibold text-slate-800">{{ $titulo }}</p>
        </div>

        @if(!empty($version))
          {{-- Version --}}
          <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Versión</p>
            <p class="mt-1 text-sm font-semibold text-slate-800">{{ $version }}</p>
          </div>
        @endif

        {{-- Fecha --}}
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
          <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Fecha de emisión</p>
          <p class="mt-1 text-sm font-semibold text-slate-800">
            @if($emitido_en)
              {{ \Carbon\Carbon::parse($emitido_en)->format('d/m/Y H:i') }}
            @else
              <span class="text-slate-400">—</span>
            @endif
          </p>
        </div>

        {{-- Clinica --}}
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
          <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Clínica emisora</p>
          <p class="mt-1 text-sm font-semibold text-slate-800">{{ $clinica }}</p>
        </div>

        @if(!empty($doctor))
          {{-- Doctor --}}
          <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Profesional</p>
            <p class="mt-1 text-sm font-semibold text-slate-800">{{ $doctor }}</p>
          </div>
        @endif

        @if(!empty($folio))
          <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Folio</p>
            <p class="mt-1 text-sm font-semibold text-slate-800">{{ $folio }}</p>
          </div>
        @endif

        @if(!empty($monto))
          <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Monto</p>
            <p class="mt-1 text-sm font-semibold text-slate-800">{{ $monto }}</p>
          </div>
        @endif

        @if(!empty($metodo_pago))
          <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Método de pago</p>
            <p class="mt-1 text-sm font-semibold text-slate-800">{{ $metodo_pago }}</p>
          </div>
        @endif

        @if(!empty($paciente))
          {{-- Patient (protected) --}}
          <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Paciente</p>
            <p class="mt-1 text-sm font-semibold text-slate-800">{{ $paciente }}</p>
            <p class="mt-0.5 text-xs text-slate-400">Nombre protegido por privacidad</p>
          </div>
        @endif

      </div>

      {{-- Footer notice --}}
      <div class="rounded-b-[2rem] border-t border-slate-100 bg-slate-50/60 px-6 py-4 text-center">
        <p class="text-xs leading-5 text-slate-500">
          Este portal solo confirma la autenticidad del documento. El contenido clínico completo
          está disponible exclusivamente para los usuarios autorizados dentro del sistema.
          No se proporciona ningún enlace de descarga en este portal.
        </p>
        <a href="{{ route('documentos.verificar.form') }}" class="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-600 hover:underline">
          <i class="ri-arrow-left-line"></i>
          Verificar otro documento
        </a>
      </div>

    </div>

  </div>
</div>
@endsection
