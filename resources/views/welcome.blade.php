{{-- resources/views/welcome.blade.php --}}
@extends('layouts.navbar')

@section('title', $clinicIdentity->name())

@push('styles')
  @php
    $welcomeSoftPrimary = $siteSettings->get('visual.soft_primary', '#dff6f2');
    $welcomeSoftSecondary = $siteSettings->get('visual.soft_secondary', '#e8f8ef');
    $welcomeGradientStart = $siteSettings->get('visual.gradient_start', '#dff4ff');
    $welcomeGradientEnd = $siteSettings->get('visual.gradient_end', '#ecfdf5');
    $welcomeBadgeSoft = $siteSettings->get('visual.badge_soft', '#d9f7ef');
  @endphp
  <style>
    :root {
      --welcome-soft-primary: {{ $welcomeSoftPrimary }};
      --welcome-soft-secondary: {{ $welcomeSoftSecondary }};
      --welcome-gradient-start: {{ $welcomeGradientStart }};
      --welcome-gradient-end: {{ $welcomeGradientEnd }};
      --welcome-badge-soft: {{ $welcomeBadgeSoft }};
    }

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

    /* ============================================================
       HERO REDESIGN — colores del sistema: var(--accent) #0f766e
    ============================================================ */

    /* ── Layout hero ── */
    .wh-hero {
      padding: 1.5rem 0 2rem;
      overflow: hidden;
      background:
        radial-gradient(circle at top left, color-mix(in srgb, var(--welcome-gradient-start) 48%, transparent), transparent 36%),
        radial-gradient(circle at top right, color-mix(in srgb, var(--welcome-gradient-end) 40%, transparent), transparent 28%),
        linear-gradient(180deg, color-mix(in srgb, var(--welcome-soft-primary) 22%, white), transparent 80%);
    }
    .wh-hero__shell {
      display: grid;
      align-items: center;
      gap: 3rem;
    }
    @media (min-width: 1024px) {
      .wh-hero__shell {
        grid-template-columns: 1.1fr 0.9fr;
        gap: 4rem;
      }
    }

    /* ── Columna izquierda ── */
    .wh-hero__left { display: flex; flex-direction: column; gap: 1.5rem; }

    /* Badge */
    .wh-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.375rem 1rem;
      border-radius: 999px;
      background: color-mix(in srgb, var(--welcome-badge-soft) 82%, white);
      color: var(--accent);
      font-size: 0.7rem;
      font-weight: 700;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      width: fit-content;
    }

    /* Título */
    .wh-title {
      font-size: clamp(2rem, 5vw, 3.25rem);
      font-weight: 800;
      line-height: 1.15;
      color: var(--ink);
      margin: 0;
    }
    .wh-title__accent {
      color: var(--accent);  /* verde institucional exacto #0f766e */
      display: block;
    }

    /* Subtítulo */
    .wh-subtitle {
      font-size: 1rem;
      color: var(--muted);
      line-height: 1.7;
      max-width: 38rem;
      margin: 0;
    }

    /* Mini-cards 2 columnas iguales */
    .wh-info-cards {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.875rem;
    }
    .wh-info-card {
      display: flex;
      align-items: flex-start;
      gap: 0.75rem;
      padding: 0.875rem 1rem;
      background: rgba(255,255,255,0.96);
      border: 1px solid rgba(148,163,184,0.62);
      border-radius: 10px;
      box-shadow: 0 4px 12px rgba(15,23,42,0.06);
    }
    .wh-info-card__icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 2.25rem;
      height: 2.25rem;
      border-radius: 8px;
      background: var(--accent-soft);
      color: var(--accent);
      font-size: 1.1rem;
      flex-shrink: 0;
    }
    .wh-info-card__title {
      font-size: 0.8125rem;
      font-weight: 700;
      color: var(--ink);
      margin: 0 0 0.2rem;
    }
    .wh-info-card__text {
      font-size: 0.75rem;
      color: var(--muted);
      line-height: 1.5;
      margin: 0;
    }

    /* Botones CTA */
    .wh-cta-row {
      display: flex;
      flex-wrap: wrap;
      gap: 0.875rem;
      align-items: center;
    }
    .wh-btn-primary {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.7rem 1.4rem;
      background: var(--accent);
      color: #fff;
      border-radius: 8px;
      border: 1px solid rgba(15,118,110,0.78);
      font-size: 0.875rem;
      font-weight: 700;
      text-decoration: none;
      box-shadow: 0 10px 22px rgba(15,118,110,0.22);
      transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
    }
    .wh-btn-primary:hover {
      background: var(--accent-strong);
      transform: translateY(-1px);
      box-shadow: 0 14px 28px rgba(15,118,110,0.28);
    }
    .wh-btn-secondary {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.7rem 1.4rem;
      background: rgba(255,255,255,0.96);
      color: var(--ink);
      border-radius: 8px;
      border: 1px solid rgba(148,163,184,0.62);
      font-size: 0.875rem;
      font-weight: 600;
      text-decoration: none;
      box-shadow: 0 2px 8px rgba(15,23,42,0.06);
      transition: background 0.2s, transform 0.15s, border-color 0.2s;
    }
    .wh-btn-secondary:hover {
      background: #f8fafc;
      border-color: #94a3b8;
      transform: translateY(-1px);
    }

    /* ── Galería / carousel derecho ── */
    .wh-hero__right { min-width: 0; }
    .wh-gallery {
      position: relative;
      border-radius: 16px;
      overflow: hidden;
      border: 1px solid rgba(148,163,184,0.62);
      box-shadow: 0 20px 60px rgba(15,23,42,0.14);
      background: #0f172a;
    }
    .wh-gallery__track {
      display: flex;
      transition: transform 0.5s ease;
    }
    .wh-gallery__slide {
      min-width: 100%;
      position: relative;
    }
    .wh-gallery__img {
      width: 100%;
      height: clamp(280px, 42vw, 420px);
      object-fit: cover;
      object-position: center;
      display: block;
    }
    /* Overlay inferior oscuro con info */
    .wh-gallery__overlay {
      position: absolute;
      inset-x: 0;
      bottom: 0;
      background: linear-gradient(to top, rgba(15,23,42,0.82), rgba(15,23,42,0.45) 50%, transparent);
      padding: 1.5rem 1.25rem 1.25rem;
      display: flex;
      align-items: center;
      gap: 0.875rem;
      color: #fff;
    }
    .wh-gallery__overlay-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 2.5rem;
      height: 2.5rem;
      border-radius: 8px;
      background: rgba(255,255,255,0.18);
      backdrop-filter: blur(4px);
      font-size: 1.1rem;
      flex-shrink: 0;
    }
    .wh-gallery__overlay-title {
      font-size: 0.9375rem;
      font-weight: 700;
      margin: 0 0 0.2rem;
    }
    .wh-gallery__overlay-subtitle {
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: color-mix(in srgb, var(--accent-soft) 34%, white);
      margin: 0 0 0.3rem;
    }
    .wh-gallery__overlay-text {
      font-size: 0.8rem;
      color: rgba(255,255,255,0.82);
      margin: 0;
      line-height: 1.5;
    }
    /* Controles del carousel */
    .wh-gallery__ctrl {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      width: 2.5rem;
      height: 2.5rem;
      border-radius: 50%;
      border: 0;
      background: rgba(255,255,255,0.22);
      backdrop-filter: saturate(160%) blur(4px);
      color: #fff;
      font-size: 1.25rem;
      display: grid;
      place-items: center;
      cursor: pointer;
      transition: background 0.2s;
      z-index: 5;
    }
    .wh-gallery__ctrl:hover { background: rgba(255,255,255,0.38); }
    .wh-gallery__ctrl--prev { left: 0.75rem; }
    .wh-gallery__ctrl--next { right: 0.75rem; }
    /* Dots */
    .wh-gallery__dots {
      position: absolute;
      bottom: 0.875rem;
      left: 50%;
      transform: translateX(-50%);
      display: flex;
      gap: 0.4rem;
      z-index: 5;
    }
    .wh-gallery__dot {
      width: 0.5rem;
      height: 0.5rem;
      border-radius: 999px;
      border: 1.5px solid rgba(255,255,255,0.8);
      background: transparent;
      cursor: pointer;
      transition: background 0.2s, width 0.2s;
      padding: 0;
    }
    .wh-gallery__dot.is-active {
      background: #fff;
      width: 1.25rem;
    }

    /* ── Sección pasos compacta ── */
    .wh-steps-section {
      padding: 1.25rem 0;
    }
    .wh-steps-wrap {
      background: rgba(255,255,255,0.96);
      border: 1px solid rgba(148,163,184,0.62);
      border-radius: 12px;
      padding: 1.25rem 1.75rem;
      box-shadow: 0 8px 24px rgba(15,23,42,0.06);
    }
    .wh-steps-heading {
      font-size: 0.9375rem;
      font-weight: 700;
      color: var(--ink);
      margin: 0 0 1rem;
    }
    .wh-steps-row {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 0.5rem;
    }
    .wh-step {
      display: flex;
      align-items: flex-start;
      gap: 0.625rem;
    }
    .wh-step__num {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 1.75rem;
      height: 1.75rem;
      border-radius: 50%;
      background: var(--accent-soft);
      color: var(--accent);
      font-size: 0.8rem;
      font-weight: 800;
      flex-shrink: 0;
    }
    .wh-step__icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 2rem;
      height: 2rem;
      border-radius: 8px;
      background: var(--accent-soft);
      color: var(--accent);
      font-size: 1rem;
      flex-shrink: 0;
    }
    .wh-step__title {
      font-size: 0.8125rem;
      font-weight: 700;
      color: var(--ink);
      margin: 0 0 0.15rem;
    }
    .wh-step__text {
      font-size: 0.72rem;
      color: var(--muted);
      line-height: 1.45;
      margin: 0;
    }

    /* ── 3 cards inferiores ── */
    .wh-cards-section {
      padding: 0 0 2rem;
    }
    .wh-cards-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 1rem;
    }
    .wh-card {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 1.25rem 1rem 1.25rem 1.25rem;
      background: rgba(255,255,255,0.96);
      border: 1px solid rgba(148,163,184,0.62);
      border-radius: 12px;
      box-shadow: 0 8px 24px rgba(15,23,42,0.06);
      text-decoration: none;
      color: inherit;
      transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
    }
    .wh-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 16px 36px rgba(15,23,42,0.1);
      border-color: rgba(15,118,110,0.35);
    }
    .wh-card__icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 2.75rem;
      height: 2.75rem;
      border-radius: 10px;
      background: var(--accent-soft);
      color: var(--accent);
      font-size: 1.25rem;
      flex-shrink: 0;
    }
    .wh-card__body { flex: 1; min-width: 0; }
    .wh-card__title {
      font-size: 0.9375rem;
      font-weight: 700;
      color: var(--ink);
      margin: 0 0 0.2rem;
    }
    .wh-card__text {
      font-size: 0.775rem;
      color: var(--muted);
      line-height: 1.5;
      margin: 0;
    }
    .wh-card__arrow {
      font-size: 1.25rem;
      color: var(--accent);
      flex-shrink: 0;
      transition: transform 0.2s;
    }
    .wh-card:hover .wh-card__arrow { transform: translateX(3px); }

    /* ── Responsive ── */
    @media (max-width: 1023px) {
      .wh-steps-row { grid-template-columns: repeat(2, 1fr); gap: 0.875rem; }
      .wh-cards-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 767px) {
      .wh-info-cards { grid-template-columns: 1fr; }
      .wh-steps-row  { grid-template-columns: 1fr 1fr; }
      .wh-gallery__img { height: 240px; }
    }
    @media (max-width: 639px) {
      .wh-cards-grid { grid-template-columns: 1fr; }
      .wh-steps-row  { grid-template-columns: 1fr; }
    }

    /* ===== AGENDA PASO A PASO + TARIFARIO ===== */
    /* Paleta alineada con los tokens del sistema (app.css) */
    .ws-section {
      background: transparent; /* usa el fondo global del body */
    }

    /* --- PASOS --- */
    .welcome-steps-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 1.5rem;           /* 24px gap entre cards */
      background: transparent;
    }
    .welcome-step-card {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: flex-start;
      padding: 1.5rem 1rem;
      background: rgba(255,255,255,0.96);       /* mismo que --panel-strong */
      border: 1px solid rgba(148,163,184,0.62); /* mismo que .card */
      border-radius: 12px;                      /* alineado con .btn y .card del sistema */
      min-height: 160px;
      text-align: center;
      box-shadow: 0 12px 30px rgba(15,23,42,0.07);
    }
    .welcome-step-num {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 2rem;
      height: 2rem;
      border-radius: 50%;
      background: rgba(17, 24, 39, 0.05); /* neutral decorative */
      font-size: 0.875rem;
      font-weight: 700;
      color: var(--accent);             /* #0f766e — teal del botón Ingresar */
      margin-bottom: 0.875rem;
      flex-shrink: 0;
    }
    .welcome-step-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 3.25rem;
      height: 3.25rem;
      border-radius: 10px;
      background: rgba(17, 24, 39, 0.05); /* neutral decorative */
      margin-bottom: 0.875rem;
      flex-shrink: 0;
    }
    .welcome-step-icon i {
      font-size: 1.4rem;
      color: var(--accent);             /* #0f766e */
    }
    .welcome-step-title {
      font-size: 0.9375rem;
      font-weight: 700;
      color: var(--ink);                /* #0f172a — mismo que headings del sistema */
      margin: 0 0 0.3rem;
    }
    .welcome-step-desc {
      font-size: 0.8125rem;
      color: var(--muted);              /* #475569 — token real del sistema */
      line-height: 1.5;
      margin: 0;
    }

    /* --- TARIFARIO --- */
    .welcome-prices-grid {
      display: grid;
      grid-template-columns: minmax(0, 1fr) 300px;
      gap: 1.5rem;
      align-items: start;
    }
    .welcome-price-cards {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
      gap: 1rem;
    }
    .welcome-price-card {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      padding: 1rem 0.75rem;
      background: rgba(255,255,255,0.96);
      border: 1px solid rgba(148,163,184,0.62);
      border-radius: 12px;
      height: 140px;                    /* altura fija: TODAS iguales */
      text-align: center;
      overflow: hidden;
      box-shadow: 0 8px 20px rgba(15,23,42,0.06);
    }
    .welcome-price-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 2.5rem;
      height: 2.5rem;
      border-radius: 50%;
      background: rgba(17, 24, 39, 0.05); /* neutral decorative */
      flex-shrink: 0;
    }
    .welcome-price-icon i {
      font-size: 1.15rem;
      color: var(--accent);             /* #0f766e */
    }
    .welcome-price-name {
      font-size: 0.75rem;
      font-weight: 600;
      color: var(--ink);                /* #0f172a */
      line-height: 1.25;
      display: -webkit-box;
      -webkit-box-orient: vertical;
      -webkit-line-clamp: 2;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 100%;
    }
    .welcome-price-amount {
      font-size: 1rem;
      font-weight: 700;
      color: var(--accent);             /* #0f766e — mismo teal del sistema */
      white-space: nowrap;              /* "Consultar" nunca se parte */
      letter-spacing: -0.01em;
    }

    /* --- TARJETA DERECHA: Atención en clínica --- */
    .ws-clinic-card-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 2.25rem;
      height: 2.25rem;
      border-radius: 8px;
      background: var(--accent-soft);
      flex-shrink: 0;
    }
    .ws-clinic-card-icon i {
      font-size: 1.1rem;
      color: var(--accent);
    }
    .ws-clinic-bullet i {
      color: var(--accent);
    }

    .welcome-service-icon {
      background: color-mix(in srgb, var(--accent-soft) 76%, white);
      color: var(--accent);
      box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--accent) 10%, white);
    }

    .welcome-team-badge {
      border: 1px solid color-mix(in srgb, var(--accent-soft) 86%, white);
      background: color-mix(in srgb, var(--welcome-badge-soft) 84%, white);
      color: var(--accent);
      box-shadow: 0 10px 26px color-mix(in srgb, var(--accent-soft) 38%, white);
    }

    .welcome-team-divider {
      background: linear-gradient(90deg, var(--accent), var(--accent-strong));
    }

    .welcome-team-chip,
    .welcome-team-icon,
    .welcome-team-specialty,
    .welcome-team-foot,
    .welcome-team-cta-icon {
      color: var(--accent);
    }

    .welcome-team-chip {
      box-shadow: 0 14px 28px color-mix(in srgb, var(--accent-soft) 42%, white);
    }

    .welcome-team-avatar {
      box-shadow: 0 14px 35px color-mix(in srgb, var(--accent-soft) 40%, white);
    }

    .welcome-team-cta-block {
      background: color-mix(in srgb, var(--welcome-soft-secondary) 18%, white);
    }

    .welcome-team-cta-box {
      background: color-mix(in srgb, var(--accent-soft) 72%, white);
      color: var(--accent);
    }

    /* --- RESPONSIVE --- */
    @media (max-width: 1023px) {
      .welcome-prices-grid {
        grid-template-columns: 1fr;
      }
    }
    @media (max-width: 1023px) and (min-width: 768px) {
      .welcome-steps-grid {
        grid-template-columns: repeat(2, 1fr);
      }
      .welcome-price-cards {
        grid-template-columns: repeat(3, 1fr);
      }
    }
    @media (max-width: 767px) {
      .welcome-steps-grid {
        grid-template-columns: repeat(2, 1fr);
      }
      .welcome-price-cards {
        grid-template-columns: repeat(2, 1fr);
      }
    }
    @media (max-width: 479px) {
      .welcome-steps-grid {
        grid-template-columns: 1fr;
      }
      .welcome-price-cards {
        grid-template-columns: 1fr;
      }
    }
  </style>
@endpush

@section('main')
  @php
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
              <a href="{{ url('/') . '?login=1' }}" class="wh-btn-primary" data-login-trigger>
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
      <a href="{{ auth()->check() ? route('paciente.citas') : url('/?login=1') }}" class="wh-card" @guest data-login-trigger @endguest>
        <span class="wh-card__icon"><i class="ri-calendar-2-line" aria-hidden="true"></i></span>
        <div class="wh-card__body">
          <p class="wh-card__title">Citas médicas</p>
          <p class="wh-card__text">Agenda, modifica o cancela tus citas según disponibilidad.</p>
        </div>
        <i class="ri-arrow-right-line wh-card__arrow" aria-hidden="true"></i>
      </a>
      <a href="{{ auth()->check() ? route('paciente.laboratorio.index') : url('/?login=1') }}" class="wh-card" @guest data-login-trigger @endguest>
        <span class="wh-card__icon"><i class="ri-file-text-line" aria-hidden="true"></i></span>
        <div class="wh-card__body">
          <p class="wh-card__title">Documentos médicos</p>
          <p class="wh-card__text">Consulta resultados, recetas y comprobantes.</p>
        </div>
        <i class="ri-arrow-right-line wh-card__arrow" aria-hidden="true"></i>
      </a>
      <a href="{{ auth()->check() ? route('paciente.citas') : url('/?login=1') }}" class="wh-card" @guest data-login-trigger @endguest>
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
      <section class="welcome-section ws-section" style="padding-top:3rem; padding-bottom:0;">
        <div class="page-shell">
          <div style="text-align:center; margin-bottom:2rem;">
            {{-- Ícono calendario — usa accent-soft del sistema --}}
            <div style="display:inline-flex; align-items:center; justify-content:center; width:3rem; height:3rem; border-radius:50%; background:var(--accent-soft); margin-bottom:0.875rem;">
              <i class="ri-calendar-2-line" style="font-size:1.4rem; color:var(--accent);" aria-hidden="true"></i>
            </div>
            {{-- Título en negro elegante — igual que h2 del resto del welcome --}}
            <h2 style="font-size:clamp(1.5rem,3vw,2rem); font-weight:700; color:var(--ink); margin:0 0 0.5rem;">{{ $pricesVisitTitle }}</h2>
            <p style="font-size:0.95rem; color:var(--muted); max-width:36rem; margin:0 auto; line-height:1.65;">{{ $pricesVisitSubtitle }}</p>
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
      <section class="welcome-section ws-section" style="padding-top:2rem;">
        <div class="page-shell">
          <div class="welcome-prices-grid" style="align-items:stretch;">

            {{-- Columna izquierda: tarifario --}}
            <div style="background:rgba(255,255,255,0.96); border-radius:12px; box-shadow:0 12px 30px rgba(15,23,42,0.08); padding:2rem; border:1px solid rgba(148,163,184,0.62);">
              <p style="font-size:0.7rem; font-weight:700; letter-spacing:0.18em; text-transform:uppercase; color:var(--accent); margin:0 0 0.4rem;">{{ $pricesBadge }}</p>
              <h2 style="font-size:1.5rem; font-weight:700; color:var(--ink); margin:0 0 0.4rem;">{{ $pricesTitle }}</h2>
              <p style="font-size:0.875rem; color:var(--muted); line-height:1.65; margin:0 0 1.5rem;">{{ $pricesSubtitle }}</p>

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
            <div style="background:rgba(255,255,255,0.96); border-radius:12px; border:1px solid rgba(148,163,184,0.62); padding:1.75rem 1.5rem 2rem; box-shadow:0 12px 30px rgba(15,23,42,0.07); display:flex; flex-direction:column; justify-content:center; height:100%;">
              <div style="display:flex; align-items:flex-start; gap:0.75rem; margin-bottom:1.25rem;">
                <div class="ws-clinic-card-icon">
                  <i class="ri-shield-cross-line" aria-hidden="true"></i>
                </div>
                <div>
                  <h3 style="font-size:0.9375rem; font-weight:700; color:var(--ink); margin:0 0 0.25rem;">{{ $pricesHighlightTitle }}</h3>
                  <p style="font-size:0.8rem; color:var(--muted); line-height:1.55; margin:0;">{{ $pricesHighlightSubtitle }}</p>
                </div>
              </div>
              <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:0.75rem;">
                <li class="ws-clinic-bullet" style="display:flex; align-items:center; gap:0.5rem; font-size:0.8125rem; color:var(--muted);">
                  <i class="ri-checkbox-circle-fill" style="font-size:1rem; flex-shrink:0;"></i>
                  Información segura y confidencial
                </li>
                <li class="ws-clinic-bullet" style="display:flex; align-items:center; gap:0.5rem; font-size:0.8125rem; color:var(--muted);">
                  <i class="ri-checkbox-circle-fill" style="font-size:1rem; flex-shrink:0;"></i>
                  Proceso rápido y organizado
                </li>
                <li class="ws-clinic-bullet" style="display:flex; align-items:center; gap:0.5rem; font-size:0.8125rem; color:var(--muted);">
                  <i class="ri-checkbox-circle-fill" style="font-size:1rem; flex-shrink:0;"></i>
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
                      <a href="{{ url('/') . '?login=1' }}" class="btn btn-primary btn-lg w-full justify-center" data-login-trigger>
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
                  <a href="{{ url('/') . '?login=1' }}" class="btn btn-primary btn-lg" data-login-trigger>
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
