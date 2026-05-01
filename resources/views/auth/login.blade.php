@extends('layouts.app')

@section('content')
  <main class="section-pad">
    <div class="page-shell">
      <div class="grid gap-8 lg:grid-cols-[1fr_0.9fr]">
        <div class="card p-8">
          <div class="mb-6">
            <p class="text-xs uppercase tracking-widest text-gray-500">Acceso</p>
            <h1 class="mt-2 text-2xl font-semibold text-gray-900">Iniciar sesion</h1>
            <p class="text-gray-600">Ingresa con tu correo y contrasena para gestionar tus citas.</p>
          </div>

          @if($errors->any())
            <x-ui.alert tone="error">{{ $errors->first() }}</x-ui.alert>
          @endif

          <form method="POST" action="{{ route('login') }}" id="loginForm" class="space-y-4" data-remember-login-form>
            @csrf
            <input type="hidden" name="remember" value="0">

            <div>
              <label class="form-label" for="email">Correo electronico</label>
              <input id="email" type="email" name="email" placeholder="correo@ejemplo.com" value="{{ old('email') }}" required class="form-input" data-remember-login-email>
            </div>

            <div>
              <label class="form-label" for="login_password">Contrasena</label>
              <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2">
                <input type="password" name="password" id="login_password" placeholder="Contrasena" required class="flex-1 bg-transparent text-sm">
                <button type="button" class="toggle-eye" data-target="login_password" aria-label="Mostrar u ocultar contrasena">
                  <i class="ri-eye-line"></i>
                </button>
              </div>
            </div>

            <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-gray-500">
              <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-gray-300" data-remember-login-checkbox @checked(old('remember'))>
              Recuerdame
            </label>

            <x-ui.form-actions>
              <x-slot:left>
                <a href="{{ url('/') }}" class="btn btn-ghost" aria-label="Volver al inicio">
                  <i class="ri-arrow-left-line"></i> Volver
                </a>
              </x-slot>
              <button type="submit" class="btn btn-primary">Ingresar</button>
            </x-ui.form-actions>
          </form>

          <div class="mt-5 flex flex-wrap gap-3 text-sm text-gray-500">
            <button type="button" class="cursor-pointer hover:text-gray-800" data-legal-open="privacy-policy-modal">
              Políticas de privacidad
            </button>
            <button type="button" class="cursor-pointer hover:text-gray-800" data-legal-open="terms-service-modal">
              Términos de servicio
            </button>
          </div>
        </div>

        <div class="glass-panel p-8">
          <p class="text-xs uppercase tracking-widest text-gray-500">Bienvenida</p>
          <h2 class="mt-2 text-2xl font-semibold text-gray-900">Bienvenido a tu panel clinico</h2>
          <p class="mt-2 text-gray-600">Accede para agendar citas, revisar resultados y consultar documentos medicos registrados en tu cuenta.</p>
          <div class="mt-6 grid gap-3">
            <div class="card p-4">
              <p class="text-sm font-semibold text-gray-900">Citas disponibles</p>
              <p class="text-sm text-gray-500">Selecciona especialidad, profesional y horario registrado.</p>
            </div>
            <div class="card p-4">
              <p class="text-sm font-semibold text-gray-900">Recordatorios de citas</p>
              <p class="text-sm text-gray-500">Recibe avisos sobre tus proximas atenciones.</p>
            </div>
            <div class="card p-4">
              <p class="text-sm font-semibold text-gray-900">Documentos medicos</p>
              <p class="text-sm text-gray-500">Accede a tus resultados y recetas.</p>
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
