@extends('layouts.demo')
@section('title', 'Panel del paciente - Demo')
@section('header-title','Panel del paciente')
@section('header-subtitle','Resumen de citas y laboratorio (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-paciente-demo')
@endsection

@php
  $nextCitas = collect($citas)->map(function($c) {
      $cita = new \App\Models\Cita();
      $cita->id = $c['id'];
      $cita->estado = match (strtolower($c['status'])) {
          'confirmada' => 'confirmada',
          'pendiente' => 'pendiente',
          'realizada' => 'realizada',
          default => 'pendiente',
      };
      $cita->fecha = \Carbon\Carbon::parse(str_replace('/', '-', $c['date']));
      $cita->hora = $c['time'];
      $cita->setRelation('doctor', new \App\Models\User(['name' => $c['doctor']]));
      $cita->setRelation('especialidad', new \App\Models\Especialidad(['nombre' => $c['specialty']]));
      return $cita;
  });

  $labResultados = collect($results)->map(function($r) {
      $orden = new \stdClass();
      $orden->id = $r['id'];
      $orden->tipo_examen = $r['exam'];
      $orden->estado = strtolower($r['status']) === 'disponible' ? 'resultado_disponible' : 'muestra_tomada';
      $orden->resultado_path = 'resultado.pdf';
      
      $cita = new \App\Models\Cita();
      $cita->fecha = \Carbon\Carbon::parse(str_replace('/', '-', $r['delivered_at']));
      $cita->hora = '08:00';
      $orden->cita = $cita;
      return $orden;
  });

  $labOrdenes = collect($labOrders)->map(function($o) {
      $orden = new \stdClass();
      $orden->tipo_examen = $o['exam'];
      $orden->estado = strtolower($o['status']) === 'resultado listo' ? 'resultado_disponible' : 'muestra_tomada';
      return $orden;
  });
  
  $labOrdenPrincipal = $labOrdenes->first();
@endphp

@section('main')
  <div class="space-y-6">
    <section class="stat-grid">
      <x-ui.stat label="Citas agendadas" :value="$totalCitas" tone="teal">
        <x-slot:icon><i class="ri-calendar-check-line"></i></x-slot:icon>
        <p class="text-xs text-gray-500">Este mes</p>
      </x-ui.stat>
      <x-ui.stat label="Completadas" :value="$totalCitasRealizadas" tone="sky">
        <x-slot:icon><i class="ri-checkbox-circle-line"></i></x-slot:icon>
        <p class="text-xs text-gray-500">Historial</p>
      </x-ui.stat>
      <x-ui.stat label="Pendientes" :value="$totalCitasPendientes" tone="amber">
        <x-slot:icon><i class="ri-timer-line"></i></x-slot:icon>
        <p class="text-xs text-gray-500">Este mes</p>
      </x-ui.stat>
    </section>

    <!-- Órdenes de cobro -->
    <section class="card p-6">
      <div class="page-header">
        <div class="page-header__info">
          <h2>Órdenes de cobro</h2>
          <p>Control de obligaciones por cita y estado de revisión (Simulado).</p>
        </div>
        <div class="page-header__actions">
          <a href="{{ route('demo.paciente.pagos.index') }}" class="btn btn-outline">
            <i class="ri-wallet-3-line"></i> Ir a órdenes de cobro
          </a>
        </div>
      </div>

      <div class="mt-4 grid gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white/90 p-4">
          <p class="text-xs uppercase tracking-widest text-gray-500">Total</p>
          <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $totalPagos ?? 0 }}</p>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
          <p class="text-xs uppercase tracking-widest text-amber-700">Pendientes/Verificación</p>
          <p class="mt-1 text-2xl font-semibold text-amber-900">{{ $pagosPendientes ?? 0 }}</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-gray-100 p-4">
          <p class="text-xs uppercase tracking-widest text-emerald-700">Pagados</p>
          <p class="mt-1 text-2xl font-semibold text-emerald-900">{{ $pagosPagados ?? 0 }}</p>
        </div>
      </div>
    </section>

    <!-- Laboratorio principal -->
    @if($labOrdenes->isNotEmpty())
      <section class="card p-6">
        <div class="page-header">
          <div class="page-header__info">
            <h2>Laboratorio</h2>
            <p>Estado de tu examen, sin detalles técnicos.</p>
          </div>
          <div class="page-header__actions">
            <a class="btn btn-outline" href="{{ route('demo.paciente.laboratorio.index') }}">
              <i class="ri-eye-line"></i> Ver detalles
            </a>
          </div>
        </div>

        @if($labOrdenPrincipal)
          @php($estadoActual = data_get($labOrdenPrincipal, 'estado', 'muestra_tomada'))
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
                  <span class="text-xs text-gray-500">{{ $estadoInfo['note'] }}</span>
                @endif
              </div>
            </div>
          </div>
        @endif
      </section>
    @endif

    <!-- Exámenes de laboratorio listado -->
    <section class="card p-6">
      <div class="page-header">
        <div class="page-header__info">
          <h2>Exámenes de laboratorio</h2>
          <p>Solicitudes activas y recientes (Simulado).</p>
        </div>
      </div>

      <div class="mt-4 space-y-3">
        @foreach($labOrders as $order)
          <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white/90 p-4">
            <div>
              <p class="text-sm font-semibold text-gray-900">{{ $order['exam'] }}</p>
              <p class="text-xs text-gray-500">{{ $order['origin'] }}</p>
            </div>
            <span class="badge {{ str_contains(strtolower($order['status']), 'listo') ? 'success' : 'warning' }}">{{ $order['status'] }}</span>
          </div>
        @endforeach
      </div>
    </section>

    <!-- Próximas citas -->
    <section class="card p-6">
      <div class="page-header">
        <div class="page-header__info">
          <h2>Mis próximas citas</h2>
          <p>Las más cercanas en tu agenda</p>
        </div>
        <div class="page-header__actions">
          <a class="btn btn-ghost" href="{{ route('demo.paciente.citas') }}">
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
                <td data-label="Hora">{{ $cita->hora }}</td>
                <td data-label="Estado">
                  @switch($cita->estado)
                    @case('pendiente')  <span class="badge warning">En revisión</span> @break
                    @case('confirmada') <span class="badge info">Confirmada</span>   @break
                    @case('cancelada')  <span class="badge danger">Cancelada</span>  @break
                    @case('realizada')  <span class="badge success">Realizada</span> @break
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

    <!-- Resultados de laboratorio -->
    <section class="card p-6">
      <div class="page-header">
        <div class="page-header__info">
          <h2>Resultados de laboratorio</h2>
          <p>Últimos resultados disponibles</p>
        </div>
        <div class="page-header__actions">
          <a class="btn btn-ghost" href="{{ route('demo.paciente.resultados') }}">
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
              </td>
              <td data-label="Estado">
                <span class="badge success">Disponible</span>
              </td>
              <td data-label="Archivo">
                @if($orden->resultado_path)
                  <button class="btn btn-outline btn-sm demo-action-blocked">
                    <i class="ri-download-line"></i> Descargar
                  </button>
                @else
                  <span class="text-xs text-gray-500">Pendiente</span>
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
