@extends('layouts.superadmin')
@section('title','Panel superadmin')
@section('header-title','Panel global')
@section('header-subtitle','Metricas y control centralizado')

@php
  $period = data_get($dashboard, 'filters.period', '30d');
  $options = app(\App\Services\DashboardAnalyticsService::class)->periodOptions();
@endphp

@section('main')
<div
  class="space-y-6"
  data-dashboard-page
  data-dashboard-endpoint="{{ route('superadmin.dashboard.data') }}"
  data-dashboard-role="superadmin"
>
  <script type="application/json" data-dashboard-state>@json($dashboard)</script>

  <div class="panel-action-bar panel-action-bar--between">
    <div class="panel-action-bar__actions">
      <a class="btn btn-outline btn-full-mobile" href="{{ route('superadmin.admins.index') }}">
        <i class="ri-shield-user-line"></i> Administradores
      </a>
      <a class="btn btn-primary btn-full-mobile" href="{{ route('superadmin.personalizacion.bienvenida.edit') }}">
        <i class="ri-palette-line"></i> Personalizacion
      </a>
    </div>
    <div class="panel-action-bar__actions">
      <a href="{{ route('superadmin.dashboard.export-pdf') }}" class="btn btn-outline btn-full-mobile">
        <i class="ri-file-pdf-line"></i> Exportar PDF
      </a>
    </div>
  </div>

  @if(($pendientesPersonalizacion ?? 0) > 0)
    <x-ui.alert tone="warning" title="Personalizacion pendiente">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          Hay <strong>{{ $pendientesPersonalizacion }}</strong> solicitud{{ $pendientesPersonalizacion === 1 ? '' : 'es' }} pendiente{{ $pendientesPersonalizacion === 1 ? '' : 's' }}.
        </div>
        <a class="btn btn-outline btn-sm" href="{{ route('superadmin.solicitudes.personalizacion.index') }}">
          Ver solicitudes
        </a>
      </div>
    </x-ui.alert>
  @endif

  <section class="card p-5">
    <form method="GET" action="{{ route('superadmin.dashboard') }}" class="flex flex-col gap-3 lg:flex-row lg:items-end lg:gap-4" data-dashboard-filters-form>
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
      chart-key="usuarios_roles"
      chart-type="donut"
      title="Distribución de usuarios por rol"
      subtitle="Incluye todos los roles reales registrados."
    />
    <x-dashboard.chart-card
      class="lg:col-span-1"
      chart-key="citas_estado"
      chart-type="donut"
      title="Citas por estado"
      subtitle="Estados reales registrados en el sistema."
    />
    <x-dashboard.chart-card
      class="lg:col-span-2"
      chart-key="citas_timeline"
      chart-type="area"
      title="Evolución de citas"
      subtitle="Muestra la actividad en el período seleccionado."
    />
    <x-dashboard.chart-card
      class="lg:col-span-1"
      chart-key="citas_doctor"
      chart-type="bar"
      title="Citas por doctor"
      subtitle="Ranking de carga asistencial en el período."
    />
    <x-dashboard.chart-card
      class="lg:col-span-1"
      chart-key="documentos_tipo"
      chart-type="donut"
      title="Documentos generados"
      subtitle="Recetas, certificados y pedidos de laboratorio."
    />
    <x-dashboard.chart-card
      class="lg:col-span-2"
      chart-key="usuarios_timeline"
      chart-type="area"
      title="Usuarios registrados"
      subtitle="Evolución de nuevos registros."
    />
  </section>
</div>
@endsection
