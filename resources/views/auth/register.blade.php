@extends('layouts.app')

@section('content')
  <main class="section-pad">
    <div class="page-shell">
      <div class="grid gap-8 lg:grid-cols-[1fr_0.9fr]">
        <div class="card p-8">
          <div class="mb-6">
            <p class="text-xs uppercase tracking-widest text-slate-500">Registro</p>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">Crear cuenta</h1>
            <p class="text-slate-600">Completa tus datos para acceder al panel y agendar citas.</p>
          </div>

          <form method="POST" action="{{ route('register') }}" class="space-y-4">
            @csrf

            <div>
              <label class="form-label" for="name">Nombre completo</label>
              <input type="text" id="name" name="name" placeholder="Nombre completo" value="{{ old('name') }}" required class="form-input">
              @error('name')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
            </div>

            <div>
              <label class="form-label" for="email">Correo electrónico</label>
              <input type="email" id="email" name="email" placeholder="correo@ejemplo.com" value="{{ old('email') }}" required class="form-input">
              @error('email')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
            </div>

            <div>
              <label class="form-label" for="password">Contraseña</label>
              <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2" data-password-wrap>
                <input type="password" name="password" id="password" placeholder="Contraseña" required minlength="8" autocomplete="new-password" class="flex-1 bg-transparent text-sm">
                <button type="button" class="toggle-eye" data-target="password" aria-label="Mostrar u ocultar contraseña">
                  <i class="ri-eye-line"></i>
                </button>
              </div>
              @error('password')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
            </div>

            <div>
              <label class="form-label" for="password_confirmation">Confirmar contraseña</label>
              <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2" data-password-wrap>
                <input type="password" name="password_confirmation" id="password_confirmation" placeholder="Confirmar contraseña" required minlength="8" autocomplete="new-password" class="flex-1 bg-transparent text-sm">
                <button type="button" class="toggle-eye" data-target="password_confirmation" aria-label="Mostrar u ocultar confirmación">
                  <i class="ri-eye-line"></i>
                </button>
              </div>
              @error('password_confirmation')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
            </div>

            <button type="submit" class="btn btn-primary w-full">Registrarse</button>
          </form>
        </div>

        <div class="glass-panel p-8">
          <p class="text-xs uppercase tracking-widest text-slate-500">Bienvenida</p>
          <h2 class="mt-2 text-2xl font-semibold text-slate-900">Te damos la bienvenida</h2>
          <p class="mt-2 text-slate-600">Inicia sesión para acceder a tu historial y agendar tus citas.</p>
          <div class="mt-6">
            <a href="{{ route('login') }}" class="btn btn-outline">Iniciar sesión</a>
          </div>
        </div>
      </div>
    </div>
  </main>
@endsection

@push('scripts')
    @vite('resources/js/auth/password-toggle.js')
@endpush