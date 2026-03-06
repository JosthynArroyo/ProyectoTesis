@extends('layouts.paciente')
@section('title', 'Resultados de Laboratorio')
@section('body-class', 'paciente-body--laboratorio')
@section('header-title','Resultados de laboratorio')
@section('header-subtitle','Consulta ordenes y resultados')

@php($highlightItem = session('highlight_lab_item'))

@section('main')
<div class="space-y-6">
  <header class="card p-6">
    <div class="page-header">
      <div class="page-header__info">
        <p class="text-xs uppercase tracking-widest text-slate-500">Laboratorio</p>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Resultados y ordenes</h1>
        <p class="text-slate-600">Consulta el estado de tus examenes, descarga resultados y revisa preparacion antes de asistir.</p>
      </div>
      <div class="page-header__actions">
        <a class="btn btn-outline btn-full-mobile" href="{{ route('paciente.crear-cita') }}">Agendar cita medica</a>
        <a class="btn btn-primary btn-full-mobile" href="{{ route('paciente.laboratorio.solicitar') }}">Solicitar examen</a>
      </div>
    </div>
  </header>

  @if ($errors->any())
    <x-ui.alert tone="error">{{ $errors->first() }}</x-ui.alert>
  @endif

  @if (session('success'))
    <x-ui.alert tone="success" title="Laboratorio actualizado">
      {{ session('success') }}
      @if(session('success_action_url') && session('success_action_label'))
        <div class="mt-3">
          <a class="btn btn-primary btn-sm" href="{{ session('success_action_url') }}">{{ session('success_action_label') }}</a>
        </div>
      @endif
    </x-ui.alert>
  @endif

  <section class="card p-6">
    <div class="page-header">
      <div class="page-header__info">
        <h2>Mis examenes y resultados</h2>
        <p>Se integran aqui tanto las ordenes tradicionales como tus auto-solicitudes.</p>
      </div>
    </div>

    @if ($ordenes->count())
      <div class="mt-4 grid gap-4">
        @foreach($ordenes as $orden)
          <article
            id="lab-item-{{ $orden->uid }}"
            class="rounded-3xl border border-slate-200 bg-white/95 p-5 shadow-sm {{ $highlightItem === $orden->uid ? 'record-highlight' : '' }}"
          >
            <div class="flex flex-wrap items-start justify-between gap-4">
              <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                  <h3 class="text-base font-semibold text-slate-900">{{ $orden->title }}</h3>
                  <span class="badge {{ $orden->status_tone }}">{{ $orden->status_label }}</span>
                </div>
                <p class="text-sm text-slate-500">{{ $orden->subtitle }}</p>
                <div class="flex flex-wrap gap-4 text-sm text-slate-600">
                  <span><strong>Fecha:</strong> {{ $orden->date_label }}</span>
                </div>
              </div>

              <div class="flex items-center gap-2">
                @if($orden->primary_action_url)
                  <a href="{{ $orden->primary_action_url }}" class="btn btn-primary btn-sm">Descargar</a>
                @endif
                <div class="relative">
                  <button
                    type="button"
                    class="btn btn-outline btn-sm"
                    data-kebab="lab-actions-{{ $orden->uid }}"
                    aria-label="Mas acciones para {{ $orden->title }}"
                  >
                    <i class="ri-more-2-fill"></i>
                  </button>
                  <div id="lab-actions-{{ $orden->uid }}" class="kebab-menu" role="menu">
                    @foreach($orden->secondary_actions as $action)
                      <a class="btn btn-ghost btn-sm justify-start" href="{{ $action['url'] }}" role="menuitem">
                        {{ $action['label'] }}
                      </a>
                    @endforeach
                  </div>
                </div>
              </div>
            </div>

            @if($orden->summary || $orden->preparation || $orden->notes)
              <div class="mt-4 grid gap-3 md:grid-cols-3">
                @if($orden->summary)
                  <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
                    <p class="text-xs uppercase tracking-widest text-slate-500">Resumen</p>
                    <p class="mt-2 text-sm text-slate-700">{{ $orden->summary }}</p>
                  </div>
                @endif
                @if($orden->preparation)
                  <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
                    <p class="text-xs uppercase tracking-widest text-slate-500">Preparacion</p>
                    <p class="mt-2 text-sm text-slate-700">{{ $orden->preparation }}</p>
                  </div>
                @endif
                @if($orden->notes)
                  <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
                    <p class="text-xs uppercase tracking-widest text-slate-500">Indicaciones</p>
                    <p class="mt-2 text-sm text-slate-700">{{ $orden->notes }}</p>
                  </div>
                @endif
              </div>
            @endif
          </article>
        @endforeach
      </div>
    @else
      <div class="mt-4">
        <x-ui.empty-state title="Aun no tienes examenes registrados." message="Cuando solicites un examen o el laboratorio publique un resultado, aparecera aqui con su estado y las acciones disponibles.">
          <div class="mt-4 flex flex-wrap justify-center gap-3">
            <a class="btn btn-primary" href="{{ route('paciente.laboratorio.solicitar') }}">Solicitar examen</a>
            <a class="btn btn-outline" href="{{ route('paciente.crear-cita') }}">Agendar cita medica</a>
          </div>
        </x-ui.empty-state>
      </div>
    @endif

    <div class="mt-6">
      {{ $ordenes->links() }}
    </div>
  </section>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const highlightId = @json($highlightItem);
  if (!highlightId) {
    return;
  }

  const target = document.getElementById(`lab-item-${highlightId}`);
  if (!target) {
    return;
  }

  target.scrollIntoView({ behavior: 'smooth', block: 'center' });
});
</script>
@endpush
