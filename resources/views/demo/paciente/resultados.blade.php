@extends('layouts.demo')
@section('title', 'Resultados de Laboratorio - Demo')
@section('header-title','Resultados de laboratorio')
@section('header-subtitle','Consulta órdenes y resultados (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-paciente-demo')
@endsection

@php
  $ordenes = collect($results)->map(function($r) {
      $orden = new \stdClass();
      $orden->uid = $r['id'];
      $orden->title = $r['exam'];
      $orden->status_tone = strtolower($r['status']) === 'disponible' ? 'success' : 'warning';
      $orden->status_label = strtolower($r['status']) === 'disponible' ? 'Resultado disponible' : 'Muestra tomada';
      $orden->subtitle = 'Doctor: ' . $r['doctor'] . ' | Código: ' . $r['code'];
      $orden->date_label = $r['delivered_at'];
      $orden->primary_action_url = strtolower($r['status']) === 'disponible' ? '#' : null;
      $orden->secondary_actions = [
          ['label' => 'Ver preparación', 'url' => '#'],
      ];
      $orden->summary = str_contains(strtolower($r['exam']), 'hemograma') 
          ? 'Hemoglobina 13.2 g/dL (Normal: 12.0 - 15.0). Hematocrito 40% (Normal: 36 - 46). Plaquetas y glóbulos blancos normales.' 
          : 'Glucosa basal: 85 mg/dL. Rango de referencia: 70 - 100 mg/dL. Valores óptimos en ayunas.';
      $orden->preparation = 'Ayuno estricto de 8 a 12 horas. Solo ingesta de agua permitida.';
      $orden->notes = 'Indicación médica de rutina para control anual.';
      return $orden;
  });
@endphp

@section('main')
<div class="space-y-6">
  <div class="panel-action-bar">
    <a class="btn btn-outline btn-full-mobile" href="{{ route('demo.paciente.crear-cita') }}">Agendar cita médica</a>
  </div>

  <section class="card p-6 bg-white">
    <div class="page-header">
      <div class="page-header__info">
        <h2>Mis exámenes y resultados</h2>
        <p>Se integran aquí las órdenes tradicionales y los resultados publicados por el laboratorio.</p>
      </div>
    </div>

    @if ($ordenes->isNotEmpty())
      <div class="mt-4 grid gap-4">
        @foreach($ordenes as $orden)
          <article id="lab-item-{{ $orden->uid }}" class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
              <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                  <h3 class="text-base font-semibold text-gray-900">{{ $orden->title }}</h3>
                  <span class="badge {{ $orden->status_tone }}">{{ $orden->status_label }}</span>
                </div>
                <p class="text-sm text-gray-500">{{ $orden->subtitle }}</p>
                <div class="flex flex-wrap gap-4 text-sm text-gray-600">
                  <span><strong>Fecha:</strong> {{ $orden->date_label }}</span>
                </div>
              </div>

              <div class="flex items-center gap-2">
                @if($orden->primary_action_url)
                  <button class="btn btn-primary btn-sm demo-action-blocked">Descargar</button>
                @endif
              </div>
            </div>

            @if($orden->summary || $orden->preparation || $orden->notes)
              <div class="mt-4 grid gap-3 md:grid-cols-3">
                @if($orden->summary)
                  <div class="rounded-2xl border border-gray-200 bg-gray-50/80 p-4">
                    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">Resumen del Resultado</p>
                    <p class="mt-2 text-xs text-gray-650 leading-relaxed">{{ $orden->summary }}</p>
                  </div>
                @endif
                @if($orden->preparation)
                  <div class="rounded-2xl border border-gray-200 bg-gray-50/80 p-4">
                    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">Preparación</p>
                    <p class="mt-2 text-xs text-gray-650 leading-relaxed">{{ $orden->preparation }}</p>
                  </div>
                @endif
                @if($orden->notes)
                  <div class="rounded-2xl border border-gray-200 bg-gray-50/80 p-4">
                    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">Indicaciones</p>
                    <p class="mt-2 text-xs text-gray-650 leading-relaxed">{{ $orden->notes }}</p>
                  </div>
                @endif
              </div>
            @endif
          </article>
        @endforeach
      </div>
    @else
      <div class="mt-4">
        <x-ui.empty-state title="Aún no tienes exámenes registrados." message="Cuando tu doctor solicite un examen o el laboratorio publique un resultado, aparecerá aquí con su estado y las acciones disponibles.">
          <div class="mt-4 flex flex-wrap justify-center gap-3">
            <a class="btn btn-outline" href="{{ route('demo.paciente.crear-cita') }}">Agendar cita médica</a>
          </div>
        </x-ui.empty-state>
      </div>
    @endif
  </section>
</div>
@endsection
