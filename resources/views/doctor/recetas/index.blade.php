@extends('layouts.doctor')
@section('title', 'Historial de Recetas')
@section('activeSidebar', 'recetas')
@section('header-title','Historial de recetas')
@section('header-subtitle','Descarga y consulta recetas')

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
              <td data-label="Paciente">{{ $receta->cita ? $receta->cita->nombrePacienteReal() : '-' }}</td>
              <td data-label="Especialidad">{{ optional($receta->cita->especialidad)->nombre ?? '-' }}</td>
              <td data-label="Fecha de cita">{{ optional($receta->cita->fecha)->format('d/m/Y') }}</td>
              <td data-label="Hora">{{ \Carbon\Carbon::parse($receta->cita->hora)->format('H:i') }}</td>
              <td data-label="PDF">
                @if($receta->pdf_path)
                  <a class="btn btn-outline" href="{{ route('doctor.recetas.download', $receta->cita->id) }}" download data-action-lock-ignore data-skip-page-loader>
                    <i class="ri-download-2-line"></i> Descargar
                  </a>
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

  <div class="mt-4">
    {{ $recetas->links() }}
  </div>
</section>
@endsection
