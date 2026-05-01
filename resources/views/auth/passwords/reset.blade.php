{{-- resources/views/auth/passwords/reset.blade.php --}}
@extends('layouts.app')
@section('title','Restablecer contraseña')

@section('content')
  <main class="section-pad">
    <div class="page-shell">
      <div class="card p-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p class="text-xs uppercase tracking-widest text-gray-500">Restablecer</p>
            <h1 class="mt-2 text-2xl font-semibold text-gray-900">Restablecer contraseña</h1>
            <p class="text-gray-600">Define tu nueva contraseña para continuar.</p>
          </div>
        </div>

        <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
          @csrf
          <input type="hidden" name="token" value="{{ $token }}">

          <div>
            <label for="email" class="form-label">Correo electrónico</label>
            <input id="email" type="email" name="email" value="{{ old('email', $email ?? '') }}" class="form-input @error('email') border-rose-300 @enderror" required placeholder="tucorreo@ejemplo.com">
            @error('email')
              <div class="text-xs text-rose-600">{{ $message }}</div>
            @else
              <div class="text-xs text-gray-500">Usa el mismo correo con el que solicitaste el enlace.</div>
            @enderror
          </div>

          <div>
            <label for="password" class="form-label">Nueva contraseña</label>
            <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2" data-password-wrap>
              <input id="password" type="password" name="password" class="flex-1 bg-transparent text-sm @error('password') border-rose-300 @enderror" required minlength="8" autocomplete="new-password" placeholder="••••••••">
              <button type="button" class="btn btn-ghost px-2 btn-eye" data-target="#password" aria-label="Mostrar u ocultar">
                <i class="ri-eye-line"></i>
              </button>
            </div>
            @error('password')
              <div class="text-xs text-rose-600">{{ $message }}</div>
            @else
              <div class="text-xs text-gray-500">Mínimo 8 caracteres. Incluye letras, números y un carácter especial.</div>
            @enderror
          </div>

          <div>
            <label for="password-confirm" class="form-label">Confirmar nueva contraseña</label>
            <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2" data-password-wrap>
              <input id="password-confirm" type="password" name="password_confirmation" class="flex-1 bg-transparent text-sm" required minlength="8" autocomplete="new-password" placeholder="••••••••">
              <button type="button" class="btn btn-ghost px-2 btn-eye" data-target="#password-confirm" aria-label="Mostrar u ocultar">
                <i class="ri-eye-line"></i>
              </button>
            </div>
            @error('password_confirmation')
              <div class="text-xs text-rose-600">{{ $message }}</div>
            @else
              <div class="text-xs text-gray-500">Debe coincidir con la nueva contraseña.</div>
            @enderror
          </div>

          <button type="submit" class="btn btn-primary">Restablecer</button>
        </form>
      </div>
    </div>
  </main>
@endsection

@push('scripts')
  @vite('resources/js/auth/password-toggle.js')
@endpush
