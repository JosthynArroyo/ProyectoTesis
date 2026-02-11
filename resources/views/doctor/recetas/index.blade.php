@extends('layouts.doctor')
@section('title', 'Historial de Recetas')
@section('activeSidebar', 'recetas')
@section('header-title','Historial de recetas')
@section('header-subtitle','Descarga y consulta recetas')

@section('content')
<section class="space-y-6">
  <div class="card p-6">
    <div class="flex items-center gap-3">
      <i class="ri-file-list-3-line text-slate-400"></i>
      <div>
        <h1 class="text-2xl font-semibold text-slate-900">Historial de Recetas</h1>
        <p class="text-slate-600">Descarga y consulta las recetas generadas.</p>
      </div>
    </div>
  </div>

  <div class="card p-6">
    <div class="table-shell">
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
              <td>{{ optional($receta->cita->paciente)->name ?? '-' }}</td>
              <td>{{ optional($receta->cita->especialidad)->nombre ?? '-' }}</td>
              <td>{{ optional($receta->cita->fecha)->format('d/m/Y') }}</td>
              <td>{{ \Carbon\Carbon::parse($receta->cita->hora)->format('H:i') }}</td>
              <td>
                @if($receta->pdf_path)
                  <a class="btn btn-outline" href="{{ route('doctor.recetas.download', $receta->cita->id) }}">
                    <i class="ri-download-2-line"></i> Descargar
                  </a>
                @else
                  <span class="text-xs text-slate-500">Sin PDF</span>
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

  <div class="mt-4">
    {{ $recetas->links() }}
  </div>
</section>
@endsection
