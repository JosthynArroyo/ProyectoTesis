@extends('layouts.doctor')
@section('title', 'Mis citas (doctor)')
@section('activeSidebar', 'citas')
@section('header-title','Mis citas')
@section('header-subtitle','Gestiona tus citas activas')

@section('main')
@php
  $estadoFiltro = $estado ?? '';
  $prioridadFiltro = $prioridad ?? '';
@endphp
<section class="appointments-page space-y-6"
         data-csrf="{{ csrf_token() }}"
         data-check-url="{{ route('doctor.disponibilidad.check') }}"
         data-plan-url="{{ route('doctor.citas.proxima.planificada',['cita'=>'__ID__']) }}"
         data-slots-url="{{ route('api.doctor.slots',['doctor'=>'__D__','fecha'=>'__F__']) }}"
         data-login-url="{{ url('/') . '?login=1' }}">
  <div class="panel-action-bar">
    <button id="btn-refresh" class="btn btn-outline btn-sm" type="button">
      <i class="ri-refresh-line"></i> Actualizar
    </button>
  </div>

  <div class="card p-6 !overflow-visible">
    <div class="flex items-center gap-2">
      <i class="ri-calendar-line text-gray-400"></i>
      <h2 class="text-lg font-semibold text-gray-900">Listado</h2>
    </div>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
      <form class="flex flex-wrap items-end gap-3" method="GET" action="{{ route('doctor.citas') }}">
        <div>
          <label for="estado" class="form-label">Estado</label>
          <select id="estado" name="estado" class="form-select">
            <option value="all" @selected($estadoFiltro==='' || $estadoFiltro==='all')>Todas</option>
            <option value="pendiente" {{ $estadoFiltro === 'pendiente' ? 'selected' : '' }}>Pendientes</option>
            <option value="confirmada" {{ $estadoFiltro === 'confirmada' ? 'selected' : '' }}>Confirmadas</option>
            <option value="cancelada" {{ $estadoFiltro === 'cancelada' ? 'selected' : '' }}>Canceladas</option>
            <option value="realizada" {{ $estadoFiltro === 'realizada' ? 'selected' : '' }}>Realizadas</option>
            <option value="no_se_presento" {{ $estadoFiltro === 'no_se_presento' ? 'selected' : '' }}>No se presentó</option>
          </select>
          @error('estado')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
        </div>
        <div>
          <label for="prioridad" class="form-label">Prioridad</label>
          <select id="prioridad" name="prioridad" class="form-select">
            <option value="all" @selected($prioridadFiltro==='' || $prioridadFiltro==='all')>Todas</option>
            <option value="ALTA" @selected($prioridadFiltro === 'ALTA')>ALTA</option>
            <option value="MEDIA" @selected($prioridadFiltro === 'MEDIA')>MEDIA</option>
            <option value="BAJA" @selected($prioridadFiltro === 'BAJA')>BAJA</option>
          </select>
          @error('prioridad')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
        </div>
        <button class="btn btn-primary btn-sm" type="submit">
          <i class="ri-filter-3-line"></i> Filtrar
        </button>
        @if($estadoFiltro !== '' || $prioridadFiltro !== '')
          <a class="btn btn-ghost btn-sm" href="{{ route('doctor.citas') }}">
            <i class="ri-refresh-line"></i> Limpiar
          </a>
        @endif
      </form>
      <div class="flex flex-wrap gap-2">
        <a class="btn btn-outline btn-sm btn-full-mobile" href="{{ route('doctor.citas.export.excel', request()->query()) }}">
          <i class="ri-file-excel-2-line"></i> Exportar Excel
        </a>
        <a class="btn btn-outline btn-sm btn-full-mobile" href="{{ route('doctor.citas.export.pdf', request()->query()) }}">
          <i class="ri-file-pdf-line"></i> Exportar PDF
        </a>
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
          @foreach($citas as $loopIndex => $cita)
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
              $soapEstado = $cita->notaSoap?->estado ?? null;
              $priorityLevel = $cita->prioridad_nivel ?? 'BAJA';
              $priorityTone = match($priorityLevel) {
                'ALTA' => 'danger',
                'MEDIA' => 'warning',
                default => 'neutral',
              };
              $prioridadUrl = route('doctor.citas.prioridad.edit', $cita).'?redirect_to='.urlencode(request()->fullUrl());
              $accionesMenuId = 'cita-actions-'.$cita->id;
            @endphp
            <tr class="{{ $estado === 'pendiente' && $priorityLevel === 'ALTA' ? 'priority-row priority-row--alta' : '' }}">
              <td data-label="#" class="col-idx">{{ $loop->iteration }}</td>
              <td data-label="Paciente">{{ $cita->nombrePacienteReal() }}</td>
              <td data-label="Especialidad">{{ optional($cita->especialidad)->nombre ?? '—' }}</td>
              <td data-label="Fecha">{{ \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') }}</td>
              <td data-label="Hora">{{ \Carbon\Carbon::parse($cita->hora)->format('H:i') }}</td>
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
                      <a class="btn btn-ghost btn-sm justify-start" href="{{ $prioridadUrl }}" role="menuitem">
                        <i class="ri-flag-2-line"></i> Ajustar prioridad
                      </a>

                      @if($estado === 'pendiente')
                        <form action="{{ route('doctor.citas.aceptar',$cita->id) }}" method="POST" role="none">
                          @csrf
                          <button class="btn btn-ghost btn-sm justify-start" type="submit" role="menuitem">
                            <i class="ri-check-line"></i> Aceptar cita
                          </button>
                        </form>
                        <form action="{{ route('doctor.citas.rechazar',$cita->id) }}" method="POST" data-confirm-title="Rechazar cita" data-confirm-message="¿Estás seguro de que deseas rechazar esta cita médica?" data-confirm-action="rechazar" data-confirm-btn="Sí, rechazar" role="none">
                          @csrf
                          <button class="btn btn-ghost btn-sm justify-start" type="submit" role="menuitem">
                            <i class="ri-close-line"></i> Rechazar cita
                          </button>
                        </form>
                        <a class="btn btn-ghost btn-sm justify-start" href="{{ route('paciente.editar-cita',$cita->id) }}" role="menuitem">
                          <i class="ri-calendar-line"></i> Reagendar
                        </a>
                      @endif

                      @if($estado === 'confirmada')
                        <a class="btn btn-ghost btn-sm justify-start" href="{{ route('doctor.citas.soap',$cita->id) }}" role="menuitem">
                          <i class="ri-stethoscope-line"></i> Registrar nota clinica
                        </a>
                        <a class="btn btn-ghost btn-sm justify-start" href="{{ route('doctor.pacientes.historial', $cita->paciente_id).($cita->dependiente_id ? '?dependiente_id='.$cita->dependiente_id : '') }}" role="menuitem">
                          <i class="ri-file-list-2-line"></i> Ver expediente del paciente
                        </a>
                        @if($soapEstado === 'signed')
                          <form action="{{ route('doctor.citas.realizar',$cita->id) }}" method="POST" role="none">
                            @csrf
                            <button class="btn btn-ghost btn-sm justify-start" type="submit" role="menuitem">
                              <i class="ri-checkbox-circle-line"></i> Marcar como realizada
                            </button>
                          </form>
                        @else
                          <span class="kebab-menu__hint">
                            <i class="ri-alert-line"></i> Nota clinica pendiente de firma
                          </span>
                        @endif
                      @endif

                      @if($estado === 'realizada')
                        @php
                          $px = $cita->proxima_cita ?? null;
                          $hayProxima = $px
                                        && ($px->activo ?? false)
                                        && !in_array($px->estado, ['cancelada', 'realizada', 'no_se_presento'], true)
                                        && \Carbon\Carbon::parse($px->fecha)->gte(\Carbon\Carbon::now('America/Guayaquil')->startOfDay());
                        @endphp

                        <a class="btn btn-ghost btn-sm justify-start" href="{{ route('doctor.citas.soap',$cita->id) }}" role="menuitem">
                          <i class="ri-stethoscope-line"></i> Ver nota clinica
                        </a>
                        <a class="btn btn-ghost btn-sm justify-start" href="{{ route('doctor.pacientes.historial', $cita->paciente_id).($cita->dependiente_id ? '?dependiente_id='.$cita->dependiente_id : '') }}" role="menuitem">
                          <i class="ri-file-list-2-line"></i> Ver expediente del paciente
                        </a>

                        @if($hayProxima)
                          <span class="kebab-menu__hint">
                            <i class="ri-calendar-event-line"></i>
                            Proxima cita: {{ \Carbon\Carbon::parse($px->fecha)->format('d/m/Y') }}
                            {{ \Carbon\Carbon::parse($px->hora)->format('H:i') }}
                            ({{ ucfirst($px->estado) }})
                          </span>
                        @else
                          <button
                            type="button"
                            class="btn btn-ghost btn-sm justify-start"
                            data-accion="planificador"
                            data-cita="{{ $cita->id }}"
                            data-doctor="{{ $cita->doctor_id }}"
                            role="menuitem"
                          >
                            <i class="ri-calendar-check-line"></i> Agendar proxima cita
                          </button>
                        @endif

                        @if($cita->receta)
                          @if($cita->receta->can_edit)
                            <a class="btn btn-ghost btn-sm justify-start" href="{{ route('doctor.recetas.edit',$cita->id) }}" role="menuitem">
                              <i class="ri-edit-line"></i> Editar receta
                            </a>
                          @else
                            <span class="kebab-menu__hint">
                              <i class="ri-lock-line"></i> Edicion de receta expirada
                            </span>
                          @endif
                        @else
                          <a class="btn btn-ghost btn-sm justify-start" href="{{ route('doctor.recetas.create',$cita->id) }}" role="menuitem">
                            <i class="ri-medicine-bottle-line"></i> Generar receta
                          </a>
                        @endif

                        @if($cita->certificadoMedico)
                          <a class="btn btn-ghost btn-sm justify-start" href="{{ route('doctor.certificados.show', $cita->certificadoMedico) }}" role="menuitem" data-action-lock-ignore data-skip-page-loader>
                            <i class="ri-file-shield-2-line"></i> Ver certificado medico
                          </a>
                          <a class="btn btn-ghost btn-sm justify-start" href="{{ route('doctor.certificados.corregir', $cita->certificadoMedico) }}" role="menuitem">
                            <i class="ri-edit-line"></i> Corregir certificado
                          </a>
                        @else
                          <a class="btn btn-ghost btn-sm justify-start" href="{{ route('doctor.certificados.create', $cita) }}" role="menuitem">
                            <i class="ri-file-shield-2-line"></i> Emitir certificado medico
                          </a>
                        @endif

                        <a class="btn btn-ghost btn-sm justify-start" href="{{ route('doctor.pedidos-laboratorio.create', $cita) }}" role="menuitem">
                          <i class="ri-flask-line"></i> Pedido laboratorio
                        </a>

                        @if($cita->pedidoLaboratorio)
                          <a class="btn btn-ghost btn-sm justify-start" href="{{ route('doctor.pedidos-laboratorio.download', $cita->pedidoLaboratorio) }}" role="menuitem" download data-action-lock-ignore data-skip-page-loader>
                            <i class="ri-file-shield-line"></i> Descargar Pedido Lab MVP
                          </a>
                        @endif
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

    @if(method_exists($citas, 'hasPages') && $citas->hasPages())
      <div class="mt-4">
        {{ $citas->links() }}
      </div>
    @endif
  </div>
</section>

<div class="toast" id="toast-planificador" aria-live="polite">
  <div class="toast-h">
    <strong>Planificar próxima cita</strong>
    <button type="button" class="btn btn-ghost px-2" id="toast-close"><i class="ri-close-line"></i></button>
  </div>
  <div class="toast-b">
    <div id="toast-warn" class="toast-warn is-hidden">
      No tienes horarios configurados esta semana.
      <div class="toast-warn-actions">
        <a href="{{ route('doctor.horario.index') }}" class="btn btn-outline">Ir a "Mi horario"</a>
      </div>
    </div>

    <div class="field">
      <label for="tp-fecha">Fecha</label>
      <input id="tp-fecha" class="form-input" type="date" min="{{ \Carbon\Carbon::now('America/Guayaquil')->toDateString() }}">
    </div>

    <div class="field">
      <label>Horas disponibles</label>
      <div id="tp-slots" class="slots flex flex-wrap gap-2"></div>
      <small id="tp-help" class="text-xs text-gray-500"></small>
    </div>
  </div>
  <div class="toast-f">
    <button type="button" class="btn btn-ghost" id="tp-cancelar">Cancelar</button>
    <button type="button" class="btn btn-primary" id="tp-crear" disabled>
      <i class="ri-save-line"></i> Crear cita
    </button>
  </div>
</div>

@push('scripts')
  @vite('resources/js/doctor/citas.js')
@endpush
@endsection
