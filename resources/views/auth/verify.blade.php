@extends('layouts.app')

@section('content')
  <main class="section-pad">
    <div class="page-shell">
      <div class="card p-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p class="text-xs uppercase tracking-widest text-gray-500">Verificación</p>
            <h1 class="mt-2 text-2xl font-semibold text-gray-900">Verifica tu correo electrónico</h1>
          </div>
          <span class="badge info">Paso requerido</span>
        </div>

        @if (session('resent'))
          <div class="alert success mt-6" role="alert">
            Se envió un nuevo enlace de verificación a tu correo.
          </div>
        @endif

        <p class="mt-6 text-gray-600">Antes de continuar, revisa tu bandeja de entrada y haz clic en el enlace de verificación.</p>
        <div class="mt-6">
          <form method="POST" action="{{ route('verification.resend') }}">
            @csrf
            <button type="submit" class="btn btn-outline">Reenviar enlace</button>
          </form>
        </div>
      </div>
    </div>
  </main>
@endsection
