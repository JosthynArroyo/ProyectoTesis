@extends('layouts.paciente')
@section('title', 'Panel del paciente - Clínica Don Bosco')
@section('body-class','patient-dashboard-page')
@section('header-title','Panel del paciente')
@section('header-subtitle','Resumen de citas y laboratorio')

@push('scripts')
  @vite(['resources/js/paciente/dashboard-paciente.js'])
@endpush

@php($user = Auth::user())
@php($nextCitas = collect($citas ?? [])->take(4))
@php($labResultados = collect($labResultados ?? []))
@php($labOrdenes = collect($labOrdenes ?? []))
@php($labOrdenProgramada = $labOrdenProgramada ?? null)
@php($labOrdenPrincipal = $labOrdenPrincipal ?? null)
@php($labResultadoDestacado = $labResultadoDestacado ?? null)
@php($labVentanaAtencion = $labVentanaAtencion ?? null)
@php($labEsperaEstimada = $labEsperaEstimada ?? null)
@php($labOrders = collect($labOrders ?? []))

@section('main')
  <div class="space-y-6">
    <section class="stat-grid">
      <x-ui.stat label="Citas agendadas" :value="$totalCitas" tone="teal">
        <x-slot:icon><i class="ri-calendar-check-line"></i></x-slot:icon>
        <p class="text-xs text-slate-500">Este mes</p>
      </x-ui.stat>
      <x-ui.stat label="Completadas" :value="$totalCitasRealizadas" tone="sky">
        <x-slot:icon><i class="ri-checkbox-circle-line"></i></x-slot:icon>
        <p class="text-xs text-slate-500">Historial</p>
      </x-ui.stat>
      <x-ui.stat label="Pendientes" :value="$totalCitasPendientes" tone="amber">
        <x-slot:icon><i class="ri-timer-line"></i></x-slot:icon>
        <p class="text-xs text-slate-500">Este mes</p>
      </x-ui.stat>
    </section>

    <section class="card p-6">
      <div class="page-header">
        <div class="page-header__info">
          <h2>Mis pagos</h2>
          <p>Control de obligaciones por cita y estado de revisión.</p>
        </div>
        <div class="page-header__actions">
          <a href="{{ route('paciente.pagos.index') }}" class="btn btn-outline">
            <i class="ri-wallet-3-line"></i> Ir a mis pagos
          </a>
        </div>
      </div>

      <div class="mt-4 grid gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white/90 p-4">
          <p class="text-xs uppercase tracking-widest text-slate-500">Total</p>
          <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $totalPagos ?? 0 }}</p>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
          <p class="text-xs uppercase tracking-widest text-amber-700">Pendientes/Verificación</p>
          <p class="mt-1 text-2xl font-semibold text-amber-900">{{ $pagosPendientes ?? 0 }}</p>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
          <p class="text-xs uppercase tracking-widest text-emerald-700">Pagados</p>
          <p class="mt-1 text-2xl font-semibold text-emerald-900">{{ $pagosPagados ?? 0 }}</p>
        </div>
      </div>

      @if($bloqueoPagosPendientes ?? false)
        <x-ui.alert tone="warning" class="mt-4">
          Tienes órdenes de pago vencidas de citas concluidas. Regulariza tu cuenta para agendar una nueva cita.
        </x-ui.alert>
      @endif
    </section>

    @if($labOrdenes->isNotEmpty())
      <section class="card p-6">
        <div class="page-header">
          <div class="page-header__info">
            <h2>Laboratorio</h2>
            <p>Estado de tu examen, sin detalles técnicos.</p>
          </div>
          <div class="page-header__actions">
            <a class="btn btn-outline" href="{{ route('paciente.laboratorio.index') }}">
              <i class="ri-eye-line"></i> Ver detalles
            </a>
          </div>
        </div>

        @if($labResultadoDestacado && $labResultadoDestacado->resultado_path)
          <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
            <div>
              <p class="text-sm font-semibold text-emerald-800">Resultado disponible</p>
              <p class="text-sm text-emerald-700">Tu informe ya está listo para revisar.</p>
            </div>
            <a class="btn btn-primary btn-full-mobile" href="{{ route('paciente.laboratorio.download', $labResultadoDestacado->id) }}">
              <i class="ri-download-line"></i> Ver o descargar
            </a>
          </div>
        @endif

        @if($labOrdenPrincipal)
          @php($estadoActual = data_get($labOrdenPrincipal, 'estado', 'orden_creada'))
          @php($estadoCatalogo = [
            'orden_creada' => ['label' => 'Pendiente de toma', 'tone' => 'warning'],
            'cita_programada' => ['label' => 'Pendiente de toma', 'tone' => 'warning'],
            'muestra_tomada' => ['label' => 'Muestra tomada', 'tone' => 'info', 'note' => 'En análisis'],
            'resultado_disponible' => ['label' => 'Resultado disponible', 'tone' => 'success'],
          ])
          @php($estadoInfo = $estadoCatalogo[$estadoActual] ?? ['label' => 'Pendiente', 'tone' => 'info'])

          <div class="mt-4 detail-grid">
            <div class="detail-item">
              <span class="detail-item__label">Examen</span>
              <span class="detail-item__value">{{ data_get($labOrdenPrincipal, 'tipo_examen', 'Examen de laboratorio') }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-item__label">Estado</span>
              <div class="mt-1 flex flex-wrap items-center gap-2">
                <span class="badge {{ $estadoInfo['tone'] }}">{{ $estadoInfo['label'] }}</span>
                @if(!empty($estadoInfo['note']))
                  <span class="text-xs text-slate-500">{{ $estadoInfo['note'] }}</span>
                @endif
              </div>
            </div>
          </div>

          @if($labOrdenProgramada && $labOrdenProgramada->cita)
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
              <div class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-white/90 p-4">
                <i class="ri-time-line text-slate-400"></i>
                <div>
                  <p class="text-xs uppercase tracking-widest text-slate-500">Ventana estimada</p>
                  <p class="text-sm text-slate-600">
                    @if($labVentanaAtencion)
                      Acércate entre {{ $labVentanaAtencion['inicio'] }} y {{ $labVentanaAtencion['fin'] }}
                    @else
                      Te avisaremos la ventana estimada.
                    @endif
                  </p>
                </div>
              </div>
              @if(!is_null($labEsperaEstimada))
                <div class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-white/90 p-4">
                  <i class="ri-group-line text-slate-400"></i>
                  <div>
                    <p class="text-xs uppercase tracking-widest text-slate-500">Espera estimada</p>
                    <p class="text-sm text-slate-600">
                      @if($labEsperaEstimada > 0)
                        Hay {{ $labEsperaEstimada }} pacientes antes que usted
                      @else
                        No hay pacientes antes que usted
                      @endif
                    </p>
                  </div>
                </div>
              @endif
            </div>
          @endif

          @if($labOrdenProgramada && in_array($labOrdenProgramada->estado, [\App\Models\LaboratorioOrden::ESTADO_ORDEN_CREADA, \App\Models\LaboratorioOrden::ESTADO_CITA_PROGRAMADA], true))
            <div class="mt-4 rounded-2xl border border-slate-200 bg-white/90 p-4">
              <h3 class="text-sm font-semibold text-slate-900">Preparación del examen</h3>
              <ul class="mt-2 space-y-1 text-sm text-slate-600">
                <li><strong>Preparación:</strong> {{ $labOrdenProgramada->preparacion ? 'Revisa la preparación indicada en tu orden.' : 'Sin preparación registrada.' }}</li>
                <li><strong>Indicaciones del médico:</strong> {{ $labOrdenProgramada->indicaciones ?? 'Sin indicaciones adicionales.' }}</li>
              </ul>
            </div>
          @endif
        @endif
      </section>
    @endif

    <section class="card p-6">
      <div class="page-header">
        <div class="page-header__info">
          <h2>Exámenes de laboratorio</h2>
          <p>Solicitudes activas y recientes.</p>
        </div>
        <div class="page-header__actions">
          <a class="btn btn-outline btn-full-mobile" href="{{ route('paciente.laboratorio.solicitar') }}">
            <i class="ri-flask-line"></i> Solicitar examen
          </a>
        </div>
      </div>

      @if($labOrders->isNotEmpty())
        <div class="mt-4 space-y-3">
          @foreach($labOrders as $order)
            @php($item = $order->items->first())
            @php($statusMap = [
              'pendiente_toma' => ['Pendiente de toma', 'warning'],
              'muestra_tomada' => ['Muestra tomada', 'info'],
              'en_analisis' => ['En análisis', 'info'],
              'resultado_listo' => ['Resultado listo', 'success'],
              'cancelado' => ['Cancelado', 'danger'],
              'no_se_presento' => ['No se presentó', 'danger'],
            ])
            @php($statusInfo = $statusMap[$order->status] ?? ['En proceso', 'info'])
            @php($originLabel = $order->source === \App\Models\LabOrder::SOURCE_MEDICAL_ORDER ? 'Con orden médica' : 'Rutina')
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white/90 p-4">
              <div>
                <p class="text-sm font-semibold text-slate-900">{{ $item->test->nombre ?? 'Examen de laboratorio' }}</p>
                <p class="text-xs text-slate-500">{{ $originLabel }}</p>
              </div>
              <span class="badge {{ $statusInfo[1] }}">{{ $statusInfo[0] }}</span>
            </div>
          @endforeach
        </div>
      @else
        <x-ui.empty-state title="Sin solicitudes" message="No tienes solicitudes de laboratorio registradas." />
      @endif
    </section>

    <section class="card p-6">
      <div class="page-header">
        <div class="page-header__info">
          <h2>Mis próximas citas</h2>
          <p>Las 4 más cercanas en tu agenda</p>
        </div>
        <div class="page-header__actions">
          <a class="btn btn-ghost" href="{{ route('paciente.citas') }}">
            <i class="ri-arrow-right-line"></i> Ver todas mis citas
          </a>
        </div>
      </div>
      <div class="mt-4 table-shell table-responsive-cards">
        <table class="table">
          <thead>
          <tr>
            <th>Doctor</th>
            <th>Especialidad</th>
            <th>Fecha</th>
            <th>Hora</th>
            <th>Estado</th>
          </tr>
          </thead>
          <tbody>
            @forelse($nextCitas as $cita)
              <tr>
                <td data-label="Doctor">{{ optional($cita->doctor)->name ?? 'Sin asignar' }}</td>
                <td data-label="Especialidad">{{ optional($cita->especialidad)->nombre ?? 'N/D' }}</td>
                <td data-label="Fecha">{{ \Carbon\Carbon::parse($cita->fecha)->format('Y/m/d') }}</td>
                <td data-label="Hora">{{ \Carbon\Carbon::parse($cita->hora)->format('H:i') }}</td>
                <td data-label="Estado">
                  @switch($cita->estado)
                    @case('pendiente')  <span class="badge warning">En revisión</span> @break
                    @case('confirmada') <span class="badge info">Confirmada</span>   @break
                    @case('cancelada')  <span class="badge danger">Cancelada</span>  @break
                    @case('realizada')  <span class="badge success">Realizada</span> @break
                    @case('no_se_presento')  <span class="badge danger">No se presentó</span> @break
                    @default            <span class="badge info">{{ ucfirst($cita->estado) }}</span>
                  @endswitch
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5">No tienes próximas citas.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>

    <section class="card p-6">
      <div class="page-header">
        <div class="page-header__info">
          <h2>Resultados de laboratorio</h2>
          <p>Últimos resultados disponibles</p>
        </div>
        <div class="page-header__actions">
          <a class="btn btn-ghost" href="{{ route('paciente.laboratorio.index') }}">
            <i class="ri-file-search-line"></i> Ver resultados
          </a>
        </div>
      </div>
      <div class="mt-4 table-shell table-responsive-cards">
        <table class="table">
          <thead>
          <tr>
            <th>Examen</th>
            <th>Fecha cita</th>
            <th>Estado</th>
            <th>Archivo</th>
          </tr>
          </thead>
          <tbody>
          @forelse($labResultados as $orden)
            <tr>
              <td data-label="Examen">{{ $orden->tipo_examen }}</td>
              <td data-label="Fecha">
                {{ optional($orden->cita->fecha)->format('Y/m/d') }}
                {{ $orden->cita->hora ? \Carbon\Carbon::parse($orden->cita->hora)->format('H:i') : '' }}
              </td>
              <td data-label="Estado">
                <span class="badge info">{{ str_replace('_', ' ', ucfirst($orden->estado)) }}</span>
              </td>
              <td data-label="Archivo">
                @if($orden->resultado_path)
                  <a href="{{ route('paciente.laboratorio.download', $orden->id) }}" class="btn btn-outline btn-sm">
                    <i class="ri-download-line"></i> Descargar
                  </a>
                @else
                  <span class="text-xs text-slate-500">Pendiente</span>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="4">No hay resultados disponibles.</td>
            </tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </section>
  </div>
@endsection
