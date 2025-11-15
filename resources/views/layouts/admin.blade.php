<!-- resources/views/layouts/admin.blade.php -->
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title','Panel Administrativo - Clínica Don Bosco')</title>
  <link rel="icon" type="image/jpg" href="{{ asset('img/LogoClinica.jpg') }}">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp" rel="stylesheet" />
  <meta name="dashboard-resumen-url" content="{{ route('admin.dashboard.resumen') }}">
  @vite(['resources/css/dashboards/admin.css', 'resources/js/dashboard-admin.js', 'resources/js/dashboard-admin-extras.js'])
  @stack('head')
</head>

@php($hasRight = View::hasSection('right'))
<body>
<div class="container{{ $hasRight ? '' : ' no-right' }}">
  <aside>
    <div class="top">
      <div class="logo">
        <h2>Clínica <span class="danger">Don Bosco</span></h2>
      </div>
      <div class="close">
        <span class="material-symbols-outlined">close</span>
      </div>
    </div>

    <div class="sidebar">
      <a href="{{ route('admin.dashboard') }}"
         class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
        <span class="material-symbols-outlined">dashboard</span><h3>Inicio</h3>
      </a>

      @php($isUsuariosSection = request()->routeIs('admin.usuarios.*') && !request()->routeIs('admin.usuarios.create'))
      <a href="{{ route('admin.usuarios.index') }}"
         class="{{ $isUsuariosSection ? 'active' : '' }}">
        <span class="material-symbols-outlined">person</span><h3>Usuarios</h3>
      </a>

      <a href="{{ route('admin.usuarios.create') }}"
         class="{{ request()->routeIs('admin.usuarios.create') ? 'active' : '' }}">
        <span class="material-symbols-outlined">person_add</span><h3>Registrar Usuario</h3>
      </a>

      <a href="{{ route('admin.horarios.index') }}"
         class="{{ request()->routeIs('admin.horarios.*') ? 'active' : '' }}">
        <span class="material-symbols-outlined">schedule</span><h3>Horarios</h3>
      </a>

      <a href="{{ route('admin.perfil.edit') }}"
         class="{{ request()->routeIs('admin.perfil.*') ? 'active' : '' }}">
        <span class="material-symbols-outlined">account_circle</span><h3>Perfil</h3>
      </a>

      <a href="{{ route('admin.cambios-citas.index') }}"
        class="{{ request()->routeIs('admin.cambios-citas.*') ? 'active' : '' }}">
        <span class="material-symbols-outlined">update</span><h3>Cambios de Citas</h3>
      </a>

      <form id="logout-form" action="{{ route('salir') }}" method="POST" style="display:none;">@csrf</form>
      <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
        <span class="material-symbols-outlined">logout</span><h3>Cerrar Sesión</h3>
      </a>
    </div>
  </aside>

  <!-- Topbar móvil persistente con el mismo id esperado por tu JS -->
  <header class="mobile-topbar">
    <button id="menu_bar" class="menu-toggle" aria-label="Abrir menú">
      <span class="material-symbols-sharp">menu</span>
    </button>
    <h1 class="topbar-title">@yield('title','Panel Administrativo')</h1>
  </header>

  <main>
    @yield('main')
  </main>

  @if($hasRight)
    <div class="right">
      <div class="top">
        <button id="menu_bar"><span class="material-symbols-sharp">menu</span></button>

        <div class="right-controls">
          <div class="theme-toggler">
            <span class="material-symbols-sharp active">light_mode</span>
            <span class="material-symbols-sharp">dark_mode</span>
          </div>

          <div class="profile">
            <div class="info">
              <p class="name"><b>{{ Auth::user()->name ?? 'Admin' }}</b></p>
              <p class="role">Panel Clínico</p>
            </div>
            <div class="profile-photo">
              <img
                src="{{ Auth::user()->avatar ? asset('storage/'.Auth::user()->avatar) : asset('img/doctor1.jpg') }}"
                alt="Foto de perfil">
            </div>
          </div>
        </div>
      </div>

      @yield('right')
    </div>
  @endif
</div>

@stack('scripts')
</body>
</html>
