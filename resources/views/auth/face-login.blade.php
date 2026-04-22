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
              data-models-url="{{ asset('models') }}"
              data-face-state="initial">
          @csrf

          <div class="relative overflow-hidden rounded-lg border border-slate-200 bg-slate-950">
            <video id="video" autoplay muted playsinline class="h-64 w-full object-cover"></video>
            <div class="absolute inset-x-3 bottom-3 rounded-lg bg-slate-950/75 px-3 py-2 text-xs font-semibold text-white shadow-sm backdrop-blur">
              <span data-face-overlay>Cámara lista.</span>
            </div>
          </div>

          <div class="rounded-lg border border-slate-200 bg-slate-50 p-4" role="status" aria-live="polite">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reconocimiento facial</p>
            <p class="mt-1 text-base font-semibold text-slate-900" data-face-state-title>Listo para escanear</p>
            <p class="mt-2 text-sm text-slate-600" data-face-state-message>
              Mantén tu rostro dentro del recuadro y presiona el botón para iniciar.
            </p>
          </div>

          <button class="btn btn-primary w-full" id="loginBtn" type="submit">Escanear rostro</button>
          <a href="{{ route('login') }}" class="block text-center text-sm text-slate-500">Usar correo y contraseña</a>
        </form>
      </div>
    </div>
  </main>

  @push('scripts')
      @vite('resources/js/auth/face-login.js')
  @endpush
@endsection
