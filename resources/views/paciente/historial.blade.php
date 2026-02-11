@extends('layouts.paciente')
@section('title', 'Historial')
@section('header-title','Historial clínico')
@section('header-subtitle','Resumen de tus atenciones')

@section('main')
  <div class="space-y-6">
    <section class="card p-6">
      <p class="text-xs uppercase tracking-widest text-slate-500">Historial</p>
      <h1 class="mt-2 text-2xl font-semibold text-slate-900">Historial clínico</h1>
      <p class="text-slate-600">Consulta tus notas SOAP firmadas por fecha.</p>
    </section>

    @if(collect($notas ?? [])->isEmpty())
      <section class="card p-6">
        <x-ui.empty-state
          title="Sin notas clínicas disponibles"
        message="Cuando tengas atenciones registradas y firmadas, aparecerán aquí."
        >
          <div class="mt-4">
            <a href="{{ route('paciente.crear-cita') }}" class="btn btn-primary">Agendar una cita</a>
          </div>
        </x-ui.empty-state>
      </section>
    @else
      <section class="grid gap-4">
        @foreach($notas as $nota)
          <article class="card p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <div class="text-xs uppercase tracking-widest text-slate-400">Atención</div>
                <h3 class="text-lg font-semibold text-slate-900">
                  {{ optional($nota->cita->especialidad)->nombre ?? 'Consulta' }}
                </h3>
                <p class="text-sm text-slate-600">
                  Doctor: {{ optional($nota->cita->doctor)->name ?? '-' }}
                </p>
              </div>
              <span class="chip">
                {{ $nota->cita->fecha ? \Carbon\Carbon::parse($nota->cita->fecha)->format('d/m/Y') : '-' }}
                {{ $nota->cita->hora ? \Carbon\Carbon::parse($nota->cita->hora)->format('H:i') : '' }}
              </span>
            </div>
            <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
              <a href="{{ route('paciente.historial.show', $nota->id) }}" class="btn btn-outline">
                <i class="ri-file-list-2-line"></i> Ver nota SOAP
              </a>
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
