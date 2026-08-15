@extends('layouts.demo')
@section('title', 'Historial de Recetas - Demo')
@section('activeSidebar', 'recetas')
@section('header-title','Historial de recetas')
@section('header-subtitle','Descarga y consulta recetas (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-doctor-demo')
@endsection

@php
  $recetas = collect($prescriptions)->map(function($p) {
      $receta = new \App\Models\Receta();
      $receta->id = 1;
      $receta->pdf_path = 'receta.pdf';
      
      $cita = new \App\Models\Cita();
      $cita->id = 1;
      $cita->fecha = \Carbon\Carbon::parse(str_replace('/', '-', $p['date']));
      $cita->hora = $p['time'];
      
      $pUser = new \App\Models\User(['name' => $p['patient']]);
      $cita->setRelation('paciente', $pUser);
      
      $esp = new \App\Models\Especialidad(['nombre' => $p['specialty']]);
      $cita->setRelation('especialidad', $esp);
      
      $receta->setRelation('cita', $cita);
      return $receta;
  });
@endphp

@section('main')
<section class="space-y-6">
  <div class="card p-6">
    <div class="table-shell table-responsive-cards">
      <table class="table">
        <thead>
          <tr>
            <th>Paciente</th>
            <th>Especialidad</th>
            <th>Fecha de cita</th>
            <th>Hora</th>
            <th>PDF</th>
          </tr>
        </thead>
        <tbody>
          @forelse($recetas as $receta)
            <tr>
              <td data-label="Paciente">{{ optional($receta->cita->paciente)->name ?? '-' }}</td>
              <td data-label="Especialidad">{{ optional($receta->cita->especialidad)->nombre ?? '-' }}</td>
              <td data-label="Fecha de cita">{{ optional($receta->cita->fecha)->format('d/m/Y') }}</td>
              <td data-label="Hora">{{ \Carbon\Carbon::parse($receta->cita->hora)->format('H:i') }}</td>
              <td data-label="PDF">
                @if($receta->pdf_path)
                  <button class="btn btn-outline demo-action-blocked">
                    <i class="ri-download-2-line"></i> Descargar
                  </button>
                @else
                  <span class="text-xs text-gray-500">Sin PDF</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="5">Aún no hay recetas.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</section>
@endsection
