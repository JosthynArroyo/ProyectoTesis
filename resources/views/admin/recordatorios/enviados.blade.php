@extends('layouts.admin')

@section('title', 'Recordatorios enviados')
@section('header-title', 'Recordatorios enviados')
@section('header-subtitle', 'Historial de recordatorios ya gestionados')
@section('back-url', route('admin.recordatorios.index'))

@section('main')
  <div class="admin-recordatorios-page space-y-6">
    <div class="panel-action-bar">
      <x-ui.context-pill :label="'Total enviados'">
        <x-slot:icon><i class="ri-check-double-line"></i></x-slot:icon>
        {{ $recordatoriosEnviados->total() }}
      </x-ui.context-pill>
    </div>

    @if(session('success'))
      <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
    @endif

    @if($errors->any())
      <x-ui.alert tone="error">{{ $errors->first() }}</x-ui.alert>
    @endif

    <section class="card p-6">
      <div class="page-header">
        <div class="page-header__info">
          <h2>Historial de envios</h2>
          <p>Se muestran los recordatorios ya gestionados como enviados.</p>
        </div>
      </div>

      @if($recordatoriosEnviados->count())
        <div class="mt-4 table-shell table-responsive-cards recordatorios-table-shell overflow-x-auto">
          <table class="table recordatorios-table w-full min-w-[1180px] xl:min-w-[1080px] 2xl:min-w-full">
            <thead>
              <tr>
                <th>Paciente</th>
                <th>Doctor</th>
                <th>Especialidad</th>
                <th>Fecha</th>
                <th>Hora</th>
                <th>Telefono</th>
                <th>Enviado el</th>
                <th>Gestionado por</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody>
              @foreach($recordatoriosEnviados as $recordatorioEnviado)
                @php
                  $citaEnviada = $recordatorioEnviado->cita;
                @endphp
                <tr id="recordatorio-enviado-{{ $recordatorioEnviado->id }}">
                  <td data-label="Paciente">
                    <div class="text-sm font-semibold text-slate-900">{{ $citaEnviada?->paciente?->name ?? 'Paciente no disponible' }}</div>
                  </td>
                  <td data-label="Doctor">
                    <div class="text-sm font-semibold text-slate-900">{{ $citaEnviada?->doctor?->name ?? 'Sin doctor asignado' }}</div>
                  </td>
                  <td data-label="Especialidad">
                    <div class="text-sm text-slate-700">{{ $citaEnviada?->especialidad?->nombre ?? 'Sin especialidad' }}</div>
                  </td>
                  <td data-label="Fecha">
                    <div class="text-sm text-slate-700">{{ optional($citaEnviada?->fecha)->format('d/m/Y') ?? '-' }}</div>
                  </td>
                  <td data-label="Hora">
                    <div class="text-sm text-slate-700">{{ $recordatorioEnviado->cita_inicio_at?->format('H:i') ?? '-' }}</div>
                  </td>
                  <td data-label="Telefono">
                    <div class="text-sm font-semibold text-slate-900">{{ $citaEnviada?->paciente?->telefono ?? 'No registrado' }}</div>
                    @if($recordatorioEnviado->telefono_normalizado)
                      <div class="mt-1 text-xs text-slate-500">WhatsApp: {{ $recordatorioEnviado->telefono_normalizado }}</div>
                    @else
                      <div class="mt-1">
                        <x-ui.badge tone="neutral">Sin telefono valido</x-ui.badge>
                      </div>
                    @endif
                  </td>
                  <td data-label="Enviado el">
                    <div class="text-sm text-slate-700">{{ $recordatorioEnviado->enviado_at?->format('d/m/Y H:i') ?? '-' }}</div>
                  </td>
                  <td data-label="Gestionado por">
                    <div class="text-sm text-slate-700">{{ $recordatorioEnviado->gestionadoPor?->name ?? 'No registrado' }}</div>
                  </td>
                  <td data-label="Estado">
                    <x-ui.badge tone="success">Enviado</x-ui.badge>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 text-sm text-slate-500">
          <div>Pagina {{ $recordatoriosEnviados->currentPage() }} de {{ $recordatoriosEnviados->lastPage() }}</div>
          {!! $recordatoriosEnviados->withQueryString()->links() !!}
        </div>
      @else
        <div class="mt-4">
          <x-ui.empty-state title="No hay recordatorios enviados." message="Cuando un recordatorio sea marcado como enviado, se mostrara en este historial."></x-ui.empty-state>
        </div>
      @endif
    </section>
  </div>
@endsection
