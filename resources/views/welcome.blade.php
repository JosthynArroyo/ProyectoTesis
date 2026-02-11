{{-- resources/views/welcome.blade.php --}}
@extends('layouts.navbar')

@section('title','Clínica Don Bosco')

@section('main')
  @php
    $heroPrimaryText = $landingWelcome->get('hero_primary_text', 'Agendar cita');
    $heroSecondaryText = $landingWelcome->get('hero_secondary_text', 'Explorar servicios');
    $heroStats = $landingWelcome->stats(true);
    $heroSlides = array_values(array_filter($landingWelcome->slides(), fn ($slide) => $slide['is_active'] ?? false));
    $showServicesBlock = $landingWelcome->getBool('show_services_block', true);
    $infoCards = $landingWelcome->infoCards(true);
    $doctors = $landingWelcome->doctors(true);
    $prices = $landingWelcome->prices(true);
    $pricesSubtitle = $landingWelcome->get('prices_subtitle', 'Consulta los valores aproximados y pregunta por promociones actuales.');
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
                <a href="{{ route('login') }}" class="btn btn-primary">{{ $heroPrimaryText }}</a>
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
                  $slideUrl = $landingWelcome->resolveImageUrl($slide['image_path'] ?? null);
                @endphp
                <div class="carousel__slide min-w-full">
                  <img src="{{ $slideUrl }}" alt="{{ $slide['alt'] ?? 'Imagen del slider' }}" class="h-80 w-full object-cover sm:h-96">
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
            <p class="font-semibold text-slate-800">Seguimiento personalizado</p>
            <p>Recibe recordatorios y notificaciones sobre tus citas, resultados y seguimiento médico.</p>
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
        <p class="text-xs uppercase tracking-widest text-slate-500">Bienvenida</p>
        <h2 class="text-3xl font-semibold text-slate-900">Gestiona tus citas médicas en un entorno seguro.</h2>
        <p class="text-slate-600">Accede a consultas con médicos especializados desde cualquier lugar. Organiza tus visitas en pocos pasos y mantente informado.</p>
      </div>
      <div class="grid gap-4 sm:grid-cols-2">
        <div class="card p-5">
          <p class="text-sm font-semibold text-slate-900">Agenda inteligente</p>
          <p class="text-sm text-slate-500">Confirmaciones automáticas, recordatorios y reprogramación sencilla.</p>
        </div>
        <div class="card p-5">
          <p class="text-sm font-semibold text-slate-900">Historial centralizado</p>
          <p class="text-sm text-slate-500">Tus recetas, resultados y citas siempre disponibles.</p>
        </div>
        <div class="card p-5">
          <p class="text-sm font-semibold text-slate-900">Seguridad y privacidad</p>
          <p class="text-sm text-slate-500">Control de acceso por rol y datos protegidos.</p>
        </div>
        <div class="card p-5">
          <p class="text-sm font-semibold text-slate-900">Atención humana</p>
          <p class="text-sm text-slate-500">Personal médico listo para responder tus dudas.</p>
        </div>
      </div>
    </div>
  </section>

  @if($showServicesBlock && isset($especialidadesDestacadas) && $especialidadesDestacadas->count())
  <section class="welcome-section">
    <div class="page-shell">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p class="text-xs uppercase tracking-widest text-slate-500">Servicios</p>
          <h2 class="text-3xl font-semibold text-slate-900">Especialidades destacadas</h2>
          <p class="text-slate-600">Atención médica integral con profesionales certificados.</p>
        </div>
        <a href="{{ route('servicios.index') }}" class="btn btn-outline">Ver todos</a>
      </div>
      <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @php
          $iconStyles = [
            ['bg' => 'bg-teal-50', 'text' => 'text-teal-700'],
            ['bg' => 'bg-amber-50', 'text' => 'text-amber-700'],
            ['bg' => 'bg-sky-50', 'text' => 'text-sky-700'],
            ['bg' => 'bg-rose-50', 'text' => 'text-rose-700'],
          ];
        @endphp
        @foreach($especialidadesDestacadas as $index => $esp)
          @php
            $iconStyle = $iconStyles[$index % count($iconStyles)];
          @endphp
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
        <p class="text-xs uppercase tracking-widest text-slate-500">Tarifario</p>
        <h2 class="mt-2 text-2xl font-semibold text-slate-900">Precios transparentes</h2>
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
            <a href="{{ route('paciente.dashboard') }}" class="btn btn-primary">Agendar cita</a>
          @else
            <a href="{{ route('login') }}" class="btn btn-primary">Agendar cita</a>
          @endauth
        </div>
      </div>
      <div class="space-y-5">
        <div class="card overflow-hidden">
          <img src="{{ asset('img/doctor2.jpg') }}" alt="Equipo médico" class="h-56 w-full object-cover">
          <div class="p-5">
            <h3 class="text-lg font-semibold">Equipo profesional</h3>
            <p class="text-sm text-slate-500">Especialistas enfocados en un trato cercano y humano.</p>
          </div>
        </div>
        <div class="card p-5">
          <h3 class="text-lg font-semibold">Agenda tu visita en minutos</h3>
          <p class="text-sm text-slate-500">Nuestro sistema te guía paso a paso para seleccionar especialista, fecha y hora.</p>
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
          <p class="text-xs uppercase tracking-widest text-slate-500">Equipo</p>
          <h2 class="text-3xl font-semibold text-slate-900">Nuestros doctores</h2>
          <p class="text-slate-600">Profesionales comprometidos con tu bienestar.</p>
        </div>
        <span class="pill">Atención personalizada</span>
      </div>
      <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($doctors as $doctor)
          @php
            $doctorPhoto = $landingWelcome->resolveImageUrl($doctor['photo_path'] ?? null);
          @endphp
          <div class="card overflow-hidden">
            @if($doctorPhoto)
              <img src="{{ $doctorPhoto }}" alt="{{ $doctor['name'] ?? 'Doctor' }}" class="h-56 w-full object-cover">
            @endif
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
