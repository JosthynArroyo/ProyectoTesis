@extends('layouts.demo')
@section('title', 'Mis citas - Demo')
@section('activeSidebar', 'citas')
@section('header-title','Mis citas')
@section('header-subtitle','Gestiona tus citas activas (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-doctor-demo')
@endsection

@php
  $estadoFiltro = request('estado', '');
  $prioridadFiltro = request('prioridad', '');

  $citasCollection = collect($citasHoy)->map(function($c, $index) {
      $cita = new \App\Models\Cita();
      $cita->id = $c['id'];
      $cita->estado = match (strtolower($c['status'])) {
          'en sala de espera' => 'confirmada',
          'pendiente' => 'pendiente',
          'realizada' => 'realizada',
          'confirmada' => 'confirmada',
          default => 'pendiente',
      };
      
      $cita->prioridad_nivel = strtolower($c['priority_label']) === 'media' ? 'MEDIA' : (strtolower($c['priority_label']) === 'alta' ? 'ALTA' : 'BAJA');
      $cita->prioridad_red_flag = strtolower($c['priority_label']) === 'alta';
      $cita->fecha = now()->toDateString();
      $cita->hora = $c['time'];
      $cita->paciente_id = 10;
      $cita->dependiente_id = str_contains($c['patient'], 'Lucia') ? 1 : null;
      
      $p = new \App\Models\User(['name' => $c['patient'], 'telefono' => '0995140927', 'dni' => '1723456789', 'email' => 'maria.fernanda@example.com']);
      $cita->setRelation('paciente', $p);
      
      $esp = new \App\Models\Especialidad(['nombre' => 'Pediatría']);
      $cita->setRelation('especialidad', $esp);
      
      return $cita;
  });
@endphp

@section('main')
<section class="appointments-page space-y-6">
  <div class="panel-action-bar">
    <button class="btn btn-outline btn-sm" type="button" onclick="window.location.reload();">
      <i class="ri-refresh-line"></i> Actualizar
    </button>
  </div>

  <div class="card p-6 !overflow-visible">
    <div class="flex items-center gap-2">
      <i class="ri-calendar-line text-gray-400"></i>
      <h2 class="text-lg font-semibold text-gray-900">Listado</h2>
    </div>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
      <form class="flex flex-wrap items-end gap-3" method="GET" action="{{ route('demo.doctor.citas') }}">
        <div>
          <label for="estado" class="form-label">Estado</label>
          <select id="estado" name="estado" class="form-select">
            <option value="all" @selected($estadoFiltro==='' || $estadoFiltro==='all')>Todas</option>
            <option value="pendiente" {{ $estadoFiltro === 'pendiente' ? 'selected' : '' }}>Pendientes</option>
            <option value="confirmada" {{ $estadoFiltro === 'confirmada' ? 'selected' : '' }}>Confirmadas</option>
            <option value="cancelada" {{ $estadoFiltro === 'cancelada' ? 'selected' : '' }}>Canceladas</option>
            <option value="realizada" {{ $estadoFiltro === 'realizada' ? 'selected' : '' }}>Realizadas</option>
          </select>
        </div>
        <div>
          <label for="prioridad" class="form-label">Prioridad</label>
          <select id="prioridad" name="prioridad" class="form-select">
            <option value="all" @selected($prioridadFiltro==='' || $prioridadFiltro==='all')>Todas</option>
            <option value="ALTA" @selected($prioridadFiltro === 'ALTA')>ALTA</option>
            <option value="MEDIA" @selected($prioridadFiltro === 'MEDIA')>MEDIA</option>
            <option value="BAJA" @selected($prioridadFiltro === 'BAJA')>BAJA</option>
          </select>
        </div>
        <button class="btn btn-primary btn-sm" type="submit">
          <i class="ri-filter-3-line"></i> Filtrar
        </button>
        @if($estadoFiltro !== '' || $prioridadFiltro !== '')
          <a class="btn btn-ghost btn-sm" href="{{ route('demo.doctor.citas') }}">
            <i class="ri-refresh-line"></i> Limpiar
          </a>
        @endif
      </form>
      <div class="flex flex-wrap gap-2">
        <button class="btn btn-outline btn-sm btn-full-mobile demo-action-blocked">
          <i class="ri-file-excel-2-line"></i> Exportar Excel
        </button>
        <button class="btn btn-outline btn-sm btn-full-mobile demo-action-blocked">
          <i class="ri-file-pdf-line"></i> Exportar PDF
        </button>
      </div>
    </div>

    <div class="mt-4 table-shell table-shell--overflow-visible table-responsive-cards !overflow-visible">
      <table class="table appointments-table" id="tabla-citas">
        <thead>
          <tr>
            <th class="col-idx">#</th>
            <th>Paciente</th>
            <th>Especialidad</th>
            <th>Fecha</th>
            <th>Hora</th>
            <th>Estado</th>
            <th>Prioridad</th>
            <th class="text-right">Acciones</th>
          </tr>
        </thead>
        <tbody>
          @foreach($citasCollection as $loopIndex => $cita)
            @php
              $estado = $cita->estado;
              $badge = match($estado) {
                'pendiente' => 'warning',
                'confirmada' => 'info',
                'cancelada' => 'danger',
                'realizada' => 'success',
                'no_se_presento' => 'danger',
                default => 'neutral',
              };
              $priorityLevel = $cita->prioridad_nivel ?? 'BAJA';
              $priorityTone = match($priorityLevel) {
                'ALTA' => 'danger',
                'MEDIA' => 'warning',
                default => 'neutral',
              };
              $accionesMenuId = 'cita-actions-'.$cita->id;
            @endphp
            <tr class="{{ $estado === 'pendiente' && $priorityLevel === 'ALTA' ? 'priority-row priority-row--alta' : '' }}">
              <td data-label="#" class="col-idx">{{ $loopIndex + 1 }}</td>
              <td data-label="Paciente">{{ $cita->nombrePacienteReal() }}</td>
              <td data-label="Especialidad">{{ optional($cita->especialidad)->nombre ?? '—' }}</td>
              <td data-label="Fecha">{{ \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') }}</td>
              <td data-label="Hora">{{ $cita->hora }}</td>
              <td data-label="Estado">
                <x-ui.badge :tone="$badge">
                  {{ $estado === 'no_se_presento' ? 'No se presentó' : ucfirst($estado) }}
                </x-ui.badge>
              </td>
              <td data-label="Prioridad">
                <x-ui.badge :tone="$priorityTone">{{ $priorityLevel }}</x-ui.badge>
                @if($cita->prioridad_red_flag)
                  <span class="badge danger">Red flag</span>
                @endif
              </td>

              <td class="cell-actions" data-label="Acciones">
                <div class="table-actions">
                  <div class="relative">
                    <button
                      type="button"
                      class="btn btn-outline btn-sm"
                      data-kebab="{{ $accionesMenuId }}"
                      aria-label="Más acciones para la cita {{ $cita->id }}"
                    >
                      <i class="ri-more-2-fill"></i>
                    </button>
                    <div id="{{ $accionesMenuId }}" class="kebab-menu" role="menu">
                      <a class="btn btn-ghost btn-sm justify-start" href="{{ route('demo.doctor.citas.prioridad.edit', $cita->id) }}" role="menuitem">
                        <i class="ri-flag-2-line"></i> Ajustar prioridad
                      </a>

                      @if($estado === 'pendiente')
                        <form action="{{ route('demo.doctor.citas.aceptar',$cita->id) }}" method="POST">
                          @csrf
                          <button class="btn btn-ghost btn-sm justify-start" type="submit" role="menuitem">
                            <i class="ri-check-line"></i> Aceptar cita
                          </button>
                        </form>
                        <form action="{{ route('demo.doctor.citas.rechazar',$cita->id) }}" method="POST">
                          @csrf
                          <button class="btn btn-ghost btn-sm justify-start" type="submit" role="menuitem">
                            <i class="ri-close-line"></i> Rechazar cita
                          </button>
                        </form>
                      @endif

                      @if($estado === 'confirmada')
                        <a class="btn btn-ghost btn-sm justify-start" href="{{ route('demo.doctor.citas.soap',$cita->id) }}" role="menuitem">
                          <i class="ri-stethoscope-line"></i> Registrar nota clínica
                        </a>
                        <a class="btn btn-ghost btn-sm justify-start" href="{{ route('demo.doctor.pacientes.historial', $cita->paciente_id).($cita->dependiente_id ? '?dependiente_id='.$cita->dependiente_id : '') }}" role="menuitem">
                          <i class="ri-file-list-2-line"></i> Ver expediente del paciente
                        </a>
                        <form action="{{ route('demo.doctor.citas.realizar',$cita->id) }}" method="POST">
                          @csrf
                          <button class="btn btn-ghost btn-sm justify-start" type="submit" role="menuitem">
                            <i class="ri-checkbox-circle-line"></i> Marcar como realizada
                          </button>
                        </form>
                      @endif

                      @if($estado === 'realizada')
                        <a class="btn btn-ghost btn-sm justify-start" href="{{ route('demo.doctor.citas.soap',$cita->id) }}" role="menuitem">
                          <i class="ri-stethoscope-line"></i> Ver nota clínica
                        </a>
                        <a class="btn btn-ghost btn-sm justify-start" href="{{ route('demo.doctor.pacientes.historial', $cita->paciente_id).($cita->dependiente_id ? '?dependiente_id='.$cita->dependiente_id : '') }}" role="menuitem">
                          <i class="ri-file-list-2-line"></i> Ver expediente del paciente
                        </a>
                      @endif
                    </div>
                  </div>
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Dynamic kebab toggling
    document.querySelectorAll('[data-kebab]').forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            const targetId = this.getAttribute('data-kebab');
            const menu = document.getElementById(targetId);
            document.querySelectorAll('.kebab-menu').forEach(m => {
                if (m !== menu) m.classList.remove('is-active');
            });
            menu.classList.toggle('is-active');
        });
    });

    document.addEventListener('click', function() {
        document.querySelectorAll('.kebab-menu').forEach(m => m.classList.remove('is-active'));
    });
});
</script>
@endsection
