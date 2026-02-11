@extends('layouts.app')

@section('content')
  <main class="section-pad">
    <div class="page-shell">
      <div class="card p-8">
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="text-xs uppercase tracking-widest text-slate-500">Estado de cuenta</p>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">Panel</h1>
            <p class="text-slate-600">Bienvenido de nuevo a Clínica Don Bosco.</p>
          </div>
          <span class="badge info">Acceso activo</span>
        </div>

        @if (session('status'))
          <div class="alert success mt-6" role="alert">{{ session('status') }}</div>
        @endif

        <p class="mt-6 text-sm text-slate-500">Has iniciado sesión correctamente. Usa el menú para continuar.</p>
      </div>
    </div>
  </main>
@endsection
