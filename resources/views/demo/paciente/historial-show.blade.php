@extends('layouts.demo')
@section('title', 'Nota clínica - Demo')
@section('header-title','Nota clínica firmada')
@section('header-subtitle','Detalle de una atención médica individual (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-paciente-demo')
@endsection

@php
  $nota = new \stdClass();
  $nota->id = 1;
  $nota->estado = 'signed';
  $nota->subjetivo_motivo = 'Dolor al tragar desde hace tres días, con congestión y malestar durante la noche.';
  $nota->subjetivo_hpi = 'Paciente de 8 años refiere dolor faríngeo intenso que dificulta la deglución. Presenta febrícula no cuantificada y tos seca nocturna.';
  $nota->subjetivo_ros = 'Resto de sistemas normales. Sin antecedentes de asma.';
  $nota->subjetivo_notas = 'Alergias negativas.';
  
  $nota->signos_vitales = [
      'ta' => '110/70',
      'fc' => '84',
      'fr' => '20',
      'temp' => '37.8',
      'peso' => '25',
      'talla' => '120'
  ];
  
  $nota->diagnosticos = [
      (object) [
          'cie10' => 'J03.9',
          'texto' => 'Faringoamigdalitis aguda, no especificada',
          'tipo' => 'principal'
      ]
  ];
  
  $nota->plan_seguimiento = 'Reposo por 3 días. Paracetamol 250mg cada 8 horas si hay dolor o fiebre. Abundante líquidos.';
  
  $cita = new \App\Models\Cita();
  $cita->id = 1;
  $cita->fecha = now()->subDays(5);
  $cita->hora = '10:00';
  $cita->setRelation('doctor', new \App\Models\User(['name' => 'Dra. Sofía Cárdenas']));
  $cita->setRelation('especialidad', new \App\Models\Especialidad(['nombre' => 'Pediatría']));
@endphp

@section('main')
<div class="space-y-6">
  <!-- SOAP detail cards -->
  <section class="card p-6 space-y-5 bg-white">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <p class="text-xs uppercase tracking-widest text-gray-500">Nota médica</p>
        <h2 class="mt-2 text-xl font-semibold text-gray-900">Nota clínica de la consulta</h2>
        <p class="text-sm text-gray-650">Documento individual de esta atención. No representa por sí solo el expediente longitudinal.</p>
      </div>
      <x-ui.badge tone="success">Firmada</x-ui.badge>
    </div>

    <div class="grid gap-3 md:grid-cols-2">
      <div class="card p-4"><div class="text-xs uppercase tracking-widest text-gray-400 font-semibold">Paciente</div><div class="text-sm font-semibold text-gray-900">Lucía Vega</div></div>
      <div class="card p-4"><div class="text-xs uppercase tracking-widest text-gray-400 font-semibold">Doctor</div><div class="text-sm font-semibold text-gray-900">{{ $cita->doctor->name }}</div></div>
      <div class="card p-4"><div class="text-xs uppercase tracking-widest text-gray-400 font-semibold">Especialidad</div><div class="text-sm font-semibold text-gray-900">{{ $cita->especialidad->nombre }}</div></div>
      <div class="card p-4"><div class="text-xs uppercase tracking-widest text-gray-400 font-semibold">Fecha y hora</div><div class="text-sm font-semibold text-gray-900">{{ $cita->fecha->format('d/m/Y') }} {{ $cita->hora }}</div></div>
      <div class="card p-4"><div class="text-xs uppercase tracking-widest text-gray-400 font-semibold">Firmada por</div><div class="text-sm font-semibold text-gray-900">{{ $cita->doctor->name }}</div></div>
      <div class="card p-4"><div class="text-xs uppercase tracking-widest text-gray-400 font-semibold">Firmada el</div><div class="text-sm font-semibold text-gray-900">{{ $cita->fecha->format('Y-m-d') }} 11:30</div></div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
      <div class="card p-4 space-y-3">
        <h3 class="text-sm font-semibold text-gray-900 border-b border-gray-100 pb-2">Subjetivo</h3>
        <p class="text-sm text-gray-700"><strong>Motivo:</strong> {{ $nota->subjetivo_motivo }}</p>
        <p class="text-sm text-gray-700"><strong>Antecedentes relevantes:</strong> {{ $nota->subjetivo_hpi }}</p>
        <p class="text-sm text-gray-700"><strong>Revisión adicional:</strong> {{ $nota->subjetivo_ros }}</p>
        <p class="text-sm text-gray-700"><strong>Notas subjetivas:</strong> {{ $nota->subjetivo_notas }}</p>
      </div>

      <div class="card p-4 space-y-3">
        <h3 class="text-sm font-semibold text-gray-900 border-b border-gray-100 pb-2">Objetivo (Signos Vitales)</h3>
        <div class="grid gap-2 sm:grid-cols-2 text-sm text-gray-700">
          <div class="rounded-xl border border-gray-200 bg-white/80 px-3 py-2">
            <span class="text-xs uppercase tracking-widest text-gray-400">Presión arterial</span>
            <div class="font-semibold text-gray-900">{{ $nota->signos_vitales['ta'] }} <span class="text-xs text-gray-500">mmHg</span></div>
          </div>
          <div class="rounded-xl border border-gray-200 bg-white/80 px-3 py-2">
            <span class="text-xs uppercase tracking-widest text-gray-400">Frecuencia cardíaca</span>
            <div class="font-semibold text-gray-900">{{ $nota->signos_vitales['fc'] }} <span class="text-xs text-gray-500">lpm</span></div>
          </div>
          <div class="rounded-xl border border-gray-200 bg-white/80 px-3 py-2">
            <span class="text-xs uppercase tracking-widest text-gray-400">Frecuencia respiratoria</span>
            <div class="font-semibold text-gray-900">{{ $nota->signos_vitales['fr'] }} <span class="text-xs text-gray-500">rpm</span></div>
          </div>
          <div class="rounded-xl border border-gray-200 bg-white/80 px-3 py-2">
            <span class="text-xs uppercase tracking-widest text-gray-400">Temperatura</span>
            <div class="font-semibold text-gray-900">{{ $nota->signos_vitales['temp'] }} <span class="text-xs text-gray-500">°C</span></div>
          </div>
        </div>
      </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
      <div class="card p-4 space-y-3">
        <h3 class="text-sm font-semibold text-gray-900 border-b border-gray-100 pb-2">Evaluación (Diagnósticos)</h3>
        @foreach($nota->diagnosticos as $diag)
          <div class="rounded-xl border border-gray-200 bg-white/90 px-3 py-2 text-sm text-gray-700">
            <span class="text-xs uppercase tracking-widest text-gray-400">Diagnóstico Principal ({{ $diag->cie10 }})</span>
            <div class="font-semibold text-gray-900">{{ $diag->texto }}</div>
          </div>
        @endforeach
      </div>

      <div class="card p-4 space-y-3">
        <h3 class="text-sm font-semibold text-gray-900 border-b border-gray-100 pb-2">Plan de tratamiento</h3>
        <p class="text-sm text-gray-700 leading-relaxed">{{ $nota->plan_seguimiento }}</p>
      </div>
    </div>
  </section>

  <section class="card p-6 bg-white">
    <x-ui.form-actions>
      <x-slot:left>
        <a href="{{ route('demo.paciente.historial') }}" class="btn btn-ghost">
          <i class="ri-arrow-left-line"></i> Volver
        </a>
      </x-slot>
    </x-ui.form-actions>
  </section>
</div>
@endsection
