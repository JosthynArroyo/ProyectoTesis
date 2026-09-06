{{-- resources/views/welcome.blade.php --}}
@extends('layouts.navbar')

@section('title', $clinicIdentity->name())

@push('styles')
  @vite('resources/css/welcome.css')
  @php
    $welcomeSoftPrimary = $siteSettings->get('visual.soft_primary', '#e2e8f0');
    $welcomeSoftSecondary = $siteSettings->get('visual.soft_secondary', '#f1f5f9');
    $welcomeGradientStart = $siteSettings->get('visual.gradient_start', '#e2e8f0');
    $welcomeGradientEnd = $siteSettings->get('visual.gradient_end', '#cbd5e1');
    $welcomeBadgeSoft = $siteSettings->get('visual.badge_soft', '#e2e8f0');
  @endphp
  <style>
    :root {
      --welcome-soft-primary: {{ $welcomeSoftPrimary }};
      --welcome-soft-secondary: {{ $welcomeSoftSecondary }};
      --welcome-gradient-start: {{ $welcomeGradientStart }};
      --welcome-gradient-end: {{ $welcomeGradientEnd }};
      --welcome-badge-soft: {{ $welcomeBadgeSoft }};
    }
  </style>
@endpush

@section('main')
  @php
    $isDemo = ($applicationMode?->isDemo() ?? app(\App\Services\ApplicationModeService::class)->isDemo());
    $heroPrimaryText = $landingWelcome->get('hero_primary_text', 'Agendar cita');
    $heroSecondaryText = $landingWelcome->get('hero_secondary_text', 'Explorar servicios');
    $heroSlides = array_values(array_filter($landingWelcome->slides(), fn ($slide) => $slide['is_active'] ?? false));
    $heroSlideCopy = [
      [
        'title' => 'Agenda de citas',
        'subtitle' => null,
        'text' => 'Elige especialidad, profesional, fecha y hora según la disponibilidad registrada.',
      ],
      [
        'title' => 'Seguimiento de atenciones',
        'subtitle' => null,
        'text' => 'Revisa el estado de tus citas y los documentos asociados a tu cuenta.',
      ],
      [
        'title' => 'Laboratorio y resultados',
        'subtitle' => null,
        'text' => 'Consulta solicitudes y resultados cuando estén disponibles en el sistema.',
      ],
    ];
    $showServicesBlock = $landingWelcome->getBool('show_services_block', true);

    $introBadge = $landingWelcome->get('intro_badge', 'Bienvenida');
    $welcomeBadge = trim((string) $introBadge);
    if ($welcomeBadge === '' || str_contains($welcomeBadge, 'Qué hace')) {
      $welcomeBadge = 'Bienvenida';
    }

    $servicesBadge = $landingWelcome->get('services_badge', 'Servicios');
    $servicesTitle = $landingWelcome->get('services_title', 'Especialidades disponibles');
    $servicesSubtitle = $landingWelcome->get('services_subtitle', 'Explora las especialidades de la clínica y agenda una cita según los horarios registrados.');
    $servicesButtonText = $landingWelcome->get('services_button_text', 'Ver todos');

    $pricesBadge = $landingWelcome->get('prices_badge', 'Tarifario');
    $pricesTitle = $landingWelcome->get('prices_title', 'Valores de referencia');
    $pricesSubtitle = $landingWelcome->get('prices_subtitle', 'Revisa los valores registrados para orientar tu agendamiento. La clínica puede confirmar el monto final antes de la atención.');
    $pricesHighlightTitle = $landingWelcome->get('prices_highlight_title', 'Atención en clínica');
    $pricesHighlightSubtitle = $landingWelcome->get('prices_highlight_subtitle', 'El sistema organiza la cita y la información necesaria para tu atención presencial.');
    $pricesVisitTitle = $landingWelcome->get('prices_visit_title', 'Agenda paso a paso');
    $pricesVisitSubtitle = $landingWelcome->get('prices_visit_subtitle', 'Selecciona especialidad, profesional, fecha y hora disponible antes de confirmar tu cita.');

    $doctorsBadge = $landingWelcome->get('doctors_badge', 'Nuestro equipo médico');
    $doctorsTitle = $landingWelcome->get('doctors_title', 'Profesionales comprometidos con tu salud');
    $doctorsSubtitle = $landingWelcome->get('doctors_subtitle', 'Contamos con especialistas altamente calificados para brindarte la mejor atención médica en un entorno seguro y confiable.');
    $doctorsPill = $landingWelcome->get('doctors_pill', 'Atención segura y confidencial');

    $doctors = collect($landingWelcome->doctors(true))->take(3)->values()->all();
    $prices = $landingWelcome->prices(true);
    $pricesList = collect(is_iterable($prices) ? $prices : []);
    $normalizePriceService = static function (?string $value): string {
      return \Illuminate\Support\Str::of((string) $value)
        ->ascii()
        ->lower()
        ->replaceMatches('/[^a-z0-9]+/', ' ')
        ->trim()
        ->value();
    };
    $priceIconMap = [
      'dermatologia' => 'ri-user-heart-line',
      'ginecologia' => 'ri-women-line',
      'laboratorio clinico' => 'ri-test-tube-line',
      'medicina general' => 'ri-stethoscope-line',
      'odontologia' => 'ri-tooth-line',
      'pediatria' => 'ri-bear-smile-line',
    ];
    $priceCards = $pricesList->map(function ($price) use ($normalizePriceService, $priceIconMap) {
      $serviceName = data_get($price, 'service', 'Servicio');
      $serviceName = filled($serviceName) ? (string) $serviceName : 'Servicio';
      $servicePrice = data_get($price, 'price');
      $servicePrice = filled($servicePrice) ? (string) $servicePrice : 'Consultar';

      return [
        'name' => $serviceName,
        'price' => $servicePrice,
        'icon' => $priceIconMap[$normalizePriceService($serviceName)] ?? 'ri-service-line',
        'is_consultation' => \Illuminate\Support\Str::of($servicePrice)->lower()->contains('consult'),
      ];
    })->values();
    $visitSteps = [
      ['number' => '1', 'title' => 'Especialidad', 'text' => 'Elige el servicio que necesitas.', 'icon' => 'ri-apps-2-line'],
      ['number' => '2', 'title' => 'Fecha', 'text' => 'Elige el día disponible para tu cita.', 'icon' => 'ri-calendar-line'],
      ['number' => '3', 'title' => 'Hora', 'text' => 'Selecciona el horario que prefieras.', 'icon' => 'ri-time-line'],
      ['number' => '4', 'title' => 'Confirmación', 'text' => 'Revisa y confirma tu cita.', 'icon' => 'ri-checkbox-circle-line'],
    ];
    $doctorsList = collect(is_iterable($doctors) ? $doctors : []);
    $normalizeDoctorSpecialty = static function (?string $value): string {
      return \Illuminate\Support\Str::of((string) $value)
        ->ascii()
        ->lower()
        ->replaceMatches('/[^a-z0-9]+/', ' ')
        ->trim()
        ->value();
    };
    $doctorSpecialtyAliases = [
      'cardiologia' => 'Medicina General',
    ];
    $doctorSpecialtyIcons = [
      'dermatologia' => 'ri-user-heart-line',
      'pediatria' => 'ri-bear-smile-line',
      'medicina general' => 'ri-stethoscope-line',
      'ginecologia' => 'ri-women-line',
      'odontologia' => 'ri-tooth-line',
      'laboratorio clinico' => 'ri-test-tube-line',
    ];
    $doctorExperienceDefaults = [
      'dermatologia' => '8+ años de experiencia',
      'pediatria' => '6+ años de experiencia',
      'medicina general' => '15+ años de experiencia',
      'ginecologia' => '10+ años de experiencia',
      'odontologia' => '7+ años de experiencia',
      'laboratorio clinico' => '12+ años de experiencia',
    ];
    $doctorPhotoPositions = [
      'img/doctora1.jpg' => 'object-[center_18%]',
      'img/doctor1.jpg' => 'object-[center_18%]',
      'img/doctor2.jpg' => 'object-[center_16%]',
    ];
    $doctorCards = $doctorsList->map(function ($doctor, $index) use (
      $doctorSpecialtyAliases,
      $doctorSpecialtyIcons,
      $doctorExperienceDefaults,
      $doctorPhotoPositions,
      $normalizeDoctorSpecialty,
      $imageUrl,
      $doctorsPill
    ) {
      $name = trim((string) data_get($doctor, 'name', 'Doctor'));
      $name = $name !== '' ? $name : 'Doctor';

      $rawSpecialty = trim((string) data_get($doctor, 'specialty', ''));
      $rawSpecialty = $rawSpecialty !== '' ? $rawSpecialty : 'Servicio';
      $normalizedSpecialty = $normalizeDoctorSpecialty($rawSpecialty);
      $displaySpecialty = $doctorSpecialtyAliases[$normalizedSpecialty] ?? $rawSpecialty;
      $specialtyKey = $normalizeDoctorSpecialty($displaySpecialty);

      $doctorPhotoPath = data_get($doctor, 'photo_path');
      $doctorPhoto = $imageUrl->variants($doctorPhotoPath, 'doctors', 'doctor', 'public_doctor');
      $doctorPhotoThumb = data_get($doctorPhoto, 'thumb', $imageUrl->fallback('doctor'));
      $doctorPhotoSrcset = data_get($doctorPhoto, 'srcset');
      $featuredLabel = trim((string) data_get($doctor, 'featured_label', ''));
      if ($featuredLabel === '') {
        $featuredLabel = \Illuminate\Support\Str::startsWith($name, 'Dra.') ? 'Destacada' : 'Destacado';
      }

      $experienceLabel = trim((string) data_get($doctor, 'experience_label', ''));
      if ($experienceLabel === '') {
        $experienceLabel = $doctorExperienceDefaults[$specialtyKey] ?? '5+ años de experiencia';
      }

      $attendanceLabel = trim((string) data_get($doctor, 'attendance_label', ''));
      $availabilityLabel = trim((string) data_get($doctor, 'availability_label', ''));

      return [
        'name' => $name,
        'specialty' => $displaySpecialty,
        'icon' => $doctorSpecialtyIcons[$specialtyKey] ?? 'ri-shield-heart-line',
        'featured_label' => $featuredLabel,
        'experience_label' => $experienceLabel,
        'attendance_label' => $attendanceLabel !== '' ? $attendanceLabel : 'Atención presencial',
        'availability_label' => $availabilityLabel !== '' ? $availabilityLabel : 'Agenda disponible',
        'photo_thumb' => $doctorPhotoThumb,
        'photo_srcset' => $doctorPhotoSrcset,
        'photo_class' => $doctorPhotoPositions[$doctorPhotoPath] ?? 'object-center',
        'sizes' => '(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw',
        'cta_text' => trim((string) data_get($doctor, 'cta_text', '')) ?: 'Agendar cita',
        'pill_text' => trim((string) data_get($doctor, 'pill_text', '')) ?: $doctorsPill,
        'key' => data_get($doctor, 'id', $index),
      ];
    })->values();
  @endphp

  {{-- ============================================================
       HERO SECTION  — 2 columnas: izquierda contenido / derecha galería
  ============================================================ --}}
  <section class="wh-hero">
    <div class="page-shell wh-hero__shell">

      {{-- ── COLUMNA IZQUIERDA ────────────────────────────────── --}}
      <div class="wh-hero__left">

        {{-- Badge BIENVENIDA --}}
        <span class="wh-badge welcome-fade-up welcome-delay-1">
          {{ $welcomeBadge }}
        </span>

        {{-- Título principal — "rápida y segura." en verde --}}
        <h1 class="wh-title welcome-fade-up welcome-delay-2">
          @php
            $heroTitle = $landingWelcome->get('hero_title', 'Gestiona tus citas médicas de forma rápida y segura.');
            // Separar última frase en verde (a partir de "rápida")
            $accentStart = mb_strpos($heroTitle, 'rápida');
            if ($accentStart !== false) {
              echo e(mb_substr($heroTitle, 0, $accentStart));
              echo '<span class="wh-title__accent">'.e(mb_substr($heroTitle, $accentStart)).'</span>';
            } else {
              echo e($heroTitle);
            }
          @endphp
        </h1>

        {{-- Subtítulo --}}
        <p class="wh-subtitle welcome-fade-up welcome-delay-3">
          {{ $landingWelcome->get('hero_subtitle', 'Agenda una cita, revisa resultados de laboratorio y consulta documentos médicos registrados en tu cuenta.') }}
        </p>

        {{-- 2 Mini-cards informativas --}}
        <div class="wh-info-cards welcome-fade-up welcome-delay-3">
          <div class="wh-info-card">
            <span class="wh-info-card__icon"><i class="ri-calendar-check-line" aria-hidden="true"></i></span>
            <div>
              <p class="wh-info-card__title">{{ $landingWelcome->get('intro_feature_1_title', 'Organiza tus atenciones') }}</p>
              <p class="wh-info-card__text">{{ $landingWelcome->get('intro_feature_1_text', 'Agenda, modifica y revisa tus citas desde tu cuenta.') }}</p>
            </div>
          </div>
          <div class="wh-info-card">
            <span class="wh-info-card__icon"><i class="ri-notification-3-line" aria-hidden="true"></i></span>
            <div>
              <p class="wh-info-card__title">{{ $landingWelcome->get('intro_feature_4_title', 'No te pierdas nada') }}</p>
              <p class="wh-info-card__text">{{ $landingWelcome->get('intro_feature_4_text', 'Recibe avisos y recordatorios sobre tus citas y resultados.') }}</p>
            </div>
          </div>
        </div>

        {{-- Botones CTA --}}
        <div class="wh-cta-row welcome-fade-up welcome-delay-4">
          @if($landingWelcome->getBool('hero_show_primary', true))
            @auth
              <a href="{{ route('home') }}" class="wh-btn-primary">
                <i class="ri-calendar-2-line" aria-hidden="true"></i>
                {{ $heroPrimaryText }}
              </a>
            @else
              <a href="{{ $isDemo ? route('demo.access.selector') : (url('/') . '?login=1') }}" class="wh-btn-primary" @if(! $isDemo) data-login-trigger @endif>
                <i class="ri-calendar-2-line" aria-hidden="true"></i>
                {{ $heroPrimaryText }}
              </a>
            @endauth
          @endif
          @if($landingWelcome->getBool('hero_show_secondary', true))
            <a href="{{ route('servicios.index') }}" class="wh-btn-secondary">
              <i class="ri-apps-2-line" aria-hidden="true"></i>
              {{ $heroSecondaryText }}
            </a>
          @endif
          @if($isDemo && \Illuminate\Support\Facades\Route::has('demo.access.selector'))
            <a href="{{ route('demo.access.selector') }}" class="wh-btn-secondary border border-sky-300 text-sky-700 bg-sky-50/80 hover:bg-sky-100 hover:text-sky-800" data-demo-preview-cta>
              <i class="ri-sparkling-line text-sky-500" aria-hidden="true"></i>
              Explorar sistema
            </a>
          @endif
        </div>

      </div>{{-- /left --}}

      {{-- ── COLUMNA DERECHA: galería/carousel ───────────────── --}}
      <div class="wh-hero__right welcome-fade-in welcome-delay-3">
        <div class="wh-gallery" data-carousel>
          <div class="wh-gallery__track" data-carousel-track>
            @foreach($heroSlides as $index => $slide)
              @php
                $slideImage = $imageUrl->variants($slide['image_path'] ?? null, 'banners', 'banner', 'public_hero');
                $defaultSlideCopy = $heroSlideCopy[$index % count($heroSlideCopy)];
                $slideCopy = [
                  'title' => filled($slide['title'] ?? null) ? $slide['title'] : $defaultSlideCopy['title'],
                  'subtitle' => filled($slide['subtitle'] ?? null) ? $slide['subtitle'] : ($defaultSlideCopy['subtitle'] ?? null),
                  'text' => filled($slide['text'] ?? null) ? $slide['text'] : $defaultSlideCopy['text'],
                ];
              @endphp
              <div class="wh-gallery__slide">
                <img
                  src="{{ $slideImage['medium'] }}"
                  @if($slideImage['srcset']) srcset="{{ $slideImage['srcset'] }}" sizes="(max-width:640px) 100vw,50vw" @endif
                  alt="{{ $slide['alt'] ?? 'Imagen clínica' }}"
                  class="wh-gallery__img"
                  loading="{{ $loop->first ? 'eager' : 'lazy' }}" decoding="async"
                >
                <div class="wh-gallery__overlay">
                  <span class="wh-gallery__overlay-icon"><i class="ri-test-tube-line" aria-hidden="true"></i></span>
                  <div>
                    @if(filled($slideCopy['subtitle'] ?? null))
                      <p class="wh-gallery__overlay-subtitle">{{ $slideCopy['subtitle'] }}</p>
                    @endif
                    <p class="wh-gallery__overlay-title">{{ $slideCopy['title'] }}</p>
                    <p class="wh-gallery__overlay-text">{{ $slideCopy['text'] }}</p>
                  </div>
                </div>
              </div>
            @endforeach
          </div>

          {{-- Controles --}}
          <button class="wh-gallery__ctrl wh-gallery__ctrl--prev" type="button" aria-label="Anterior" data-carousel-prev>
            <i class="ri-arrow-left-s-line"></i>
          </button>
          <button class="wh-gallery__ctrl wh-gallery__ctrl--next" type="button" aria-label="Siguiente" data-carousel-next>
            <i class="ri-arrow-right-s-line"></i>
          </button>

          {{-- Dots --}}
          <div class="wh-gallery__dots" role="tablist">
            @foreach($heroSlides as $slide)
              <button class="wh-gallery__dot {{ $loop->first ? 'is-active' : '' }}" aria-label="Diapositiva {{ $loop->iteration }}" data-carousel-dot></button>
            @endforeach
          </div>
        </div>
      </div>{{-- /right --}}

    </div>
  </section>


  {{-- ============================================================
       3 CARDS INFERIORES — Citas / Documentos / Recordatorios
  ============================================================ --}}
  <section class="wh-cards-section">
    <div class="page-shell wh-cards-grid">
      <a href="{{ auth()->check() ? route('paciente.citas') : ($isDemo ? route('demo.access.selector') : url('/?login=1')) }}" class="wh-card" @guest @if(! $isDemo) data-login-trigger @endif @endguest>
        <span class="wh-card__icon"><i class="ri-calendar-2-line" aria-hidden="true"></i></span>
        <div class="wh-card__body">
          <p class="wh-card__title">Citas médicas</p>
          <p class="wh-card__text">Agenda, modifica o cancela tus citas según disponibilidad.</p>
        </div>
        <i class="ri-arrow-right-line wh-card__arrow" aria-hidden="true"></i>
      </a>
      <a href="{{ auth()->check() ? route('paciente.laboratorio.index') : ($isDemo ? route('demo.access.selector') : url('/?login=1')) }}" class="wh-card" @guest @if(! $isDemo) data-login-trigger @endif @endguest>
        <span class="wh-card__icon"><i class="ri-file-text-line" aria-hidden="true"></i></span>
        <div class="wh-card__body">
          <p class="wh-card__title">Documentos médicos</p>
          <p class="wh-card__text">Consulta resultados, recetas y comprobantes.</p>
        </div>
        <i class="ri-arrow-right-line wh-card__arrow" aria-hidden="true"></i>
      </a>
      <a href="{{ auth()->check() ? route('paciente.citas') : ($isDemo ? route('demo.access.selector') : url('/?login=1')) }}" class="wh-card" @guest @if(! $isDemo) data-login-trigger @endif @endguest>
        <span class="wh-card__icon"><i class="ri-notification-3-line" aria-hidden="true"></i></span>
        <div class="wh-card__body">
          <p class="wh-card__title">Recordatorios</p>
          <p class="wh-card__text">Recibe avisos sobre tus próximas citas y resultados.</p>
        </div>
        <i class="ri-arrow-right-line wh-card__arrow" aria-hidden="true"></i>
      </a>
    </div>
  </section>


    @if($showServicesBlock && isset($especialidadesDestacadas) && $especialidadesDestacadas->count())
      <section class="welcome-section">
        <div class="page-shell">
          <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
              <p class="text-xs uppercase tracking-widest text-gray-500">{{ $servicesBadge }}</p>
              <h2 class="text-3xl font-semibold text-gray-900">{{ $servicesTitle }}</h2>
              <p class="text-gray-600">{{ $servicesSubtitle }}</p>
            </div>
            <a href="{{ route('servicios.index') }}" class="btn btn-outline">{{ $servicesButtonText }}</a>
          </div>

          <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($especialidadesDestacadas as $index => $esp)
              <div class="card p-5">
                <div class="welcome-service-icon mb-3 inline-flex h-12 w-12 items-center justify-center rounded-2xl">
                  <i class="{{ $esp->icono ?? 'ri-stethoscope-line' }}"></i>
                </div>
                <h3 class="text-lg font-semibold">{{ $esp->nombre }}</h3>
                <p class="text-sm text-gray-500">{{ $esp->descripcion }}</p>
              </div>
            @endforeach
          </div>
        </div>
      </section>
    @endif

    @if($pricesList->isNotEmpty())
      {{-- ===== ENCABEZADO: AGENDA PASO A PASO ===== --}}
      <section class="welcome-section ws-section ws-section--steps">
        <div class="page-shell">
          <div class="ws-steps-header">
            {{-- Ícono calendario — usa accent-soft del sistema --}}
            <div class="ws-steps-badge">
              <i class="ri-calendar-2-line" aria-hidden="true"></i>
            </div>
            {{-- Título en negro elegante — igual que h2 del resto del welcome --}}
            <h2 class="ws-steps-title">{{ $pricesVisitTitle }}</h2>
            <p class="ws-steps-subtitle">{{ $pricesVisitSubtitle }}</p>
          </div>

          {{-- 4 PASOS — clases CSS definidas arriba con tokens del sistema --}}
          <div class="welcome-steps-grid">
            @foreach($visitSteps as $step)
              <div class="welcome-step-card">
                <div class="welcome-step-num">{{ $step['number'] }}</div>
                <div class="welcome-step-icon"><i class="{{ $step['icon'] }}" aria-hidden="true"></i></div>
                <h4 class="welcome-step-title">{{ $step['title'] }}</h4>
                <p class="welcome-step-desc">{{ $step['text'] }}</p>
              </div>
            @endforeach
          </div>
        </div>
      </section>

      {{-- ===== SECCIÓN TARIFARIO ===== --}}
      <section class="welcome-section ws-section ws-section--prices">
        <div class="page-shell">
          <div class="welcome-prices-grid">

            {{-- Columna izquierda: tarifario --}}
            <div class="ws-prices-card">
              <p class="ws-prices-badge">{{ $pricesBadge }}</p>
              <h2 class="ws-prices-title">{{ $pricesTitle }}</h2>
              <p class="ws-prices-subtitle">{{ $pricesSubtitle }}</p>

              {{-- Grid de especialidades --}}
              <div class="welcome-price-cards">
                @foreach($priceCards as $priceCard)
                  <div class="welcome-price-card">
                    <div class="welcome-price-icon">
                      <i class="{{ data_get($priceCard,'icon','ri-service-line') }}" aria-hidden="true"></i>
                    </div>
                    <span class="welcome-price-name">{{ data_get($priceCard,'name','Servicio') }}</span>
                    <span class="welcome-price-amount">{{ data_get($priceCard,'price','Consultar') }}</span>
                  </div>
                @endforeach
              </div>
            </div>

            {{-- Columna derecha: atención en clínica (igual alto que tarifario) --}}
            <div class="ws-clinic-card">
              <div class="ws-clinic-card-header">
                <div class="ws-clinic-card-icon">
                  <i class="ri-shield-cross-line" aria-hidden="true"></i>
                </div>
                <div>
                  <h3 class="ws-clinic-card-title">{{ $pricesHighlightTitle }}</h3>
                  <p class="ws-clinic-card-subtitle">{{ $pricesHighlightSubtitle }}</p>
                </div>
              </div>
              <ul class="ws-clinic-bullets">
                <li class="ws-clinic-bullet">
                  <i class="ri-checkbox-circle-fill"></i>
                  Información segura y confidencial
                </li>
                <li class="ws-clinic-bullet">
                  <i class="ri-checkbox-circle-fill"></i>
                  Proceso rápido y organizado
                </li>
                <li class="ws-clinic-bullet">
                  <i class="ri-checkbox-circle-fill"></i>
                  Atención profesional y cercana
                </li>
              </ul>
            </div>

          </div>
        </div>
      </section>
    @endif

    @if($doctorCards->isNotEmpty())
      <section class="welcome-section">
        <div class="page-shell">
          <div class="mx-auto max-w-3xl text-center">
            <div class="welcome-team-badge inline-flex items-center gap-2 rounded-full px-5 py-2 text-sm font-semibold">
              <i class="ri-team-line" aria-hidden="true"></i>
              {{ $doctorsBadge }}
            </div>
            <h2 class="mt-5 text-3xl font-semibold tracking-tight text-gray-900 sm:text-4xl lg:text-[2.8rem]">{{ $doctorsTitle }}</h2>
            <p class="mx-auto mt-4 max-w-2xl text-base leading-8 text-gray-600 sm:text-lg">{{ $doctorsSubtitle }}</p>
            <div class="welcome-team-divider mx-auto mt-6 h-1 w-20 rounded-full"></div>
          </div>

          <div class="mt-10 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            @foreach($doctorCards as $doctorCard)
              <article class="overflow-hidden rounded-[2rem] border border-gray-200/80 bg-white shadow-[0_24px_70px_rgba(15,23,42,0.08)] transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_26px_80px_rgba(15,23,42,0.12)]">
                <div class="relative overflow-hidden rounded-t-[2rem] border-b border-gray-100 bg-gray-100">
                  <span class="welcome-team-chip absolute left-5 top-5 z-10 inline-flex items-center gap-2 rounded-full bg-white/95 px-4 py-2 text-sm font-semibold">
                    <i class="ri-star-smile-line" aria-hidden="true"></i>
                    {{ data_get($doctorCard, 'featured_label', 'Destacado') }}
                  </span>
                  <img
                    src="{{ data_get($doctorCard, 'photo_thumb') }}"
                    @if(data_get($doctorCard, 'photo_srcset')) srcset="{{ data_get($doctorCard, 'photo_srcset') }}" sizes="{{ data_get($doctorCard, 'sizes') }}" @endif
                    alt="{{ data_get($doctorCard, 'name', 'Doctor') }}"
                    class="h-72 w-full object-cover {{ data_get($doctorCard, 'photo_class', 'object-center') }} sm:h-80"
                    loading="lazy"
                    decoding="async"
                  >
                  <div class="pointer-events-none absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-white via-white/80 to-transparent"></div>
                </div>
                <div class="relative px-6 pb-6 pt-0">
                  <span class="welcome-team-avatar welcome-team-icon mx-auto -mt-9 inline-flex h-[74px] w-[74px] items-center justify-center rounded-full border-8 border-white bg-white text-3xl">
                    <i class="{{ data_get($doctorCard, 'icon', 'ri-shield-heart-line') }}" aria-hidden="true"></i>
                  </span>
                  <div class="mt-4 text-center">
                    <h3 class="text-[1.75rem] font-semibold tracking-tight text-gray-900">{{ data_get($doctorCard, 'name', 'Doctor') }}</h3>
                    <p class="welcome-team-specialty mt-1 text-lg font-medium">{{ data_get($doctorCard, 'specialty', 'Servicio') }}</p>
                    <div class="welcome-team-divider mx-auto mt-4 h-1 w-16 rounded-full"></div>
                  </div>

                  <ul class="mt-6 space-y-3 text-sm text-gray-600">
                    <li class="flex items-center gap-3 rounded-2xl bg-gray-50 px-4 py-3">
                      <span class="welcome-team-icon inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-white shadow-sm shadow-gray-200/80">
                        <i class="ri-star-line" aria-hidden="true"></i>
                      </span>
                      <span>{{ data_get($doctorCard, 'experience_label', '5+ años de experiencia') }}</span>
                    </li>
                    <li class="flex items-center gap-3 rounded-2xl bg-gray-50 px-4 py-3">
                      <span class="welcome-team-icon inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-white shadow-sm shadow-gray-200/80">
                        <i class="ri-user-heart-line" aria-hidden="true"></i>
                      </span>
                      <span>{{ data_get($doctorCard, 'attendance_label', 'Atención presencial') }}</span>
                    </li>
                    <li class="flex items-center gap-3 rounded-2xl bg-gray-50 px-4 py-3">
                      <span class="welcome-team-icon inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-white shadow-sm shadow-gray-200/80">
                        <i class="ri-calendar-check-line" aria-hidden="true"></i>
                      </span>
                      <span>{{ data_get($doctorCard, 'availability_label', 'Agenda disponible') }}</span>
                    </li>
                  </ul>

                  <div class="mt-6">
                    @auth
                      <a href="{{ route('paciente.crear-cita') }}" class="btn btn-primary btn-lg w-full justify-center">
                        <i class="ri-calendar-check-line" aria-hidden="true"></i>
                        {{ data_get($doctorCard, 'cta_text', 'Agendar cita') }}
                      </a>
                    @else
                      <a href="{{ $isDemo ? route('demo.access.selector') : (url('/') . '?login=1') }}" class="btn btn-primary btn-lg w-full justify-center" @if(! $isDemo) data-login-trigger @endif>
                        <i class="ri-login-circle-line" aria-hidden="true"></i>
                        {{ data_get($doctorCard, 'cta_text', 'Agendar cita') }}
                      </a>
                    @endauth
                  </div>

                  <p class="mt-5 flex items-center justify-center gap-2 text-sm font-medium text-gray-500">
                    <i class="welcome-team-foot ri-shield-check-line text-base" aria-hidden="true"></i>
                    {{ data_get($doctorCard, 'pill_text', $doctorsPill) }}
                  </p>
                </div>
              </article>
            @endforeach
          </div>

          <div class="welcome-team-cta-block mt-10 rounded-[2rem] border border-gray-200/80 p-6 shadow-[0_22px_65px_rgba(15,23,42,0.08)] sm:p-8">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
              <div class="flex items-start gap-4">
                <span class="welcome-team-cta-box inline-flex h-16 w-16 shrink-0 items-center justify-center rounded-[1.5rem] text-3xl">
                  <i class="ri-calendar-schedule-line" aria-hidden="true"></i>
                </span>
                <div class="max-w-2xl">
                  <h3 class="text-2xl font-semibold text-gray-900 sm:text-[2rem]">¿Listo para tu próxima consulta?</h3>
                  <p class="mt-2 text-base leading-7 text-gray-600">Agenda tu cita con el especialista que necesitas de forma rápida, segura y desde cualquier dispositivo.</p>
                </div>
              </div>
              <div class="flex flex-col gap-3 sm:flex-row">
                @auth
                  <a href="{{ route('paciente.crear-cita') }}" class="btn btn-primary btn-lg">
                    <i class="ri-calendar-check-line" aria-hidden="true"></i>
                    Agendar cita ahora
                  </a>
                @else
                  <a href="{{ $isDemo ? route('demo.access.selector') : (url('/') . '?login=1') }}" class="btn btn-primary btn-lg" @if(! $isDemo) data-login-trigger @endif>
                    <i class="ri-login-circle-line" aria-hidden="true"></i>
                    Agendar cita ahora
                  </a>
                @endauth
                <a href="{{ route('servicios.index') }}" class="btn btn-outline btn-lg">
                  Ver todos los servicios
                  <i class="ri-arrow-right-line" aria-hidden="true"></i>
                </a>
              </div>
            </div>
          </div>
        </div>
      </section>
    @endif

    @include('partials.footer')
  </div>
@endsection
