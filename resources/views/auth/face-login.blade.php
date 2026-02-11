@extends('layouts.app')

@section('content')
  <main class="section-pad">
    <div class="page-shell">
      <div class="card p-8 max-w-xl mx-auto">
        <div class="text-center">
          <p class="text-xs uppercase tracking-widest text-slate-500">Acceso biométrico</p>
          <h1 class="mt-2 text-2xl font-semibold text-slate-900">Ingresar con reconocimiento facial</h1>
          <p class="text-slate-600">Asegura tu acceso con tu rostro registrado.</p>
        </div>

        <form id="faceLoginForm"
              class="mt-6 space-y-5"
              data-endpoint="{{ route('face.login') }}"
              data-csrf="{{ csrf_token() }}"
              data-models-url="{{ asset('models') }}">
          @csrf
          <div>
            <label class="form-label" for="email">Correo</label>
            <input type="email" id="email" name="email" class="form-input" required>
            @error('email')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
          </div>

          <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-900">
            <video id="video" autoplay class="w-full h-64 object-cover"></video>
          </div>

          <button class="btn btn-primary w-full" id="loginBtn" type="submit">Entrar</button>
          <a href="{{ route('login') }}" class="block text-center text-sm text-slate-500">Usar correo y contraseña</a>
        </form>

        <div id="error" class="text-rose-600 text-center text-sm"></div>
      </div>
    </div>
  </main>

  @push('scripts')
      @vite('resources/js/auth/face-login.js')
  @endpush
@endsection