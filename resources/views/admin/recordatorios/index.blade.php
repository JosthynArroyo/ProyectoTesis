@extends('layouts.admin')

@section('title', 'Recordatorios')
@section('header-title', 'Recordatorios')
@section('header-subtitle', 'Recordatorios pendientes por revision')

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

    @if($errors->any())
      <x-ui.alert tone="error">{{ $errors->first() }}</x-ui.alert>
    @endif

    <section class="stat-grid">
      <x-ui.stat label="Pendientes por enviar" :value="$stats['pendientes']" tone="amber">
        <x-slot:icon><i class="ri-notification-3-line"></i></x-slot:icon>
      </x-ui.stat>
      <x-ui.stat label="Con WhatsApp valido" :value="$stats['con_telefono']" tone="teal">
        <x-slot:icon><i class="ri-whatsapp-line"></i></x-slot:icon>
      </x-ui.stat>
      <x-ui.stat label="Sin telefono valido" :value="$stats['sin_telefono']" tone="rose">
        <x-slot:icon><i class="ri-phone-off-line"></i></x-slot:icon>
      </x-ui.stat>
    </section>

    <section class="card p-6">
      <div class="page-header">
        <div class="page-header__info">
          <h2>Listado de recordatorios</h2>
          <p>Se muestran las citas agendadas cuyo recordatorio ya debe ser gestionado.</p>
        </div>
      </div>

      @if($recordatorios->count())
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
                <th>Telefono</th>
                <th>Estado del recordatorio</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              @foreach($recordatorios as $recordatorio)
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
                  <td data-label="Telefono">
                    <div class="text-sm font-semibold text-gray-900">{{ $cita?->paciente?->telefono ?? 'No registrado' }}</div>
                    @if($recordatorio->telefono_normalizado)
                      <div class="mt-1 text-xs text-gray-500">WhatsApp: {{ $recordatorio->telefono_normalizado }}</div>
                    @else
                      <div class="mt-1">
                        <x-ui.badge tone="neutral">Sin telefono valido</x-ui.badge>
                      </div>
                    @endif
                  </td>
                  <td data-label="Estado del recordatorio">
                    <x-ui.badge tone="warning">Pendiente</x-ui.badge>
                    <div class="mt-1 text-xs text-gray-500">Objetivo: {{ $recordatorio->recordar_en?->format('d/m/Y H:i') ?? '-' }}</div>
                    @if(! $puedeGestionar)
                      <div class="mt-1 text-xs text-amber-600">Se habilita desde el dia anterior segun la regla activa.</div>
                    @endif
                  </td>
                  <td data-label="Acciones">
                    <div class="action-group flex w-full flex-wrap justify-end gap-2">
                      @if($whatsappDisponible)
                        <a href="{{ $recordatorio->whatsapp_url }}" target="_blank" rel="noopener" class="btn btn-primary btn-sm">
                          <i class="ri-whatsapp-line"></i> Enviar por WhatsApp
                        </a>
                      @elseif($puedeGestionar)
                        <button
                          type="button"
                          class="btn btn-primary btn-sm cursor-not-allowed opacity-60"
                          disabled
                          aria-disabled="true"
                          title="La cita no tiene un telefono de WhatsApp valido."
                        >
                          <i class="ri-whatsapp-line"></i> Sin WhatsApp valido
                        </button>
                      @else
                        <button
                          type="button"
                          class="btn btn-primary btn-sm cursor-not-allowed opacity-60"
                          disabled
                          aria-disabled="true"
                          title="El envio por WhatsApp aun no esta habilitado."
                        >
                          <i class="ri-whatsapp-line"></i> Proximamente
                        </button>
                      @endif

                      <button type="button" class="btn btn-outline btn-sm" data-copy-target="{{ $mensajeId }}">
                        <i class="ri-file-copy-line"></i> Copiar mensaje
                      </button>

                      @if($puedeGestionar)
                        <form method="POST" action="{{ route('admin.recordatorios.enviado', $recordatorio) }}">
                          @csrf
                          @method('PATCH')
                          <button type="submit" class="btn btn-outline btn-sm">
                            <i class="ri-check-line"></i> Marcar enviado
                          </button>
                        </form>

                        <form method="POST" action="{{ route('admin.recordatorios.omitido', $recordatorio) }}">
                          @csrf
                          @method('PATCH')
                          <button type="submit" class="btn btn-ghost btn-sm">
                            <i class="ri-close-line"></i> Omitir
                          </button>
                        </form>
                      @else
                        <button
                          type="button"
                          class="btn btn-outline btn-sm cursor-not-allowed opacity-60"
                          disabled
                          aria-disabled="true"
                          title="La gestion manual del recordatorio aun no esta habilitada."
                        >
                          <i class="ri-check-line"></i> Marcar enviado
                        </button>

                        <button
                          type="button"
                          class="btn btn-ghost btn-sm cursor-not-allowed opacity-60"
                          disabled
                          aria-disabled="true"
                          title="La gestion manual del recordatorio aun no esta habilitada."
                        >
                          <i class="ri-close-line"></i> Omitir
                        </button>
                      @endif
                    </div>

                    <p class="mt-2 text-left text-xs text-gray-500">
                      {{ $puedeGestionar
                          ? 'El recordatorio ya puede gestionarse.'
                          : 'La cita permanece visible como pendiente, pero su envio se habilita solo cuando entra en la ventana del dia anterior.' }}
                    </p>
                    <textarea id="{{ $mensajeId }}" class="hidden">{{ $recordatorio->mensaje_sugerido }}</textarea>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 text-sm text-gray-500">
          <div>Pagina {{ $recordatorios->currentPage() }} de {{ $recordatorios->lastPage() }}</div>
          {!! $recordatorios->withQueryString()->links() !!}
        </div>
      @else
        <div class="mt-4">
          <x-ui.empty-state title="No hay recordatorios pendientes." message="Solo apareceran aqui las citas agendadas con al menos 1 dia de anticipacion desde las 12:00 PM del dia anterior.">
            <div class="mt-4 flex flex-wrap justify-center gap-3">
              <a href="{{ route('admin.recordatorios.enviados') }}" class="btn btn-primary">
                <i class="ri-check-double-line"></i> Recordatorios enviados
              </a>
            </div>
          </x-ui.empty-state>
        </div>
      @endif
    </section>
  </div>
@endsection

@push('scripts')
  <script>
    document.addEventListener('click', async function (event) {
      const trigger = event.target.closest('[data-copy-target]');
      if (!trigger) {
        return;
      }

      const source = document.getElementById(trigger.dataset.copyTarget);
      if (!source) {
        return;
      }

      const text = source.value || source.textContent || '';
      const original = trigger.dataset.originalLabel || trigger.innerHTML;
      trigger.dataset.originalLabel = original;

      try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          await navigator.clipboard.writeText(text);
        } else {
          const helper = document.createElement('textarea');
          helper.value = text;
          document.body.appendChild(helper);
          helper.select();
          document.execCommand('copy');
          document.body.removeChild(helper);
        }

        trigger.innerHTML = '<i class="ri-check-line"></i> Mensaje copiado';
        window.setTimeout(() => {
          trigger.innerHTML = original;
        }, 1800);
      } catch (error) {
        trigger.innerHTML = '<i class="ri-close-line"></i> No se pudo copiar';
        window.setTimeout(() => {
          trigger.innerHTML = original;
        }, 1800);
      }
    });
  </script>
@endpush
