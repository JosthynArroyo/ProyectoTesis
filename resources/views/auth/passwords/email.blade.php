{{-- resources/views/auth/passwords/email.blade.php --}}
@extends('layouts.app')
@section('title','Recuperar acceso')

@section('content')
  <main class="section-pad">
    <div class="page-shell">
      <div class="card p-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p class="text-xs uppercase tracking-widest text-slate-500">Recuperación</p>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">Recuperar acceso</h1>
            <p class="text-slate-600">Ingresa tu correo para enviarte un enlace de restablecimiento.</p>
          </div>
          <span class="badge info">Cuenta registrada</span>
        </div>

        @if (session('status'))
          <div class="alert success mt-6" role="status">
            {{ session('status') }}
          </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
          @csrf
          <div>
            <label for="email" class="form-label">Correo electrónico</label>
            <input id="email" type="email" class="form-input @error('email') border-rose-300 @enderror"
                   name="email" value="{{ old('email') }}" required placeholder="tucorreo@ejemplo.com">
            @error('email')
              <div class="text-xs text-rose-600">{{ $message }}</div>
            @else
              <div class="text-xs text-slate-500">Debe ser un correo registrado en el sistema.</div>
            @enderror
          </div>

          <button type="submit" class="btn btn-primary">
            <i class="ri-send-plane-2-line"></i> Enviar enlace
          </button>
        </form>
      </div>
    </div>
  </main>
@endsection
