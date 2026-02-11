@extends('layouts.app')

@section('content')
@php
    $user = auth()->user();
    $returnUrl = route('home');
    if ($user) {
        if ($user->hasRole('administrador')) $returnUrl = route('admin.perfil.edit');
        if ($user->hasRole('doctor')) $returnUrl = route('doctor.perfil.edit');
        if ($user->hasRole('paciente')) $returnUrl = route('paciente.perfil.edit');
    }
@endphp

  <main class="section-pad">
    <div class="page-shell">
      <div class="card p-8 max-w-2xl mx-auto">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div>
            <p class="text-xs uppercase tracking-widest text-slate-500">Perfil</p>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">Registrar reconocimiento facial</h1>
            <p class="text-slate-600">Permite un acceso más seguro a tu cuenta.</p>
          </div>
          <span class="badge info">Seguridad avanzada</span>
        </div>

        @if (session('status'))
          <div class="alert success mt-6">{{ session('status') }}</div>
        @endif

        <div class="mt-6 space-y-6">
          <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-900">
            <video id="video" autoplay class="w-full h-72 object-cover"></video>
          </div>
          <button
              id="capture"
              class="btn btn-primary w-full"
              data-enroll-url="{{ route('face.enroll') }}"
              data-csrf="{{ csrf_token() }}"
              data-models-url="{{ asset('models') }}"
              data-return-url="{{ $returnUrl }}"
              data-return-anchor="perfil-face"
              data-has-face="{{ $user && $user->faceProfile ? '1' : '0' }}"
              data-saved-status="{{ $user && $user->faceProfile ? 'Rostro registrado.' : '' }}">
              Guardar perfil
          </button>
          <p id="faceEnrollStatus" class="text-sm font-semibold text-slate-600"></p>
          <div id="faceEnrollProgress" class="h-2 overflow-hidden rounded-full bg-slate-200">
              <span class="face-enroll-progress__bar block h-2 w-0 bg-teal-600"></span>
          </div>
        </div>
      </div>
    </div>
  </main>

  @push('scripts')
      @vite('resources/js/auth/face-enroll.js')
  @endpush
@endsection
