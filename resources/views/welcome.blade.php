{{-- resources/views/welcome.blade.php --}}
@extends('layouts.navbar')

@section('title','Clínica Don Bosco')

@push('styles')
  <style>
    @keyframes welcomeInfoMarquee {
      from {
        transform: translate3d(0, 0, 0);
      }
      to {
        transform: translate3d(-50%, 0, 0);
      }
    }

    .welcome-info-marquee {
      --welcome-info-duration: 38s;
    }

    .welcome-info-marquee__track {
      animation: welcomeInfoMarquee var(--welcome-info-duration) linear infinite;
      will-change: transform;
    }

    .welcome-info-marquee:hover .welcome-info-marquee__track,
    .welcome-info-marquee:focus-within .welcome-info-marquee__track {
      animation-play-state: paused;
    }

    @media (prefers-reduced-motion: reduce) {
      .welcome-info-marquee__viewport {
        overflow-x: auto;
        scroll-behavior: auto;
      }

      .welcome-info-marquee__track {
        animation: none;
        transform: none;
      }

      .welcome-info-marquee__copy[aria-hidden='true'] {
        display: none;
      }
    }
  </style>
@endpush

@section('main')
  @php
    $heroPrimaryText = $landingWelcome->get('hero_primary_text', 'Agendar cita');
    $heroSecondaryText = $landingWelcome->get('hero_secondary_text', 'Explorar servicios');
    $heroFollowupTitle = $landingWelcome->get('hero_followup_title', 'Seguimiento de tus citas');
    $heroFollowupSubtitle = $landingWelcome->get('hero_followup_subtitle', 'Recibe avisos sobre tus próximas citas y revisa el estado de tus atenciones cuando estén registradas.');
    $heroStats = $landingWelcome->stats(true);
    $heroSlides = array_values(array_filter($landingWelcome->slides(), fn ($slide) => $slide['is_active'] ?? false));
    $heroSlideCopy = [
      [
        'title' => 'Agenda de citas',
        'text' => 'Elige especialidad, profesional, fecha y hora según la disponibilidad registrada.',
      ],
      [
        'title' => 'Seguimiento de atenciones',
        'text' => 'Revisa el estado de tus citas y los documentos asociados a tu cuenta.',
      ],
      [
        'title' => 'Laboratorio y resultados',
        'text' => 'Consulta solicitudes y resultados cuando estén disponibles en el sistema.',
      ],
    ];
    $showServicesBlock = $landingWelcome->getBool('show_services_block', true);

    $introBadge = $landingWelcome->get('intro_badge', 'Bienvenida');
    $introTitle = $landingWelcome->get('intro_title', 'Qué puedes hacer en la plataforma.');
    $introSubtitle = $landingWelcome->get('intro_subtitle', 'La página reúne las opciones principales para pacientes: agendar, revisar citas, consultar documentos médicos y mantenerse informado.');
    $welcomeBadge = trim((string) $introBadge);
    if ($welcomeBadge === '' || str_contains($welcomeBadge, 'Qué hace')) {
      $welcomeBadge = 'Bienvenida';
    }
    $heroValueTitle = trim((string) $introTitle);
    if ($heroValueTitle === '' || str_contains($heroValueTitle, 'Qué puedes hacer')) {
      $heroValueTitle = 'Organiza tus atenciones antes y después de cada cita.';
    }
    $heroValueSubtitle = trim((string) $introSubtitle);
    if ($heroValueSubtitle === '' || str_contains($heroValueSubtitle, 'opciones principales')) {
      $heroValueSubtitle = 'Ahorra tiempo al agendar, revisar avisos y consultar documentos médicos desde tu cuenta.';
    }
    $featuresBadge = 'Funcionalidades';
    $featuresTitle = 'Lo que puedes gestionar desde tu cuenta.';
    $featuresSubtitle = 'Estas son las acciones principales disponibles para pacientes dentro de la plataforma.';
    $introFeatures = [
      [
        'title' => $landingWelcome->get('intro_feature_1_title', 'Agendar citas'),
        'subtitle' => $landingWelcome->get('intro_feature_1_text', 'Selecciona especialidad, profesional, fecha y hora según la disponibilidad registrada.'),
      ],
      [
        'title' => $landingWelcome->get('intro_feature_2_title', 'Revisar tu información'),
        'subtitle' => $landingWelcome->get('intro_feature_2_text', 'Consulta citas, recetas, resultados y documentos médicos asociados a tus atenciones.'),
      ],
      [
        'title' => $landingWelcome->get('intro_feature_3_title', 'Datos protegidos'),
        'subtitle' => $landingWelcome->get('intro_feature_3_text', 'Tu cuenta usa acceso seguro para proteger la información médica y administrativa.'),
      ],
      [
        'title' => $landingWelcome->get('intro_feature_4_title', 'Avisos de seguimiento'),
        'subtitle' => $landingWelcome->get('intro_feature_4_text', 'Recibe recordatorios y notificaciones relacionadas con tus citas y resultados.'),
      ],
    ];

    $servicesBadge = $landingWelcome->get('services_badge', 'Servicios');
    $servicesTitle = $landingWelcome->get('services_title', 'Especialidades disponibles');
    $servicesSubtitle = $landingWelcome->get('services_subtitle', 'Explora las especialidades de la clínica y agenda una cita según los horarios registrados.');
    $servicesButtonText = $landingWelcome->get('services_button_text', 'Ver todos');

    $pricesBadge = $landingWelcome->get('prices_badge', 'Tarifario');
    $pricesTitle = $landingWelcome->get('prices_title', 'Valores de referencia');
    $pricesSubtitle = $landingWelcome->get('prices_subtitle', 'Revisa los valores registrados para orientar tu agendamiento. La clínica puede confirmar el monto final antes de la atención.');
    $pricesButtonText = $landingWelcome->get('prices_button_text', 'Agendar cita');
    $pricesHighlightTitle = $landingWelcome->get('prices_highlight_title', 'Atención en clínica');
    $pricesHighlightSubtitle = $landingWelcome->get('prices_highlight_subtitle', 'El sistema organiza la cita y la información necesaria para tu atención presencial.');
    $pricesHighlightImagePath = $landingWelcome->get('prices_highlight_image', 'img/doctor2.jpg');
    $pricesVisitTitle = $landingWelcome->get('prices_visit_title', 'Agenda paso a paso');
    $pricesVisitSubtitle = $landingWelcome->get('prices_visit_subtitle', 'Selecciona especialidad, profesional, fecha y hora disponible antes de confirmar tu cita.');

    $doctorsBadge = $landingWelcome->get('doctors_badge', 'Equipo');
    $doctorsTitle = $landingWelcome->get('doctors_title', 'Profesionales disponibles');
    $doctorsSubtitle = $landingWelcome->get('doctors_subtitle', 'Conoce el equipo registrado para las especialidades de la clínica.');
    $doctorsPill = $landingWelcome->get('doctors_pill', 'Atención presencial agendada');

    $infoCards = $landingWelcome->infoCards(true);
    $doctors = $landingWelcome->doctors(true);
    $prices = $landingWelcome->prices(true);

    $infoCardStyles = [
      ['bg' => 'bg-teal-50', 'text' => 'text-teal-700'],
      ['bg' => 'bg-sky-50', 'text' => 'text-sky-700'],
      ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-700'],
      ['bg' => 'bg-amber-50', 'text' => 'text-amber-700'],
    ];
  @endphp

  <div style="min-height:calc(100vh - 4.5rem);display:flex;flex-direction:column;">
    <section class="welcome-section welcome-section--first welcome-section--hero">
      <div class="page-shell">
        <div class="grid items-center gap-8 lg:grid-cols-[1.05fr_0.95fr]">
          <div class="space-y-6">
            <div class="inline-flex items-center gap-2 rounded-full bg-teal-50 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-teal-700 welcome-fade-up welcome-delay-1">
              <span class="h-2 w-2 rounded-full bg-teal-500"></span>
              {{ $welcomeBadge }}
            </div>
            <h1 class="max-w-2xl text-4xl font-semibold leading-tight text-slate-900 sm:text-5xl lg:text-6xl welcome-fade-up welcome-delay-2">{{ $landingWelcome->get('hero_title', 'Gestiona tus citas médicas de forma rápida y segura.') }}</h1>
            <p class="max-w-2xl text-lg leading-8 text-slate-600 welcome-fade-up welcome-delay-3">{{ $landingWelcome->get('hero_subtitle', 'Agenda una cita, revisa resultados de laboratorio y consulta documentos médicos registrados en tu cuenta.') }}</p>
            <div class="max-w-2xl rounded-lg border border-teal-100 bg-white/80 p-4 text-sm text-slate-600 shadow-sm welcome-fade-up welcome-delay-3">
              <p class="font-semibold text-slate-900">{{ $heroValueTitle }}</p>
              <p class="mt-1">{{ $heroValueSubtitle }}</p>
            </div>
            <div class="flex flex-wrap gap-3 welcome-fade-up welcome-delay-4">
              @if($landingWelcome->getBool('hero_show_primary', true))
                @auth
                  <a href="{{ route('home') }}" class="btn btn-primary">{{ $heroPrimaryText }}</a>
                @else
                  <a href="{{ url('/') . '?login=1' }}" class="btn btn-primary" data-login-trigger>{{ $heroPrimaryText }}</a>
                @endauth
              @endif
              @if($landingWelcome->getBool('hero_show_secondary', true))
                <a href="{{ route('servicios.index') }}" class="btn btn-outline">{{ $heroSecondaryText }}</a>
              @endif
            </div>
          </div>

          <div class="relative">
            <div class="carousel card overflow-hidden welcome-fade-in welcome-delay-3" data-carousel>
              <div class="carousel__track flex transition-transform duration-500" data-carousel-track>
                @foreach($heroSlides as $index => $slide)
                  @php
                    $slideImage = $imageUrl->variants($slide['image_path'] ?? null, 'banners', 'banner');
                    $slideCopy = $heroSlideCopy[$index % count($heroSlideCopy)];
                  @endphp
                  <div class="carousel__slide relative min-w-full">
                    <img
                      src="{{ $slideImage['thumb'] }}"
                      @if($slideImage['srcset']) srcset="{{ $slideImage['srcset'] }}" sizes="(max-width: 640px) 100vw, 50vw" @endif
                      alt="{{ $slide['alt'] ?? 'Imagen del slider' }}"
                      class="h-80 w-full object-cover sm:h-96"
                      loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                      decoding="async"
                    >
                    <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-slate-950/75 via-slate-950/45 to-transparent px-5 pb-6 pt-16 text-white">
                      <p class="text-base font-semibold">{{ $slideCopy['title'] }}</p>
                      <p class="mt-1 max-w-md text-sm leading-6 text-white/90">{{ $slideCopy['text'] }}</p>
                    </div>
                  </div>
                @endforeach
              </div>

              <button class="carousel__control prev" type="button" aria-label="Anterior" data-carousel-prev>
                <i class="ri-arrow-left-s-line"></i>
              </button>
              <button class="carousel__control next" type="button" aria-label="Siguiente" data-carousel-next>
                <i class="ri-arrow-right-s-line"></i>
              </button>

              <div class="carousel__indicators" role="tablist">
                @foreach($heroSlides as $slide)
                  <button class="dot {{ $loop->first ? 'is-active' : '' }}" aria-label="Ir a la diapositiva {{ $loop->iteration }}" data-carousel-dot></button>
                @endforeach
              </div>
            </div>

            <div class="mt-5 rounded-2xl border border-slate-200 bg-white/90 p-5 text-sm text-slate-600 shadow-sm welcome-fade-up welcome-delay-4">
              <p class="font-semibold text-slate-800">{{ $heroFollowupTitle }}</p>
              <p>{{ $heroFollowupSubtitle }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    @if(count($heroStats))
      <section class="pb-6 sm:pb-8">
        <div class="page-shell">
          <div class="grid-auto-fit welcome-stagger">
            @foreach($heroStats as $stat)
              <div class="card p-4">
                @if(!empty($stat['label']))
                  <p class="text-xs uppercase tracking-wide text-slate-500">{{ $stat['label'] }}</p>
                @endif
                @if(!empty($stat['value']))
                  <p class="{{ empty($stat['label']) ? '' : 'mt-2' }} text-2xl font-semibold text-slate-900">{{ $stat['value'] }}</p>
                @endif
                @if(!empty($stat['note']))
                  <p class="text-sm text-slate-500">{{ $stat['note'] }}</p>
                @endif
              </div>
            @endforeach
          </div>
        </div>
      </section>
    @endif

    @if(count($infoCards))
      <section class="pb-6 sm:pb-8">
        <div class="page-shell">
          <div class="welcome-info-marquee overflow-hidden rounded-lg border border-white/70 bg-white/70 py-4 text-sm shadow-sm backdrop-blur welcome-fade-up welcome-delay-2 [mask-image:linear-gradient(to_right,transparent,black_7%,black_93%,transparent)]" style="--welcome-info-duration: 38s;" aria-label="Beneficios destacados">
            <div class="welcome-info-marquee__viewport overflow-hidden px-4 py-1 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden" tabindex="0">
              <div class="welcome-info-marquee__track flex w-max">
                @for($copy = 0; $copy < 2; $copy++)
                  <div class="welcome-info-marquee__copy flex shrink-0 gap-4 pr-4" @if($copy === 0) role="list" @else aria-hidden="true" @endif>
                    @foreach($infoCards as $index => $card)
                      @php
                        $cardStyle = $infoCardStyles[$index % count($infoCardStyles)];
                      @endphp
                      <div class="card flex w-[82vw] max-w-[21rem] shrink-0 items-start gap-3 border-slate-200/80 bg-white/95 p-4 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-slate-300 hover:shadow-lg sm:w-[300px] lg:w-[290px] xl:w-[300px]" @if($copy === 0) role="listitem" @endif>
                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $cardStyle['bg'] }} {{ $cardStyle['text'] }}">
                          <i class="{{ $card['icon'] ?? 'ri-information-line' }}"></i>
                        </span>
                        <div class="min-w-0">
                          <p class="font-semibold text-slate-900">{{ $card['title'] ?? '' }}</p>
                          @if(!empty($card['value']))
                            <p class="text-xs text-slate-500">{{ $card['value'] }}</p>
                          @endif
                          <p class="text-xs text-slate-500">{{ $card['description'] ?? '' }}</p>
                        </div>
                      </div>
                    @endforeach
                  </div>
                @endfor
              </div>
            </div>
          </div>
        </div>
      </section>
    @endif

    <section class="welcome-section welcome-section--next">
      <div class="page-shell grid gap-8 lg:grid-cols-[0.7fr_1.3fr]">
        <div class="space-y-4">
          <p class="text-xs uppercase tracking-widest text-slate-500">{{ $featuresBadge }}</p>
          <h2 class="text-3xl font-semibold text-slate-900">{{ $featuresTitle }}</h2>
          <p class="text-slate-600">{{ $featuresSubtitle }}</p>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          @foreach($introFeatures as $feature)
            <div class="card p-5">
              <p class="text-sm font-semibold text-slate-900">{{ $feature['title'] }}</p>
              <p class="text-sm text-slate-500">{{ $feature['subtitle'] }}</p>
            </div>
          @endforeach
        </div>
      </div>
    </section>

    @if($showServicesBlock && isset($especialidadesDestacadas) && $especialidadesDestacadas->count())
      <section class="welcome-section">
        <div class="page-shell">
          <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
              <p class="text-xs uppercase tracking-widest text-slate-500">{{ $servicesBadge }}</p>
              <h2 class="text-3xl font-semibold text-slate-900">{{ $servicesTitle }}</h2>
              <p class="text-slate-600">{{ $servicesSubtitle }}</p>
            </div>
            <a href="{{ route('servicios.index') }}" class="btn btn-outline">{{ $servicesButtonText }}</a>
          </div>

          <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @php
              $iconStyles = [
                ['bg' => 'bg-teal-50', 'text' => 'text-teal-700'],
                ['bg' => 'bg-amber-50', 'text' => 'text-amber-700'],
                ['bg' => 'bg-sky-50', 'text' => 'text-sky-700'],
                ['bg' => 'bg-rose-50', 'text' => 'text-rose-700'],
              ];
            @endphp

            @foreach($especialidadesDestacadas as $index => $esp)
              @php($iconStyle = $iconStyles[$index % count($iconStyles)])
              <div class="card p-5">
                <div class="mb-3 inline-flex h-12 w-12 items-center justify-center rounded-2xl {{ $iconStyle['bg'] }} {{ $iconStyle['text'] }}">
                  <i class="{{ $esp->icono ?? 'ri-stethoscope-line' }}"></i>
                </div>
                <h3 class="text-lg font-semibold">{{ $esp->nombre }}</h3>
                <p class="text-sm text-slate-500">{{ $esp->descripcion }}</p>
              </div>
            @endforeach
          </div>
        </div>
      </section>
    @endif

    @if(count($prices))
      <section class="welcome-section">
        <div class="page-shell grid gap-6 lg:grid-cols-[1fr_0.9fr]">
          <div class="card p-6">
            <p class="text-xs uppercase tracking-widest text-slate-500">{{ $pricesBadge }}</p>
            <h2 class="mt-2 text-2xl font-semibold text-slate-900">{{ $pricesTitle }}</h2>
            <p class="text-slate-600">{{ $pricesSubtitle }}</p>

            <div class="mt-4 table-shell">
              <table class="table min-w-0">
                <tbody>
                  @foreach($prices as $price)
                    <tr><td>{{ $price['service'] ?? '' }}</td><td class="text-right font-semibold">{{ $price['price'] ?? '' }}</td></tr>
                  @endforeach
                </tbody>
              </table>
            </div>

            <div class="mt-5">
              @auth
                <a href="{{ route('paciente.dashboard') }}" class="btn btn-primary">{{ $pricesButtonText }}</a>
              @else
                <a href="{{ url('/') . '?login=1' }}" class="btn btn-primary" data-login-trigger>{{ $pricesButtonText }}</a>
              @endauth
            </div>
          </div>

          <div class="space-y-5">
            @php($teamImage = $imageUrl->variants($pricesHighlightImagePath, 'banners', 'doctor'))
            <div class="card overflow-hidden">
              <div class="professional-photo-frame professional-photo-frame--wide">
                <img
                  src="{{ $teamImage['thumb'] }}"
                  @if($teamImage['srcset']) srcset="{{ $teamImage['srcset'] }}" sizes="(max-width: 768px) 100vw, 40vw" @endif
                  alt="{{ $pricesHighlightTitle }}"
                  class="professional-photo"
                  loading="lazy"
                  decoding="async"
                >
              </div>
              <div class="p-5">
                <h3 class="text-lg font-semibold">{{ $pricesHighlightTitle }}</h3>
                <p class="text-sm text-slate-500">{{ $pricesHighlightSubtitle }}</p>
              </div>
            </div>

            <div class="card p-5">
              <h3 class="text-lg font-semibold">{{ $pricesVisitTitle }}</h3>
              <p class="text-sm text-slate-500">{{ $pricesVisitSubtitle }}</p>
            </div>
          </div>
        </div>
      </section>
    @endif

    @if(count($doctors))
      <section class="welcome-section">
        <div class="page-shell">
          <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
              <p class="text-xs uppercase tracking-widest text-slate-500">{{ $doctorsBadge }}</p>
              <h2 class="text-3xl font-semibold text-slate-900">{{ $doctorsTitle }}</h2>
              <p class="text-slate-600">{{ $doctorsSubtitle }}</p>
            </div>
            <span class="pill">{{ $doctorsPill }}</span>
          </div>

          <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($doctors as $doctor)
              @php($doctorPhoto = $imageUrl->variants($doctor['photo_path'] ?? null, 'doctors', 'doctor'))
              <div class="card overflow-hidden">
                <div class="doctor-photo-frame">
                  <img
                    src="{{ $doctorPhoto['thumb'] }}"
                    @if($doctorPhoto['srcset']) srcset="{{ $doctorPhoto['srcset'] }}" sizes="(max-width: 768px) 100vw, 33vw" @endif
                    alt="{{ $doctor['name'] ?? 'Doctor' }}"
                    class="doctor-photo"
                    loading="lazy"
                    decoding="async"
                  >
                </div>
                <div class="p-5">
                  <p class="text-sm font-semibold">{{ $doctor['name'] ?? '' }}</p>
                  <p class="text-xs text-slate-500">{{ $doctor['specialty'] ?? '' }}</p>
                </div>
              </div>
            @endforeach
          </div>
        </div>
      </section>
    @endif

    @include('partials.footer')
  </div>
@endsection
