{{-- resources/views/servicios.blade.php --}}
@extends('layouts.navbar')

@section('title','Servicios - Clínica Don Bosco')

@push('head')
  @vite(['resources/css/servicios.css','resources/js/servicios.js'])
@endpush

@section('main')
<section class="svc">
  <div class="svc__hero">
    <div class="svc__hero-inner">
      <h1>Nuestros Servicios</h1>
      <p>Especialidades médicas y procedimientos para tu bienestar. Agenda en línea en minutos.</p>
      @auth
        <a href="{{ route('paciente.dashboard') }}" class="btn-cta">Agendar Cita</a>
      @else
        <a href="{{ route('login') }}" class="btn-cta">Agendar Cita</a>
      @endauth
    </div>
  </div>

  <div class="svc__toolbar container">
    <div class="svc__search">
      <i class="ri-search-line" aria-hidden="true"></i>
      <input type="search" id="svcSearch" placeholder="Buscar servicio o especialidad…">
    </div>
    <div class="svc__filters" role="tablist">
      <button class="chip is-active" data-filter="all" role="tab">Todos</button>
      <button class="chip" data-filter="general" role="tab">General</button>
      <button class="chip" data-filter="especialidad" role="tab">Especialidades</button>
      <button class="chip" data-filter="diagnostico" role="tab">Diagnóstico</button>
      <button class="chip" data-filter="procedimiento" role="tab">Procedimientos</button>
    </div>
  </div>

  <div class="svc__grid container" id="svcGrid">
    {{-- Tarjetas --}}
    <article class="card" data-tags="general">
      <div class="card__icon"><i class="ri-stethoscope-line"></i></div>
      <h3>Medicina General</h3>
      <p>Evaluación integral, diagnósticos iniciales y derivación oportuna.</p>
      <div class="meta">
        <span class="badge">Desde $25</span>
        <span class="badge badge--muted">Duración 20-30 min</span>
      </div>
    </article>

    <article class="card" data-tags="especialidad">
      <div class="card__icon"><i class="ri-heart-pulse-line"></i></div>
      <h3>Cardiología</h3>
      <p>Prevención y tratamiento de enfermedades del sistema cardiovascular.</p>
      <div class="meta">
        <span class="badge">Desde $40</span>
        <span class="badge badge--muted">ECG, Holter</span>
      </div>
    </article>

    <article class="card" data-tags="especialidad">
      <div class="card__icon"><i class="ri-bear-smile-line"></i></div>
      <h3>Pediatría</h3>
      <p>Atención de bebés, niños y adolescentes con enfoque preventivo.</p>
      <div class="meta">
        <span class="badge">Desde $35</span>
        <span class="badge badge--muted">Controles de niño sano</span>
      </div>
    </article>

    <article class="card" data-tags="especialidad">
      <div class="card__icon"><i class="ri-user-heart-line"></i></div>
      <h3>Dermatología</h3>
      <p>Diagnóstico y tratamiento de piel, cabello y uñas.</p>
      <div class="meta">
        <span class="badge">Desde $30</span>
        <span class="badge badge--muted">Crioterapia, biopsias</span>
      </div>
    </article>

    <article class="card" data-tags="diagnostico">
      <div class="card__icon"><i class="ri-contrast-drop-2-line"></i></div>
      <h3>Laboratorio Clínico</h3>
      <p>Exámenes de rutina y perfiles especializados con resultados oportunos.</p>
      <div class="meta">
        <span class="badge">Previa orden</span>
        <span class="badge badge--muted">Entrega en 24-48 h</span>
      </div>
    </article>

    <article class="card" data-tags="diagnostico">
      <div class="card__icon"><i class="ri-pulse-line"></i></div>
      <h3>Electrocardiograma (ECG)</h3>
      <p>Registro de la actividad eléctrica del corazón en reposo.</p>
      <div class="meta">
        <span class="badge">Desde $20</span>
        <span class="badge badge--muted">10-15 min</span>
      </div>
    </article>

    <article class="card" data-tags="procedimiento">
      <div class="card__icon"><i class="ri-hearts-line"></i></div>
      <h3>Ecocardiograma</h3>
      <p>Ultrasonido cardíaco para evaluar estructura y función.</p>
      <div class="meta">
        <span class="badge">Desde $60</span>
        <span class="badge badge--muted">Con agendamiento</span>
      </div>
    </article>

    <article class="card" data-tags="procedimiento">
      <div class="card__icon"><i class="ri-magic-line"></i></div>
      <h3>Procedimientos Dermatológicos</h3>
      <p>Extracción de lesiones menores, crioterapia y curaciones.</p>
      <div class="meta">
        <span class="badge">Según evaluación</span>
        <span class="badge badge--muted">Material estéril</span>
      </div>
    </article>
  </div>

  <div class="svc__cta container">
    @auth
      <a href="{{ route('paciente.dashboard') }}" class="btn-cta btn-cta--lg"><i class="ri-calendar-check-line"></i> Agendar Cita</a>
    @else
      <a href="{{ route('login') }}" class="btn-cta btn-cta--lg"><i class="ri-login-circle-line"></i> Ingresar para Agendar</a>
    @endauth
  </div>
</section>
@endsection
