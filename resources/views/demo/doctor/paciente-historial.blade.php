@extends('layouts.demo')
@section('title', 'Expediente clínico del paciente - Demo')
@section('activeSidebar', 'pacientes')
@section('header-title', 'Lucía Vega')
@section('header-subtitle', 'Expediente clínico longitudinal (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-doctor-demo')
@endsection

@section('main')
<div class="space-y-6">
  <div class="panel-action-bar">
    <a href="{{ route('demo.doctor.citas') }}" class="btn btn-outline">
      <i class="ri-arrow-left-line"></i> Volver a citas
    </a>
  </div>

  @if(session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  <!-- Perfil Paciente -->
  <section class="card p-6">
    <div class="flex flex-wrap items-center gap-4">
      <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-100 text-xl font-bold text-blue-700">
        LV
      </div>
      <div>
        <h2 class="text-xl font-bold text-gray-900">Lucía Vega</h2>
        <p class="text-sm text-gray-500">Cédula: 1756789012 | Edad: 8 años | Sexo: Femenino</p>
        <p class="text-sm text-gray-500">Representante: María Fernanda Vega (DNI: 1723456789, Tel: 0995140927)</p>
      </div>
    </div>
  </section>

  <!-- Paneles del Expediente -->
  <div class="grid gap-6 lg:grid-cols-3">
    <!-- Alertas Médicas y Antecedentes -->
    <div class="lg:col-span-1 space-y-6">
      <section class="card p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-3"><i class="ri-alert-line text-amber-500"></i> Alertas Clínicas</h3>
        <div class="space-y-2">
          <span class="badge danger block text-center">Faringitis recurrente</span>
        </div>
      </section>

      <section class="card p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-3"><i class="ri-heart-pulse-line text-red-500"></i> Antecedentes</h3>
        <ul class="space-y-2 text-sm text-gray-750">
          <li><strong>Crónicos:</strong> Ninguno reportado.</li>
          <li><strong>Quirúrgicos:</strong> Amigdalectomía programada para revisión.</li>
          <li><strong>Alergias:</strong> Ninguna conocida (alergias de control negativas).</li>
        </ul>
      </section>
    </div>

    <!-- Problemas Activos y Medicación -->
    <div class="lg:col-span-2 space-y-6">
      <section class="card p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-3"><i class="ri-pulse-line text-blue-500"></i> Problemas Activos</h3>
        <div class="table-shell table-responsive-cards p-0">
          <table class="table">
            <thead>
              <tr>
                <th>Problema / Diagnóstico</th>
                <th>CIE-10</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Faringoamigdalitis aguda</td>
                <td>J03.9</td>
                <td><span class="badge warning">Activo</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="card p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-3"><i class="ri-medicine-bottle-line text-teal-500"></i> Medicación Activa</h3>
        <div class="table-shell table-responsive-cards p-0">
          <table class="table">
            <thead>
              <tr>
                <th>Medicamento</th>
                <th>Dosis</th>
                <th>Periodo</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Paracetamol Jarabe</td>
                <td>250mg cada 8 horas</td>
                <td>3 días</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </div>

  <!-- Citas y Notas Clínicas Longitudinales -->
  <section class="card p-6">
    <h3 class="text-base font-semibold text-gray-900 mb-3"><i class="ri-history-line"></i> Historial de Notas Clínicas (Longitudinal)</h3>
    <div class="table-shell table-responsive-cards p-0">
      <table class="table">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Doctor</th>
            <th>Diagnóstico Principal</th>
            <th>Indicaciones / Resumen SOAP</th>
            <th>Acción</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>{{ now()->format('d/m/Y') }}</td>
            <td>Dra. Sofía Cárdenas</td>
            <td>Faringoamigdalitis aguda (J03.9)</td>
            <td>Dolor al tragar desde hace tres días, con congestión y malestar durante la noche.</td>
            <td><a href="{{ route('demo.doctor.citas.soap', 1) }}" class="btn btn-outline btn-sm">Ver SOAP</a></td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</div>
@endsection
