<!-- resources/views/layouts/admin.blade.php -->
<!DOCTYPE html>
<html lang="es" class="dashboard-root">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'Panel Administrativo - '.$clinicIdentity->name())</title>
  @include('layouts.partials.panel-theme-head')
  @include('layouts.partials.favicon')
  @include('layouts.partials.fonts')
  <meta name="dashboard-resumen-url" content="{{ route('admin.dashboard.resumen') }}">
  @vite(['resources/css/app.css','resources/css/panel-theme.css','resources/js/app.js','resources/js/panel-theme.js'])
  @stack('head')
  @stack('styles')
</head>
@php
  $hasRight = View::hasSection('right');
  $headerTitle = trim($__env->yieldContent('header-title')) ?: 'Administración';
  $headerSubtitle = trim($__env->yieldContent('header-subtitle')) ?: 'Gestión integral del sistema';
  $panelBackDefaultUrl = match (true) {
    request()->routeIs('admin.usuarios.*') => route('admin.usuarios.index'),
    request()->routeIs('admin.horarios.*') => route('admin.horarios.index'),
    request()->routeIs('admin.pagos.*') => route('admin.pagos.index'),
    request()->routeIs('admin.historial.*') => route('admin.historial.index'),
    request()->routeIs('admin.recordatorios.*') => route('admin.recordatorios.index'),
    request()->routeIs('admin.contacto.mensajes.*') => route('admin.contacto.mensajes'),
    request()->routeIs('admin.cambios-citas.*') => route('admin.cambios-citas.index'),
    request()->routeIs('admin.citas.override.*') => route('admin.pagos.index'),
    request()->routeIs('admin.citas.prioridad.*') => route('admin.cambios-citas.index'),
    request()->routeIs('admin.personalizacion.*') => route('admin.personalizacion.index'),
    default => route('admin.dashboard'),
  };
  $panelBackFallbackUrl = trim($__env->yieldContent('back-url')) ?: $panelBackDefaultUrl;
@endphp
@php
  $sidebarRoutes = ['admin.dashboard', 'admin.perfil.edit', 'admin.usuarios.index', 'admin.personalizacion.index', 'admin.horarios.index', 'admin.pagos.index', 'admin.cambios-citas.index', 'admin.historial.index', 'admin.recordatorios.index', 'admin.contacto.mensajes'];
@endphp
<body class="min-h-screen text-gray-900 dashboard-shell">
<div class="min-h-screen lg:flex dashboard-layout">
  @include('admin.partials.sidebar')
  <div class="fixed inset-0 z-30 hidden bg-gray-900/50 backdrop-blur-sm lg:hidden" data-sidebar-overlay></div>

  <div class="flex min-h-screen flex-1 flex-col">
    <x-layout.dashboard-header :title="$headerTitle" :subtitle="$headerSubtitle" role="Administración" :profile-route="route('admin.perfil.edit')" />

    <div class="dashboard-content dashboard-content--with-sidebar flex-1 pb-10">
      <div class="page-shell min-w-0">
        @if($hasRight)
          <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
            <main class="space-y-6">
              <x-layout.panel-back-button :fallback-url="$panelBackFallbackUrl" :sidebar-routes="$sidebarRoutes" />
              @yield('main')
            </main>
            <aside class="space-y-4">@yield('right')</aside>
          </div>
        @else
          <main class="space-y-6">
            <x-layout.panel-back-button :fallback-url="$panelBackFallbackUrl" :sidebar-routes="$sidebarRoutes" />
            @yield('main')
          </main>
        @endif
      </div>
    </div>
  </div>
</div>

@php
  $personalizacionStatus = ($adminLayoutMetrics ?? [])['personalizacion'] ?? ['can_access' => false, 'pending' => false, 'expires_at' => null];
  $personalizacionPending = $personalizacionStatus['pending'] ?? false;
  $personalizacionAutoOpen = session('open_personalizacion_modal');
@endphp

<div id="personalizacionModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="personalizacionTitle" aria-hidden="true" data-open-onload="{{ $personalizacionAutoOpen ? '1' : '0' }}">
  <div class="modal-backdrop" data-close-personalizacion></div>
  <div class="modal-dialog" role="document" tabindex="-1">
    <div class="card mx-auto w-full max-w-md">
      <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
        <h3 id="personalizacionTitle" class="text-lg font-semibold">Solicitar permiso para acceder a Personalización</h3>
        <button class="btn btn-ghost px-2" aria-label="Cerrar modal" data-close-personalizacion>
          <i class="ri-close-line text-lg"></i>
        </button>
      </div>
      <div class="p-6 space-y-4">
        <p class="text-sm text-gray-600">
          Para acceder a la sección de Personalización necesitas aprobación del superadmin.
        </p>
        <div @class(['hidden' => ! $personalizacionPending]) data-personalizacion-pending-state>
          <x-ui.alert tone="warning">Ya enviaste una solicitud. Recibirás respuesta pronto.</x-ui.alert>
        </div>
          <form
            method="POST"
            action="{{ route('admin.personalizacion.request') }}"
            data-personalizacion-request-form
            @class(['hidden' => $personalizacionPending])
          >
            @csrf
            <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
            <button class="btn btn-primary w-full" type="submit" data-personalizacion-request-submit>
              <i class="ri-send-plane-2-line"></i> Solicitar
            </button>
          </form>
      </div>
    </div>
  </div>
</div>

@stack('modals')
@include('partials.legal-modals')
@stack('scripts')
@vite('resources/js/admin/personalizacion-modal.js')
</body>
</html>
