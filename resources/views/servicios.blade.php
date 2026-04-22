{{-- resources/views/servicios.blade.php --}}
@extends('layouts.navbar')

@section('title','Servicios - Clínica Don Bosco')

@section('main')
<div style="min-height:calc(100vh - 4.5rem);display:flex;flex-direction:column;">
<section class="section-pad section-pad--first">
  <div class="page-shell">
    <div class="glass-panel p-6 sm:p-8 text-slate-800">
      <div class="flex flex-wrap items-center justify-between gap-6">
        <div>
          <p class="text-xs uppercase tracking-widest text-slate-500">Servicios</p>
          <h1 class="mt-2 text-3xl font-semibold sm:text-4xl">{{ $siteSettings->get('services.title', 'Especialidades y servicios disponibles') }}</h1>
          <p class="mt-2 text-slate-600">{{ $siteSettings->get('services.subtitle', 'Explora las opciones de la clínica y agenda una cita según los horarios registrados en el sistema.') }}</p>
        </div>
        @auth
          <a href="{{ route('paciente.crear-cita') }}" class="btn btn-primary">{{ $siteSettings->get('services.cta_text', 'Agendar cita') }}</a>
        @else
          <a href="{{ url('/') . '?login=1' }}" class="btn btn-primary" data-login-trigger>{{ $siteSettings->get('services.cta_text', 'Agendar cita') }}</a>
        @endauth
      </div>
    </div>

    <form class="mt-6 grid gap-4 lg:grid-cols-[1fr_auto]" method="GET" action="{{ route('servicios.index') }}" role="search">
      <input type="hidden" name="tipo" value="{{ $tipo ?? 'all' }}">
      <div class="flex items-center gap-3 rounded-2xl border border-slate-200/40 bg-white/80 px-4 py-3 shadow-sm shadow-slate-200/30 focus-within:border-teal-200 focus-within:ring-2 focus-within:ring-teal-100">
        <i class="ri-search-line text-slate-400" aria-hidden="true"></i>
        <input type="search" name="q" value="{{ $q ?? '' }}" placeholder="Buscar servicio o especialidad..." class="w-full appearance-none border-0 bg-transparent text-sm text-slate-700 placeholder:text-slate-400 outline-none ring-0 focus:outline-none focus:ring-0 focus:border-transparent">
      </div>
      <div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-slate-500" role="tablist" aria-label="Tipo de servicio">
        @foreach($serviceTypes as $value => $label)
          <a
            class="chip {{ ($tipo ?? 'all') === $value ? 'is-active' : '' }} rounded-full border border-slate-200 px-3 py-2"
            href="{{ route('servicios.index', array_filter(['q' => $q ?? '', 'tipo' => $value === 'all' ? null : $value], fn ($item) => filled($item))) }}"
            role="tab"
            aria-selected="{{ ($tipo ?? 'all') === $value ? 'true' : 'false' }}"
          >{{ $label }}</a>
        @endforeach
      </div>
    </form>

    @if(($q ?? '') !== '' || ($tipo ?? 'all') !== 'all')
      <div class="mt-3">
        <a class="btn btn-ghost btn-sm" href="{{ route('servicios.index') }}">
          <i class="ri-refresh-line"></i> Limpiar filtros
        </a>
      </div>
    @endif

    @php
      $metaMap = [
        'Dermatología' => ['tag' => 'especialidad', 'icon' => 'ri-user-heart-line', 'badge' => 'Cita presencial', 'badge2' => 'Según disponibilidad'],
        'Medicina General' => ['tag' => 'general', 'icon' => 'ri-stethoscope-line', 'badge' => 'Cita presencial', 'badge2' => 'Según disponibilidad'],
        'Pediatría' => ['tag' => 'especialidad', 'icon' => 'ri-bear-smile-line', 'badge' => 'Atención pediátrica', 'badge2' => 'Según disponibilidad'],
        'Ginecología' => ['tag' => 'especialidad', 'icon' => 'ri-women-line', 'badge' => 'Cita presencial', 'badge2' => 'Según disponibilidad'],
        'Laboratorio Clínico' => ['tag' => 'diagnostico', 'tag_label' => 'Diagnóstico', 'icon' => 'ri-test-tube-line', 'badge' => 'Con solicitud médica', 'badge2' => 'Resultados en el sistema'],
        'Odontología' => ['tag' => 'procedimiento', 'icon' => 'ri-tooth-line', 'badge' => 'Cita presencial', 'badge2' => 'Según disponibilidad'],
      ];
    @endphp

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      @forelse($especialidades as $esp)
        @php
          $meta = $metaMap[$esp->nombre] ?? ['tag' => 'especialidad', 'tag_label' => 'Especialidad', 'icon' => 'ri-stethoscope-line', 'badge' => 'Cita presencial', 'badge2' => 'Según disponibilidad'];
          $icon = $esp->icono ?? $meta['icon'];
        @endphp
        <article class="card p-5">
          <div class="flex items-start justify-between gap-4">
            <div class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-600">
              <i class="{{ $icon }}"></i>
            </div>
            <span class="badge neutral">{{ $meta['tag_label'] ?? ucfirst($meta['tag']) }}</span>
          </div>
          <h3 class="mt-4 text-lg font-semibold text-slate-900">{{ $esp->nombre }}</h3>
          <p class="mt-2 text-sm text-slate-500">{{ $esp->descripcion }}</p>
          <div class="mt-4 flex flex-wrap gap-2">
            <span class="badge info">{{ $meta['badge'] }}</span>
            <span class="badge neutral">{{ $meta['badge2'] }}</span>
          </div>
          <div class="mt-5">
            @auth
              <a href="{{ route('paciente.crear-cita', ['especialidad' => $esp->id]) }}" class="btn btn-outline w-full">Agendar</a>
            @else
              <a href="{{ url('/') . '?login=1' }}" class="btn btn-outline w-full" data-login-trigger>Agendar</a>
            @endauth
          </div>
        </article>
      @empty
        <div class="sm:col-span-2 lg:col-span-3">
          <x-ui.empty-state title="No hay servicios disponibles para la busqueda realizada." message="Ajusta el texto o el tipo de servicio para consultar nuevamente." />
        </div>
      @endforelse
    </div>

    @if(method_exists($especialidades, 'links'))
      <div class="mt-6 flex justify-center">
        {{ $especialidades->links() }}
      </div>
    @endif

    <div class="mt-10 flex flex-col items-center justify-center gap-3 text-center">
      @auth
        <a href="{{ route('paciente.crear-cita') }}" class="btn btn-primary"><i class="ri-calendar-check-line"></i> Agendar cita</a>
      @else
        <a href="{{ url('/') . '?login=1' }}" class="btn btn-primary" data-login-trigger><i class="ri-login-circle-line"></i> Ingresar para agendar</a>
        <p class="text-sm text-slate-500">También puedes iniciar el agendamiento con el asistente virtual.</p>
      @endauth
    </div>
  </div>
</section>
@include('partials.footer')
</div>
@endsection
