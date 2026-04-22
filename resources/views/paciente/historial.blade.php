@extends('layouts.paciente')
@section('title', 'Historial clinico')
@section('header-title','Historial clinico')
@section('header-subtitle','Documentos individuales de tus atenciones')

@section('main')
  @php
    $certificados = $certificados ?? collect();
    $sinNotas = isset($notas) && method_exists($notas, 'count') ? $notas->count() === 0 : collect($notas ?? [])->isEmpty();
    $sinCertificados = $certificados->isEmpty();
  @endphp
  <div class="space-y-6">
    @if($sinNotas && $sinCertificados)
      <section class="card p-6">
        <x-ui.empty-state title="Sin documentos clinicos disponibles" message="Cuando tengas atenciones registradas, notas firmadas o certificados emitidos, apareceran aqui.">
          <div class="mt-4">
            <a href="{{ route('paciente.crear-cita') }}" class="btn btn-primary">Agendar una cita</a>
          </div>
        </x-ui.empty-state>
      </section>
    @endif

    @if(! $sinCertificados)
      <section class="grid gap-4">
        <div>
          <p class="text-xs uppercase tracking-widest text-slate-500">Certificados</p>
          <h2 class="mt-1 text-xl font-semibold text-slate-900">Certificados medicos emitidos</h2>
        </div>
        @foreach($certificados as $certificado)
          <article class="card p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <div class="text-xs uppercase tracking-widest text-slate-400">Certificado medico</div>
                <h3 class="text-lg font-semibold text-slate-900">{{ $certificado->codigo }}</h3>
                <p class="text-sm text-slate-600">Doctor: {{ optional($certificado->doctor ?? $certificado->cita?->doctor)->name ?? '-' }}</p>
                <p class="text-sm text-slate-600">Cita: {{ optional($certificado->cita?->especialidad)->nombre ?? 'Consulta' }}</p>
              </div>
              <span class="chip">
                {{ $certificado->fecha_emision ? $certificado->fecha_emision->format('d/m/Y') : '-' }}
              </span>
            </div>
            <div class="mt-4 flex flex-wrap justify-end gap-2">
              <a href="{{ route('paciente.certificados.show', $certificado) }}" class="btn btn-outline">Ver certificado</a>
              <a href="{{ route('paciente.certificados.download', $certificado) }}" class="btn btn-primary">Descargar</a>
            </div>
          </article>
        @endforeach
      </section>
    @endif

    @if(! $sinNotas)
      <section class="grid gap-4">
        <div>
          <p class="text-xs uppercase tracking-widest text-slate-500">Notas medicas</p>
          <h2 class="mt-1 text-xl font-semibold text-slate-900">Notas clinicas firmadas</h2>
        </div>
        @foreach($notas as $nota)
          <article class="card p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <div class="text-xs uppercase tracking-widest text-slate-400">Atencion</div>
                <h3 class="text-lg font-semibold text-slate-900">{{ optional($nota->cita->especialidad)->nombre ?? 'Consulta' }}</h3>
                <p class="text-sm text-slate-600">Doctor: {{ optional($nota->cita->doctor)->name ?? '-' }}</p>
              </div>
              <span class="chip">
                {{ $nota->cita->fecha ? \Carbon\Carbon::parse($nota->cita->fecha)->format('d/m/Y') : '-' }}
                {{ $nota->cita->hora ? \Carbon\Carbon::parse($nota->cita->hora)->format('H:i') : '' }}
              </span>
            </div>
            <div class="mt-4 flex justify-end">
              <a href="{{ route('paciente.historial.show', $nota->id) }}" class="btn btn-outline">Ver nota clinica</a>
            </div>
          </article>
        @endforeach
      </section>

      @if(method_exists($notas, 'links'))
        <div class="flex justify-center">
          {{ $notas->links() }}
        </div>
      @endif
    @endif
  </div>
@endsection
