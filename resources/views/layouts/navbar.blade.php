{{-- resources/views/layouts/navbar.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title','Clínica Don Bosco')</title>
  <link rel="icon" type="image/jpg" href="{{ asset('img/LogoClinica.jpg') }}">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.min.css">
  @vite([
    'resources/css/navbar.css',
    'resources/css/modal.css',
    'resources/js/navbar.js',
  ])
  @stack('head')
  @stack('styles')
</head>
<body class="@yield('body-class')">
@php use Illuminate\Support\Facades\Route as R; @endphp

<header class="cnav-header" id="cnav-header">
  <nav class="cnav" aria-label="Barra de navegación principal">
    <div class="cnav-container">
      {{-- Logo --}}
      <a href="{{ url('/') }}" class="cnav__logo" aria-label="Inicio">
        <img src="{{ asset('img/logo-welcomeBlanco.jpg') }}" alt="Clínica" style="height:40px">
      </a>

      {{-- Menú principal --}}
      <div class="cnav__menu" id="cnav-menu" aria-hidden="true">
        <ul class="cnav__list" role="menubar">
          <li class="cnav__item" role="none">
            <a role="menuitem" href="{{ url('/') }}" class="cnav__link {{ request()->is('/') ? 'is-active':'' }}">
              <i class="ri-home-4-line" aria-hidden="true"></i><span>Inicio</span>
            </a>
          </li>

          @if(R::has('servicios.index'))
          <li class="cnav__item" role="none">
            <a role="menuitem" href="{{ route('servicios.index') }}" class="cnav__link {{ request()->routeIs('servicios.*')?'is-active':'' }}">
              <i class="ri-stethoscope-line" aria-hidden="true"></i><span>Servicios</span>
            </a>
          </li>
          @endif

          @if(R::has('contacto.form'))
          <li class="cnav__item" role="none">
            <a role="menuitem" href="{{ route('contacto.form') }}" class="cnav__link {{ request()->routeIs('contacto.*')?'is-active':'' }}">
              <i class="ri-contacts-book-2-line" aria-hidden="true"></i><span>Contacto</span>
            </a>
          </li>
          @endif

          @auth
            <li class="cnav__item" role="none">
              @php
                $panel = route('home');
                if (R::has('paciente.dashboard')) $panel = route('paciente.dashboard');
              @endphp
              <a role="menuitem" href="{{ $panel }}" class="cnav__link {{ request()->routeIs('home')?'is-active':'' }}">
                <i class="ri-dashboard-line" aria-hidden="true"></i><span>Mi Panel</span>
              </a>
            </li>
            <li class="cnav__item" role="none">
              <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="cnav__link cnav__link--button">
                  <i class="ri-logout-box-line" aria-hidden="true"></i><span>Salir</span>
                </button>
              </form>
            </li>
          @else
            @if(R::has('login'))
            <li class="cnav__item" role="none">
              <a role="menuitem" href="{{ route('login') }}" class="cnav__link" data-login-trigger>
                <i class="ri-login-box-line" aria-hidden="true"></i><span>Ingresar</span>
              </a>
            </li>
            @endif
          @endauth
        </ul>

        {{-- Botón cerrar menú móvil --}}
        <button class="cnav__close" id="cnav-close" aria-label="Cerrar menú">
          <i class="ri-close-large-line"></i>
        </button>

        {{-- Redes sociales --}}
        <div class="cnav__social" aria-label="Redes sociales">
          <a href="https://www.instagram.com/" target="_blank" class="cnav__social-link" aria-label="Instagram">
            <i class="ri-instagram-line"></i>
          </a>
          <a href="https://wa.me/593998740927" target="_blank" class="cnav__social-link" aria-label="WhatsApp">
            <i class="ri-whatsapp-line"></i>
          </a>
          <a href="https://www.facebook.com/" target="_blank" class="cnav__social-link" aria-label="Facebook">
            <i class="ri-facebook-circle-line"></i>
          </a>
          <a href="https://twitter.com/" target="_blank" class="cnav__social-link" aria-label="Twitter">
            <i class="ri-twitter-x-line"></i>
          </a>
        </div>
      </div> {{-- cierre .cnav__menu --}}

      {{-- Botón hamburguesa --}}
      <button class="cnav__toggle" id="cnav-toggle" aria-label="Abrir menú" aria-expanded="false" aria-controls="cnav-menu">
        <i class="ri-menu-line"></i>
      </button>
    </div>
  </nav>
</header>

{{-- Modal login --}}
@guest
<div id="loginModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="loginTitle" aria-hidden="true">
  <div class="modal-backdrop" data-close-login></div>
  <div class="modal-dialog" role="document" tabindex="-1">
    <div class="panel">
      <div class="panel-header">
        <h3 id="loginTitle">Iniciar sesión</h3>
        <button id="closeLoginModal" class="close-btn" aria-label="Cerrar modal" data-close-login>✕</button>
      </div>
      <div class="panel-body">
        @if(session('status')) <div class="alert ok">{{ session('status') }}</div> @endif
        @if(session('auth_error') || $errors->any()) <span id="openLoginOnLoad" hidden></span> @endif
        @if(session('auth_error'))
          <div class="alert err">{{ session('auth_error') }}</div>
        @elseif($errors->has('email'))
          <div class="alert err">{{ $errors->first('email') }}</div>
        @elseif($errors->any())
          <div class="alert err">Revisa tus datos e inténtalo nuevamente.</div>
        @endif

        <form method="POST" action="{{ route('login') }}" id="loginForm">
          @csrf
          <div class="field">
            <label>Correo</label>
            <input type="email" name="email" autocomplete="email" required value="{{ old('email') }}" placeholder="correo@ejemplo.com" inputmode="email">
            @error('email') <p class="error">{{ $message }}</p> @enderror
          </div>
          <div class="field password-field">
            <label>Contraseña</label>
            <div class="password-input">
              <input type="password" name="password" id="loginPassword" autocomplete="current-password" required placeholder="••••••••">
              <button type="button" id="togglePassword" aria-label="Mostrar u ocultar contraseña">
                <i id="togglePasswordIcon" class="ri-eye-line" aria-hidden="true"></i>
              </button>
            </div>
            @error('password') <p class="error">{{ $message }}</p> @enderror
          </div>
          <div class="row">
            <label class="remember"><input type="checkbox" name="remember"> Recuérdame</label>
            @if (R::has('password.request'))
              <a href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a>
            @endif
          </div>
          <button type="submit" class="submit">Entrar</button>
        </form>
      </div>
    </div>
  </div>
</div>
@endguest

<main>@yield('main')</main>

{{-- Widget chatbot solo para visitantes --}}
@guest
  @include('chatbot.widget')
@endguest

@stack('scripts')
</body>
</html>
