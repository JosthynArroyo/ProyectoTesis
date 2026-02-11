@extends('layouts.doctor')
@section('title', 'Mis citas (doctor)')
@section('activeSidebar', 'citas')
@section('header-title','Mis citas')
@section('header-subtitle','Gestiona tus citas activas')

@section('content')
@php
  $estadoFiltro = $estado ?? '';
@endphp
<section class="appointments-page space-y-6"
         data-csrf="{{ csrf_token() }}"
         data-check-url="{{ route('doctor.disponibilidad.check') }}"
         data-plan-url="{{ route('doctor.citas.proxima.planificada',['cita'=>'__ID__']) }}"
         data-slots-url="{{ route('api.doctor.slots',['doctor'=>'__D__','fecha'=>'__F__']) }}"
         data-login-url="{{ route('login') }}">
  <header class="card p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Panel médico</p>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Mis citas</h1>
        <p class="text-slate-600">Revisa y gestiona tus citas con acciones rápidas.</p>
      </div>
      <div class="flex items-center gap-2">
        <button id="btn-refresh" class="btn btn-outline" type="button">
          <i class="ri-refresh-line"></i> Actualizar
        </button>
      </div>
    </div>
  </header>

  <div class="card p-6">
    <div class="flex items-center gap-2">
      <i class="ri-calendar-line text-slate-400"></i>
      <h2 class="text-lg font-semibold text-slate-900">Listado</h2>
    </div>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
      <form class="flex flex-wrap items-end gap-3" method="GET" action="{{ route('doctor.citas') }}">
        <div>
          <label for="estado" class="form-label">Estado</label>
          <select id="estado" name="estado" class="form-select" required>
            <option value="all" @selected($estadoFiltro==='' || $estadoFiltro==='all')>Todas</option>
            <option value="pendiente" {{ $estadoFiltro === 'pendiente' ? 'selected' : '' }}>Pendientes</option>
            <option value="confirmada" {{ $estadoFiltro === 'confirmada' ? 'selected' : '' }}>Confirmadas</option>
            <option value="cancelada" {{ $estadoFiltro === 'cancelada' ? 'selected' : '' }}>Canceladas</option>
            <option value="realizada" {{ $estadoFiltro === 'realizada' ? 'selected' : '' }}>Realizadas</option>
            <option value="no_se_presento" {{ $estadoFiltro === 'no_se_presento' ? 'selected' : '' }}>No se presentó</option>
          </select>
          @error('estado')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
        </div>
        <button class="btn btn-primary" type="submit">
          <i class="ri-filter-3-line"></i> Filtrar
        </button>
        @if($estadoFiltro !== '')
          <a class="btn btn-ghost" href="{{ route('doctor.citas') }}">Limpiar</a>
        @endif
      </form>
      <div class="flex flex-wrap gap-2">
        <a class="btn btn-outline" href="{{ route('doctor.citas.export.excel', request()->query()) }}">
          <i class="ri-file-excel-2-line"></i> Exportar Excel
        </a>
        <a class="btn btn-outline" href="{{ route('doctor.citas.export.pdf', request()->query()) }}">
          <i class="ri-file-pdf-line"></i> Exportar PDF
        </a>
      </div>
    </div>

    <div class="mt-4 table-shell">
      <table class="table appointments-table mobile-cards" id="tabla-citas">
        <thead>
          <tr>
            <th class="col-idx">#</th>
            <th>Paciente</th>
            <th>Especialidad</th>
            <th>Fecha</th>
            <th>Hora</th>
            <th>Estado</th>
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
              $priorityLevel = $cita->priority_level ?? 'baja';
              $priorityLabel = [
                'baja' => 'Baja',
                'media' => 'Media',
                'alta' => 'Alta',
                'critica' => 'Crítica',
              ];
            @endphp
            <tr class="{{ $estado === 'pendiente' && $priorityLevel === 'critica' ? 'priority-row priority-row--critica' : '' }}">
              <td data-label="#" class="col-idx">{{ $loop->iteration }}</td>
              <td data-label="Paciente">{{ optional($cita->paciente)->name ?? '—' }}</td>
              <td data-label="Especialidad">{{ optional($cita->especialidad)->nombre ?? '—' }}</td>
              <td data-label="Fecha">{{ \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') }}</td>
              <td data-label="Hora">{{ \Carbon\Carbon::parse($cita->hora)->format('H:i') }}</td>
              <td data-label="Estado">
                <x-ui.badge :tone="$badge">
                  {{ $estado === 'no_se_presento' ? 'No se presentó' : ucfirst($estado) }}
                </x-ui.badge>
                @if($estado === 'pendiente')
                  <span class="badge warning">{{ $priorityLabel[$priorityLevel] ?? 'Baja' }}</span>
                @endif
              </td>

              <td class="cell-actions" data-label="Acciones">
                <button type="button" class="action-toggle mobile-only" aria-expanded="false" aria-label="Mostrar acciones de esta cita">
                  <i class="ri-more-2-line"></i>
                </button>
                <div class="actions-scroll table-actions">

                  @if($estado === 'pendiente')
                    <div class="row-actions flex flex-wrap gap-2">
                      <form action="{{ route('doctor.citas.aceptar',$cita->id) }}" method="POST">@csrf
                        <button class="btn btn-outline" type="submit">
                          <i class="ri-check-line"></i> Aceptar
                        </button>
                      </form>
                      <form action="{{ route('doctor.citas.rechazar',$cita->id) }}" method="POST" onsubmit="return confirm('Rechazar cita');">@csrf
                        <button class="btn btn-outline" type="submit">
                          <i class="ri-close-line"></i> Rechazar
                        </button>
                      </form>
                      <a href="{{ route('paciente.editar-cita',$cita->id) }}" class="btn btn-primary">
                        <i class="ri-calendar-line"></i> Reagendar
                      </a>
                    </div>
                  @endif

                  @if($estado === 'confirmada')
                    <div class="row-actions">
                      <div class="flex flex-wrap gap-2">
                        <a href="{{ route('doctor.citas.soap',$cita->id) }}" class="btn btn-outline">
                          <i class="ri-stethoscope-line"></i> Nota clínica (SOAP)
                        </a>
                        <a href="{{ route('doctor.pacientes.historial', $cita->paciente_id) }}" class="btn btn-outline">
                          <i class="ri-file-list-2-line"></i> Historial paciente
                        </a>
                        @if($soapEstado === 'signed')
                          <form action="{{ route('doctor.citas.realizar',$cita->id) }}" method="POST">@csrf
                            <button class="btn btn-primary" type="submit">
                              <i class="ri-checkbox-circle-line"></i> Marcar como realizada
                            </button>
                          </form>
                        @else
                          <span class="text-xs text-slate-500 self-center">
                            <i class="ri-alert-line"></i> SOAP pendiente de firma
                          </span>
                        @endif
                      </div>
                    </div>
                  @endif

                  @if($estado === 'realizada')
                    @php
                      $px = $cita->proxima_cita ?? null;
                      $hayProxima = $px && \Carbon\Carbon::parse($px->fecha)
                                    ->gte(\Carbon\Carbon::now('America/Guayaquil')->startOfDay());
                    @endphp

                    <div class="stack space-y-2">
                      <div class="row-actions">
                        <a href="{{ route('doctor.citas.soap',$cita->id) }}" class="btn btn-outline">
                          <i class="ri-stethoscope-line"></i> Ver nota clínica
                        </a>
                        <a href="{{ route('doctor.pacientes.historial', $cita->paciente_id) }}" class="btn btn-outline">
                          <i class="ri-file-list-2-line"></i> Historial paciente
                        </a>
                      </div>
                      @if($hayProxima)
                        <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2 text-sm text-slate-600">
                          <i class="ri-calendar-event-line"></i>
                          <span>
                            Próxima cita: {{ \Carbon\Carbon::parse($px->fecha)->format('d/m/Y') }}
                            {{ \Carbon\Carbon::parse($px->hora)->format('H:i') }}
                            ({{ ucfirst($px->estado) }})
                          </span>
                        </div>
                      @else
                        <div class="row-actions">
                          <button type="button"
                                  class="btn btn-outline"
                                  data-accion="planificador"
                                  data-cita="{{ $cita->id }}"
                                  data-doctor="{{ $cita->doctor_id }}">
                            <i class="ri-calendar-check-line"></i> Agendar próxima cita
                          </button>
                        </div>
                      @endif

                      <div class="row-actions">
                        @if($cita->receta)
                          @if($cita->receta->can_edit)
                            <a href="{{ route('doctor.recetas.edit',$cita->id) }}" class="btn btn-outline">
                              <i class="ri-edit-line"></i> Editar receta
                            </a>
                          @else
                            <span class="text-xs text-slate-500"><i class="ri-lock-line"></i> Edición expirada</span>
                          @endif
                        @else
                          <a href="{{ route('doctor.recetas.create',$cita->id) }}" class="btn btn-outline">
                            <i class="ri-medicine-bottle-line"></i> Generar receta
                          </a>
                        @endif
                      </div>

                      <div class="row-actions">
                        <a href="{{ route('doctor.laboratorio.create', ['paciente_id' => $cita->paciente_id]) }}" class="btn btn-outline">
                          <i class="ri-flask-line"></i> Orden laboratorio
                        </a>
                      </div>
                    </div>
                  @endif

                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
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
        <a href="{{ route('doctor.horario.index') }}" class="btn btn-outline">Ir a “Mi horario”</a>
      </div>
    </div>

    <div class="field">
      <label for="tp-fecha">Fecha</label>
      <input id="tp-fecha" class="form-input" type="date" min="{{ \Carbon\Carbon::now('America/Guayaquil')->toDateString() }}">
    </div>

    <div class="field">
      <label>Horas disponibles</label>
      <div id="tp-slots" class="slots flex flex-wrap gap-2"></div>
      <small id="tp-help" class="text-xs text-slate-500"></small>
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
