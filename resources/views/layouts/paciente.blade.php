<!DOCTYPE html>
<html lang="es" class="dashboard-root">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Area de Paciente')</title>
    @include('layouts.partials.panel-theme-head')
    @include('layouts.partials.favicon')
    @include('layouts.partials.fonts')
    @vite(['resources/css/app.css','resources/css/panel-theme.css','resources/js/app.js','resources/js/panel-theme.js'])
    @stack('head')
</head>
@php
    $hasRight = $__env->hasSection('right');
    $headerTitle = trim($__env->yieldContent('header-title')) ?: 'Panel del paciente';
    $headerSubtitle = trim($__env->yieldContent('header-subtitle')) ?: 'Resumen personal y citas';
    $panelBackDefaultUrl = match (true) {
        request()->routeIs('paciente.crear-cita*', 'paciente.editar-cita*') => route('paciente.citas'),
        request()->routeIs('paciente.pagos.*') => route('paciente.pagos.index'),
        request()->routeIs('paciente.historial.show', 'paciente.certificados.*') => route('paciente.historial'),
        request()->routeIs('paciente.laboratorio.download', 'paciente.lab-orders.*') => route('paciente.laboratorio.index'),
        default => route('paciente.dashboard'),
    };
    $panelBackFallbackUrl = trim($__env->yieldContent('back-url')) ?: $panelBackDefaultUrl;
    $sidebarRoutes = ['paciente.dashboard', 'paciente.perfil.edit', 'paciente.citas', 'paciente.pagos.index', 'paciente.historial', 'paciente.laboratorio.index', 'paciente.mensajes'];
@endphp
<body class="min-h-screen text-gray-900 dashboard-shell @yield('body-class')">
    <div class="min-h-screen lg:flex dashboard-layout">
        @include('paciente.partials.sidebar')
        <div class="fixed inset-0 z-30 hidden bg-gray-900/50 backdrop-blur-sm lg:hidden" data-sidebar-overlay></div>

        <div class="flex min-h-screen flex-1 flex-col">
            <x-layout.dashboard-header :title="$headerTitle" :subtitle="$headerSubtitle" role="Paciente" :profile-route="route('paciente.perfil.edit')" />

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

    @stack('modals')
    @include('partials.legal-modals')
    @stack('scripts')
</body>
</html>
