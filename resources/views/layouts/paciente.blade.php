<!DOCTYPE html>
<html lang="es" class="dashboard-root">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Area de Paciente')</title>
    @include('layouts.partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2family=Sora:wght@300;400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.min.css">
    @vite(['resources/css/app.css','resources/js/app.js'])
    @stack('head')
</head>
@php($hasRight = $__env->hasSection('right'))
@php($headerTitle = trim($__env->yieldContent('header-title')) ?: 'Panel del paciente')
@php($headerSubtitle = trim($__env->yieldContent('header-subtitle')) ?: 'Resumen personal y citas')
<body class="min-h-screen text-slate-900 dashboard-shell @yield('body-class')">
    <div class="min-h-screen lg:flex dashboard-layout">
        @include('paciente.partials.sidebar')
        <div class="fixed inset-0 z-30 hidden bg-slate-900/50 backdrop-blur-sm lg:hidden" data-sidebar-overlay></div>

        <div class="flex min-h-screen flex-1 flex-col">
            <x-layout.dashboard-header :title="$headerTitle" :subtitle="$headerSubtitle" role="Paciente" :profile-route="route('paciente.perfil.edit')" />

            <div class="dashboard-content flex-1 px-4 pb-10 lg:px-8">
                @if($hasRight)
                    <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
                        <main class="space-y-6">@yield('main')</main>
                        <aside class="space-y-4">@yield('right')</aside>
                    </div>
                @else
                    <main class="space-y-6">@yield('main')</main>
                @endif
            </div>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
