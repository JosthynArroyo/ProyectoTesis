@extends('layouts.demo')

@section('title', 'Panel administrativo - Demo')
@section('header-title', 'Panel administrativo')
@section('header-subtitle', 'Visión general de la operación (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-admin-demo')
@endsection

@php
  $period = data_get($dashboard, 'filters.period', '30d');
  $options = ['all' => 'Todo el historial', '7d' => 'Últimos 7 días', '30d' => 'Últimos 30 días', 'month' => 'Mes actual', 'year' => 'Año actual'];
  
  // Transform recentAppointments into Cita models in memory
  $citasCollection = collect($recentAppointments)->map(function($app) use ($usuarios) {
      $app['id'] = $app['id'] ?? 1;
      $app['fecha'] = $app['fecha'] ?? now()->toDateString();
      $app['hora'] = $app['hora'] ?? '08:00';
      $app['status'] = $app['status'] ?? 'pendiente';
      $app['prioridad_nivel'] = $app['prioridad_nivel'] ?? 'BAJA';
      $app['prioridad_red_flag'] = (bool) ($app['prioridad_red_flag'] ?? false);
      $app['motivo'] = $app['motivo'] ?? 'Consulta';
      
      // We map using the controller helper concept
      $citaObj = new \App\Models\Cita($app);
      $citaObj->id = $app['id'];
      $citaObj->exists = true;
      $citaObj->fecha = \Carbon\Carbon::parse($app['fecha']);
      $citaObj->hora = $app['hora'];
      $citaObj->estado = $app['status'];
      $citaObj->prioridad_nivel = $app['prioridad_nivel'];
      $citaObj->prioridad_red_flag = $app['prioridad_red_flag'];
      
      $pData = collect($usuarios)->firstWhere('name', $app['patient']);
      if ($pData) {
          $role = new \App\Models\Role(['name' => strtolower($pData['role'])]);
          $pUser = new \App\Models\User($pData);
          $pUser->id = $pData['id'];
          $pUser->setRelation('roles', collect([$role]));
          $citaObj->setRelation('paciente', $pUser);
      } else {
          $citaObj->setRelation('paciente', new \App\Models\User(['name' => $app['patient'], 'dni' => '1723456789']));
      }

      $dData = collect($usuarios)->firstWhere('name', $app['doctor']);
      if ($dData) {
          $role = new \App\Models\Role(['name' => strtolower($dData['role'])]);
          $dUser = new \App\Models\User($dData);
          $dUser->id = $dData['id'];
          $dUser->setRelation('roles', collect([$role]));
          $citaObj->setRelation('doctor', $dUser);
      } else {
          $citaObj->setRelation('doctor', new \App\Models\User(['name' => $app['doctor']]));
      }
      return $citaObj;
  });
@endphp

@section('main')
  <div
    class="space-y-6"
    data-dashboard-page
    data-dashboard-endpoint="{{ route('demo.admin.dashboard.data') }}"
    data-dashboard-resumen="{{ route('demo.admin.dashboard.resumen') }}"
    data-dashboard-role="admin"
  >
    <script type="application/json" data-dashboard-state>@json($dashboard)</script>

    @if(($recordatoriosPendientes ?? 0) > 0)
      <section>
        <x-ui.alert tone="warning" title="Tienes recordatorios por enviar">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              Hay <strong>{{ $recordatoriosPendientes }}</strong> cita{{ $recordatoriosPendientes === 1 ? '' : 's' }} con recordatorio pendiente.
              @if(($recordatoriosSinTelefono ?? 0) > 0)
                <span class="block text-xs text-gray-600">{{ $recordatoriosSinTelefono }} requiere{{ $recordatoriosSinTelefono === 1 ? '' : 'n' }} revision porque no tiene{{ $recordatoriosSinTelefono === 1 ? '' : 'n' }} telefono valido.</span>
              @endif
            </div>
            <a href="{{ route('demo.admin.recordatorios.index') }}" class="btn btn-primary btn-sm">
              <i class="ri-whatsapp-line"></i> Revisar recordatorios
            </a>
          </div>
        </x-ui.alert>
      </section>
    @endif

    <section class="card p-5">
      <form method="GET" action="{{ route('demo.admin.dashboard') }}" class="flex flex-col gap-3 lg:flex-row lg:items-end lg:gap-4" data-dashboard-filters-form>
        <div class="min-w-0 lg:w-56">
          <label class="form-label" for="period">Periodo</label>
          <select id="period" name="period" class="form-select">
            @foreach($options as $value => $label)
              <option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="lg:ml-auto">
          <button class="btn btn-primary w-full lg:w-auto" type="submit">
            <i class="ri-refresh-line"></i> Actualizar
          </button>
        </div>
      </form>
    </section>

    <section class="grid gap-6 lg:grid-cols-2">
      <x-dashboard.chart-card
        class="lg:col-span-1"
        chart-key="citas_estado"
        chart-type="donut"
        title="Citas por estado"
        subtitle="Estados reales de las citas del sistema."
      />
      <x-dashboard.chart-card
        class="lg:col-span-1"
        chart-key="citas_doctor"
        chart-type="bar"
        title="Citas por doctor"
        subtitle="Comparación apilada de carga operativa entre doctores."
      />
      <x-dashboard.chart-card
        class="lg:col-span-2"
        chart-key="citas_por_dia_estado"
        chart-type="bar"
        title="Agenda de citas por día y estado"
        subtitle="Citas programadas por día y su estado de atención."
      />
      <x-dashboard.chart-card
        class="lg:col-span-2"
        chart-key="pacientes_nuevos_atendidos"
        chart-type="bar"
        title="Pacientes nuevos y atendidos"
        subtitle="Evolución de pacientes registrados frente a atendidos."
      />
    </section>

    <div class="grid gap-6 lg:grid-cols-[1.4fr_0.6fr]">
      <section class="card p-6">
        <div class="page-header">
          <div class="page-header__info">
            <h2>Citas recientes</h2>
            <p>Ultimas citas registradas en el sistema (Simulado).</p>
          </div>
          <div class="page-header__actions">
            <form method="GET" action="{{ route('demo.admin.dashboard') }}" class="flex items-center gap-2">
              <select name="prioridad" class="form-select">
                <option value="all" @selected(($prioridad ?? '') === '')>Todas las prioridades</option>
                <option value="ALTA" @selected(($prioridad ?? '') === 'ALTA')>ALTA</option>
                <option value="MEDIA" @selected(($prioridad ?? '') === 'MEDIA')>MEDIA</option>
                <option value="BAJA" @selected(($prioridad ?? '') === 'BAJA')>BAJA</option>
              </select>
              <button type="submit" class="btn btn-outline btn-sm">
                <i class="ri-filter-3-line"></i> Filtrar
              </button>
              @if(($prioridad ?? '') !== '')
                <a href="{{ route('demo.admin.dashboard') }}" class="btn btn-ghost btn-sm">
                  <i class="ri-refresh-line"></i> Limpiar
                </a>
              @endif
            </form>
            <button class="btn btn-outline btn-sm demo-action-blocked">
              <i class="ri-download-2-line"></i> Exportar
            </button>
          </div>
        </div>

        <div class="mt-4 table-shell table-responsive-cards">
          <table class="table">
            <thead>
              <tr>
                <th>Paciente</th>
                <th>Doctor</th>
                <th>Estado</th>
                <th>Prioridad</th>
                <th>Horario</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody id="citasBody">
              @php use Illuminate\Support\Carbon; @endphp
              @forelse($citasCollection as $cita)
                @php
                  $priorityTone = match($cita->prioridad_nivel) {
                    'ALTA' => 'danger',
                    'MEDIA' => 'warning',
                    default => 'neutral',
                  };
                  $redirectTo = route('demo.admin.citas.prioridad.edit', $cita->id).'?redirect_to='.urlencode(request()->fullUrl());
                @endphp
                <tr>
                  <td data-label="Paciente">{{ optional($cita->paciente)->name ?? 'Sin paciente' }}</td>
                  <td data-label="Doctor">{{ optional($cita->doctor)->name ?? 'Sin asignar' }}</td>
                  <td data-label="Estado">
                    <x-ui.badge :tone="$cita->estado === 'cancelada' ? 'danger' : ($cita->estado === 'pendiente' ? 'warning' : 'success')">
                      {{ $cita->estado === 'no_se_presento' ? 'No se presento' : ucfirst($cita->estado) }}
                    </x-ui.badge>
                  </td>
                  <td data-label="Prioridad">
                    <x-ui.badge :tone="$priorityTone">{{ $cita->prioridad_nivel ?? 'BAJA' }}</x-ui.badge>
                    @if($cita->prioridad_red_flag)
                      <span class="badge danger">Red flag</span>
                    @endif
                  </td>
                  <td data-label="Horario">{{ Carbon::parse($cita->fecha)->format('Y-m-d') }} {{ Carbon::parse($cita->hora)->format('H:i') }}</td>
                  <td data-label="Acciones">
                    <a href="{{ $redirectTo }}" class="btn btn-outline btn-sm">
                      <i class="ri-scales-3-line"></i> Ajustar prioridad
                    </a>
                  </td>
                </tr>
              @empty
                <tr><td colspan="6">No hay citas recientes.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </section>

      <aside class="space-y-4">
        <section class="card p-6">
          <h3 class="text-lg font-semibold text-gray-900">Interpretación del período</h3>
          <p class="mt-2 text-sm text-gray-600">
            {{ data_get($dashboard, 'filters.label', 'Ultimos 30 dias') }}.
            Agrupacion: {{ ucfirst(data_get($dashboard, 'filters.grouping', 'dia')) }}.
          </p>
        </section>
      </aside>
    </div>
  </div>
@endsection

@push('scripts')
    @vite(['resources/js/dashboard-admin.js'])
@endpush
