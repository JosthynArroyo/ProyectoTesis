@extends('layouts.admin')
@section('title', 'Notas del paciente')
@section('header-title', $paciente->name)
@section('header-subtitle','Notas clínicas firmadas por consulta')

@section('main')
  @php
    $hasNotas = method_exists($notas, 'count')
        ? $notas->count() > 0
        : collect($notas ?? [])->isNotEmpty();
  @endphp

  <div class="space-y-6">
    @if(! $hasNotas)
      <section class="card p-6">
        <x-ui.empty-state title="No hay historiales clinicos disponibles" message="No existen notas clinicas firmadas para este paciente."></x-ui.empty-state>
      </section>
    @else
      <section class="grid gap-4">
        @foreach($notas as $nota)
          <article class="card p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <div class="text-xs uppercase tracking-widest text-gray-400">Consulta</div>
                <h3 class="text-lg font-semibold text-gray-900">{{ optional($nota->cita->especialidad)->nombre ?? 'Consulta' }}</h3>
                <p class="text-sm text-gray-600">Doctor: {{ optional($nota->cita->doctor)->name ?? 'Doctor/a' }}</p>
              </div>
              <div class="text-right">
                <div class="text-sm text-gray-600">{{ $nota->cita->fecha ? \Carbon\Carbon::parse($nota->cita->fecha)->format('d/m/Y') : '-' }} {{ $nota->cita->hora ? \Carbon\Carbon::parse($nota->cita->hora)->format('H:i') : '' }}</div>
                <a href="{{ route('admin.historial.nota', $nota->id) }}" class="btn btn-outline mt-2">Ver nota</a>
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
