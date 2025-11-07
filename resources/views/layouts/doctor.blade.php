<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Panel del Doctor')</title>
    <link rel="icon" type="image/jpg" href="{{ asset('img/LogoClinica.jpg') }}">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp" />
    @stack('head')
    @vite(['resources/css/dashboards/doctor.css', 'resources/js/sidebar-toggle.js'])
    @stack('styles')
</head>
<body class="@yield('body-class')">
    @php($hasRight = $__env->hasSection('right'))
    @php($activeSidebar = trim($__env->yieldContent('activeSidebar')))
    @php($user = Auth::user())

    <div class="container dashboard-container @if($hasRight) has-right @else has-no-right @endif">
        @include('doctor.partials.sidebar', ['active' => $activeSidebar])

        <div class="layout-content">
            <main>
                @yield('content')
            </main>

            {{-- Mostrar el panel derecho SOLO si la vista define @section('right') --}}
            @if($hasRight)
                <div class="right">
                    <div class="top">
                        <button id="menu_bar">
                            <span class="material-symbols-sharp">menu</span>
                        </button>
                        <div class="theme-toggler">
                            <span class="material-symbols-sharp active">light_mode</span>
                            <span class="material-symbols-sharp">dark_mode</span>
                        </div>
                        <div class="profile">
                            <div class="info">
                                <p><b>{{ $user?->name ?? 'Usuario' }}</b></p>
                                <p>Panel Médico</p>
                            </div>
                            <div class="profile-photo">
                                <img src="{{ $user && $user->avatar ? asset('storage/' . $user->avatar) : asset('img/doctor1.jpg') }}" alt="Foto del doctor">
                            </div>
                        </div>
                    </div>

                    @yield('right')
                </div>
            @endif
        </div>
    </div>

    @stack('scripts')
</body>
</html>
