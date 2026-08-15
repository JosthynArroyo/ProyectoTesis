@extends('layouts.demo')
@section('title', 'Historiales clínicos - Demo')
@section('header-title','Historiales clínicos')
@section('header-subtitle','Acceso administrativo al expediente longitudinal por paciente (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-admin-demo')
@endsection

@php
  $notas = collect($historialEntries)->map(function($entry, $index) {
      $nota = new \App\Models\NotaSoap();
      $nota->id = $index + 1;
      
      $cita = new \App\Models\Cita();
      $cita->id = $index + 10;
      $cita->fecha = \Carbon\Carbon::parse(str_replace('/', '-', $entry['date']));
      $cita->hora = '08:00';
      $cita->paciente_id = 10;
      
      if ($entry['patient'] === 'Lucia Vega') {
          $cita->dependiente_id = 1;
          $dep = new \App\Models\Dependiente(['nombre' => 'Lucia Vega', 'dni' => '1756789012']);
          $cita->setRelation('dependiente', $dep);
      }
      
      $pUser = new \App\Models\User(['name' => $entry['patient'], 'dni' => '1723456789']);
      $cita->setRelation('paciente', $pUser);
      
      $dUser = new \App\Models\User(['name' => $entry['doctor']]);
      $cita->setRelation('doctor', $dUser);
      
      $esp = new \App\Models\Especialidad(['nombre' => $entry['specialty']]);
      $cita->setRelation('especialidad', $esp);
      
      $nota->setRelation('cita', $cita);
      return $nota;
  });
  
  $hasNotas = $notas->isNotEmpty();
@endphp

@section('main')
  <div class="space-y-6">
    <section class="card p-6">
      <form class="flex flex-wrap items-end gap-3" method="GET" action="{{ route('demo.admin.historial.index') }}">
        <div class="inline-control-shell flex-1">
          <i class="ri-search-line text-gray-400"></i>
          <input type="text" name="q" value="{{ request('q', '') }}" placeholder="Buscar por paciente, cedula o correo..." class="w-full bg-transparent text-sm text-gray-700">
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
                  <td data-label="Paciente">{{ $nota->cita->nombrePacienteReal() }}</td>
                  <td data-label="Doctor">{{ optional($nota->cita->doctor)->name ?? 'Doctor/a' }}</td>
                  <td data-label="Especialidad">{{ optional($nota->cita->especialidad)->nombre ?? '-' }}</td>
                  <td data-label="Fecha">{{ $nota->cita->fecha ? \Carbon\Carbon::parse($nota->cita->fecha)->format('d/m/Y') : '-' }} {{ $nota->cita->hora ? \Carbon\Carbon::parse($nota->cita->hora)->format('H:i') : '' }}</td>
                  <td class="text-right" data-label="Acciones">
                    <a href="{{ route('demo.admin.historial.show', $nota->id) }}" class="btn btn-outline">Ver historial</a>
                    @if($nota->cita->paciente_id)
                      <a href="{{ route('demo.admin.historial.paciente', $nota->cita->paciente_id) }}{{ $nota->cita->dependiente_id ? '?dependiente_id=' . $nota->cita->dependiente_id : '' }}" class="btn btn-ghost">Notas firmadas</a>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </section>
    @endif
  </div>
@endsection
