{{-- resources/views/paciente/citas/index.blade.php --}}
@extends('layouts.paciente')
@section('title', 'Mis citas médicas')
@section('body-class', 'paciente-body--citas')
@section('header-title','Mis citas')
@section('header-subtitle','Gestiona tus citas en un solo lugar')

@php
  $bloqueoPagosPendientes = $bloqueoPagosPendientes ?? false;
  $highlightCita = session('highlight_cita');
@endphp

@section('main')
<div class="space-y-6">
  <div class="panel-action-bar">
    @if($bloqueoPagosPendientes)
      <button type="button" class="btn btn-primary btn-full-mobile cursor-not-allowed opacity-60" disabled aria-disabled="true" title="Tienes órdenes de cobro vencidas de citas concluidas. Regulariza tu cuenta para agendar una nueva cita.">
        <i class="ri-lock-2-line"></i>
        <span class="cta-text">Agendar cita</span>
      </button>
    @else
      <a href="{{ route('paciente.crear-cita') }}" class="btn btn-primary btn-full-mobile" aria-label="Agendar nueva cita">
        <i class="ri-add-line"></i>
        <span class="cta-text">Agendar cita</span>
      </a>
    @endif
  </div>

  <section class="card p-6" aria-label="Barra de búsqueda y filtros">
    <form class="flex flex-wrap items-end gap-3" method="GET" action="{{ url()->current() }}">
      <div class="flex flex-1 items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2" role="search">
        <i class="ri-search-line text-gray-400"></i>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar por doctor o especialidad..." aria-label="Buscar citas" class="w-full bg-transparent text-sm text-gray-700"/>
      </div>
      @error('q')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
      @php $est = request('estado'); @endphp
      <div>
        <select name="estado" aria-label="Filtrar por estado" class="form-select">
          <option value="all" @selected($est==='' || $est==='all')>Todos los estados</option>
          <option value="pendiente"  {{ $est==='pendiente' ? 'selected' : '' }}>En revisión</option>
          <option value="confirmada" {{ $est==='confirmada' ? 'selected' : '' }}>Confirmada</option>
          <option value="cancelada"  {{ $est==='cancelada' ? 'selected' : '' }}>Cancelada</option>
          <option value="realizada"  {{ $est==='realizada' ? 'selected' : '' }}>Realizada</option>
          <option value="no_se_presento"  {{ $est==='no_se_presento' ? 'selected' : '' }}>No se presentó</option>
        </select>
        @error('estado')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
      </div>
      <button class="btn btn-outline btn-sm" type="submit" aria-label="Aplicar filtros">
        <i class="ri-filter-3-line"></i>
        Filtrar
      </button>
    </form>
  </section>

  @if(session('success') || session('error'))
    <div aria-live="polite" aria-atomic="true">
      @if(session('success'))
        <x-ui.alert tone="success" title="Citas actualizadas">
          {{ session('success') }}
          <div class="mt-3">
            <a class="btn btn-primary btn-sm" href="{{ session('success_action_url', route('paciente.citas')) }}">
              {{ session('success_action_label', 'Ver mis citas') }}
            </a>
          </div>
        </x-ui.alert>
      @endif
      @if(session('error'))
        <x-ui.alert tone="error" role="alert">{{ session('error') }}</x-ui.alert>
      @endif
    </div>
  @endif

  @if($bloqueoPagosPendientes)
    <x-ui.alert tone="warning">
      Tienes órdenes de cobro vencidas de citas concluidas. Regulariza tu cuenta para agendar una nueva cita.
      <a href="{{ route('paciente.pagos.index') }}" class="font-semibold underline">Ir a Órdenes de cobro</a>
    </x-ui.alert>
  @endif

  <section class="grid gap-4" aria-label="Listado de citas">
  @php $collection = $citas && method_exists($citas, 'getCollection') ? $citas->getCollection() : collect($citas ?? []); @endphp

    @if($collection->isEmpty())
      <x-ui.empty-state title="{{ $emptyMessage ?? 'No tienes citas registradas.' }}">
        @if(empty($emptyMessage) || Str::startsWith($emptyMessage, 'No tienes citas registradas'))
          <p>Agenda tu primera cita para verla aquí con su estado y acciones.</p>
          @if($bloqueoPagosPendientes)
            <button type="button" class="btn btn-primary cursor-not-allowed opacity-60" disabled aria-disabled="true">Agendar cita</button>
            <p class="text-xs text-amber-700">Tienes órdenes de cobro vencidas de citas concluidas. Regulariza tu cuenta para agendar una nueva cita.</p>
          @else
            <a href="{{ route('paciente.crear-cita') }}" class="btn btn-primary">
              <i class="ri-add-line"></i>
              Agendar cita
            </a>
          @endif
        @endif
      </x-ui.empty-state>
    @else
      @foreach($collection as $cita)
        <article id="cita-{{ $cita->id }}" class="card p-5 {{ $highlightCita === $cita->id ? 'record-highlight' : '' }}">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-center gap-3">
              <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-100 text-sm font-semibold text-gray-600" aria-hidden="true">
                @php $name = optional($cita->doctor)->name ?? 'DR'; $ini = mb_substr(trim($name),0,2,'UTF-8'); @endphp
                {{ mb_strtoupper($ini,'UTF-8') }}
              </div>
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <h3 class="doctor-name text-base font-semibold text-gray-900">{{ optional($cita->doctor)->name ?? 'Sin asignar' }}</h3>
                  @switch($cita->estado)
                    @case('pendiente')  <span class="badge warning">En revisión</span>  @break
                    @case('confirmada') <span class="badge info">Confirmada</span> @break
                    @case('cancelada')  <span class="badge danger">Cancelada</span>   @break
                    @case('realizada')  <span class="badge success">Realizada</span>     @break
                    @case('no_se_presento')  <span class="badge danger">No se presentó</span> @break
                    @default            <span class="badge info">{{ ucfirst($cita->estado) }}</span>
                  @endswitch
                </div>
                <p class="text-sm text-gray-500">{{ optional($cita->especialidad)->nombre ?? 'Sin especialidad' }}</p>
              </div>
            </div>
            <span class="chip" aria-label="Fecha y hora">
              <i class="ri-time-line"></i>
              {{ \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') }} ·
              {{ strlen($cita->hora ?? '') >= 5 ? substr($cita->hora, 0, 5) : ($cita->hora ?? '') }}
            </span>
          </div>

          <div class="mt-4 flex flex-wrap gap-4 text-sm text-gray-600">
            <span><strong>Fecha:</strong> {{ \Carbon\Carbon::parse($cita->fecha)->format('Y/m/d') }}</span>
            <span><strong>Hora:</strong> {{ \Carbon\Carbon::parse($cita->hora)->format('H:i') }}</span>
          </div>

          <div class="mt-4 rounded-2xl border border-gray-200 bg-gray-50 p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p class="text-xs uppercase tracking-widest text-gray-500">Comprobante de cita</p>
                <p class="text-sm font-semibold text-gray-900">{{ $cita->folio_cita ?: 'Se emitirá al descargar' }}</p>
                <p class="text-xs text-gray-500">Sirve para validar en recepción que la cita te pertenece.</p>
              </div>
              <span class="badge {{ $cita->comprobanteEstaVigente() ? 'success' : 'danger' }}">
                {{ $cita->comprobanteEstaVigente() ? 'Vigente' : 'Sin vigencia' }}
              </span>
            </div>

            @if($cita->token_validacion)
              <p class="mt-2 text-xs text-gray-500">Código: {{ $cita->token_validacion }}</p>
            @endif

            <div class="mt-3 flex flex-wrap gap-2">
              <a class="btn btn-outline btn-sm" href="{{ route('paciente.citas.comprobante.pdf', $cita) }}" target="_blank" rel="noopener">
                <i class="ri-file-download-line"></i>
                Descargar comprobante
              </a>
              @if($cita->token_validacion)
                <a class="btn btn-outline btn-sm" href="{{ route('citas.comprobante.show', $cita->token_validacion) }}">
                  <i class="ri-qr-code-line"></i>
                  Validar comprobante
                </a>
              @endif
            </div>

            @if(!$cita->comprobanteEstaVigente())
              <p class="mt-3 rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700">
                Este comprobante ya no está vigente porque la cita está {{ strtolower($cita->estadoComprobante()) }}.
              </p>
            @endif
          </div>

          @if(!in_array($cita->estado, ['cancelada','realizada','no_se_presento']))
            <div class="mt-4 flex flex-wrap gap-2">
              <form action="{{ route('paciente.citas.cancelar', $cita->id) }}" method="POST" style="display:inline-block">
                @csrf
                <button type="submit" class="btn btn-danger btn-sm" aria-label="Cancelar cita">
                  <i class="ri-close-line"></i>
                  Cancelar
                </button>
              </form>
              <a class="btn btn-outline btn-sm" href="{{ route('paciente.editar-cita', $cita->id) }}" aria-label="Reagendar cita">
                <i class="ri-calendar-line"></i>
                Reagendar
              </a>
            </div>
          @endif
        </article>
      @endforeach
    @endif
  </section>

  @if(method_exists($citas, 'links'))
    <div class="flex justify-center">
      {{ $citas->appends(['q'=>request('q'),'estado'=>request('estado')])->links() }}
    </div>
  @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const highlightId = @json($highlightCita);
  if (!highlightId) {
    return;
  }

  const target = document.getElementById(`cita-${highlightId}`);
  if (!target) {
    return;
  }

  target.scrollIntoView({ behavior: 'smooth', block: 'center' });
});
</script>
@endpush
