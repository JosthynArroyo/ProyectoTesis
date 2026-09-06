@extends('layouts.app')

@section('content')
  <main class="section-pad">
    <div class="page-shell">
      <div class="grid gap-8 lg:grid-cols-[1fr_0.9fr]">
        <div class="card p-8">
          <div class="mb-6">
            <p class="text-xs uppercase tracking-widest text-red-500 font-semibold">Seguridad</p>
            <h1 class="mt-2 text-2xl font-semibold text-gray-900">Cambio de contraseña obligatorio</h1>
            <p class="text-gray-600">Por motivos de seguridad, debe cambiar su contraseña inicial antes de continuar al panel.</p>
          </div>

          @if($errors->any())
            <x-ui.alert tone="error">
              <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </x-ui.alert>
          @endif

          <form method="POST" action="{{ route('auth.must-change-password.update') }}" class="space-y-4" data-action-lock-title="Actualizando contraseña..." data-action-lock-description="Por favor, espera mientras guardamos tu nueva contraseña.">
            @csrf

            <div>
              <label class="form-label" for="current_password">Contraseña actual</label>
              <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2">
                <input type="password" name="current_password" id="current_password" placeholder="Contraseña actual" required class="flex-1 bg-transparent text-sm">
                <button type="button" class="toggle-eye" data-target="current_password" aria-label="Mostrar u ocultar contraseña">
                  <i class="ri-eye-line"></i>
                </button>
              </div>
            </div>

            <div>
              <label class="form-label" for="password">Nueva contraseña</label>
              <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2">
                <input type="password" name="password" id="password" placeholder="Nueva contraseña" required class="flex-1 bg-transparent text-sm">
                <button type="button" class="toggle-eye" data-target="password" aria-label="Mostrar u ocultar contraseña">
                  <i class="ri-eye-line"></i>
                </button>
              </div>
              <p class="text-xs text-gray-500 mt-1">Debe tener al menos 12 caracteres, incluir una mayúscula, una minúscula, un número y un símbolo.</p>
            </div>

            <div>
              <label class="form-label" for="password_confirmation">Confirmar nueva contraseña</label>
              <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2">
                <input type="password" name="password_confirmation" id="password_confirmation" placeholder="Confirmar nueva contraseña" required class="flex-1 bg-transparent text-sm">
                <button type="button" class="toggle-eye" data-target="password_confirmation" aria-label="Mostrar u ocultar contraseña">
                  <i class="ri-eye-line"></i>
                </button>
              </div>
            </div>

            <x-ui.form-actions>
              <x-slot:left>
                <button type="button" data-logout-trigger data-logout-form="logout-form-cancel" class="btn btn-ghost" data-action-lock-ignore>Cerrar sesión</button>
              </x-slot>
              <button type="submit" class="btn btn-primary" data-loading-text="Actualizando contraseña...">Actualizar contraseña</button>
            </x-ui.form-actions>
          </form>

          <form id="logout-form-cancel" action="{{ route('logout') }}" method="POST" class="hidden">
            @csrf
          </form>
        </div>

        <div class="glass-panel p-8">
          <p class="text-xs uppercase tracking-widest text-gray-500">Requisitos de Seguridad</p>
          <h2 class="mt-2 text-2xl font-semibold text-gray-900">Políticas de contraseñas seguras</h2>
          <p class="mt-2 text-gray-600">Para proteger el acceso a su cuenta con privilegios administrativos, la nueva contraseña debe cumplir con los siguientes requisitos:</p>
          <div class="mt-6 grid gap-3">
            <div class="card p-4">
              <p class="text-sm font-semibold text-gray-900">Longitud mínima</p>
              <p class="text-sm text-gray-500">Al menos 12 caracteres para dificultar ataques de fuerza bruta.</p>
            </div>
            <div class="card p-4">
              <p class="text-sm font-semibold text-gray-900">Complejidad obligatoria</p>
              <p class="text-sm text-gray-500">Debe contener mayúsculas (A-Z), minúsculas (a-z), números (0-9) y caracteres especiales (como @, #, $, %, etc.).</p>
            </div>
            <div class="card p-4">
              <p class="text-sm font-semibold text-gray-900">Sin similitud de cuenta</p>
              <p class="text-sm text-gray-500">No puede ser idéntica a su dirección de correo electrónico.</p>
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
