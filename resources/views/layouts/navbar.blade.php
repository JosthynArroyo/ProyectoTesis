{{-- resources/views/layouts/navbar.blade.php --}}
<!DOCTYPE html>
<html lang="es" class="m-0 p-0">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', $clinicIdentity->name())</title>
  @include('layouts.partials.favicon')
  @include('layouts.partials.fonts')
  @php
    $brandName = $clinicIdentity->name();
    $brandLogo = $clinicIdentity->logoPath();
    $navbarText = $siteSettings->get('branding.navbar_text', $clinicIdentity->slogan());
    $accent = $siteSettings->get('branding.accent', '#0f766e');
    $accentStrong = $siteSettings->get('branding.accent_strong', '#14b8a6');
    $accentSoft = $siteSettings->get('branding.accent_soft', '#ccfbf1');
    $headerName = $landingWelcome->get('header_name', $brandName);
    $headerLogo = $landingWelcome->get('header_logo', $brandLogo);
    $headerLoginText = $landingWelcome->get('header_login_text', 'Ingresar');
    $headerShowSocials = $landingWelcome->getBool('header_show_socials', true);
    $stickyHeader = $siteSettings->getBool('header.sticky_enabled', true);
  @endphp
  <style>
    :root {
      --accent:
        {{ $accent }}
      ;
      --accent-strong:
        {{ $accentStrong }}
      ;
      --accent-soft:
        {{ $accentSoft }}
      ;
    }
  </style>
  @vite(['resources/css/app.css', 'resources/css/modal.css', 'resources/js/app.js'])
  @stack('head')
  @stack('styles')
</head>

<body class="min-h-screen text-gray-900 m-0 p-0">
  @php
    use Illuminate\Support\Facades\Route as R;
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag();
    $loginErrors = $errors->getBag('login');
    $loginModalUrl = url('/') . '?login=1';
    $navigationOrder = array_values(array_filter(array_map('trim', explode(',', (string) $siteSettings->get('header.navigation_order', 'home,services,contact')))));
    $navigationOrder = empty($navigationOrder) ? ['home', 'services', 'contact'] : $navigationOrder;
    $publicNavItems = collect([
      [
        'key' => 'home',
        'label' => 'Inicio',
        'icon' => 'ri-home-4-line',
        'visible' => $siteSettings->getBool('header.show_home', true),
        'url' => url('/'),
        'active' => request()->is('/'),
      ],
      [
        'key' => 'services',
        'label' => 'Servicios',
        'icon' => 'ri-stethoscope-line',
        'visible' => $siteSettings->getBool('header.show_services', true) && R::has('servicios.index'),
        'url' => R::has('servicios.index') ? route('servicios.index') : '#',
        'active' => request()->routeIs('servicios.*'),
      ],
      [
        'key' => 'contact',
        'label' => 'Contacto',
        'icon' => 'ri-contacts-book-2-line',
        'visible' => $siteSettings->getBool('header.show_contact', true) && R::has('contacto.form'),
        'url' => R::has('contacto.form') ? route('contacto.form') : '#',
        'active' => request()->routeIs('contacto.*'),
      ],
      [
        'key' => 'verify',
        'label' => 'Verificar documento',
        'icon' => 'ri-qr-scan-2-line',
        'visible' => R::has('documentos.verificar.form'),
        'url' => R::has('documentos.verificar.form') ? route('documentos.verificar.form') : '#',
        'active' => request()->routeIs('documentos.verificar.*'),
      ],
      [
        'key' => 'demo',
        'label' => 'Explorar demo',
        'icon' => 'ri-eye-line',
        'visible' => R::has('demo.index'),
        'url' => R::has('demo.index') ? route('demo.index') : '#',
        'active' => request()->routeIs('demo.*'),
      ],
    ])->keyBy('key');
    $orderedNavItems = collect($navigationOrder)
      ->map(fn($key) => $publicNavItems->get($key))
      ->filter()
      ->merge($publicNavItems->except($navigationOrder)->values())
      ->filter(fn($item) => !empty($item['visible']))
      ->values();
  @endphp

  <header class="{{ $stickyHeader ? 'fixed inset-x-0 top-0 z-50' : 'relative' }} border-b border-gray-200/70 bg-white"
    id="cnav-header">
    <nav class="page-shell" aria-label="Barra de navegación principal">
      <div class="flex min-w-0 items-center justify-between gap-3 py-4 sm:gap-4">
        <a href="{{ url('/') }}" class="flex min-w-0 items-center gap-3" aria-label="Inicio">
          @php
            $logoImage = $headerLogo ? $imageUrl->variants($headerLogo, entity: 'banner') : null;
          @endphp
          @if($logoImage)
            <img src="{{ $logoImage['thumb'] }}" @if($logoImage['srcset']) srcset="{{ $logoImage['srcset'] }}"
            sizes="160px" @endif alt="{{ $headerName }}" class="h-10 w-auto" loading="eager" decoding="async">
          @else
            <span
              class="flex h-10 w-10 items-center justify-center rounded-2xl border border-gray-200 bg-gray-50 text-gray-500">
              <i class="ri-hospital-line text-lg"></i>
            </span>
          @endif
          <span class="hidden min-w-0 sm:inline">
            <span
              class="block truncate text-sm font-semibold uppercase tracking-wide text-gray-500">{{ $headerName }}</span>
            @if(filled($navbarText))
              <span class="block truncate text-xs text-gray-400">{{ $navbarText }}</span>
            @endif
          </span>
        </a>

        <div id="cnav-menu"
          class="cnav__menu fixed inset-0 z-50 hidden flex-col bg-white text-gray-700 lg:static lg:flex lg:flex-row lg:items-center lg:gap-4 lg:bg-transparent lg:p-0">
          <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 lg:hidden">
            <span class="text-base font-semibold text-gray-700">Menú</span>
            <button
              class="flex h-11 w-11 items-center justify-center rounded-xl text-gray-500 hover:bg-gray-100 active:bg-gray-200"
              id="cnav-close" aria-label="Cerrar menú">
              <i class="ri-close-line text-xl"></i>
            </button>
          </div>

          <ul
            class="flex flex-col gap-1 px-4 py-4 text-base font-semibold lg:flex-row lg:items-center lg:gap-4 lg:px-0 lg:py-0 lg:text-sm"
            role="menubar">
            @foreach($orderedNavItems as $item)
              <li role="none">
                <a role="menuitem" href="{{ $item['url'] }}"
                  class="{{ $item['key'] === 'demo' ? 'btn cnav-demo-link flex min-h-[48px] w-full items-center justify-center gap-2 rounded-xl px-4 py-3 text-base lg:min-h-0 lg:w-auto lg:rounded-full lg:px-4 lg:py-2 lg:text-sm' : 'flex min-h-[48px] items-center gap-3 rounded-xl px-4 py-3 transition-colors lg:min-h-0 lg:rounded-full lg:px-4 lg:py-2' }} {{ $item['key'] === 'demo' ? ($item['active'] ? 'is-active' : '') : ($item['active'] ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50 active:bg-gray-100') }}">
                  <i class="{{ $item['icon'] }} text-lg"></i>{{ $item['label'] }}
                </a>
              </li>
            @endforeach

            @auth
              <li role="none">
                @php
                  $user = Auth::user();
                  $panel = $user?->dashboardPath() ?? route('home');
                @endphp
                <a role="menuitem" href="{{ $panel }}"
                  class="flex min-h-[48px] items-center gap-3 rounded-xl px-4 py-3 transition-colors lg:min-h-0 lg:rounded-full lg:px-4 lg:py-2 {{ request()->routeIs('home') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50 active:bg-gray-100' }}">
                  <i class="ri-dashboard-line text-lg"></i>Mi panel
                </a>
              </li>
              <li role="none" class="mt-2 border-t border-gray-100 pt-2 lg:mt-0 lg:border-0 lg:pt-0">
                <form method="POST" action="{{ route('salir') }}">
                  @csrf
                  <button type="submit"
                    class="flex min-h-[48px] w-full items-center gap-3 rounded-xl px-4 py-3 text-left text-rose-600 transition-colors hover:bg-rose-50 active:bg-rose-100 lg:min-h-0 lg:w-auto lg:rounded-full lg:px-4 lg:py-2">
                    <i class="ri-logout-box-line text-lg"></i>Salir
                  </button>
                </form>
              </li>
            @else
              @if(R::has('login'))
                <li role="none" class="mt-3 lg:mt-0">
                  <a role="menuitem" href="{{ $loginModalUrl }}"
                    class="btn btn-primary flex min-h-[48px] w-full items-center justify-center gap-2 rounded-xl text-base lg:min-h-0 lg:w-auto lg:rounded-full lg:text-sm"
                    data-login-trigger>
                    <i class="ri-login-box-line text-lg"></i>{{ $headerLoginText }}
                  </a>
                </li>
              @endif
            @endauth
          </ul>

          @if($headerShowSocials)
            <div
              class="mt-auto flex items-center gap-2 border-t border-gray-100 px-5 py-4 text-xl text-gray-400 lg:mt-0 lg:border-0 lg:px-0 lg:py-0">
            </div>
          @endif
        </div>

        <button class="btn btn-ghost px-2 lg:hidden" id="cnav-toggle" aria-label="Abrir menú" aria-expanded="false"
          aria-controls="cnav-menu">
          <i class="ri-menu-2-line text-lg"></i>
        </button>
      </div>
    </nav>
  </header>

  @guest
  @php($maintenanceEnabled = $siteSettings->getBool('maintenance.enabled', false))

  {{-- ================================================================
  MODAL LOGIN - Premium 2-Column Design
  Verde institucional: var(--accent) #0f766e
  ================================================================ --}}
  <div id="loginModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="loginTitle" aria-hidden="true">

    {{-- Backdrop blur --}}
    <div class="modal-backdrop" data-close-login></div>

    {{-- Dialog --}}
    <div class="modal-dialog" role="document" tabindex="-1">
      <div class="lm-panel">

        {{-- Two-column grid --}}
        <div class="lm-grid">

          {{-- ================================
          LEFT - Decorative / Informative
          ================================ --}}
          <aside class="lm-left" aria-hidden="true">

            {{-- Heading --}}
            <div>
              <h2 class="lm-left-title">Bienvenido de nuevo</h2>
              <p class="lm-left-desc" style="margin-top:0.5rem;">
                Ingresa a tu cuenta para continuar gestionando tus citas y servicios de forma rápida y segura.
              </p>
            </div>

            {{-- Medical SVG illustration --}}
            <div class="lm-illustration">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 280 220" fill="none" aria-hidden="true">
                <!-- Shield -->
                <path d="M140 18 L196 42 L196 100 C196 138 140 166 140 166 C140 166 84 138 84 100 L84 42 Z"
                  fill="url(#shieldGrad)" opacity="0.92" />
                <path d="M140 30 L184 50 L184 100 C184 130 140 154 140 154 C140 154 96 130 96 100 L96 50 Z" fill="white"
                  opacity="0.55" />
                <!-- Checkmark -->
                <path d="M118 100 L133 115 L164 82" stroke="#0f766e" stroke-width="5" stroke-linecap="round"
                  stroke-linejoin="round" />
                <!-- Calendar -->
                <rect x="48" y="120" width="58" height="52" rx="8" fill="white" stroke="#14b8a6" stroke-width="1.5"
                  opacity="0.9" />
                <rect x="48" y="120" width="58" height="14" rx="8" fill="#14b8a6" opacity="0.75" />
                <line x1="64" y1="147" x2="64" y2="147" stroke="#0f766e" stroke-width="4" stroke-linecap="round" />
                <line x1="76" y1="147" x2="76" y2="147" stroke="#0f766e" stroke-width="4" stroke-linecap="round" />
                <line x1="88" y1="147" x2="88" y2="147" stroke="#0f766e" stroke-width="4" stroke-linecap="round" />
                <line x1="64" y1="160" x2="64" y2="160" stroke="#94a3b8" stroke-width="4" stroke-linecap="round" />
                <line x1="76" y1="160" x2="76" y2="160" stroke="#94a3b8" stroke-width="4" stroke-linecap="round" />
                <!-- Clock -->
                <circle cx="208" cy="152" r="30" fill="white" stroke="#14b8a6" stroke-width="1.5" opacity="0.9" />
                <circle cx="208" cy="152" r="3" fill="#0f766e" />
                <line x1="208" y1="152" x2="208" y2="138" stroke="#0f766e" stroke-width="2.5" stroke-linecap="round" />
                <line x1="208" y1="152" x2="218" y2="158" stroke="#14b8a6" stroke-width="2" stroke-linecap="round" />
                <!-- Person -->
                <circle cx="210" cy="86" r="18" fill="white" stroke="#14b8a6" stroke-width="1.5" opacity="0.9" />
                <circle cx="210" cy="82" r="6" fill="#14b8a6" opacity="0.7" />
                <path d="M198 104 Q210 96 222 104" fill="#14b8a6" opacity="0.5" />
                <!-- Defs -->
                <defs>
                  <linearGradient id="shieldGrad" x1="140" y1="18" x2="140" y2="166" gradientUnits="userSpaceOnUse">
                    <stop offset="0%" stop-color="#14b8a6" />
                    <stop offset="100%" stop-color="#0f766e" />
                  </linearGradient>
                </defs>
              </svg>
            </div>

            {{-- Mini indicators --}}
            <div class="lm-indicators">
              <div class="lm-indicator">
                <span class="lm-indicator-icon"><i class="ri-shield-check-line"></i></span>
                <span class="lm-indicator-label">Seguridad<br>total</span>
              </div>
              <div class="lm-indicator">
                <span class="lm-indicator-icon"><i class="ri-speed-line"></i></span>
                <span class="lm-indicator-label">Acceso<br>rápido</span>
              </div>
              <div class="lm-indicator">
                <span class="lm-indicator-icon"><i class="ri-lock-password-line"></i></span>
                <span class="lm-indicator-label">Tus datos<br>protegidos</span>
              </div>
            </div>
          </aside>

          {{-- ================================
          RIGHT - Login Form
          ================================ --}}
          <section class="lm-right">

            {{-- Close button --}}
            <button id="closeLoginModal" class="lm-close" aria-label="Cerrar modal" data-close-login>
              <i class="ri-close-line"></i>
            </button>

            {{-- Heading --}}
            <h3 id="loginTitle" class="lm-form-title">Iniciar sesión</h3>
            <p class="lm-form-subtitle">Elige tu método de acceso</p>

            {{-- Alerts --}}
            @if(session('status'))
              <div class="lm-alert success">
                <i class="ri-checkbox-circle-line" style="flex-shrink:0"></i>
                {{ session('status') }}
              </div>
            @endif
            @if(session('auth_error') || $loginErrors->any())
              <span data-open-login-onload hidden></span>
            @endif
            @if(session('auth_error'))
              <div class="lm-alert error">
                <i class="ri-error-warning-line" style="flex-shrink:0"></i>
                {{ session('auth_error') }}
              </div>
            @elseif($loginErrors->has('email'))
              <div class="lm-alert error">
                <i class="ri-error-warning-line" style="flex-shrink:0"></i>
                {{ $loginErrors->first('email') }}
              </div>
            @elseif($loginErrors->any())
              <div class="lm-alert error">
                <i class="ri-error-warning-line" style="flex-shrink:0"></i>
                Revisa tus datos e inténtalo nuevamente.
              </div>
            @endif


            {{-- â”€â”€ PANELS â”€â”€ --}}
            <div class="lm-panels">

              {{-- Password panel --}}
              <div id="lm-panel-password" class="lm-tabpanel" role="tabpanel" data-login-panel="password">
                <form method="POST" action="{{ route('login') }}" id="loginForm" class="space-y-0"
                  data-remember-login-form data-action-lock>
                  @csrf
                  <input type="hidden" name="remember" value="0">

                  {{-- Email --}}
                  <div class="lm-field">
                    <label class="lm-label" for="loginEmail">Correo electrónico</label>
                    <div class="lm-input-wrap">
                      <i class="ri-mail-line lm-input-icon"></i>
                      <input type="email" id="loginEmail" name="email" autocomplete="email" required
                        value="{{ old('email') }}" placeholder="correo@ejemplo.com" inputmode="email" class="lm-input"
                        data-remember-login-email>
                    </div>
                  </div>

                  {{-- Password --}}
                  <div class="lm-field">
                    <label class="lm-label" for="loginPassword">Contraseña</label>
                    <div class="lm-input-wrap">
                      <i class="ri-lock-line lm-input-icon"></i>
                      <input type="password" id="loginPassword" name="password" autocomplete="current-password" required
                        placeholder="••••••••" class="lm-input">
                      <button type="button" class="lm-eye-btn btn-eye" data-target="#loginPassword"
                        aria-label="Mostrar u ocultar contraseña">
                        <i class="ri-eye-line" aria-hidden="true"></i>
                      </button>
                    </div>
                  </div>

                  {{-- Remember + forgot --}}
                  <div class="lm-row">
                    <label class="lm-remember">
                      <input type="checkbox" name="remember" value="1" data-remember-login-checkbox
                        @checked(old('remember'))>
                      Recuérdame
                    </label>
                    @if(R::has('password.request'))
                      <a href="{{ route('password.request') }}" class="lm-forgot">¿Olvidaste tu contraseña?</a>
                    @endif
                  </div>

                  {{-- Submit --}}
                  <button type="submit" class="lm-submit" data-loading-text="Ingresando...">
                    Entrar <i class="ri-arrow-right-line"></i>
                  </button>
                </form>
              </div>{{-- /password panel --}}



            </div>{{-- /lm-panels --}}
          </section>{{-- /lm-right --}}

        </div>{{-- /lm-grid --}}

        {{-- Footer stripe --}}
        <div class="lm-footer">
          <i class="ri-shield-check-line"></i>
          <span>Protegemos tu información con los más altos estándares de seguridad.</span>
        </div>

      </div>{{-- /lm-panel --}}
    </div>{{-- /modal-dialog --}}
  </div>{{-- /#loginModal --}}
  @endguest

  <main class="min-h-screen {{ $stickyHeader ? 'pt-[4.5rem]' : '' }}">@yield('main')</main>

  @guest
    @unless($siteSettings->getBool('maintenance.enabled', false))
      @include('chatbot.widget')
    @endunless

  @endguest

  @include('partials.legal-modals')
  <x-ui.global-action-lock />
  @stack('scripts')
</body>

</html>
