@extends('layouts.admin')
@section('title', 'Historiales clínicos')
@section('header-title','Historiales clínicos')
@section('header-subtitle','Acceso administrativo al expediente longitudinal por paciente')

@section('main')
  @php
    $hasNotas = method_exists($notas, 'count')
        ? $notas->count() > 0
        : collect($notas ?? [])->isNotEmpty();
  @endphp

  <div class="space-y-6">
    <section class="card p-6">
      <form class="flex flex-wrap items-end gap-3" method="GET" action="{{ route('admin.historial.index') }}">
        <div class="inline-control-shell flex-1">
          <i class="ri-search-line text-gray-400"></i>
          <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Buscar por paciente, cedula o correo..." class="w-full bg-transparent text-sm text-gray-700" required>
        </div>
        <button class="btn btn-outline" type="submit">Filtrar</button>
      </form>
    </section>

    @if(! $hasNotas)
      <section class="card p-6">
        <x-ui.empty-state title="No hay historiales clinicos disponibles" message="No existen historiales clinicos firmados para mostrar con los filtros actuales."></x-ui.empty-state>
      </section>
    @else
      <section class="card p-6">
        <div class="table-shell table-responsive-cards">
          <table class="table w-full">
            <thead>
              <tr>
                <th>Paciente</th>
                <th>Doctor</th>
                <th>Especialidad</th>
                <th>Fecha</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              @foreach($notas as $nota)
                <tr>
                  <td data-label="Paciente">{{ optional($nota->cita->paciente)->name ?? 'Paciente' }}</td>
                  <td data-label="Doctor">{{ optional($nota->cita->doctor)->name ?? 'Doctor/a' }}</td>
                  <td data-label="Especialidad">{{ optional($nota->cita->especialidad)->nombre ?? '-' }}</td>
                  <td data-label="Fecha">{{ $nota->cita->fecha ? \Carbon\Carbon::parse($nota->cita->fecha)->format('d/m/Y') : '-' }} {{ $nota->cita->hora ? \Carbon\Carbon::parse($nota->cita->hora)->format('H:i') : '' }}</td>
                  <td class="text-right" data-label="Acciones">
                    <a href="{{ route('admin.historial.show', $nota->id) }}" class="btn btn-outline">Ver historial</a>
                    @if($nota->cita->paciente_id)
                      <a href="{{ route('admin.historial.paciente', $nota->cita->paciente_id) }}" class="btn btn-ghost">Notas firmadas</a>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </section>

      @if(method_exists($notas, 'links'))
        <div class="flex justify-center">
          {{ $notas->links() }}
        </div>
      @endif
    @endif
  </div>
@endsection
