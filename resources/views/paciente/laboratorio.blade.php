@extends('layouts.paciente')
@section('title', 'Resultados de Laboratorio')
@section('body-class', 'paciente-body--laboratorio')
@section('header-title','Resultados de laboratorio')
@section('header-subtitle','Consulta órdenes y resultados')

@section('main')
<div class="space-y-6">
  <header class="card p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Laboratorio</p>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Resultados y órdenes</h1>
        <p class="text-slate-600">Consulta el estado de tus exámenes y descarga resultados.</p>
      </div>
      <a class="btn btn-outline" href="{{ route('paciente.crear-cita') }}">Agendar cita con médico</a>
    </div>
  </header>

  @if ($errors->any())
    <x-ui.alert tone="error">{{ $errors->first() }}</x-ui.alert>
  @endif
  @if (session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  <div class="card p-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h2 class="text-lg font-semibold text-slate-900">?rdenes de laboratorio</h2>
        <p class="text-sm text-slate-500">Se muestran tus citas de laboratorio más recientes.</p>
      </div>
    </div>
    <div class="mt-4 table-shell">
      <table class="table">
        <thead>
        <tr>
          <th>Examen</th>
          <th>Laboratorio</th>
          <th>Fecha</th>
          <th>Estado</th>
          <th>Archivo</th>
        </tr>
        </thead>
        <tbody>
        @forelse($ordenes as $orden)
          <tr>
            <td data-label="Examen">
              <strong>{{ $orden->tipo_examen }}</strong>
              @if($orden->preparacion)
                <div class="text-xs text-slate-500">Prep: {{ $orden->preparacion }}</div>
              @endif
            </td>
            <td data-label="Laboratorio">{{ optional($orden->cita->doctor)->name ?? 'Laboratorio' }}</td>
            <td data-label="Fecha">
              {{ optional($orden->cita->fecha)->format('Y/m/d') }}
              {{ $orden->cita->hora ? \Carbon\Carbon::parse($orden->cita->hora)->format('H:i') : '' }}
            </td>
            <td data-label="Estado">
              <span class="badge info">{{ str_replace('_', ' ', ucfirst($orden->estado)) }}</span>
            </td>
            <td data-label="Archivo">
              @if($orden->resultado_path)
                <a href="{{ route('paciente.laboratorio.download', $orden->id) }}" class="btn btn-outline">Descargar</a>
              @else
                <span class="text-xs text-slate-500">Pendiente</span>
              @endif
            </td>
          </tr>
          @if($orden->resultado_resumen)
            <tr class="bg-slate-50/60">
              <td colspan="5">
                <div class="text-sm text-slate-600"><strong>Resumen:</strong> {{ $orden->resultado_resumen }}</div>
              </td>
            </tr>
          @endif
        @empty
          <tr>
            <td colspan="5">No tienes órdenes de laboratorio registradas.</td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>

    <div class="mt-4">
      {{ $ordenes->links() }}
    </div>
  </div>
</div>
@endsection
