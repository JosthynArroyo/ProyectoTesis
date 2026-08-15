@extends('layouts.demo')
@section('title', 'Panel laboratorio - Demo')
@section('activeSidebar', 'dashboard')
@section('header-title','Panel laboratorio')
@section('header-subtitle','Controla pedidos y resultados (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-laboratorio-demo')
@endsection

@php
  $period = request('period', '30d');
@endphp

@section('main')
  <div class="space-y-6">
    <!-- Filtro de Periodo -->
    <section class="card p-5 bg-white">
      <form method="GET" action="{{ route('demo.laboratorio.dashboard') }}" class="flex flex-col gap-3 lg:flex-row lg:items-end lg:gap-4">
        <div class="min-w-0 lg:w-56">
          <label class="form-label" for="period">Periodo</label>
          <select id="period" name="period" class="form-select">
            <option value="7d" @selected($period === '7d')>Últimos 7 días</option>
            <option value="30d" @selected($period === '30d')>Últimos 30 días</option>
            <option value="90d" @selected($period === '90d')>Últimos 3 meses</option>
          </select>
        </div>
        <div class="lg:ml-auto">
          <button class="btn btn-primary w-full lg:w-auto" type="submit">
            <i class="ri-refresh-line"></i> Actualizar
          </button>
        </div>
      </form>
    </section>

    <!-- Stats -->
    <section class="stat-grid">
        <x-ui.stat label="Ordenes pendientes" :value="$estadisticas['pendientes']" tone="amber">
            <x-slot:icon><i class="ri-flask-line"></i></x-slot:icon>
        </x-ui.stat>
        <x-ui.stat label="Muestras hoy" :value="$estadisticas['muestras_hoy']" tone="sky">
            <x-slot:icon><i class="ri-test-tube-line"></i></x-slot:icon>
        </x-ui.stat>
        <x-ui.stat label="Resultados publicados" :value="$estadisticas['resultados_publicados']" tone="teal">
            <x-slot:icon><i class="ri-checkbox-circle-line"></i></x-slot:icon>
        </x-ui.stat>
    </section>

    <!-- Gráficas -->
    <section class="grid gap-6 lg:grid-cols-2">
      <!-- Pedidos por estado -->
      <div class="card p-6 bg-white">
        <h3 class="text-base font-semibold text-gray-900">Pedidos por estado</h3>
        <p class="text-xs text-gray-500 mb-4">Muestra el balance actual de la carga operativa.</p>
        <div id="chart-orders-status" class="h-64"></div>
      </div>

      <!-- Pedidos por médico -->
      <div class="card p-6 bg-white">
        <h3 class="text-base font-semibold text-gray-900">Pedidos por médico solicitante</h3>
        <p class="text-xs text-gray-500 mb-4">médicos derivadores con más actividad.</p>
        <div id="chart-orders-doctor" class="h-64"></div>
      </div>

      <!-- Evolución de pedidos -->
      <div class="card p-6 bg-white lg:col-span-2">
        <h3 class="text-base font-semibold text-gray-900">Evolución de pedidos</h3>
        <p class="text-xs text-gray-500 mb-4">Histórico diario de solicitudes.</p>
        <div id="chart-orders-timeline" class="h-64"></div>
      </div>
    </section>

    <!-- Tabla Operativa -->
    <section class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
      <article class="card p-6 bg-white">
        <div class="page-header">
          <div class="page-header__info">
            <p class="text-xs uppercase tracking-widest text-gray-500">Trabajo diario</p>
            <h2>Pedidos y resultados recientes</h2>
            <p>Documentos operativos vinculados al flujo del laboratorio (Simulado).</p>
          </div>
          <div class="page-header__actions">
            <a class="btn btn-outline btn-full-mobile" href="{{ route('demo.laboratorio.citas-resultados') }}">
              <i class="ri-file-list-3-line"></i> Gestionar resultados
            </a>
          </div>
        </div>

        <div class="mt-4 table-shell table-responsive-cards">
          <table class="table">
            <thead>
              <tr>
                <th>Paciente</th>
                <th>Examen</th>
                <th>Estado</th>
                <th>Fecha</th>
              </tr>
            </thead>
            <tbody>
              @foreach($ordenes as $orden)
                <tr>
                  <td data-label="Paciente">{{ $orden['patient'] }}</td>
                  <td data-label="Examen">{{ $orden['exam'] }}</td>
                  <td data-label="Estado">
                    <span class="badge {{ $orden['status_tone'] }}">{{ $orden['status'] }}</span>
                  </td>
                  <td data-label="Fecha">{{ $orden['date'] }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </article>

      <aside class="space-y-4">
        <section class="card p-6 bg-white">
          <div class="flex items-center justify-between gap-3 mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Pedidos recientes</h3>
            <i class="ri-inbox-line text-gray-400"></i>
          </div>
          <div class="space-y-3">
            <div class="p-3 bg-gray-50 rounded-xl border border-gray-150">
              <p class="text-xs text-gray-400 font-semibold">Examen: Hemograma Completo</p>
              <p class="text-sm font-semibold text-gray-900">Paciente: Lucía Vega</p>
              <span class="mt-2 inline-block badge warning">Pendiente Toma</span>
            </div>
            <div class="p-3 bg-gray-50 rounded-xl border border-gray-150">
              <p class="text-xs text-gray-400 font-semibold">Examen: Glucosa</p>
              <p class="text-sm font-semibold text-gray-900">Paciente: Daniel Salazar</p>
              <span class="mt-2 inline-block badge success">Resultado Disponible</span>
            </div>
          </div>
        </section>
      </aside>
    </section>
  </div>

  <!-- ApexCharts Script Loading and Setup -->
  <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
  <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Chart 1: Donut Orders Status
        new ApexCharts(document.querySelector("#chart-orders-status"), {
            series: [5, 3, 12],
            labels: ['Pendiente Toma', 'En Análisis', 'Completados'],
            chart: { type: 'donut', height: '100%' },
            colors: ['#f59e0b', '#3b82f6', '#10b981'],
            legend: { position: 'bottom' }
        }).render();

        // Chart 2: Bar Orders By Doctor
        new ApexCharts(document.querySelector("#chart-orders-doctor"), {
            series: [{ name: 'Pedidos', data: [12, 8, 4] }],
            chart: { type: 'bar', height: '100%', toolbar: {show: false} },
            colors: ['#334155'],
            plotOptions: { bar: { borderRadius: 4, horizontal: true } },
            xaxis: { categories: ['Dra. Sofía Cárdenas', 'Dr. Andrés Molina', 'Otros'] }
        }).render();

        // Chart 3: Area Orders Timeline
        new ApexCharts(document.querySelector("#chart-orders-timeline"), {
            series: [{ name: 'Pedidos Totales', data: [3, 5, 2, 7, 5, 8, 9] }],
            chart: { type: 'area', height: '100%', toolbar: {show: false} },
            colors: ['#475569'],
            stroke: { curve: 'smooth', width: 2 },
            xaxis: { categories: ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] }
        }).render();
    });
  </script>
@endsection
