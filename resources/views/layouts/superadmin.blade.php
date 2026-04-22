<!DOCTYPE html>
<html lang="es" class="dashboard-root">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title','Panel Superadmin - Clínica Don Bosco')</title>
  @include('layouts.partials.panel-theme-head')
  @include('layouts.partials.favicon')
  @include('layouts.partials.fonts')
  @vite(['resources/css/app.css','resources/css/panel-theme.css','resources/js/app.js','resources/js/panel-theme.js'])
  @stack('head')
</head>
@php
  $hasRight = View::hasSection('right');
  $headerTitle = trim($__env->yieldContent('header-title')) ?: 'Supervisión total';
  $headerSubtitle = trim($__env->yieldContent('header-subtitle')) ?: 'Control global del sistema';
  $panelBackDefaultUrl = match (true) {
    request()->routeIs('superadmin.admins.*') => route('superadmin.admins.index'),
    request()->routeIs('superadmin.personalizacion.*') => route('superadmin.personalizacion.index'),
    request()->routeIs('superadmin.maintenance.*') => route('superadmin.dashboard'),
    default => route('superadmin.dashboard'),
  };
  $panelBackFallbackUrl = trim($__env->yieldContent('back-url')) ?: $panelBackDefaultUrl;
@endphp
@php
  $sidebarRoutes = ['superadmin.dashboard', 'superadmin.users.index', 'superadmin.admins.index', 'superadmin.solicitudes.personalizacion.index', 'superadmin.personalizacion.index'];
@endphp
<body class="min-h-screen text-slate-900 dashboard-shell">
<div class="min-h-screen lg:flex dashboard-layout">
  @include('superadmin.partials.sidebar')
  <div class="fixed inset-0 z-30 hidden bg-slate-900/50 backdrop-blur-sm lg:hidden" data-sidebar-overlay></div>

  <div class="flex min-h-screen flex-1 flex-col">
    <x-layout.dashboard-header :title="$headerTitle" :subtitle="$headerSubtitle" role="Superadmin" />

    <div class="dashboard-content flex-1 pb-10">
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

@stack('modals')
@include('partials.legal-modals')
@stack('scripts')
</body>
</html>
