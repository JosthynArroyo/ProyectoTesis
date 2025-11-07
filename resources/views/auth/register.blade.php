@extends('layouts.app')

@section('content')
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro | Clínica Médica</title>
    @vite('resources/css/register.css')
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap">
</head>
<body>
    <div class="container">
        <div class="toggle-container">
            <h1>Te damos la bienvenida</h1>
            <p>Inicia sesión para acceder a tu historial y agendar tus citas</p>
            <a href="{{ route('login') }}">
                <button>Iniciar Sesión</button>
            </a>
        </div>

        <div class="form-container sign-up">
            <form method="POST" action="{{ route('register') }}">
                @csrf
                <h1>Crear Cuenta</h1>

                <input type="text" name="name" placeholder="Nombre completo" value="{{ old('name') }}" required>
                @error('name')<span class="error">{{ $message }}</span>@enderror

                <input type="email" name="email" placeholder="Correo electrónico" value="{{ old('email') }}" required>
                @error('email')<span class="error">{{ $message }}</span>@enderror

                <div class="password-field">
                    <input type="password" name="password" id="password" placeholder="Contraseña" required>
                    <button type="button" class="toggle-eye" data-target="password" aria-label="Mostrar u ocultar contraseña">
                        <svg class="eye-on" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg>
                        <svg class="eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M3 3l18 18" stroke="currentColor" stroke-width="2"/><path d="M9.9 5.1A10.5 10.5 0 0122 12s-3.6 7-10 7a10.7 10.7 0 01-4-.8M6.1 7.3A11 11 0 002 12s3.6 7 10 7a10.9 10.9 0 004.8-1.1" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg>
                    </button>
                </div>
                @error('password')<span class="error">{{ $message }}</span>@enderror

                <div class="password-field">
                    <input type="password" name="password_confirmation" id="password_confirmation" placeholder="Confirmar contraseña" required>
                    <button type="button" class="toggle-eye" data-target="password_confirmation" aria-label="Mostrar u ocultar confirmación">
                        <svg class="eye-on" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg>
                        <svg class="eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M3 3l18 18" stroke="currentColor" stroke-width="2"/><path d="M9.9 5.1A10.5 10.5 0 0122 12s-3.6 7-10 7a10.7 10.7 0 01-4-.8M6.1 7.3A11 11 0 002 12s3.6 7 10 7a10.9 10.9 0 004.8-1.1" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg>
                    </button>
                </div>

                <button type="submit">Registrarse</button>
            </form>
        </div>
    </div>

    <script>
        document.querySelectorAll('.toggle-eye').forEach(function(b){
            b.addEventListener('click',function(){
                var id=b.getAttribute('data-target');var i=document.getElementById(id);if(!i)return;
                i.type=i.type==='password'?'text':'password';
                b.classList.toggle('is-on');
            });
        });
    </script>
</body>
</html>
@endsection
