@extends('layouts.laboratorio')
@section('title', 'Panel del laboratorio - '.$clinicIdentity->name())
@section('activeSidebar', 'dashboard')
@section('header-title','Panel laboratorio')
@section('header-subtitle','Controla pedidos y resultados')

@php
  $period = data_get($dashboard, 'filters.period', '30d');
  $options = app(\App\Services\DashboardAnalyticsService::class)->periodOptions();
@endphp

@section('main')
  <div
    class="space-y-6"
    data-dashboard-page
    data-dashboard-endpoint="{{ route('laboratorio.dashboard.data') }}"
    data-dashboard-role="laboratorio"
  >
    <script type="application/json" data-dashboard-state>@json($dashboard)</script>

    @if(session('success'))
      <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
    @endif

    <section class="card p-5">
      <form method="GET" action="{{ route('laboratorio.dashboard') }}" class="flex flex-col gap-3 lg:flex-row lg:items-end lg:gap-4" data-dashboard-filters-form>
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
        chart-key="orders_status"
        chart-type="donut"
        title="Pedidos por estado"
        subtitle="Combina los estados reales del sistema."
      />
      <x-dashboard.chart-card
        class="lg:col-span-1"
        chart-key="orders_by_doctor"
        chart-type="bar"
        title="Pedidos por médico solicitante"
        subtitle="Solo solicitudes reales del sistema."
      />
      <x-dashboard.chart-card
        class="lg:col-span-2"
        chart-key="orders_timeline"
        chart-type="area"
        title="Evolución de pedidos"
        subtitle="Pedidos recibidos según el período seleccionado."
      />
      <x-dashboard.chart-card
        class="lg:col-span-2"
        chart-key="orders_status_timeline"
        chart-type="bar"
        title="Pedidos pendientes, en proceso, completados y cancelados en el tiempo"
        subtitle="Comparación apilada del flujo de trabajo."
      />
      <x-dashboard.chart-card
        class="lg:col-span-1"
        chart-key="top_exams"
        chart-type="bar"
        title="Exámenes más solicitados"
        subtitle="Basado en pedidos reales registrados."
      />
    </section>

    <section class="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
      <article class="card p-6">
        <div class="page-header">
          <div class="page-header__info">
            <p class="text-xs uppercase tracking-widest text-gray-500">Trabajo diario</p>
            <h2>Pedidos y resultados recientes</h2>
            <p>Documentos operativos vinculados al flujo del laboratorio.</p>
          </div>
          <div class="page-header__actions">
            <a class="btn btn-outline btn-full-mobile" href="{{ route('laboratorio.ordenes.index') }}">
              <i class="ri-file-list-3-line"></i> Gestionar resultados
            </a>
          </div>
        </div>

        <div class="mt-4 table-shell table-responsive-cards">
          <table class="table">
            <thead>
              <tr>
                <th>Pedido</th>
                <th>Detalle</th>
                <th>Estado</th>
                <th>Fecha</th>
              </tr>
            </thead>
            <tbody>
              @forelse($ordenesRecientes as $orden)
                <tr>
                  <td data-label="Pedido">{{ $orden['label'] ?? $orden->label ?? 'Examen' }}</td>
                  <td data-label="Detalle">{{ $orden['meta'] ?? $orden->meta ?? 'Pedido de laboratorio' }}</td>
                  <td data-label="Estado">
                    <x-ui.badge :tone="$orden['tone'] ?? $orden->tone ?? 'neutral'">Reciente</x-ui.badge>
                  </td>
                  <td data-label="Fecha">{{ $orden['when'] ?? $orden->when ?? 'Sin fecha' }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="4">Sin pedidos recientes.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </article>

      <aside class="space-y-4">
        <section class="card p-6 dashboard-list-card" data-dashboard-list="recent_orders">
          <div class="flex items-center justify-between gap-3">
            <h3 class="text-lg font-semibold text-gray-900">Pedidos recientes</h3>
            <i class="ri-inbox-line text-gray-400"></i>
          </div>
          <div class="mt-4 space-y-3" data-dashboard-list-body></div>
          <div class="mt-4 hidden text-sm text-gray-500" data-dashboard-list-empty>No hay pedidos recientes.</div>
        </section>

        <section class="card p-6">
          <h3 class="text-lg font-semibold text-gray-900">Cobertura operativa</h3>
          <p class="mt-2 text-sm text-gray-600">
            {{ data_get($dashboard, 'filters.label', 'Ultimos 30 dias') }}.
            Agrupacion: {{ ucfirst(data_get($dashboard, 'filters.grouping', 'dia')) }}.
          </p>
        </section>
      </aside>
    </section>
  </div>
@endsection
