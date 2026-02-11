@extends('layouts.app')

@section('content')
  <main class="section-pad">
    <div class="page-shell">
      <div class="grid gap-8 lg:grid-cols-[1fr_0.9fr]">
        <div class="card p-8">
          <div class="mb-6">
            <p class="text-xs uppercase tracking-widest text-slate-500">Acceso</p>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">Iniciar sesión</h1>
            <p class="text-slate-600">Ingresa con tu correo y contraseña para gestionar tus citas.</p>
          </div>

          <form method="POST" action="{{ route('login') }}" id="loginForm" class="space-y-4">
            @csrf
            <input type="hidden" name="remember" value="0">

            <div>
              <label class="form-label" for="email">Correo electrónico</label>
              <input id="email" type="email" name="email" placeholder="correo@ejemplo.com" value="{{ old('email') }}" required class="form-input">
              @error('email')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
            </div>

            <div>
              <label class="form-label" for="login_password">Contraseña</label>
              <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
                <input type="password" name="password" id="login_password" placeholder="Contraseña" required class="flex-1 bg-transparent text-sm">
                <button type="button" class="toggle-eye" data-target="login_password" aria-label="Mostrar u ocultar contraseña">
                  <i class="ri-eye-line"></i>
                </button>
              </div>
              @error('password')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
            </div>

            <x-ui.form-actions>
              <x-slot:left>
                <a href="{{ url('/') }}" class="btn btn-ghost" aria-label="Volver al inicio">
                  <i class="ri-arrow-left-line"></i> Volver
                </a>
              </x-slot>
              <button type="submit" class="btn btn-primary">Ingresar</button>
            </x-ui.form-actions>
          </form>
        </div>

        <div class="glass-panel p-8">
          <p class="text-xs uppercase tracking-widest text-slate-500">Bienvenida</p>
          <h2 class="mt-2 text-2xl font-semibold text-slate-900">Bienvenido a tu panel clínico</h2>
          <p class="mt-2 text-slate-600">Accede para agendar, modificar o revisar tus citas y resultados médicos desde cualquier dispositivo.</p>
          <div class="mt-6 grid gap-3">
            <div class="card p-4">
              <p class="text-sm font-semibold text-slate-900">Gestión rápida</p>
              <p class="text-sm text-slate-500">Reserva citas en minutos.</p>
            </div>
            <div class="card p-4">
              <p class="text-sm font-semibold text-slate-900">Recordatorios inteligentes</p>
              <p class="text-sm text-slate-500">No olvides tus consultas.</p>
            </div>
            <div class="card p-4">
              <p class="text-sm font-semibold text-slate-900">Historial centralizado</p>
              <p class="text-sm text-slate-500">Accede a tus resultados y recetas.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
@endsection

@push('scripts')
  @vite('resources/js/auth/password-toggle.js')
@endpush