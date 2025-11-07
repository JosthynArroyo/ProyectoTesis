{{-- resources/views/welcome.blade.php --}}
@extends('layouts.navbar')

@section('title','Clínica Don Bosco')

@push('head')
  @vite([
    'resources/css/welcome.css',
    'resources/js/welcome-login-modal.js',
    'resources/js/welcome-carousel.js',
    'resources/js/navbar.js'
  ])
@endpush

@section('main')

  {{-- HERO: Carrusel --}}
  <section class="hero">
    <div class="carousel" data-carousel>
      <div class="carousel__track" data-carousel-track>
        <div class="carousel__slide is-active">
          <img src="{{ asset('img/hero1.jpg') }}" alt="Fachada principal de la clínica" loading="eager" decoding="async">
        </div>
        <div class="carousel__slide">
          <img src="{{ asset('img/hero2.jpg') }}" alt="Recepción y sala de espera" loading="lazy" decoding="async">
        </div>
        <div class="carousel__slide">
          <img src="{{ asset('img/hero3.jpg') }}" alt="Equipos médicos y consultorios" loading="lazy" decoding="async">
        </div>
      </div>

      <button class="carousel__control prev" type="button" aria-label="Anterior" data-carousel-prev>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 19l-7-7 7-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </button>
      <button class="carousel__control next" type="button" aria-label="Siguiente" data-carousel-next>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </button>

      <div class="carousel__indicators" role="tablist">
        <button class="dot is-active" aria-label="Ir a la diapositiva 1" data-carousel-dot></button>
        <button class="dot" aria-label="Ir a la diapositiva 2" data-carousel-dot></button>
        <button class="dot" aria-label="Ir a la diapositiva 3" data-carousel-dot></button>
      </div>

      {{-- Overlay + texto visible sobre TODAS las imágenes --}}
      <div class="hero__overlay">
        <div class="hero__content container">
          <h1>CLÍNICA</h1>
          <span>Don Bosco</span>
          <p>Atención especializada y agendamiento de citas en línea de forma rápida y segura.</p>
          @auth
            <a href="{{ route('paciente.dashboard') }}" class="btn-1">Agendar Cita</a>
          @else
            <a href="{{ route('login') }}" class="btn-1">Agendar Cita</a>
          @endauth
        </div>
      </div>
    </div>
  </section>

  {{-- BIENVENIDA --}}
  <section class="welcome">
    <div class="welcome-1"></div>
    <div class="welcome-2">
      <h2>Bienvenidos al Sistema de Gestión de Citas Médicas</h2>
      <p class="b1">Accede a consultas con médicos especializados desde cualquier lugar.</p>
      <p>Nuestro sistema te permite agendar, modificar o cancelar tus citas de forma rápida y segura.</p>
    </div>
  </section>

  {{-- SERVICIOS --}}
  <main class="services container">
    <div class="services-txt">
      <h2>Nuestros Servicios</h2>
      <hr>
      <p>Contamos con atención médica en múltiples especialidades para cuidar de tu salud y bienestar.</p>
    </div>
    <div class="services-group">
      <div class="services-1">
        <svg class="svc-icon" viewBox="0 0 24 24" aria-hidden="true">
          <circle cx="5" cy="5" r="1.6" fill="currentColor"/>
          <circle cx="19" cy="5" r="1.6" fill="currentColor"/>
          <path d="M6 3v5a4 4 0 0 0 8 0V3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M10 13v3a5 5 0 0 0 5 5h1" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <h3>Medicina General</h3>
        <p>Evaluaciones integrales, diagnósticos iniciales y derivaciones oportunas.</p>
      </div>
      <div class="services-1">
        <svg class="svc-icon" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M20.5 9.5c0 5.4-8.5 10-8.5 10S3.5 14.9 3.5 9.5A4.9 4.9 0 0 1 12 8a4.9 4.9 0 0 1 8.5 1.5Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M7 12h3l1-2 2 6 1-2h3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <h3>Cardiología</h3>
        <p>Prevención, diagnóstico y tratamiento del sistema cardiovascular.</p>
      </div>
      <div class="services-1">
        <svg class="svc-icon" viewBox="0 0 24 24" aria-hidden="true">
          <circle cx="12" cy="8" r="3.2" fill="none" stroke="currentColor" stroke-width="1.8"/>
          <path d="M5 19a7 7 0 0 1 14 0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          <path d="M12 5.5c.5-.7 1-.9 1.6-1" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.8"/>
        </svg>
        <h3>Pediatría</h3>
        <p>Atención para bebés, niños y adolescentes con enfoque preventivo.</p>
      </div>
      <div class="services-1">
        <svg class="svc-icon" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M12 3c2.6 2.7 4 5 4 7a4 4 0 1 1-8 0c0-2 1.4-4.3 4-7Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          <circle cx="9" cy="14" r=".9" fill="currentColor"/>
          <circle cx="12" cy="16" r=".9" fill="currentColor"/>
          <circle cx="15" cy="14" r=".9" fill="currentColor"/>
        </svg>
        <h3>Dermatología</h3>
        <p>Diagnóstico y tratamiento de piel, cabello y uñas.</p>
      </div>
    </div>
  </main>

  {{-- PRECIOS --}}
  <section class="prices">
    <div class="prices-1">
      <h2>Tarifario de Servicios</h2>
      <p>Consulta los precios aproximados de nuestros servicios. Pregunta por promociones.</p>
      <table>
        <tbody>
          <tr><th>Medicina General</th><td>$25</td></tr>
          <tr><th>Cardiología</th><td>$40</td></tr>
          <tr><th>Pediatría</th><td>$35</td></tr>
          <tr><th>Dermatología</th><td>$30</td></tr>
        </tbody>
      </table>
      @auth
        <a href="{{ route('paciente.dashboard') }}" class="btn-1">Agendar Cita</a>
      @else
        <a href="{{ route('login') }}" class="btn-1">Agendar Cita</a>
      @endauth
    </div>
    <div class="prices-2"></div>
  </section>

  {{-- EQUIPO --}}
  <section class="personal container">
    <div class="personal-txt">
      <h2>Nuestros Doctores</h2>
      <p>Equipo médico calificado y comprometido.</p>
    </div>
    <div class="personal-group">
      <div class="personal-1">
        <img src="{{ asset('img/doctora1.jpg') }}" alt="Doctora Dermatóloga">
        <p>Dra. Ana Martínez — Dermatología.</p>
      </div>
      <div class="personal-1">
        <img src="{{ asset('img/doctor1.jpg') }}" alt="Doctor Pediatra">
        <p>Dr. Carlos Pérez — Pediatría.</p>
      </div>
      <div class="personal-1">
        <img src="{{ asset('img/doctor2.jpg') }}" alt="Doctor Cardiólogo">
        <p>Dr. Juan Torres — Cardiología.</p>
      </div>
    </div>
  </section>

  {{-- FOOTER --}}
  <footer id="footer">
    <div class="footer-txt">
      <p>Clínica Don Bosco © {{ date('Y') }} - Todos los derechos reservados.</p>
    </div>
  </footer>

@endsection
