@extends('layouts.admin')
@section('title', 'Historial del paciente')
@section('header-title','Historial del paciente')
@section('header-subtitle','Notas SOAP firmadas')

@section('main')
  <div class="space-y-6">
    <section class="card p-6">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p class="text-xs uppercase tracking-widest text-slate-500">Paciente</p>
          <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $paciente->name }}</h1>
          <p class="text-slate-600">Historial clínico firmado del paciente.</p>
        </div>
      </div>
    </section>

    @if(collect($notas ?? [])->isEmpty())
      <section class="card p-6">
        <x-ui.empty-state title="Sin notas registradas" message="No hay notas SOAP firmadas para este paciente."></x-ui.empty-state>
      </section>
    @else
      <section class="grid gap-4">
        @foreach($notas as $nota)
          <article class="card p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <div class="text-xs uppercase tracking-widest text-slate-400">Consulta</div>
                <h3 class="text-lg font-semibold text-slate-900">
                  {{ optional($nota->cita->especialidad)->nombre ?? 'Consulta' }}
                </h3>
                <p class="text-sm text-slate-600">
                  Doctor: {{ optional($nota->cita->doctor)->name ?? 'Doctor/a' }}
                </p>
              </div>
              <div class="text-right">
                <div class="text-sm text-slate-600">
                  {{ $nota->cita->fecha ? \Carbon\Carbon::parse($nota->cita->fecha)->format('d/m/Y') : '-' }}
                  {{ $nota->cita->hora ? \Carbon\Carbon::parse($nota->cita->hora)->format('H:i') : '' }}
                </div>
                <a href="{{ route('admin.historial.show', $nota->id) }}" class="btn btn-outline mt-2">
                  <i class="ri-file-list-2-line"></i> Ver nota
                </a>
              </div>
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

    <section class="card p-6">
      <x-ui.form-actions>
        <x-slot:left>
          <a href="{{ route('admin.historial.index') }}" class="btn btn-ghost">Volver</a>
        </x-slot>
      </x-ui.form-actions>
    </section>
  </div>
@endsection
