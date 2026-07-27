<!DOCTYPE html>
<html lang="es" class="dashboard-root">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'Demo del sistema')</title>
  @include('demo.partials.panel-theme-head')
  @include('layouts.partials.favicon')
  @include('layouts.partials.fonts')
  @vite([
    'resources/css/app.css',
    'resources/css/panel-theme.css',
    'resources/css/demo/demo-dashboard.css',
    'resources/js/app.js',
    'resources/js/panel-theme.js',
    'resources/js/demo/demo-actions.js',
  ])
  @stack('head')
</head>
@php
  $hasSidebar = trim($__env->yieldContent('sidebar')) !== '';
  $resolvedHeaderTitle = trim($__env->yieldContent('header-title')) ?: ($headerTitle ?? ($demoUser['roleLabel'] ?? 'Demo'));
  $resolvedHeaderSubtitle = trim($__env->yieldContent('header-subtitle')) ?: ($headerSubtitle ?? 'Datos simulados y acciones bloqueadas.');
@endphp
<body class="min-h-screen text-gray-900 dashboard-shell demo-shell">
<div class="min-h-screen {{ $hasSidebar ? 'lg:flex dashboard-layout' : '' }} relative">
  @if($hasSidebar)
    @yield('sidebar')
    <div class="fixed inset-0 z-30 hidden bg-gray-900/50 backdrop-blur-sm lg:hidden" data-sidebar-overlay></div>
  @endif

  <div class="flex min-h-screen flex-1 flex-col">
    @include('demo.partials.header-demo', [
      'headerTitle' => $resolvedHeaderTitle,
      'headerSubtitle' => $resolvedHeaderSubtitle,
      'hasSidebar' => $hasSidebar,
    ])

    <div @class([
      'dashboard-content flex-1 pb-10',
      'dashboard-content--with-sidebar' => $hasSidebar,
    ])>
      @include('demo.partials.demo-alert-bar')
      <div class="page-shell min-w-0 px-4 pt-4 sm:px-6">
        @if(View::hasSection('right'))
          <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
            <main class="space-y-6 min-w-0">
              @yield('main')
            </main>
            <aside class="space-y-4 min-w-0">
              @yield('right')
            </aside>
          </div>
        @else
          <main class="space-y-6 min-w-0">
            @yield('main')
          </main>
        @endif
      </div>
    </div>
  </div>
</div>

@if($showRoleSwitcher ?? false)
  @include('demo.partials.role-switcher-demo')
@endif

@stack('modals')
@include('demo.partials.action-blocked-modal')
<x-ui.global-action-lock />
</body>
</html>
