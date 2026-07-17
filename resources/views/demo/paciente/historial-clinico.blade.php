@extends('layouts.demo')
@section('title', 'Historial clínico - Demo')
@section('activeSidebar', 'historial')
@section('header-title','Historial clínico')
@section('header-subtitle','Documentos individuales de tus atenciones (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-paciente-demo')
@endsection

@php
  $notas = collect($historyEntries)->map(function($e, $i) {
      $nota = new \App\Models\NotaSoap();
      $nota->id = $i + 1;
      
      $cita = new \App\Models\Cita();
      $cita->id = $i + 1;
      $cita->fecha = \Carbon\Carbon::parse($e['date']);
      $cita->hora = '10:00';
      $cita->setRelation('doctor', new \App\Models\User(['name' => $e['doctor']]));
      $cita->setRelation('especialidad', new \App\Models\Especialidad(['nombre' => $e['specialty']]));
      
      $nota->setRelation('cita', $cita);
      return $nota;
  });

  $certificados = collect([
      (object) [
          'id' => 1,
          'codigo' => 'CERT-MED-9812',
          'doctor' => (object) ['name' => 'Dra. Sofía Cárdenas'],
          'cita' => (object) [
              'especialidad' => (object) ['nombre' => 'Pediatría']
          ],
          'fecha_emision' => now()->subDays(5)
      ]
  ]);

  $pacienteFilter = 'all';
@endphp

@section('main')
  <div class="space-y-6">
    <section class="card p-4 bg-white">
      <form method="GET" action="{{ route('demo.paciente.historial') }}" class="flex flex-wrap items-end gap-3">
        <div class="w-full md:w-72">
          <label class="form-label" for="paciente">Filtrar por paciente</label>
          <select id="paciente" name="paciente" class="form-select">
            <option value="all" selected>Todos</option>
            <option value="principal">María Fernanda Vega (cuenta principal)</option>
            <option value="1">Lucía Vega (cuenta dependiente)</option>
          </select>
        </div>
      </form>
    </section>

    <!-- Certificados -->
    <section class="grid gap-4">
      <div>
        <p class="text-xs uppercase tracking-widest text-gray-500">Certificados</p>
        <h2 class="mt-1 text-xl font-semibold text-gray-900">Certificados médicos emitidos</h2>
      </div>
      @foreach($certificados as $certificado)
        <article class="card p-5 bg-white">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <div class="text-xs uppercase tracking-widest text-gray-400">Certificado médico</div>
              <h3 class="text-lg font-semibold text-gray-900">{{ $certificado->codigo }}</h3>
              <p class="text-sm text-gray-600">Doctor: {{ $certificado->doctor->name }}</p>
              <p class="text-sm text-gray-600">Cita: {{ $certificado->cita->especialidad->nombre }}</p>
            </div>
            <span class="chip">
              {{ $certificado->fecha_emision->format('d/m/Y') }}
            </span>
          </div>
          <div class="mt-4 flex flex-wrap justify-end gap-2">
            <button class="btn btn-outline demo-action-blocked">Ver certificado</button>
            <button class="btn btn-primary demo-action-blocked">Descargar</button>
          </div>
        </article>
      @endforeach
    </section>

    <!-- Notas Médicas -->
    <section class="grid gap-4">
      <div>
        <p class="text-xs uppercase tracking-widest text-gray-500">Notas médicas</p>
        <h2 class="mt-1 text-xl font-semibold text-gray-900">Notas clínicas firmadas</h2>
      </div>
      @foreach($notas as $nota)
        <article class="card p-5 bg-white">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <div class="text-xs uppercase tracking-widest text-gray-400">Atención</div>
              <h3 class="text-lg font-semibold text-gray-900">{{ optional($nota->cita->especialidad)->nombre }}</h3>
              <p class="text-sm text-gray-600">Doctor: {{ optional($nota->cita->doctor)->name }}</p>
            </div>
            <span class="chip">
              {{ $nota->cita->fecha->format('d/m/Y') }}
            </span>
          </div>
          <div class="mt-4 flex justify-end">
            <a href="{{ route('demo.paciente.historial.show', $nota->id) }}" class="btn btn-outline">Ver nota clínica</a>
          </div>
        </article>
      @endforeach
    </section>
  </div>
@endsection
