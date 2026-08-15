@extends('layouts.demo')
@section('title', 'Recordatorios - Demo')
@section('header-title', 'Recordatorios')
@section('header-subtitle', 'Recordatorios pendientes por revisión (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-admin-demo')
@endsection

@php
  $recordatoriosCollection = collect($recordatorios)->map(function($r, $index) {
      $rec = new \stdClass();
      $rec->id = $r['id'];
      
      $cita = new \App\Models\Cita();
      $cita->id = $index + 10;
      $appDate = str_replace('/', '-', $r['appointment']);
      $cita->fecha = \Carbon\Carbon::parse($appDate);
      $cita->hora = '08:00';
      $cita->estado = 'pendiente';
      
      $p = new \App\Models\User(['name' => $r['patient'], 'telefono' => $r['phone']]);
      $cita->setRelation('paciente', $p);
      
      $d = new \App\Models\User(['name' => 'Dra. Sofia Cardenas']);
      $cita->setRelation('doctor', $d);
      
      $esp = new \App\Models\Especialidad(['nombre' => 'Pediatría']);
      $cita->setRelation('especialidad', $esp);
      
      $rec->cita = $cita;
      $rec->cita_inicio_at = \Carbon\Carbon::parse($appDate);
      $rec->recordar_en = \Carbon\Carbon::parse($appDate)->subDay();
      $rec->puede_gestionar = true;
      $rec->whatsapp_url = 'https://wa.me/' . preg_replace('/\D/', '', $r['phone']);
      $rec->telefono_normalizado = $r['phone'];
      $rec->mensaje = "Hola " . $r['patient'] . ", te recordamos tu cita médica mañana a las 08:00.";
      
      return $rec;
  });
  
  $reglaActiva = '24 horas antes de la cita';
  $stats = [
      'pendientes' => $recordatoriosCollection->count(),
      'con_telefono' => $recordatoriosCollection->count(),
      'sin_telefono' => 0,
  ];
@endphp

@section('main')
  <div class="admin-recordatorios-page space-y-6">
    <div class="panel-action-bar">
      <x-ui.context-pill :label="'Regla activa'">
        <x-slot:icon><i class="ri-time-line"></i></x-slot:icon>
        {{ $reglaActiva }}
      </x-ui.context-pill>
    </div>

    @if(session('success'))
      <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
    @endif

    <section class="stat-grid">
      <x-ui.stat label="Pendientes por enviar" :value="$stats['pendientes']" tone="amber">
        <x-slot:icon><i class="ri-notification-3-line"></i></x-slot:icon>
      </x-ui.stat>
      <x-ui.stat label="Con WhatsApp válido" :value="$stats['con_telefono']" tone="teal">
        <x-slot:icon><i class="ri-whatsapp-line"></i></x-slot:icon>
      </x-ui.stat>
      <x-ui.stat label="Sin teléfono válido" :value="$stats['sin_telefono']" tone="rose">
        <x-slot:icon><i class="ri-phone-off-line"></i></x-slot:icon>
      </x-ui.stat>
    </section>

    <section class="card p-6">
      <div class="page-header">
        <div class="page-header__info">
          <h2>Listado de recordatorios</h2>
          <p>Se muestran las citas agendadas cuyo recordatorio ya debe ser gestionado (Simulado).</p>
        </div>
      </div>

      @if($recordatoriosCollection->count())
        <div class="mt-4 table-shell table-responsive-cards recordatorios-table-shell overflow-x-auto">
          <table class="table recordatorios-table w-full min-w-[1180px] xl:min-w-[1080px] 2xl:min-w-full">
            <thead>
              <tr>
                <th>Paciente</th>
                <th>Doctor</th>
                <th>Especialidad</th>
                <th>Fecha</th>
                <th>Hora</th>
                <th>Estado de la cita</th>
                <th>Teléfono</th>
                <th>Estado del recordatorio</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              @foreach($recordatoriosCollection as $recordatorio)
                @php
                  $cita = $recordatorio->cita;
                  $mensajeId = 'mensaje-recordatorio-'.$recordatorio->id;
                  $puedeGestionar = (bool) ($recordatorio->puede_gestionar ?? false);
                  $whatsappDisponible = $puedeGestionar && filled($recordatorio->whatsapp_url);
                  $estadoCita = (string) ($cita?->estado ?? '');
                  $estadoCitaLabel = match ($estadoCita) {
                    'confirmada' => 'Confirmada',
                    'pendiente' => 'Pendiente',
                    default => ucfirst($estadoCita ?: 'Sin estado'),
                  };
                  $estadoCitaTone = $estadoCita === 'confirmada' ? 'success' : 'warning';
                @endphp
                <tr id="recordatorio-{{ $recordatorio->id }}">
                  <td data-label="Paciente">
                    <div class="text-sm font-semibold text-gray-900">{{ $cita?->paciente?->name ?? 'Paciente no disponible' }}</div>
                  </td>
                  <td data-label="Doctor">
                    <div class="text-sm font-semibold text-gray-900">{{ $cita?->doctor?->name ?? 'Sin doctor asignado' }}</div>
                  </td>
                  <td data-label="Especialidad">
                    <div class="text-sm text-gray-700">{{ $cita?->especialidad?->nombre ?? 'Sin especialidad' }}</div>
                  </td>
                  <td data-label="Fecha">
                    <div class="text-sm text-gray-700">{{ optional($cita?->fecha)->format('d/m/Y') ?? '-' }}</div>
                  </td>
                  <td data-label="Hora">
                    <div class="text-sm text-gray-700">{{ $recordatorio->cita_inicio_at?->format('H:i') ?? '-' }}</div>
                  </td>
                  <td data-label="Estado de la cita">
                    <x-ui.badge :tone="$estadoCitaTone">{{ $estadoCitaLabel }}</x-ui.badge>
                  </td>
                  <td data-label="Teléfono">
                    <div class="text-sm font-semibold text-gray-900">{{ $cita?->paciente?->telefono ?? 'No registrado' }}</div>
                    @if($recordatorio->telefono_normalizado)
                      <div class="mt-1 text-xs text-gray-500">WhatsApp: {{ $recordatorio->telefono_normalizado }}</div>
                    @else
                      <div class="mt-1">
                        <x-ui.badge tone="neutral">Sin teléfono válido</x-ui.badge>
                      </div>
                    @endif
                  </td>
                  <td data-label="Estado del recordatorio">
                    <x-ui.badge tone="warning">Pendiente</x-ui.badge>
                    <div class="mt-1 text-xs text-gray-500">Objetivo: {{ $recordatorio->recordar_en?->format('d/m/Y H:i') ?? '-' }}</div>
                  </td>
                  <td data-label="Acciones">
                    <div class="action-group flex w-full flex-wrap justify-end gap-2">
                      @if($whatsappDisponible)
                        <a href="{{ $recordatorio->whatsapp_url }}" target="_blank" rel="noopener" class="btn btn-primary btn-sm">
                          <i class="ri-whatsapp-line"></i> Enviar por WhatsApp
                        </a>
                      @endif

                      <div style="display:none" id="{{ $mensajeId }}">{{ $recordatorio->mensaje }}</div>
                      <button type="button" class="btn btn-outline btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('{{ $mensajeId }}').innerText); alert('Mensaje copiado.');">
                        <i class="ri-file-copy-line"></i> Copiar mensaje
                      </button>

                      <form method="POST" action="{{ route('demo.admin.recordatorios.enviado', $recordatorio->id) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-outline btn-sm">
                          <i class="ri-check-line"></i> Marcar enviado
                        </button>
                      </form>

                      <form method="POST" action="{{ route('demo.admin.recordatorios.omitido', $recordatorio->id) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-ghost btn-sm">
                          <i class="ri-close-line"></i> Omitir
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <div class="mt-4">
          <x-ui.empty-state title="No hay recordatorios pendientes" message="Todos los recordatorios han sido enviados u omitidos." />
        </div>
      @endif
    </section>
  </div>
@endsection
