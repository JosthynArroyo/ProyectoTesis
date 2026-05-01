@extends('layouts.app')

@section('content')
  <main class="section-pad">
    <div class="page-shell">
      <div class="card p-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p class="text-xs uppercase tracking-widest text-gray-500">Seguridad</p>
            <h1 class="mt-2 text-2xl font-semibold text-gray-900">Confirma tu contraseña</h1>
            <p class="text-gray-600">Por seguridad, confirma tu contraseña antes de continuar.</p>
          </div>
        </div>

        <form method="POST" action="{{ route('password.confirm') }}" class="mt-6 space-y-4">
          @csrf

          <div>
            <label for="password" class="form-label">Contraseña</label>
            <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2" data-password-wrap>
              <input id="password" type="password" class="flex-1 bg-transparent text-sm @error('password') border-rose-300 @enderror" name="password" required autocomplete="current-password">
              <button type="button" class="btn-eye" data-target="#password" aria-label="Mostrar u ocultar">
                <i class="ri-eye-line"></i>
              </button>
            </div>
            @error('password')
              <span class="text-xs text-rose-600" role="alert">{{ $message }}</span>
            @enderror
          </div>

          <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="btn btn-primary">Confirmar</button>
            @if (Route::has('password.request'))
              <a class="btn btn-ghost" href="{{ route('password.request') }}">Olvidaste tu contraseña</a>
            @endif
          </div>
        </form>
      </div>
    </div>
  </main>
@endsection