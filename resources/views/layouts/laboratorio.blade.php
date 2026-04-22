<!DOCTYPE html>
<html lang="es" class="dashboard-root">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Panel de Laboratorio')</title>
    @include('layouts.partials.panel-theme-head')
    @include('layouts.partials.favicon')
    @include('layouts.partials.fonts')
    @stack('head')
    @vite(['resources/css/app.css','resources/css/panel-theme.css','resources/js/app.js','resources/js/panel-theme.js'])
    @stack('styles')
</head>
@php
    $hasRight = $__env->hasSection('right');
    $activeSidebar = trim($__env->yieldContent('activeSidebar'));
    $sidebarRoutes = ['laboratorio.dashboard', 'laboratorio.ordenes.index', 'laboratorio.horario.index'];
    $headerTitle = trim($__env->yieldContent('header-title')) ?: 'Panel laboratorio';
    $headerSubtitle = trim($__env->yieldContent('header-subtitle')) ?: 'Gestión de órdenes y resultados';
    $panelBackDefaultUrl = match (true) {
        request()->routeIs('laboratorio.horario.*') => route('laboratorio.horario.index'),
        request()->routeIs('laboratorio.ordenes.*', 'laboratorio.lab-orders.*') => route('laboratorio.ordenes.index'),
        default => route('laboratorio.dashboard'),
    };
    $panelBackFallbackUrl = trim($__env->yieldContent('back-url')) ?: $panelBackDefaultUrl;
@endphp
<body class="min-h-screen text-slate-900 dashboard-shell @yield('body-class')">
    <div class="min-h-screen lg:flex dashboard-layout">
        @include('laboratorio.partials.sidebar', ['active' => $activeSidebar])
        <div class="fixed inset-0 z-30 hidden bg-slate-900/50 backdrop-blur-sm lg:hidden" data-sidebar-overlay></div>

        <div class="flex min-h-screen flex-1 flex-col">
            <x-layout.dashboard-header :title="$headerTitle" :subtitle="$headerSubtitle" role="Laboratorio" :profile-route="null" />

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
