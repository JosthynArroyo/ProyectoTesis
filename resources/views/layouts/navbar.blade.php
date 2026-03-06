{{-- resources/views/layouts/navbar.blade.php --}}
<!DOCTYPE html>
<html lang="es" class="m-0 p-0">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', $siteSettings->get('branding.name','Clínica Don Bosco'))</title>
  @include('layouts.partials.favicon')
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.min.css">
  @php
    $brandName = $siteSettings->get('branding.name','Clínica Don Bosco');
    $brandLogo = $siteSettings->get('branding.logo','img/logo-welcomeBlanco.jpg');
    $accent = $siteSettings->get('branding.accent','#0f766e');
    $accentStrong = $siteSettings->get('branding.accent_strong','#14b8a6');
    $accentSoft = $siteSettings->get('branding.accent_soft','#ccfbf1');
    $headerName = $landingWelcome->get('header_name', $brandName);
    $headerLogo = $landingWelcome->get('header_logo', $brandLogo);
    $headerShowSocials = $landingWelcome->getBool('header_show_socials', true);
  @endphp
  <style>
    :root {
      --accent: {{ $accent }};
      --accent-strong: {{ $accentStrong }};
      --accent-soft: {{ $accentSoft }};
    }
  </style>
  @vite(['resources/css/app.css','resources/js/app.js'])
  @stack('head')
  @stack('styles')
</head>
<body class="min-h-screen text-slate-900 m-0 p-0">
@php
  use Illuminate\Support\Facades\Route as R;
  $errors = $errors ?? new \Illuminate\Support\ViewErrorBag();
  $loginModalUrl = url('/') . '?login=1';
@endphp

<header class="fixed inset-x-0 top-0 z-50 border-b border-slate-200/70 bg-white" id="cnav-header">
  <nav class="page-shell" aria-label="Barra de navegación principal">
    <div class="flex items-center justify-between gap-4 py-4">
      <a href="{{ url('/') }}" class="flex items-center gap-3" aria-label="Inicio">
        @php
          $logoImage = $imageUrl->variants($headerLogo, entity: 'banner');
        @endphp
        <img
          src="{{ $logoImage['thumb'] }}"
          @if($logoImage['srcset']) srcset="{{ $logoImage['srcset'] }}" sizes="160px" @endif
          alt="{{ $headerName }}"
          class="h-10 w-auto"
          loading="eager"
          decoding="async"
        >
        <span class="hidden text-sm font-semibold uppercase tracking-wide text-slate-500 sm:inline">{{ $headerName }}</span>
      </a>

      {{-- ═══ Mobile-optimized menu overlay ═══ --}}
      <div id="cnav-menu" class="cnav__menu fixed inset-0 z-50 hidden flex-col bg-white text-slate-700 lg:static lg:flex lg:flex-row lg:items-center lg:gap-4 lg:bg-transparent lg:p-0">

        {{-- Mobile header bar --}}
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 lg:hidden">
          <span class="text-base font-semibold text-slate-700">Menú</span>
          <button class="flex h-11 w-11 items-center justify-center rounded-xl text-slate-500 hover:bg-slate-100 active:bg-slate-200" id="cnav-close" aria-label="Cerrar menú">
            <i class="ri-close-line text-xl"></i>
          </button>
        </div>

        {{-- Navigation links (large touch targets on mobile) --}}
        <ul class="flex flex-col gap-1 px-4 py-4 text-base font-semibold lg:flex-row lg:items-center lg:gap-4 lg:px-0 lg:py-0 lg:text-sm" role="menubar">
          <li role="none">
            <a role="menuitem" href="{{ url('/') }}" class="flex min-h-[48px] items-center gap-3 rounded-xl px-4 py-3 transition-colors lg:min-h-0 lg:rounded-full lg:px-4 lg:py-2 {{ request()->is('/') ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-slate-50 active:bg-slate-100' }}">
              <i class="ri-home-4-line text-lg"></i>Inicio
            </a>
          </li>

          @if(R::has('servicios.index'))
          <li role="none">
            <a role="menuitem" href="{{ route('servicios.index') }}" class="flex min-h-[48px] items-center gap-3 rounded-xl px-4 py-3 transition-colors lg:min-h-0 lg:rounded-full lg:px-4 lg:py-2 {{ request()->routeIs('servicios.*') ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-slate-50 active:bg-slate-100' }}">
              <i class="ri-stethoscope-line text-lg"></i>Servicios
            </a>
          </li>
          @endif

          @if(R::has('contacto.form'))
          <li role="none">
            <a role="menuitem" href="{{ route('contacto.form') }}" class="flex min-h-[48px] items-center gap-3 rounded-xl px-4 py-3 transition-colors lg:min-h-0 lg:rounded-full lg:px-4 lg:py-2 {{ request()->routeIs('contacto.*') ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-slate-50 active:bg-slate-100' }}">
              <i class="ri-contacts-book-2-line text-lg"></i>Contacto
            </a>
          </li>
          @endif

          @auth
            <li role="none">
              @php
                $user = Auth::user();
                $panel = route('home');
                if ($user && $user->hasRole('superadmin') && R::has('superadmin.dashboard')) {
                  $panel = route('superadmin.dashboard');
                } elseif ($user && $user->hasRole('administrador') && R::has('admin.dashboard')) {
                  $panel = route('admin.dashboard');
                } elseif ($user && $user->hasRole('paciente') && R::has('paciente.dashboard')) {
                  $panel = route('paciente.dashboard');
                } elseif ($user && $user->hasRole('doctor') && R::has('doctor.dashboard')) {
                  $panel = route('doctor.dashboard');
                } elseif ($user && $user->hasRole('laboratorio') && R::has('laboratorio.dashboard')) {
                  $panel = route('laboratorio.dashboard');
                }
              @endphp
              <a role="menuitem" href="{{ $panel }}" class="flex min-h-[48px] items-center gap-3 rounded-xl px-4 py-3 transition-colors lg:min-h-0 lg:rounded-full lg:px-4 lg:py-2 {{ request()->routeIs('home') ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-slate-50 active:bg-slate-100' }}">
                <i class="ri-dashboard-line text-lg"></i>Mi panel
              </a>
            </li>
            <li role="none" class="mt-2 border-t border-slate-100 pt-2 lg:mt-0 lg:border-0 lg:pt-0">
              <form method="POST" action="{{ route('salir') }}">
                @csrf
                <button type="submit" class="flex min-h-[48px] w-full items-center gap-3 rounded-xl px-4 py-3 text-left text-rose-600 transition-colors hover:bg-rose-50 active:bg-rose-100 lg:min-h-0 lg:w-auto lg:rounded-full lg:px-4 lg:py-2">
                  <i class="ri-logout-box-line text-lg"></i>Salir
                </button>
              </form>
            </li>
          @else
            @if(R::has('login'))
            <li role="none" class="mt-3 lg:mt-0">
              <a role="menuitem" href="{{ $loginModalUrl }}" class="btn btn-primary flex min-h-[48px] w-full items-center justify-center gap-2 rounded-xl text-base lg:min-h-0 lg:w-auto lg:rounded-full lg:text-sm" data-login-trigger>
                <i class="ri-login-box-line text-lg"></i>Ingresar
              </a>
            </li>
            @endif
          @endauth
        </ul>

        @if($headerShowSocials)
          <div class="mt-auto flex items-center gap-2 border-t border-slate-100 px-5 py-4 text-xl text-slate-400 lg:mt-0 lg:border-0 lg:px-0 lg:py-0">
            <a href="https://www.instagram.com/" target="_blank" aria-label="Instagram" class="flex h-11 w-11 items-center justify-center rounded-xl hover:bg-slate-50 hover:text-slate-600 active:bg-slate-100 lg:h-auto lg:w-auto lg:rounded-full"><i class="ri-instagram-line"></i></a>
            <a href="https://wa.me/593998740927" target="_blank" aria-label="WhatsApp" class="flex h-11 w-11 items-center justify-center rounded-xl hover:bg-slate-50 hover:text-slate-600 active:bg-slate-100 lg:h-auto lg:w-auto lg:rounded-full"><i class="ri-whatsapp-line"></i></a>
            <a href="https://www.facebook.com/" target="_blank" aria-label="Facebook" class="flex h-11 w-11 items-center justify-center rounded-xl hover:bg-slate-50 hover:text-slate-600 active:bg-slate-100 lg:h-auto lg:w-auto lg:rounded-full"><i class="ri-facebook-circle-line"></i></a>
            <a href="https://twitter.com/" target="_blank" aria-label="Twitter" class="flex h-11 w-11 items-center justify-center rounded-xl hover:bg-slate-50 hover:text-slate-600 active:bg-slate-100 lg:h-auto lg:w-auto lg:rounded-full"><i class="ri-twitter-x-line"></i></a>
          </div>
        @endif
      </div>

      <button class="btn btn-ghost px-2 lg:hidden" id="cnav-toggle" aria-label="Abrir menú" aria-expanded="false" aria-controls="cnav-menu">
        <i class="ri-menu-2-line text-lg"></i>
      </button>
    </div>
  </nav>
</header>

@guest
@php($errorsBag = $errors ?? null)
<div id="loginModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="loginTitle" aria-hidden="true">
  <div class="modal-backdrop" data-close-login></div>
  <div class="modal-dialog" role="document" tabindex="-1">
    <div class="card mx-auto w-full max-w-xl">
      <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
        <h3 id="loginTitle" class="text-lg font-semibold">Iniciar sesión</h3>
        <button id="closeLoginModal" class="btn btn-ghost px-2" aria-label="Cerrar modal" data-close-login>
          <i class="ri-close-line text-lg"></i>
        </button>
      </div>
      <div class="p-6">
        @if(session('status')) <div class="alert success">{{ session('status') }}</div> @endif
        @if(session('auth_error') || ($errorsBag && $errorsBag->any())) <span data-open-login-onload hidden></span> @endif
        @if(session('auth_error'))
          <div class="alert error">{{ session('auth_error') }}</div>
        @elseif($errorsBag && $errorsBag->has('email'))
          <div class="alert error">{{ $errorsBag->first('email') }}</div>
        @elseif($errorsBag && $errorsBag->any())
          <div class="alert error">Revisa tus datos e inténtalo nuevamente.</div>
        @endif

        <div class="mt-4 flex gap-2" role="tablist">
          <button type="button" class="btn btn-outline px-4 py-2 text-xs is-active" data-login-tab="password">Correo y contraseña</button>
          <button type="button" class="btn btn-outline px-4 py-2 text-xs" data-login-tab="face">Reconocimiento facial</button>
        </div>

        <div class="mt-4">
          <div class="login-panel is-active" data-login-panel="password">
            <form method="POST" action="{{ route('login') }}" id="loginForm" class="space-y-4">
              @csrf
              <input type="hidden" name="remember" value="0">
              <div>
                <label class="form-label">Correo</label>
                <input type="email" name="email" autocomplete="email" required value="{{ old('email') }}" placeholder="correo@ejemplo.com" inputmode="email" class="form-input">
              </div>
              <div>
                <label class="form-label">Contraseña</label>
                <div class="relative">
                  <input type="password" name="password" id="loginPassword" autocomplete="current-password" required placeholder="********" class="form-input pr-10">
                  <button type="button" class="btn-eye absolute right-3 top-1/2 inline-flex -translate-y-1/2 items-center justify-center text-slate-500 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-200" data-target="#loginPassword" aria-label="Mostrar u ocultar contraseña">
                    <i class="ri-eye-line text-lg leading-none" aria-hidden="true"></i>
                  </button>
                </div>
              </div>
              <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-slate-500">
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-300"> Recuérdame</label>
                @if (R::has('password.request'))
                  <a href="{{ route('password.request') }}" class="text-teal-600 hover:underline">Olvidaste tu contraseña</a>
                @endif
              </div>
              <button type="submit" class="btn btn-primary w-full">Entrar</button>
            </form>
          </div>

          <div class="login-panel" data-login-panel="face" hidden>
            <form id="faceLoginForm"
                  data-endpoint="{{ route('face.login') }}"
                  data-csrf="{{ csrf_token() }}"
                  data-models-url="{{ asset('models') }}" class="space-y-4">
              <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-900">
                <video id="faceLoginVideo" autoplay muted playsinline class="h-48 w-full object-cover"></video>
              </div>
              <p class="text-sm text-slate-500">Mira directo a la camara y espera el escaneo.</p>
              <button type="submit" class="btn btn-primary w-full" id="faceLoginSubmit">Reconocer rostro</button>
              <p class="text-sm text-slate-500" data-face-status></p>
              <p class="text-xs text-slate-500">
                No tienes rostro registrado Entra con tu contraseña y visita
                <a href="{{ route('paciente.perfil.edit') }}#perfil-face" class="text-teal-600 hover:underline">Registrar reconocimiento facial</a>.
              </p>
            </form>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>
@endguest

<main class="min-h-screen pt-[4.5rem]">@yield('main')</main>

@guest
  @include('chatbot.widget')
@endguest

@stack('scripts')
</body>
</html>
