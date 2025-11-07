<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Área de Paciente')</title>
    <link rel="icon" type="image/jpg" href="{{ asset('img/LogoClinica.jpg') }}">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap">
    @vite(['resources/css/paciente/paciente.css', 'resources/js/sidebar-toggle.js'])
    @stack('head')
</head>
<body class="paciente-body @yield('body-class')">
    @php($hasRight = $__env->hasSection('right'))
    @php($user = Auth::user())

    <div class="container paciente-container {{ $hasRight ? 'has-right' : 'has-no-right' }}">
        @include('paciente.partials.sidebar')

        <div class="layout-content">
            <main>
                @yield('main')
            </main>

            @if($hasRight)
                <div class="right">
                    <div class="top">
                        <button id="menu_bar"><span class="material-symbols-sharp">menu</span></button>
                        <div class="theme-toggler">
                            <span class="material-symbols-sharp active">light_mode</span>
                            <span class="material-symbols-sharp">dark_mode</span>
                        </div>
                        <div class="profile">
                            <div class="info">
                                <p><b>{{ $user?->name ?? 'Paciente' }}</b></p>
                                <p>Panel Personal</p>
                            </div>
                            <div class="profile-photo">
                                <img src="{{ $user && $user->avatar ? asset('storage/'.$user->avatar) : asset('img/paciente1.jpg') }}" alt="Foto del paciente">
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
