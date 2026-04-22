@php
  $layout = match (true) {
    $esSuperadmin => 'layouts.superadmin',
    $esAdmin => 'layouts.admin',
    $esProfesionalResponsable && auth()->user()?->hasRole('laboratorio') => 'layouts.laboratorio',
    $esProfesionalResponsable => 'layouts.doctor',
    default => 'layouts.paciente',
  };
@endphp

@extends($layout)
@section('title', 'Comprobante de cita')
@section('header-title', 'Comprobante de cita')
@section('header-subtitle', 'Validación del agendamiento')

@section('main')
<div class="space-y-6">
  <section class="card p-6">
    <p class="text-xs uppercase tracking-widest text-slate-500">Comprobante de cita</p>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">Folio {{ $cita->folio_cita ?: 'SIN FOLIO' }}</h1>
    <p class="mt-1 text-sm text-slate-600">Código de validación: {{ $cita->token_validacion ?: 'N/D' }}</p>
  </section>

  <section class="card p-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Estado actual de la cita</p>
        <p class="text-lg font-semibold text-slate-900">{{ $cita->estadoComprobante() }}</p>
      </div>
      <span class="badge {{ $cita->comprobanteEstaVigente() ? 'success' : 'danger' }}">
        {{ $cita->comprobanteEstaVigente() ? 'Comprobante vigente' : 'Comprobante sin vigencia' }}
      </span>
    </div>

    <div class="mt-6 grid gap-4 md:grid-cols-2">
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Paciente</p>
        <p class="text-sm font-semibold text-slate-900">{{ $cita->paciente?->name ?? 'N/D' }}</p>
        <p class="text-xs text-slate-500">{{ $cita->paciente?->dni ?? 'N/D' }}</p>
      </div>
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Clínica</p>
        <p class="text-sm font-semibold text-slate-900">Clínica Don Bosco</p>
      </div>
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Fecha</p>
        <p class="text-sm font-semibold text-slate-900">{{ $cita->fecha?->format('Y-m-d') ?? 'N/D' }}</p>
      </div>
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Hora</p>
        <p class="text-sm font-semibold text-slate-900">{{ $cita->hora ? substr((string) $cita->hora, 0, 5) : 'N/D' }}</p>
      </div>
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Médico</p>
        <p class="text-sm font-semibold text-slate-900">{{ $cita->doctor?->name ?? 'N/D' }}</p>
      </div>
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Especialidad</p>
        <p class="text-sm font-semibold text-slate-900">{{ $cita->especialidad?->nombre ?? 'N/D' }}</p>
      </div>
    </div>

    <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
      Este comprobante identifica la cita agendada para validación en recepción. No representa una deuda ni una orden de pago.
    </div>

    @if(!$cita->comprobanteEstaVigente())
      <div class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
        El comprobante ya no está vigente porque la cita se encuentra en estado {{ strtolower($cita->estadoComprobante()) }}.
      </div>
    @endif

    <div class="mt-6 flex flex-wrap gap-2">
      @if($esPacientePropietario)
        <a href="{{ route('paciente.citas.comprobante.pdf', $cita) }}" class="btn btn-primary" target="_blank" rel="noopener">Descargar comprobante</a>
        <a href="{{ route('paciente.citas') }}" class="btn btn-outline">Volver a mis citas</a>
      @elseif($esAdmin || $esSuperadmin)
        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline">Volver al panel</a>
      @elseif($esProfesionalResponsable && auth()->user()?->hasRole('laboratorio'))
        <a href="{{ route('laboratorio.dashboard') }}" class="btn btn-outline">Volver al panel</a>
      @elseif($esProfesionalResponsable)
        <a href="{{ route('doctor.citas') }}" class="btn btn-outline">Volver a mis citas</a>
      @endif
    </div>
  </section>
</div>
@endsection
