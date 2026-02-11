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
          <h1 class="mt-2 text-3xl font-semibold sm:text-4xl">{{ $siteSettings->get('services.title', 'Especialidades médicas para tu bienestar') }}</h1>
          <p class="mt-2 text-slate-600">{{ $siteSettings->get('services.subtitle', 'Agenda en línea con médicos certificados y recibe seguimiento personalizado.') }}</p>
        </div>
        @auth
          <a href="{{ route('paciente.crear-cita') }}" class="btn btn-primary">{{ $siteSettings->get('services.cta_text', 'Agendar cita') }}</a>
        @else
          <a href="{{ route('login') }}" class="btn btn-primary">{{ $siteSettings->get('services.cta_text', 'Agendar cita') }}</a>
        @endauth
      </div>
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-[1fr_auto]">
      <div class="flex items-center gap-3 rounded-2xl border border-slate-200/40 bg-white/80 px-4 py-3 shadow-sm shadow-slate-200/30 focus-within:border-teal-200 focus-within:ring-2 focus-within:ring-teal-100">
        <i class="ri-search-line text-slate-400" aria-hidden="true"></i>
        <input type="search" id="svcSearch" placeholder="Buscar servicio o especialidad..." class="w-full appearance-none border-0 bg-transparent text-sm text-slate-700 placeholder:text-slate-400 outline-none ring-0 focus:outline-none focus:ring-0 focus:border-transparent">
      </div>
      <div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-slate-500" role="tablist">
        <button class="chip is-active rounded-full border border-slate-200 px-3 py-2" data-filter="all" role="tab">Todos</button>
        <button class="chip rounded-full border border-slate-200 px-3 py-2" data-filter="general" role="tab">General</button>
        <button class="chip rounded-full border border-slate-200 px-3 py-2" data-filter="especialidad" role="tab">Especialidades</button>
        <button class="chip rounded-full border border-slate-200 px-3 py-2" data-filter="diagnostico" role="tab">Diagnóstico</button>
        <button class="chip rounded-full border border-slate-200 px-3 py-2" data-filter="procedimiento" role="tab">Procedimientos</button>
      </div>
    </div>

    @php
      $metaMap = [
        'Dermatología' => ['tag' => 'especialidad', 'icon' => 'ri-user-heart-line', 'badge' => 'Cuidado dermatológico', 'badge2' => 'Consulta especializada'],
        'Medicina General' => ['tag' => 'general', 'icon' => 'ri-stethoscope-line', 'badge' => 'Consulta general', 'badge2' => 'Duración 20-30 min'],
        'Pediatría' => ['tag' => 'especialidad', 'icon' => 'ri-bear-smile-line', 'badge' => 'Atención infantil', 'badge2' => 'Controles preventivos'],
        'Ginecología' => ['tag' => 'especialidad', 'icon' => 'ri-women-line', 'badge' => 'Salud femenina', 'badge2' => 'Controles y asesorías'],
        'Laboratorio Clínico' => ['tag' => 'diagnostico', 'tag_label' => 'Diagnóstico', 'icon' => 'ri-test-tube-line', 'badge' => 'Previa orden', 'badge2' => 'Entrega 24-48 h'],
        'Odontología' => ['tag' => 'procedimiento', 'icon' => 'ri-tooth-line', 'badge' => 'Odontología general', 'badge2' => 'Limpieza y resinas'],
      ];
    @endphp

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" id="svcGrid">
      @foreach($especialidades as $esp)
        @php
          $meta = $metaMap[$esp->nombre] ?? ['tag' => 'especialidad', 'tag_label' => 'Especialidad', 'icon' => 'ri-stethoscope-line', 'badge' => 'Consulta', 'badge2' => 'Agendable'];
          $icon = $esp->icono ?? $meta['icon'];
        @endphp
        <article class="card p-5" data-tags="{{ $meta['tag'] }}">
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
              <a href="{{ route('login') }}" class="btn btn-outline w-full">Agendar</a>
            @endauth
          </div>
        </article>
      @endforeach
    </div>

    <p id="svcEmpty" class="mt-6 text-center text-sm text-slate-500" hidden>No hay servicios disponibles para la búsqueda realizada.</p>

    <div class="mt-10 flex flex-col items-center justify-center gap-3 text-center">
      @auth
        <a href="{{ route('paciente.crear-cita') }}" class="btn btn-primary"><i class="ri-calendar-check-line"></i> Agendar cita</a>
      @else
        <a href="{{ route('login') }}" class="btn btn-primary"><i class="ri-login-circle-line"></i> Ingresar para agendar</a>
        <p class="text-sm text-slate-500">O, si lo prefieres, agenda a través de nuestro chatbot.</p>
      @endauth
    </div>
  </div>
</section>
@include('partials.footer')
</div>
@endsection
