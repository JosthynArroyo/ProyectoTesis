@extends('layouts.app')

@section('content')
    {{-- CSS propio de la página de login --}}
    @vite('resources/css/login.css')
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap">

    <!-- Botón Volver -->
    <a href="{{ url('/') }}" class="back-btn" aria-label="Volver al inicio">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M15 19l-7-7 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Volver
    </a>

    <div class="container">
        {{-- Columna izquierda: formulario de inicio de sesión --}}
        <div class="form-container sign-in">
            <form method="POST" action="{{ route('login') }}" id="loginForm">
                @csrf
                <h1>Iniciar Sesión</h1>

                <input type="email" name="email" placeholder="Correo electrónico" value="{{ old('email') }}" required>
                @error('email')<span class="error">{{ $message }}</span>@enderror

                <div class="password-field">
                    <input type="password" name="password" id="login_password" placeholder="Contraseña" required>
                    <button type="button" class="toggle-eye" data-target="login_password" aria-label="Mostrar u ocultar contraseña">
                        <svg class="eye-on" width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z" stroke="currentColor" stroke-width="2"/>
                            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
                        </svg>
                        <svg class="eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M3 3l18 18" stroke="currentColor" stroke-width="2"/>
                            <path d="M9.9 5.1A10.5 10.5 0 0122 12s-3.6 7-10 7a10.7 10.7 0 01-4-.8M6.1 7.3A11 11 0 002 12s3.6 7 10 7a10.9 10.9 0 004.8-1.1" stroke="currentColor" stroke-width="2"/>
                            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
                        </svg>
                    </button>
                </div>
                @error('password')<span class="error">{{ $message }}</span>@enderror


                <button type="submit">Ingresar</button>
            </form>
        </div>

        {{-- Columna derecha: bienvenida (sin registro) --}}
        <div class="toggle-container">
            <h1>Bienvenido</h1>
            <p>Inicia sesión para agendar y gestionar tus citas médicas fácilmente.</p>
            
        </div>
    </div>

    <script>
        // Mostrar/ocultar contraseña
        document.querySelectorAll('.toggle-eye').forEach(function(b){
            b.addEventListener('click', function(){
                var id = b.getAttribute('data-target');
                var i = document.getElementById(id);
                if(!i) return;
                i.type = i.type === 'password' ? 'text' : 'password';
                b.classList.toggle('is-on');
            });
        });
    </script>
@endsection
