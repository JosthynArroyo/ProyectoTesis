<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>JA MedSys — Sistema Web Integral para la Gestión de Clínicas</title>
  @include('layouts.partials.favicon')
  @include('layouts.partials.fonts')
  @vite(['resources/css/app.css', 'resources/css/commercial-landing.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased selection:bg-cyan-600 selection:text-white flex flex-col font-sans">

  {{-- ============================================================
       1. NAVBAR (Header Fijo con Menú Hamburguesa Accesible)
  ============================================================ --}}
  <header class="fixed inset-x-0 top-0 z-50 cg-glass-nav transition-all duration-200" id="navbar">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between gap-4">
      
      {{-- Brand Logo --}}
      <a href="{{ route('home.index') }}" class="flex items-center gap-3 group shrink-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600 rounded-xl" aria-label="JA MedSys - Inicio">
        <img src="{{ asset('images/demo/logo-demo.png') }}" alt="Logo JA MedSys" class="h-10 w-auto object-contain shrink-0" width="40" height="40">
        <div class="flex flex-col">
          <span class="text-xl font-bold tracking-tight text-slate-900 flex items-center gap-1">
            JA <span class="text-cyan-600">MedSys</span>
          </span>
          <span class="text-[10px] uppercase font-bold tracking-wider text-slate-500 -mt-0.5">Software Clínico</span>
        </div>
      </a>

      {{-- Nav Links (Desktop xl+) --}}
      <nav class="cg-desktop-nav hidden xl:flex items-center gap-1.5 2xl:gap-2 text-sm font-semibold text-slate-600 whitespace-nowrap" aria-label="Navegación principal">
        <a href="#inicio" class="px-3 py-2 rounded-lg hover:text-cyan-700 hover:bg-slate-100/80 transition-colors">Inicio</a>
        <a href="#beneficios" class="px-3 py-2 rounded-lg hover:text-cyan-700 hover:bg-slate-100/80 transition-colors">Características</a>
        <a href="#modulos" class="px-3 py-2 rounded-lg hover:text-cyan-700 hover:bg-slate-100/80 transition-colors">Módulos</a>
        <a href="#capturas" class="px-3 py-2 rounded-lg hover:text-cyan-700 hover:bg-slate-100/80 transition-colors">Capturas</a>
        <a href="#roles" class="px-3 py-2 rounded-lg hover:text-cyan-700 hover:bg-slate-100/80 transition-colors">Roles</a>
        <a href="#white-label" class="px-3 py-2 rounded-lg hover:text-cyan-700 hover:bg-slate-100/80 transition-colors">White-label</a>
        <a href="#contacto" class="px-3 py-2 rounded-lg hover:text-cyan-700 hover:bg-slate-100/80 transition-colors">Contacto</a>
      </nav>

      {{-- CTA Action & Mobile Toggle --}}
      <div class="flex items-center gap-3 shrink-0">
        <a href="{{ route('demo.clinic') }}"
           class="hidden sm:inline-flex items-center justify-center gap-2 px-4.5 py-2.5 rounded-full bg-cyan-700 text-white text-sm font-bold hover:bg-cyan-800 active:scale-95 shadow-sm shadow-cyan-700/25 transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-cyan-700 whitespace-nowrap"
           id="nav-cta-preview">
          <i class="ri-eye-line text-base" aria-hidden="true"></i>
          <span>Vista previa</span>
        </a>

        {{-- Mobile Menu Hamburger Button --}}
        <button type="button"
                id="mobile-menu-btn"
                class="cg-mobile-toggle xl:hidden inline-flex items-center justify-center p-2.5 rounded-xl text-slate-700 hover:text-cyan-700 hover:bg-slate-100 border border-slate-200 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600"
                aria-controls="mobile-menu"
                aria-expanded="false"
                aria-label="Abrir menú de navegación">
          <i class="ri-menu-4-line text-2xl" id="menu-icon-open" aria-hidden="true"></i>
          <i class="ri-close-line text-2xl hidden" id="menu-icon-close" aria-hidden="true"></i>
        </button>
      </div>
    </div>

    {{-- Mobile Menu Dropdown Panel --}}
    <div id="mobile-menu" class="hidden xl:hidden bg-white/98 backdrop-blur-xl border-b border-slate-200 shadow-xl px-4 pt-2 pb-6 space-y-1 transition-all">
      <nav class="flex flex-col gap-1 text-base font-semibold text-slate-700" aria-label="Navegación móvil">
        <a href="#inicio" class="mobile-nav-link px-4 py-3 rounded-xl hover:bg-cyan-50 hover:text-cyan-700 transition-colors">Inicio</a>
        <a href="#beneficios" class="mobile-nav-link px-4 py-3 rounded-xl hover:bg-cyan-50 hover:text-cyan-700 transition-colors">Características</a>
        <a href="#modulos" class="mobile-nav-link px-4 py-3 rounded-xl hover:bg-cyan-50 hover:text-cyan-700 transition-colors">Módulos</a>
        <a href="#capturas" class="mobile-nav-link px-4 py-3 rounded-xl hover:bg-cyan-50 hover:text-cyan-700 transition-colors">Capturas</a>
        <a href="#roles" class="mobile-nav-link px-4 py-3 rounded-xl hover:bg-cyan-50 hover:text-cyan-700 transition-colors">Roles</a>
        <a href="#white-label" class="mobile-nav-link px-4 py-3 rounded-xl hover:bg-cyan-50 hover:text-cyan-700 transition-colors">White-label</a>
        <a href="#contacto" class="mobile-nav-link px-4 py-3 rounded-xl hover:bg-cyan-50 hover:text-cyan-700 transition-colors">Contacto</a>
      </nav>
      <div class="pt-4 border-t border-slate-100 flex flex-col gap-2">
        <a href="{{ route('demo.clinic') }}" class="w-full py-3 px-4 rounded-xl bg-cyan-700 text-white text-center font-bold shadow-md shadow-cyan-700/20 flex items-center justify-center gap-2">
          <i class="ri-eye-line text-lg" aria-hidden="true"></i>
          <span>Explorar Vista Previa</span>
        </a>
      </div>
    </div>
  </header>

  <main class="pt-20 flex-grow">

    {{-- ============================================================
         2. HERO SECTION
    ============================================================ --}}
    <section id="inicio" class="relative overflow-hidden pt-8 pb-16 sm:pt-10 sm:pb-20 lg:pt-12 lg:pb-20 cg-hero-mesh border-b border-slate-200/70">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-10 items-center">
          
          {{-- Hero Left Content --}}
          <div class="lg:col-span-6 space-y-6 text-left">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-cyan-100/90 border border-cyan-200 text-cyan-800 text-xs font-bold uppercase tracking-wider shadow-xs">
              <i class="ri-sparkling-fill text-cyan-600" aria-hidden="true"></i>
              Solución Integral para Clínicas y Consultorios
            </div>

            <h1 class="text-3xl sm:text-4xl lg:text-5xl font-black text-slate-900 tracking-tight leading-[1.18]">
              Sistema web integral para la <span class="text-transparent bg-clip-text bg-gradient-to-r from-cyan-700 to-teal-600">gestión de clínicas</span>
            </h1>

            <p class="text-base sm:text-lg text-slate-600 leading-relaxed font-normal">
              Administra citas, pacientes, atención clínica, laboratorio y toda tu operación desde un solo lugar.
            </p>

            {{-- Feature highlights --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 pt-2">
              <div class="flex items-center gap-3 text-sm font-semibold text-slate-700">
                <span class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0" aria-hidden="true">
                  <i class="ri-check-line text-sm font-bold"></i>
                </span>
                <span>Más control y eficiencia</span>
              </div>
              <div class="flex items-center gap-3 text-sm font-semibold text-slate-700">
                <span class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0" aria-hidden="true">
                  <i class="ri-check-line text-sm font-bold"></i>
                </span>
                <span>Información en tiempo real</span>
              </div>
              <div class="flex items-center gap-3 text-sm font-semibold text-slate-700">
                <span class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0" aria-hidden="true">
                  <i class="ri-check-line text-sm font-bold"></i>
                </span>
                <span>Seguridad y respaldo de datos</span>
              </div>
              <div class="flex items-center gap-3 text-sm font-semibold text-slate-700">
                <span class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0" aria-hidden="true">
                  <i class="ri-check-line text-sm font-bold"></i>
                </span>
                <span>Interfaz moderna y fácil de usar</span>
              </div>
            </div>

            {{-- CTA Row --}}
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-4 pt-4">
              <a href="#contacto"
                 class="inline-flex items-center justify-center gap-2 px-7 py-4 rounded-xl bg-cyan-700 text-white font-bold hover:bg-cyan-800 active:scale-95 shadow-md shadow-cyan-700/30 transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-cyan-700">
                <span>Solicitar información</span>
                <i class="ri-arrow-right-line text-lg" aria-hidden="true"></i>
              </a>
              <a href="{{ route('demo.clinic') }}"
                 class="inline-flex items-center justify-center gap-2 px-7 py-4 rounded-xl bg-white text-slate-700 border border-slate-300 font-bold hover:bg-slate-50 hover:text-cyan-700 hover:border-cyan-400 active:scale-95 shadow-xs transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-cyan-600">
                <i class="ri-eye-line text-cyan-700 text-lg" aria-hidden="true"></i>
                <span>Vista previa</span>
              </a>
            </div>
          </div>

          {{-- Hero Right — Product Showcase Frame --}}
          <div class="lg:col-span-6">
            <div class="relative mx-auto max-w-lg lg:max-w-none">
              {{-- Ambient Glow Behind Mockup --}}
              <div class="absolute -inset-1 bg-gradient-to-r from-cyan-500/20 to-teal-500/20 rounded-3xl blur-xl -z-10 opacity-70"></div>
              
              {{-- Laptop/Browser Device Container --}}
              <div class="bg-slate-900 rounded-2xl p-2.5 sm:p-3.5 cg-device-mockup border border-slate-800">
                {{-- Browser chrome bar --}}
                <div class="flex items-center justify-between pb-2.5 px-2 border-b border-slate-800 text-xs text-slate-400">
                  <div class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-rose-500/80"></span>
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500/80"></span>
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500/80"></span>
                  </div>
                  <div class="px-3 py-0.5 rounded-md bg-slate-800/90 font-mono text-[11px] text-slate-300">app.jamedys.com/dashboard</div>
                  <div class="w-8"></div>
                </div>
                {{-- Real screenshot --}}
                <div class="mt-2.5 rounded-lg overflow-hidden bg-slate-950">
                  <img
                    src="{{ asset('images/landing/capturas/dashboard-administrativo.jpg') }}"
                    alt="Dashboard administrativo del sistema JA MedSys"
                    class="w-full h-auto block object-cover"
                    loading="eager"
                    fetchpriority="high"
                  >
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </section>

    {{-- ============================================================
         3. BENEFICIOS PRINCIPALES (4 CARDS)
    ============================================================ --}}
    <section id="beneficios" class="py-16 sm:py-20 bg-white border-b border-slate-200/70">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          
          {{-- Card 1 --}}
          <div class="p-6 rounded-2xl cg-card-elevated group">
            <div class="w-12 h-12 rounded-xl bg-cyan-100 text-cyan-700 flex items-center justify-center text-2xl mb-4 group-hover:scale-110 group-hover:bg-cyan-700 group-hover:text-white transition-all duration-200">
              <i class="ri-bubble-chart-line" aria-hidden="true"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-900 mb-2">Gestión centralizada</h3>
            <p class="text-sm text-slate-600 leading-relaxed font-normal">
              Toda la información de tu clínica en un solo sistema, accesible desde cualquier lugar.
            </p>
          </div>

          {{-- Card 2 --}}
          <div class="p-6 rounded-2xl cg-card-elevated group">
            <div class="w-12 h-12 rounded-xl bg-teal-100 text-teal-700 flex items-center justify-center text-2xl mb-4 group-hover:scale-110 group-hover:bg-teal-700 group-hover:text-white transition-all duration-200">
              <i class="ri-stethoscope-line" aria-hidden="true"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-900 mb-2">Atención clínica</h3>
            <p class="text-sm text-slate-600 leading-relaxed font-normal">
              Historiales, diagnósticos, órdenes médicas y seguimiento de pacientes en forma eficiente.
            </p>
          </div>

          {{-- Card 3 --}}
          <div class="p-6 rounded-2xl cg-card-elevated group">
            <div class="w-12 h-12 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center text-2xl mb-4 group-hover:scale-110 group-hover:bg-indigo-700 group-hover:text-white transition-all duration-200">
              <i class="ri-flask-line" aria-hidden="true"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-900 mb-2">Laboratorio integrado</h3>
            <p class="text-sm text-slate-600 leading-relaxed font-normal">
              Solicitudes, resultados y catálogo de estudios con integración completa.
            </p>
          </div>

          {{-- Card 4 --}}
          <div class="p-6 rounded-2xl cg-card-elevated group">
            <div class="w-12 h-12 rounded-xl bg-purple-100 text-purple-700 flex items-center justify-center text-2xl mb-4 group-hover:scale-110 group-hover:bg-purple-700 group-hover:text-white transition-all duration-200">
              <i class="ri-paint-brush-line" aria-hidden="true"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-900 mb-2">Personalización white-label</h3>
            <p class="text-sm text-slate-600 leading-relaxed font-normal">
              Personaliza logo, colores, nombre y más para reflejar la identidad de tu clínica.
            </p>
          </div>

        </div>
      </div>
    </section>

    {{-- ============================================================
         4. CAPTURAS DEL SISTEMA (6 SHOWCASES)
    ============================================================ --}}
    <section id="capturas" class="py-20 sm:py-24 bg-slate-50 border-b border-slate-200/70">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">
        
        <div class="text-center max-w-3xl mx-auto space-y-3">
          <span class="text-xs font-bold uppercase tracking-wider text-cyan-700 bg-cyan-100/90 px-3.5 py-1 rounded-full border border-cyan-200/80">Recorrido Visual del Software</span>
          <h2 class="text-3xl sm:text-4xl font-black text-slate-900 tracking-tight">Capturas del sistema</h2>
          <p class="text-base text-slate-600 leading-relaxed">Conoce las principales pantallas del sistema y cómo facilitan tu día a día.</p>
        </div>

        <div class="space-y-12">
          
          {{-- 1. Dashboard Administrativo --}}
          <div class="bg-white rounded-3xl p-6 sm:p-8 lg:p-10 border border-slate-200 shadow-sm grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10 items-center">
            <div class="lg:col-span-5 space-y-4">
              <span class="text-xs font-bold text-cyan-700 uppercase tracking-wider">Módulo Central</span>
              <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-900">Dashboard Administrativo</h3>
              <p class="text-slate-600 leading-relaxed text-sm sm:text-base">
                Visualiza indicadores clave, citas del día, ingresos, pacientes y accesos rápidos para gestionar tu clínica en tiempo real.
              </p>
            </div>
            <div class="lg:col-span-7 bg-slate-900 rounded-2xl p-2.5 sm:p-3 border border-slate-800 shadow-md">
              <div class="rounded-lg overflow-hidden">
                <img
                  src="{{ asset('images/landing/capturas/dashboard-administrativo.jpg') }}"
                  alt="Dashboard administrativo de JA MedSys"
                  class="w-full h-auto block"
                  loading="lazy"
                >
              </div>
            </div>
          </div>

          {{-- 2. Gestión de Citas --}}
          <div class="bg-white rounded-3xl p-6 sm:p-8 lg:p-10 border border-slate-200 shadow-sm grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10 items-center">
            <div class="lg:col-span-7 lg:order-1 order-2 bg-slate-900 rounded-2xl p-2.5 sm:p-3 border border-slate-800 shadow-md">
              <div class="rounded-lg overflow-hidden">
                <img
                  src="{{ asset('images/landing/capturas/gestion-citas.jpg') }}"
                  alt="Gestión de citas médicas en JA MedSys"
                  class="w-full h-auto block"
                  loading="lazy"
                >
              </div>
            </div>
            <div class="lg:col-span-5 lg:order-2 order-1 space-y-4">
              <span class="text-xs font-bold text-teal-700 uppercase tracking-wider">Agendamiento &amp; Flujos</span>
              <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-900">Gestión de Citas</h3>
              <p class="text-slate-600 leading-relaxed text-sm sm:text-base">
                Agenda, reprograma y administra citas de forma sencilla. Controla disponibilidad de médicos y recordatorios.
              </p>
            </div>
          </div>

          {{-- 3. Historial Clínico --}}
          <div class="bg-white rounded-3xl p-6 sm:p-8 lg:p-10 border border-slate-200 shadow-sm grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10 items-center">
            <div class="lg:col-span-5 space-y-4">
              <span class="text-xs font-bold text-indigo-700 uppercase tracking-wider">Atención Médica</span>
              <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-900">Historial Clínico</h3>
              <p class="text-slate-600 leading-relaxed text-sm sm:text-base">
                Accede al historial completo del paciente, antecedentes, diagnósticos, tratamientos y archivos relacionados.
              </p>
            </div>
            <div class="lg:col-span-7 bg-slate-900 rounded-2xl p-2.5 sm:p-3 border border-slate-800 shadow-md">
              <div class="rounded-lg overflow-hidden">
                <img
                  src="{{ asset('images/landing/capturas/historial-clinico.jpeg') }}"
                  alt="Historial clínico del paciente en JA MedSys"
                  class="w-full h-auto block"
                  loading="lazy"
                >
              </div>
            </div>
          </div>

          {{-- 4. Laboratorio --}}
          <div class="bg-white rounded-3xl p-6 sm:p-8 lg:p-10 border border-slate-200 shadow-sm grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10 items-center">
            <div class="lg:col-span-7 lg:order-1 order-2 bg-slate-900 rounded-2xl p-2.5 sm:p-3 border border-slate-800 shadow-md">
              <div class="rounded-lg overflow-hidden">
                <img
                  src="{{ asset('images/landing/capturas/laboratorio-dashboard.jpeg') }}"
                  alt="Panel de laboratorio de JA MedSys"
                  class="w-full h-auto block"
                  loading="lazy"
                >
              </div>
            </div>
            <div class="lg:col-span-5 lg:order-2 order-1 space-y-4">
              <span class="text-xs font-bold text-purple-700 uppercase tracking-wider">Diagnóstico</span>
              <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-900">Laboratorio</h3>
              <p class="text-slate-600 leading-relaxed text-sm sm:text-base">
                Solicita estudios, registra resultados y consulta información de laboratorio de manera integrada.
              </p>
            </div>
          </div>

          {{-- 5. Portal del Paciente --}}
          <div class="bg-white rounded-3xl p-6 sm:p-8 lg:p-10 border border-slate-200 shadow-sm grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10 items-center">
            <div class="lg:col-span-5 space-y-4">
              <span class="text-xs font-bold text-emerald-700 uppercase tracking-wider">Experiencia del Usuario</span>
              <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-900">Portal del Paciente</h3>
              <p class="text-slate-600 leading-relaxed text-sm sm:text-base">
                Tus pacientes pueden agendar citas, ver resultados, descargar documentos y consultar su historial de forma segura.
              </p>
            </div>
            <div class="lg:col-span-7 bg-slate-900 rounded-2xl p-2.5 sm:p-3 border border-slate-800 shadow-md">
              <div class="rounded-lg overflow-hidden">
                <img
                  src="{{ asset('images/landing/capturas/portal-paciente.jpeg') }}"
                  alt="Portal del paciente de JA MedSys"
                  class="w-full h-auto block"
                  loading="lazy"
                >
              </div>
            </div>
          </div>

          {{-- 6. Personalización --}}
          <div class="bg-white rounded-3xl p-6 sm:p-8 lg:p-10 border border-slate-200 shadow-sm grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10 items-center">
            <div class="lg:col-span-7 lg:order-1 order-2 bg-slate-900 rounded-2xl p-2.5 sm:p-3 border border-slate-800 shadow-md">
              <div class="rounded-lg overflow-hidden">
                <img
                  src="{{ asset('images/landing/capturas/personalizacion-clinica.jpeg') }}"
                  alt="Personalización white-label de una clínica en JA MedSys"
                  class="w-full h-auto block"
                  loading="lazy"
                >
              </div>
            </div>
            <div class="lg:col-span-5 lg:order-2 order-1 space-y-4">
              <span class="text-xs font-bold text-amber-700 uppercase tracking-wider">Identidad Propia</span>
              <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-900">Personalización</h3>
              <p class="text-slate-600 leading-relaxed text-sm sm:text-base">
                Configura la identidad visual de tu clínica: logo, colores, nombre, servicios, equipo, tarifario, contacto y página pública.
              </p>
            </div>
          </div>

        </div>

      </div>
    </section>

    {{-- ============================================================
         5. MÓDULOS DEL SISTEMA (10 ICONS GRID)
    ============================================================ --}}
    <section id="modulos" class="py-16 sm:py-20 bg-white border-b border-slate-200/70">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
        <div class="text-center max-w-2xl mx-auto space-y-2">
          <span class="text-xs font-bold uppercase tracking-wider text-cyan-700 bg-cyan-100/90 px-3.5 py-1 rounded-full border border-cyan-200/80">Ecosistema Completo</span>
          <h2 class="text-3xl sm:text-4xl font-black text-slate-900 tracking-tight">Módulos del sistema</h2>
          <p class="text-base text-slate-600">Herramientas especializadas para cada área funcional de tu centro de salud.</p>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-4">
          
          <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200/80 text-center hover:border-cyan-400 hover:bg-white hover:shadow-md transition-all duration-200 group">
            <div class="w-11 h-11 mx-auto rounded-xl bg-cyan-100 text-cyan-700 flex items-center justify-center text-xl mb-3 group-hover:scale-110 transition-transform">
              <i class="ri-calendar-line" aria-hidden="true"></i>
            </div>
            <div class="text-sm font-bold text-slate-900">Citas</div>
          </div>

          <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200/80 text-center hover:border-cyan-400 hover:bg-white hover:shadow-md transition-all duration-200 group">
            <div class="w-11 h-11 mx-auto rounded-xl bg-teal-100 text-teal-700 flex items-center justify-center text-xl mb-3 group-hover:scale-110 transition-transform">
              <i class="ri-user-heart-line" aria-hidden="true"></i>
            </div>
            <div class="text-sm font-bold text-slate-900">Pacientes</div>
          </div>

          <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200/80 text-center hover:border-cyan-400 hover:bg-white hover:shadow-md transition-all duration-200 group">
            <div class="w-11 h-11 mx-auto rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center text-xl mb-3 group-hover:scale-110 transition-transform">
              <i class="ri-heart-pulse-line" aria-hidden="true"></i>
            </div>
            <div class="text-sm font-bold text-slate-900">Atención clínica</div>
          </div>

          <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200/80 text-center hover:border-cyan-400 hover:bg-white hover:shadow-md transition-all duration-200 group">
            <div class="w-11 h-11 mx-auto rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center text-xl mb-3 group-hover:scale-110 transition-transform">
              <i class="ri-file-text-line" aria-hidden="true"></i>
            </div>
            <div class="text-sm font-bold text-slate-900">Historial</div>
          </div>

          <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200/80 text-center hover:border-cyan-400 hover:bg-white hover:shadow-md transition-all duration-200 group">
            <div class="w-11 h-11 mx-auto rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center text-xl mb-3 group-hover:scale-110 transition-transform">
              <i class="ri-article-line" aria-hidden="true"></i>
            </div>
            <div class="text-sm font-bold text-slate-900">Documentos</div>
          </div>

          <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200/80 text-center hover:border-cyan-400 hover:bg-white hover:shadow-md transition-all duration-200 group">
            <div class="w-11 h-11 mx-auto rounded-xl bg-purple-100 text-purple-700 flex items-center justify-center text-xl mb-3 group-hover:scale-110 transition-transform">
              <i class="ri-flask-line" aria-hidden="true"></i>
            </div>
            <div class="text-sm font-bold text-slate-900">Laboratorio</div>
          </div>

          <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200/80 text-center hover:border-cyan-400 hover:bg-white hover:shadow-md transition-all duration-200 group">
            <div class="w-11 h-11 mx-auto rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center text-xl mb-3 group-hover:scale-110 transition-transform">
              <i class="ri-bank-card-line" aria-hidden="true"></i>
            </div>
            <div class="text-sm font-bold text-slate-900">Cobros</div>
          </div>

          <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200/80 text-center hover:border-cyan-400 hover:bg-white hover:shadow-md transition-all duration-200 group">
            <div class="w-11 h-11 mx-auto rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-xl mb-3 group-hover:scale-110 transition-transform">
              <i class="ri-bar-chart-2-line" aria-hidden="true"></i>
            </div>
            <div class="text-sm font-bold text-slate-900">Reportes</div>
          </div>

          <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200/80 text-center hover:border-cyan-400 hover:bg-white hover:shadow-md transition-all duration-200 group">
            <div class="w-11 h-11 mx-auto rounded-xl bg-slate-200 text-slate-800 flex items-center justify-center text-xl mb-3 group-hover:scale-110 transition-transform">
              <i class="ri-shield-user-line" aria-hidden="true"></i>
            </div>
            <div class="text-sm font-bold text-slate-900">Usuarios y roles</div>
          </div>

          <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200/80 text-center hover:border-cyan-400 hover:bg-white hover:shadow-md transition-all duration-200 group">
            <div class="w-11 h-11 mx-auto rounded-xl bg-pink-100 text-pink-700 flex items-center justify-center text-xl mb-3 group-hover:scale-110 transition-transform">
              <i class="ri-palette-line" aria-hidden="true"></i>
            </div>
            <div class="text-sm font-bold text-slate-900">Personalización</div>
          </div>

        </div>
      </div>
    </section>

    {{-- ============================================================
         6. ROLES DEL SISTEMA (5 CARDS)
    ============================================================ --}}
    <section id="roles" class="py-16 sm:py-20 bg-slate-50 border-b border-slate-200/70">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
        <div class="text-center max-w-2xl mx-auto space-y-2">
          <span class="text-xs font-bold uppercase tracking-wider text-cyan-700 bg-cyan-100/90 px-3.5 py-1 rounded-full border border-cyan-200/80">Control y Seguridad</span>
          <h2 class="text-3xl sm:text-4xl font-black text-slate-900 tracking-tight">Roles del sistema</h2>
          <p class="text-base text-slate-600">Control de acceso por roles para mantener la seguridad y el orden en la clínica.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6">
          
          <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs flex flex-col justify-between hover:border-cyan-300 transition-colors">
            <div>
              <div class="w-10 h-10 rounded-xl bg-cyan-100 text-cyan-700 flex items-center justify-center text-xl mb-3">
                <i class="ri-hospital-line" aria-hidden="true"></i>
              </div>
              <h3 class="font-bold text-slate-900 text-base mb-1.5">Administrador</h3>
              <p class="text-xs text-slate-600 leading-relaxed font-normal">
                Gestiona la operación completa del sistema y configura todas las opciones.
              </p>
            </div>
          </div>

          <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs flex flex-col justify-between hover:border-teal-300 transition-colors">
            <div>
              <div class="w-10 h-10 rounded-xl bg-teal-100 text-teal-700 flex items-center justify-center text-xl mb-3">
                <i class="ri-stethoscope-line" aria-hidden="true"></i>
              </div>
              <h3 class="font-bold text-slate-900 text-base mb-1.5">Médico</h3>
              <p class="text-xs text-slate-600 leading-relaxed font-normal">
                Atiende pacientes, registra consultas y da seguimiento clínico.
              </p>
            </div>
          </div>

          <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs flex flex-col justify-between hover:border-indigo-300 transition-colors">
            <div>
              <div class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center text-xl mb-3">
                <i class="ri-user-heart-line" aria-hidden="true"></i>
              </div>
              <h3 class="font-bold text-slate-900 text-base mb-1.5">Paciente</h3>
              <p class="text-xs text-slate-600 leading-relaxed font-normal">
                Agenda citas, consulta resultados y accede a su información.
              </p>
            </div>
          </div>

          <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs flex flex-col justify-between hover:border-purple-300 transition-colors">
            <div>
              <div class="w-10 h-10 rounded-xl bg-purple-100 text-purple-700 flex items-center justify-center text-xl mb-3">
                <i class="ri-flask-line" aria-hidden="true"></i>
              </div>
              <h3 class="font-bold text-slate-900 text-base mb-1.5">Laboratorio</h3>
              <p class="text-xs text-slate-600 leading-relaxed font-normal">
                Gestiona estudios, registra resultados y emite informes analíticos.
              </p>
            </div>
          </div>

          <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs flex flex-col justify-between hover:border-rose-300 transition-colors sm:col-span-2 lg:col-span-1">
            <div>
              <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center text-xl mb-3">
                <i class="ri-shield-star-line" aria-hidden="true"></i>
              </div>
              <h3 class="font-bold text-slate-900 text-base mb-1.5">Superadministrador</h3>
              <p class="text-xs text-slate-600 leading-relaxed font-normal">
                Control total del sistema, permisos avanzados y personalización.
              </p>
            </div>
          </div>

        </div>
      </div>
    </section>

    {{-- ============================================================
         7. WHITE-LABEL: TU CLÍNICA, TU MARCA
    ============================================================ --}}
    <section id="white-label" class="py-20 sm:py-24 bg-white border-b border-slate-200/70">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
        <div class="text-center max-w-2xl mx-auto space-y-2">
          <span class="text-xs font-bold uppercase tracking-wider text-purple-700 bg-purple-100 px-3.5 py-1 rounded-full border border-purple-200/80">Marca Blanca &amp; Modular</span>
          <h2 class="text-3xl sm:text-4xl font-black text-slate-900 tracking-tight">White-label: tu clínica, tu marca</h2>
          <p class="text-base text-slate-600">Cada clínica puede personalizar su identidad y página pública para reflejar su marca.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-11 gap-8 items-center">
          
          {{-- Panel de Personalización (Left) --}}
          <div class="lg:col-span-5 bg-slate-50 p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-xs space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-200">
              <span class="font-bold text-slate-900 text-sm">Panel de personalización</span>
              <span class="text-xs bg-cyan-100 text-cyan-800 px-2.5 py-0.5 rounded font-semibold">Ajustes</span>
            </div>
            <div class="space-y-3.5 text-xs">
              <div>
                <label class="font-bold text-slate-700 block mb-1">Logotipo institucional</label>
                <div class="p-2.5 bg-white rounded-lg border border-slate-200 flex items-center gap-2 text-slate-500 font-medium">
                  <i class="ri-image-add-line text-slate-400 text-base" aria-hidden="true"></i> mi-clinica-logo.png
                </div>
              </div>
              <div>
                <label class="font-bold text-slate-700 block mb-1">Nombre comercial</label>
                <div class="p-2.5 bg-white rounded-lg border border-slate-200 font-semibold text-slate-800">
                  Centro Clínico Familiar Ejemplo
                </div>
              </div>
              <div>
                <label class="font-bold text-slate-700 block mb-1.5">Color primario &amp; acento</label>
                <div class="flex items-center gap-2.5">
                  <span class="w-7 h-7 rounded-full bg-cyan-700 border-2 border-white shadow-xs" title="Cian Primario"></span>
                  <span class="w-7 h-7 rounded-full bg-teal-500 border-2 border-white shadow-xs" title="Verde Salud"></span>
                  <span class="w-7 h-7 rounded-full bg-indigo-600 border-2 border-white shadow-xs" title="Índigo"></span>
                  <span class="w-7 h-7 rounded-full bg-rose-500 border-2 border-white shadow-xs" title="Rosa"></span>
                </div>
              </div>
            </div>
          </div>

          {{-- Transfer Arrow --}}
          <div class="lg:col-span-1 flex justify-center text-slate-400 text-3xl font-bold">
            <i class="ri-arrow-right-line hidden lg:block" aria-hidden="true"></i>
            <i class="ri-arrow-down-line lg:hidden" aria-hidden="true"></i>
          </div>

          {{-- Resultado Visual Página Pública (Right) --}}
          <div class="lg:col-span-5 bg-gradient-to-br from-cyan-50 to-teal-50/60 p-6 sm:p-8 rounded-3xl border border-cyan-200/80 shadow-md space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-cyan-200/60">
              <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded-lg bg-cyan-700 text-white flex items-center justify-center text-sm font-bold shadow-xs">
                  <i class="ri-hospital-line" aria-hidden="true"></i>
                </div>
                <span class="font-bold text-slate-900 text-sm">Tu Clínica</span>
              </div>
              <span class="text-xs text-cyan-800 font-bold">Página Pública Adaptada</span>
            </div>
            <div class="space-y-2 text-xs">
              <div class="p-5 bg-white/95 backdrop-blur-xs rounded-2xl border border-cyan-100 space-y-2.5 shadow-sm">
                <div class="text-sm sm:text-base font-bold text-slate-900 leading-snug">Cuidado de calidad para tu bienestar</div>
                <div class="text-xs text-slate-600 leading-relaxed font-normal">En Tu Clínica brindamos atención médica integral con tecnología y calidez humana.</div>
                <button type="button" class="px-3.5 py-2 rounded-lg bg-cyan-700 text-white font-bold text-xs shadow-xs pointer-events-none">Agendar Cita</button>
              </div>
            </div>
          </div>

        </div>
      </div>
    </section>

    {{-- ============================================================
         8. VISTA PREVIA DEL SISTEMA (HIGHLIGHT CTA)
    ============================================================ --}}
    <section id="vista-previa" class="py-20 sm:py-24 bg-slate-900 text-white relative overflow-hidden">
      {{-- Background aesthetic glow --}}
      <div class="absolute -top-40 -right-40 w-96 h-96 rounded-full bg-cyan-500/15 blur-3xl pointer-events-none"></div>
      <div class="absolute -bottom-40 -left-40 w-96 h-96 rounded-full bg-teal-500/15 blur-3xl pointer-events-none"></div>

      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
          
          <div class="lg:col-span-7 space-y-6 text-left">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-cyan-950/90 border border-cyan-500/40 text-cyan-400 text-xs font-bold uppercase tracking-wider">
              <i class="ri-sparkling-line text-sm" aria-hidden="true"></i>
              Prueba Interactiva
            </div>

            <h2 class="text-3xl sm:text-4xl font-black tracking-tight leading-tight text-white">
              Vista previa del sistema
            </h2>

            <p class="text-slate-300 text-base sm:text-lg leading-relaxed font-normal">
              Explora el sistema con datos de demostración y conoce su funcionamiento antes de tomar una decisión.
            </p>

            <div class="space-y-3.5 pt-2">
              <div class="flex items-center gap-3 text-sm sm:text-base text-slate-200">
                <i class="ri-checkbox-circle-fill text-cyan-400 text-xl shrink-0" aria-hidden="true"></i>
                <span>Acceso a todas las funcionalidades principales</span>
              </div>
              <div class="flex items-center gap-3 text-sm sm:text-base text-slate-200">
                <i class="ri-checkbox-circle-fill text-cyan-400 text-xl shrink-0" aria-hidden="true"></i>
                <span>Datos ficticios para una experiencia segura</span>
              </div>
              <div class="flex items-center gap-3 text-sm sm:text-base text-slate-200">
                <i class="ri-checkbox-circle-fill text-cyan-400 text-xl shrink-0" aria-hidden="true"></i>
                <span>Entorno de prueba siempre disponible y aislado</span>
              </div>
            </div>

            <div class="pt-4 flex flex-col sm:flex-row items-stretch sm:items-center gap-4">
              <a href="{{ route('demo.clinic') }}"
                 class="inline-flex items-center justify-center gap-2 px-8 py-4 rounded-xl bg-cyan-500 text-slate-950 font-black hover:bg-cyan-400 active:scale-95 shadow-lg shadow-cyan-500/30 transition-all text-base focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900 focus-visible:ring-cyan-400"
                 id="hero-preview-btn">
                <i class="ri-play-circle-line text-xl" aria-hidden="true"></i>
                <span>ABRIR VISTA PREVIA</span>
              </a>
              <a href="#contacto" class="text-sm font-bold text-slate-300 hover:text-white underline underline-offset-4 transition-colors text-center sm:text-left py-2">
                Solicitar información
              </a>
            </div>
          </div>

          <div class="lg:col-span-5 flex justify-center">
            <div class="w-full max-w-sm p-6 sm:p-8 rounded-3xl bg-slate-800/90 border border-slate-700/80 text-center space-y-4 shadow-xl">
              <div class="w-16 h-16 mx-auto rounded-2xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center text-3xl">
                <i class="ri-computer-line" aria-hidden="true"></i>
              </div>
              <h3 class="text-xl font-bold text-white">Clínica Demo Interactiva</h3>
              <p class="text-xs sm:text-sm text-slate-300 leading-relaxed font-normal">
                Navega como Administrador, Médico, Paciente, Laboratorio o Superadmin y prueba todas las funciones reales.
              </p>
              <div class="pt-2">
                <span class="inline-block px-3.5 py-1.5 rounded-full bg-emerald-950/90 border border-emerald-500/40 text-emerald-400 text-xs font-bold">
                  Sin persistencia de cambios
                </span>
              </div>
            </div>
          </div>

        </div>
      </div>
    </section>

    {{-- ============================================================
         9. QUÉ INCLUYE (6 CARDS)
    ============================================================ --}}
    <section id="que-incluye" class="py-16 sm:py-20 bg-white border-b border-slate-200/70">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
        <div class="text-center max-w-2xl mx-auto space-y-2">
          <span class="text-xs font-bold uppercase tracking-wider text-cyan-700 bg-cyan-100/90 px-3.5 py-1 rounded-full border border-cyan-200/80">Propuesta de Valor</span>
          <h2 class="text-3xl sm:text-4xl font-black text-slate-900 tracking-tight">Qué incluye</h2>
          <p class="text-base text-slate-600">Todo lo necesario para poner en marcha tu plataforma médica de inmediato.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          
          <div class="p-6 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-2.5 cg-card-elevated">
            <div class="w-10 h-10 rounded-xl bg-cyan-100 text-cyan-700 flex items-center justify-center text-xl mb-3">
              <i class="ri-apps-2-line" aria-hidden="true"></i>
            </div>
            <h3 class="font-bold text-slate-900 text-base">Sistema completo</h3>
            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed font-normal">
              Todos los módulos y funcionalidades listos para usar en producción.
            </p>
          </div>

          <div class="p-6 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-2.5 cg-card-elevated">
            <div class="w-10 h-10 rounded-xl bg-teal-100 text-teal-700 flex items-center justify-center text-xl mb-3">
              <i class="ri-code-s-slash-line" aria-hidden="true"></i>
            </div>
            <h3 class="font-bold text-slate-900 text-base">Código fuente</h3>
            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed font-normal">
              Entregamos el código fuente del sistema para control y personalización interna (si aplica).
            </p>
          </div>

          <div class="p-6 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-2.5 cg-card-elevated">
            <div class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center text-xl mb-3">
              <i class="ri-server-line" aria-hidden="true"></i>
            </div>
            <h3 class="font-bold text-slate-900 text-base">Instalación y configuración</h3>
            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed font-normal">
              Instalación en tu servidor y configuración inicial de servicios y base de datos (si aplica).
            </p>
          </div>

          <div class="p-6 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-2.5 cg-card-elevated">
            <div class="w-10 h-10 rounded-xl bg-purple-100 text-purple-700 flex items-center justify-center text-xl mb-3">
              <i class="ri-book-read-line" aria-hidden="true"></i>
            </div>
            <h3 class="font-bold text-slate-900 text-base">Documentación</h3>
            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed font-normal">
              Manuales y guías detalladas para administradores, médicos y personal técnico.
            </p>
          </div>

          <div class="p-6 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-2.5 cg-card-elevated">
            <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center text-xl mb-3">
              <i class="ri-paint-brush-line" aria-hidden="true"></i>
            </div>
            <h3 class="font-bold text-slate-900 text-base">Personalización</h3>
            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed font-normal">
              Adaptamos el sistema a tu identidad corporativa y flujos de trabajo (si aplica).
            </p>
          </div>

          <div class="p-6 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-2.5 cg-card-elevated">
            <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center text-xl mb-3">
              <i class="ri-customer-service-2-line" aria-hidden="true"></i>
            </div>
            <h3 class="font-bold text-slate-900 text-base">Soporte inicial</h3>
            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed font-normal">
              Acompañamiento y soporte técnico para asegurar una puesta en marcha exitosa (si aplica).
            </p>
          </div>

        </div>
      </div>
    </section>

    {{-- ============================================================
         10. CONTACTO COMERCIAL & FAQ
    ============================================================ --}}
    <section id="contacto" class="pt-16 pb-12 sm:pt-20 sm:pb-14 bg-slate-50 border-b border-slate-200/70">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
        
        <div class="text-center max-w-2xl mx-auto space-y-2">
          <span class="text-xs font-bold uppercase tracking-wider text-cyan-700 bg-cyan-100/90 px-3.5 py-1 rounded-full border border-cyan-200/80">Contacto Comercial</span>
          <h2 class="text-3xl sm:text-4xl font-black text-slate-900 tracking-tight">Conversemos sobre tu proyecto</h2>
          <p class="text-base text-slate-600">Estamos listos para ayudarte. Completa el formulario o contáctanos directamente.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
          
          {{-- Commercial Contact Info & Form (Left) --}}
          <div class="lg:col-span-6 space-y-6">
            {{-- Contact Direct Channels --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <a href="https://wa.me/593998740927" target="_blank" rel="noopener" data-action-lock-ignore="1" data-no-loader="1" class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-xs hover:border-emerald-400 hover:shadow-sm transition-all flex flex-col items-center text-center group">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-xl mb-2 group-hover:scale-110 transition-transform">
                  <i class="ri-whatsapp-line" aria-hidden="true"></i>
                </div>
                <div class="text-xs font-bold text-slate-900">WhatsApp</div>
                <div class="text-[11px] text-slate-500 mt-0.5 font-medium">+593 998 740 927</div>
              </a>

              <a href="https://mail.google.com/mail/?view=cm&fs=1&to=alejandroucenriquez@gmail.com" target="_blank" rel="noopener noreferrer" data-action-lock-ignore="1" data-no-loader="1" class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-xs hover:border-cyan-400 hover:shadow-sm transition-all flex flex-col items-center text-center group break-all">
                <div class="w-10 h-10 rounded-xl bg-cyan-100 text-cyan-700 flex items-center justify-center text-xl mb-2 group-hover:scale-110 transition-transform">
                  <i class="ri-mail-send-line" aria-hidden="true"></i>
                </div>
                <div class="text-xs font-bold text-slate-900">Correo</div>
                <div class="text-[11px] text-slate-500 mt-0.5 font-medium">alejandroucenriquez@gmail.com</div>
              </a>

              <div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col items-center text-center">
                <div class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center text-xl mb-2">
                  <i class="ri-time-line" aria-hidden="true"></i>
                </div>
                <div class="text-xs font-bold text-slate-900">Horario</div>
                <div class="text-[11px] text-slate-500 mt-0.5 font-medium">Lun - Vie 9:00 a 18:00</div>
              </div>
            </div>

            {{-- Commercial Inquiries Form Feedback --}}
            @if(session('success'))
              <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium flex items-center gap-3" role="status">
                <i class="ri-checkbox-circle-line text-emerald-600 text-xl shrink-0" aria-hidden="true"></i>
                <span>{{ session('success') }}</span>
              </div>
            @endif

            @if(session('error'))
              <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-medium flex items-center gap-3" role="alert">
                <i class="ri-error-warning-line text-rose-600 text-xl shrink-0" aria-hidden="true"></i>
                <span>{{ session('error') }}</span>
              </div>
            @endif

            @if($errors->getBag('contacto')->any())
              <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-medium flex items-start gap-3" role="alert">
                <i class="ri-error-warning-line text-rose-600 text-xl shrink-0 mt-0.5" aria-hidden="true"></i>
                <div class="space-y-1">
                  <p class="font-bold">Por favor verifica los campos:</p>
                  <ul class="list-disc list-inside text-xs space-y-0.5">
                    @foreach($errors->getBag('contacto')->all() as $error)
                      <li>{{ $error }}</li>
                    @endforeach
                  </ul>
                </div>
              </div>
            @endif

            {{-- Commercial Inquiries Form --}}
            <form action="{{ URL::signedRoute('contacto.enviar', ['context' => 'commercial']) }}" method="POST" class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200/90 shadow-sm space-y-4">
              @csrf
              <input type="hidden" name="t0" value="{{ now()->subSeconds(4)->timestamp }}">
              <div class="hidden" aria-hidden="true">
                <label for="form-empresa">Empresa</label>
                <input id="form-empresa" type="text" name="empresa" value="" tabindex="-1" autocomplete="off">
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label for="form-nombre" class="block text-xs font-bold text-slate-700 mb-1.5">Nombre completo *</label>
                  <input id="form-nombre" name="nombre" type="text" placeholder="Ej. Dr. Roberto Gómez" value="{{ old('nombre') }}" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-600 focus:border-transparent text-slate-900">
                </div>
                <div>
                  <label for="form-correo" class="block text-xs font-bold text-slate-700 mb-1.5">Correo electrónico *</label>
                  <input id="form-correo" name="email" type="email" placeholder="roberto@clinica.com" value="{{ old('email') }}" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-600 focus:border-transparent text-slate-900">
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label for="form-clinica" class="block text-xs font-bold text-slate-700 mb-1.5">Clínica o empresa *</label>
                  <input id="form-clinica" name="asunto" type="text" placeholder="Ej. Centro Médico San Juan" value="{{ old('asunto') }}" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-600 focus:border-transparent text-slate-900">
                </div>
                <div>
                  <label for="form-telefono" class="block text-xs font-bold text-slate-700 mb-1.5">Teléfono *</label>
                  <input id="form-telefono" name="telefono" type="tel" placeholder="0998740927" maxlength="10" pattern="[0-9]{10}" value="{{ old('telefono') }}" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-600 focus:border-transparent text-slate-900">
                </div>
              </div>

              <div>
                <label for="form-mensaje" class="block text-xs font-bold text-slate-700 mb-1.5">Mensaje *</label>
                <textarea id="form-mensaje" name="mensaje" rows="4" placeholder="Cuéntanos sobre tu clínica, número de médicos o requerimientos específicos..." required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-600 focus:border-transparent resize-none text-slate-900">{{ old('mensaje') }}</textarea>
              </div>

              <button type="submit" class="w-full py-3.5 rounded-xl bg-cyan-700 text-white font-bold hover:bg-cyan-800 shadow-md shadow-cyan-700/30 transition-all text-sm flex items-center justify-center gap-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-700">
                <span>Solicitar información</span>
                <i class="ri-send-plane-line" aria-hidden="true"></i>
              </button>
            </form>
          </div>

          {{-- FAQ Accordion (Right) --}}
          <div class="lg:col-span-6 space-y-4">
            <h3 class="text-xl sm:text-2xl font-black text-slate-900 mb-4 flex items-center gap-2.5">
              <i class="ri-question-line text-cyan-700" aria-hidden="true"></i>
              Preguntas frecuentes
            </h3>

            <div class="space-y-3" id="faq">
              
              <details class="group bg-white rounded-2xl border border-slate-200/90 shadow-xs p-4.5 open:ring-1 open:ring-cyan-600/30 transition-all">
                <summary class="font-bold text-slate-900 text-sm cursor-pointer list-none flex items-center justify-between gap-2">
                  <span>¿En qué servidores puede instalarse?</span>
                  <i class="ri-arrow-down-s-line text-slate-400 group-open:rotate-180 transition-transform text-lg" aria-hidden="true"></i>
                </summary>
                <div class="mt-3 text-xs sm:text-sm text-slate-600 leading-relaxed border-t border-slate-100 pt-3 font-normal">
                  Puede desplegarse en cualquier servidor con soporte para PHP 8.2+, MySQL / MariaDB, y entornos Cloud / VPS (AWS, DigitalOcean, Hetzner, cPanel/WHM o Docker).
                </div>
              </details>

              <details class="group bg-white rounded-2xl border border-slate-200/90 shadow-xs p-4.5 open:ring-1 open:ring-cyan-600/30 transition-all">
                <summary class="font-bold text-slate-900 text-sm cursor-pointer list-none flex items-center justify-between gap-2">
                  <span>¿El sistema incluye código fuente?</span>
                  <i class="ri-arrow-down-s-line text-slate-400 group-open:rotate-180 transition-transform text-lg" aria-hidden="true"></i>
                </summary>
                <div class="mt-3 text-xs sm:text-sm text-slate-600 leading-relaxed border-t border-slate-100 pt-3 font-normal">
                  Sí, se entrega el código fuente completo en Laravel, TailwindCSS y JavaScript moderno, sin ofuscación y con arquitectura modular documentada.
                </div>
              </details>

              <details class="group bg-white rounded-2xl border border-slate-200/90 shadow-xs p-4.5 open:ring-1 open:ring-cyan-600/30 transition-all">
                <summary class="font-bold text-slate-900 text-sm cursor-pointer list-none flex items-center justify-between gap-2">
                  <span>¿Puedo personalizar el sistema?</span>
                  <i class="ri-arrow-down-s-line text-slate-400 group-open:rotate-180 transition-transform text-lg" aria-hidden="true"></i>
                </summary>
                <div class="mt-3 text-xs sm:text-sm text-slate-600 leading-relaxed border-t border-slate-100 pt-3 font-normal">
                  Totalmente. Cuenta con módulo white-label para adaptar nombre institucional, colores corporativos, logotipo, eslogan, servicios y equipo médico directamente desde el panel administrativo.
                </div>
              </details>

              <details class="group bg-white rounded-2xl border border-slate-200/90 shadow-xs p-4.5 open:ring-1 open:ring-cyan-600/30 transition-all">
                <summary class="font-bold text-slate-900 text-sm cursor-pointer list-none flex items-center justify-between gap-2">
                  <span>¿Qué soporte incluye?</span>
                  <i class="ri-arrow-down-s-line text-slate-400 group-open:rotate-180 transition-transform text-lg" aria-hidden="true"></i>
                </summary>
                <div class="mt-3 text-xs sm:text-sm text-slate-600 leading-relaxed border-t border-slate-100 pt-3 font-normal">
                  Incluye documentación completa, manuales de usuario y administración, guía de despliegue paso a paso y acompañamiento técnico inicial para la puesta en marcha.
                </div>
              </details>

              <details class="group bg-white rounded-2xl border border-slate-200/90 shadow-xs p-4.5 open:ring-1 open:ring-cyan-600/30 transition-all">
                <summary class="font-bold text-slate-900 text-sm cursor-pointer list-none flex items-center justify-between gap-2">
                  <span>¿El sistema es web y responsive?</span>
                  <i class="ri-arrow-down-s-line text-slate-400 group-open:rotate-180 transition-transform text-lg" aria-hidden="true"></i>
                </summary>
                <div class="mt-3 text-xs sm:text-sm text-slate-600 leading-relaxed border-t border-slate-100 pt-3 font-normal">
                  Sí, la plataforma es 100% web y está optimizada para funcionar fluidamente en computadoras de escritorio, tablets y dispositivos móviles sin necesidad de instalar aplicaciones adicionales.
                </div>
              </details>

            </div>
          </div>

        </div>

      </div>
    </section>

  </main>

  {{-- ============================================================
       11. FOOTER
  ============================================================ --}}
  <footer class="bg-slate-900 text-slate-400 py-12 border-t border-slate-800 text-xs">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
      <div class="grid grid-cols-2 md:grid-cols-6 gap-8">
        
        {{-- Brand Col (Social icons removed, balanced single unit) --}}
        <div class="col-span-2 space-y-3.5">
          <div class="flex items-center gap-2.5">
            <img src="{{ asset('images/demo/logo-demo.png') }}" alt="Logo JA MedSys" class="h-8 w-auto object-contain shrink-0" width="32" height="32">
            <span class="text-lg font-extrabold text-white tracking-tight">JA <span class="text-cyan-400">MedSys</span></span>
          </div>
          <p class="text-xs text-slate-400 leading-relaxed max-w-sm font-normal">
            Sistema web integral para la gestión de clínicas. Moderno, seguro y eficiente.
          </p>
        </div>

        {{-- Producto --}}
        <div class="space-y-2.5">
          <div class="font-bold text-slate-200 uppercase tracking-wider text-[11px]">Producto</div>
          <ul class="space-y-2 font-medium">
            <li><a href="#beneficios" class="hover:text-white transition-colors">Características</a></li>
            <li><a href="#modulos" class="hover:text-white transition-colors">Módulos</a></li>
            <li><a href="#capturas" class="hover:text-white transition-colors">Capturas</a></li>
            <li><a href="#roles" class="hover:text-white transition-colors">Roles</a></li>
          </ul>
        </div>

        {{-- Vista previa --}}
        <div class="space-y-2.5">
          <div class="font-bold text-slate-200 uppercase tracking-wider text-[11px]">Vista previa</div>
          <ul class="space-y-2 font-medium">
            <li><a href="{{ route('demo.clinic') }}" class="hover:text-white transition-colors">Clínica Demo</a></li>
            <li><a href="{{ route('demo.access.selector') }}" class="hover:text-white transition-colors">Selector de Roles</a></li>
            <li><a href="#vista-previa" class="hover:text-white transition-colors">Requisitos</a></li>
          </ul>
        </div>

        {{-- Documentación --}}
        <div class="space-y-2.5">
          <div class="font-bold text-slate-200 uppercase tracking-wider text-[11px]">Documentación</div>
          <ul class="space-y-2 font-medium">
            <li><a href="#faq" class="hover:text-white transition-colors">Preguntas Frecuentes</a></li>
            <li><a href="#que-incluye" class="hover:text-white transition-colors">Guía &amp; Manuales</a></li>
          </ul>
        </div>

        {{-- Contacto & Legal --}}
        <div class="space-y-2.5">
          <div class="font-bold text-slate-200 uppercase tracking-wider text-[11px]">Contacto</div>
          <ul class="space-y-2 font-medium">
            <li><a href="#contacto" class="hover:text-white transition-colors">Solicitar información</a></li>
            <li><a href="https://wa.me/593998740927" target="_blank" rel="noopener" data-action-lock-ignore="1" data-no-loader="1" class="hover:text-white transition-colors">WhatsApp</a></li>
            <li><a href="https://mail.google.com/mail/?view=cm&fs=1&to=alejandroucenriquez@gmail.com" target="_blank" rel="noopener noreferrer" data-action-lock-ignore="1" data-no-loader="1" class="hover:text-white transition-colors">Correo</a></li>
          </ul>
        </div>

      </div>

      {{-- Copyright --}}
      <div class="pt-8 border-t border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4 text-[11px] text-slate-400">
        <div>© 2026 JA MedSys. Todos los derechos reservados.</div>
        <div class="flex items-center gap-4 font-medium">
          <a href="#" class="hover:text-white transition-colors">Términos de uso</a>
          <a href="#" class="hover:text-white transition-colors">Política de privacidad</a>
          <a href="#" class="hover:text-white transition-colors">Licencia</a>
        </div>
      </div>
    </div>
  </footer>

  {{-- Script ultra-ligero específico de navegación móvil accesible --}}
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const menuBtn = document.getElementById('mobile-menu-btn');
      const mobileMenu = document.getElementById('mobile-menu');
      const iconOpen = document.getElementById('menu-icon-open');
      const iconClose = document.getElementById('menu-icon-close');

      if (menuBtn && mobileMenu) {
        menuBtn.addEventListener('click', function() {
          const isExpanded = menuBtn.getAttribute('aria-expanded') === 'true';
          menuBtn.setAttribute('aria-expanded', (!isExpanded).toString());
          mobileMenu.classList.toggle('hidden');
          if (iconOpen && iconClose) {
            iconOpen.classList.toggle('hidden');
            iconClose.classList.toggle('hidden');
          }
        });

        // Close on link click
        document.querySelectorAll('.mobile-nav-link').forEach(link => {
          link.addEventListener('click', () => {
            mobileMenu.classList.add('hidden');
            menuBtn.setAttribute('aria-expanded', 'false');
            if (iconOpen && iconClose) {
              iconOpen.classList.remove('hidden');
              iconClose.classList.add('hidden');
            }
          });
        });
      }
    });
  </script>
</body>
</html>
