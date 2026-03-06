{{-- resources/views/welcome.blade.php --}}
@extends('layouts.navbar')

@section('title','Clínica Don Bosco')

@section('main')
  @php
    $heroPrimaryText = $landingWelcome->get('hero_primary_text', 'Agendar cita');
    $heroSecondaryText = $landingWelcome->get('hero_secondary_text', 'Explorar servicios');
    $heroFollowupTitle = $landingWelcome->get('hero_followup_title', 'Seguimiento personalizado');
    $heroFollowupSubtitle = $landingWelcome->get('hero_followup_subtitle', 'Recibe recordatorios y notificaciones sobre tus citas, resultados y seguimiento médico.');
    $heroStats = $landingWelcome->stats(true);
    $heroSlides = array_values(array_filter($landingWelcome->slides(), fn ($slide) => $slide['is_active'] ?? false));
    $showServicesBlock = $landingWelcome->getBool('show_services_block', true);

    $introBadge = $landingWelcome->get('intro_badge', 'Bienvenida');
    $introTitle = $landingWelcome->get('intro_title', 'Gestiona tus citas médicas en un entorno seguro.');
    $introSubtitle = $landingWelcome->get('intro_subtitle', 'Accede a consultas con médicos especializados desde cualquier lugar. Organiza tus visitas en pocos pasos y mantente informado.');
    $introFeatures = [
      [
        'title' => $landingWelcome->get('intro_feature_1_title', 'Agenda inteligente'),
        'subtitle' => $landingWelcome->get('intro_feature_1_text', 'Confirmaciones automáticas, recordatorios y reprogramación sencilla.'),
      ],
      [
        'title' => $landingWelcome->get('intro_feature_2_title', 'Historial centralizado'),
        'subtitle' => $landingWelcome->get('intro_feature_2_text', 'Tus recetas, resultados y citas siempre disponibles.'),
      ],
      [
        'title' => $landingWelcome->get('intro_feature_3_title', 'Seguridad y privacidad'),
        'subtitle' => $landingWelcome->get('intro_feature_3_text', 'Control de acceso por rol y datos protegidos.'),
      ],
      [
        'title' => $landingWelcome->get('intro_feature_4_title', 'Atención humana'),
        'subtitle' => $landingWelcome->get('intro_feature_4_text', 'Personal médico listo para responder tus dudas.'),
      ],
    ];

    $servicesBadge = $landingWelcome->get('services_badge', 'Servicios');
    $servicesTitle = $landingWelcome->get('services_title', 'Especialidades destacadas');
    $servicesSubtitle = $landingWelcome->get('services_subtitle', 'Atención médica integral con profesionales certificados.');
    $servicesButtonText = $landingWelcome->get('services_button_text', 'Ver todos');

    $pricesBadge = $landingWelcome->get('prices_badge', 'Tarifario');
    $pricesTitle = $landingWelcome->get('prices_title', 'Precios transparentes');
    $pricesSubtitle = $landingWelcome->get('prices_subtitle', 'Consulta los valores aproximados y pregunta por promociones actuales.');
    $pricesButtonText = $landingWelcome->get('prices_button_text', 'Agendar cita');
    $pricesHighlightTitle = $landingWelcome->get('prices_highlight_title', 'Equipo profesional');
    $pricesHighlightSubtitle = $landingWelcome->get('prices_highlight_subtitle', 'Especialistas enfocados en un trato cercano y humano.');
    $pricesHighlightImagePath = $landingWelcome->get('prices_highlight_image', 'img/doctor2.jpg');
    $pricesVisitTitle = $landingWelcome->get('prices_visit_title', 'Agenda tu visita en minutos');
    $pricesVisitSubtitle = $landingWelcome->get('prices_visit_subtitle', 'Nuestro sistema te guia paso a paso para seleccionar especialista, fecha y hora.');

    $doctorsBadge = $landingWelcome->get('doctors_badge', 'Equipo');
    $doctorsTitle = $landingWelcome->get('doctors_title', 'Nuestros doctores');
    $doctorsSubtitle = $landingWelcome->get('doctors_subtitle', 'Profesionales comprometidos con tu bienestar.');
    $doctorsPill = $landingWelcome->get('doctors_pill', 'Atención personalizada');

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
        <div class="grid gap-8 lg:grid-cols-[1.1fr_0.9fr]">
          <div class="space-y-5">
            <div class="inline-flex items-center gap-2 rounded-full bg-teal-50 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-teal-700 welcome-fade-up welcome-delay-1">
              <span class="h-2 w-2 rounded-full bg-teal-500"></span>
              {{ $landingWelcome->get('hero_badge', 'Salud integral y tecnología humana') }}
            </div>
            <h1 class="text-4xl font-semibold text-slate-900 sm:text-5xl welcome-fade-up welcome-delay-2">{{ $landingWelcome->get('hero_title', 'Tu clínica digital para una atención más cercana y rápida.') }}</h1>
            <p class="text-lg text-slate-600 welcome-fade-up welcome-delay-3">{{ $landingWelcome->get('hero_subtitle', 'Agenda consultas, revisa resultados y recibe recordatorios inteligentes desde cualquier dispositivo. Todo en un mismo lugar.') }}</p>
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

            @if(count($heroStats))
              <div class="grid-auto-fit welcome-stagger">
                @foreach($heroStats as $stat)
                  <div class="card p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ $stat['label'] ?? '' }}</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $stat['value'] ?? '' }}</p>
                    <p class="text-sm text-slate-500">{{ $stat['note'] ?? '' }}</p>
                  </div>
                @endforeach
              </div>
            @endif
          </div>

          <div class="relative">
            <div class="carousel card overflow-hidden welcome-fade-in welcome-delay-3" data-carousel>
              <div class="carousel__track flex transition-transform duration-500" data-carousel-track>
                @foreach($heroSlides as $slide)
                  @php
                    $slideImage = $imageUrl->variants($slide['image_path'] ?? null, 'banners', 'banner');
                  @endphp
                  <div class="carousel__slide min-w-full">
                    <img
                      src="{{ $slideImage['thumb'] }}"
                      @if($slideImage['srcset']) srcset="{{ $slideImage['srcset'] }}" sizes="(max-width: 640px) 100vw, 50vw" @endif
                      alt="{{ $slide['alt'] ?? 'Imagen del slider' }}"
                      class="h-80 w-full object-cover sm:h-96"
                      loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                      decoding="async"
                    >
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

    @if(count($infoCards))
      <section class="pb-6 sm:pb-8">
        <div class="page-shell">
          <div class="rounded-2xl border border-white/70 bg-white/70 p-4 text-sm shadow-sm backdrop-blur welcome-fade-up welcome-delay-2">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
              @foreach($infoCards as $index => $card)
                @php
                  $cardStyle = $infoCardStyles[$index % count($infoCardStyles)];
                @endphp
                <div class="flex items-start gap-3">
                  <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl {{ $cardStyle['bg'] }} {{ $cardStyle['text'] }}">
                    <i class="{{ $card['icon'] ?? 'ri-information-line' }}"></i>
                  </span>
                  <div>
                    <p class="font-semibold text-slate-900">{{ $card['title'] ?? '' }}</p>
                    @if(!empty($card['value']))
                      <p class="text-xs text-slate-500">{{ $card['value'] }}</p>
                    @endif
                    <p class="text-xs text-slate-500">{{ $card['description'] ?? '' }}</p>
                  </div>
                </div>
              @endforeach
            </div>
          </div>
        </div>
      </section>
    @endif

    <section class="welcome-section welcome-section--next">
      <div class="page-shell grid gap-8 lg:grid-cols-[0.7fr_1.3fr]">
        <div class="space-y-4">
          <p class="text-xs uppercase tracking-widest text-slate-500">{{ $introBadge }}</p>
          <h2 class="text-3xl font-semibold text-slate-900">{{ $introTitle }}</h2>
          <p class="text-slate-600">{{ $introSubtitle }}</p>
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
              <img
                src="{{ $teamImage['thumb'] }}"
                @if($teamImage['srcset']) srcset="{{ $teamImage['srcset'] }}" sizes="(max-width: 768px) 100vw, 40vw" @endif
                alt="{{ $pricesHighlightTitle }}"
                class="h-56 w-full object-cover"
                loading="lazy"
                decoding="async"
              >
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
                <img
                  src="{{ $doctorPhoto['thumb'] }}"
                  @if($doctorPhoto['srcset']) srcset="{{ $doctorPhoto['srcset'] }}" sizes="(max-width: 768px) 100vw, 33vw" @endif
                  alt="{{ $doctor['name'] ?? 'Doctor' }}"
                  class="h-56 w-full object-cover"
                  loading="lazy"
                  decoding="async"
                >
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
