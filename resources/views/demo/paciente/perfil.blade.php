@extends('layouts.demo')
@section('title', 'Perfil del paciente - Demo')
@section('activeSidebar', 'perfil')
@section('header-title','Perfil')
@section('header-subtitle','Actualiza tu información personal (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-paciente-demo')
@endsection

@php
  $user = new \App\Models\User([
      'name' => $patientProfile['name'],
      'email' => $patientProfile['email'],
      'telefono' => $patientProfile['phone'],
      'dni' => $patientProfile['document'],
      'direccion' => 'Av. de la Prensa, Quito',
      'sexo' => 'Femenino',
      'fecha_nacimiento' => '1992-05-14',
  ]);
@endphp

@section('main')
<div class="space-y-6">
  <div class="grid gap-6 lg:grid-cols-[0.7fr_1.3fr]">
    <!-- Aside -->
    <aside class="card p-6 bg-white">
      <div class="flex flex-col items-center text-center">
        <div class="relative h-32 w-32">
          <div class="flex h-32 w-32 items-center justify-center rounded-3xl bg-blue-100 text-3xl font-bold text-blue-700">
            MV
          </div>
          <button type="button" class="btn btn-primary btn-sm absolute inset-x-2 bottom-2 demo-action-blocked">Cambiar</button>
        </div>

        <h2 class="mt-4 text-lg font-semibold text-gray-900">{{ $user->name }}</h2>
        <p class="text-sm text-gray-500">{{ $user->email }}</p>
        <span class="mt-3 badge info">Rol: Paciente</span>
      </div>

      <div class="mt-6 space-y-3 text-sm text-gray-650">
        <div class="flex items-center gap-2"><i class="ri-phone-line"></i> {{ $user->telefono }}</div>
        <div class="flex items-center gap-2"><i class="ri-map-pin-line"></i> {{ $user->direccion }}</div>
      </div>
    </aside>

    <!-- Form -->
    <section class="card p-6 bg-white">
      <div>
        <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">Datos personales</p>
        <h3 class="mt-2 text-lg font-semibold text-gray-900">Información del paciente</h3>
        <p class="text-sm text-gray-500">Los cambios se aplican inmediatamente después de guardar (Simulado).</p>
      </div>

      @if(session('success'))
        <x-ui.alert tone="success" class="mt-4">{{ session('success') }}</x-ui.alert>
      @endif

      <form method="POST" action="{{ route('demo.paciente.perfil.update') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
        @csrf

        <section>
          <h4 class="text-sm font-semibold text-gray-700">Identificación</h4>
          <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div>
              <label class="form-label">Nombre</label>
              <input class="form-input" type="text" name="name" value="{{ old('name', $user->name) }}" required>
            </div>
            <div>
              <label class="form-label">Correo</label>
              <input class="form-input" type="email" name="email" value="{{ old('email', $user->email) }}" required>
            </div>
            <div>
              <label class="form-label">Teléfono</label>
              <input class="form-input" type="tel" name="telefono" value="{{ old('telefono', $user->telefono) }}" required>
            </div>
            <div>
              <label class="form-label">Cédula</label>
              <input class="form-input" type="text" name="dni" value="{{ old('dni', $user->dni) }}" required>
            </div>
          </div>
        </section>

        <section class="border-t border-gray-100 pt-6">
          <h4 class="text-sm font-semibold text-gray-700">Información adicional</h4>
          <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div>
              <label class="form-label">Dirección</label>
              <input class="form-input" type="text" name="direccion" value="{{ old('direccion', $user->direccion) }}" required>
            </div>
            <div>
              <label class="form-label">Fecha de nacimiento</label>
              <input class="form-input" type="date" name="fecha_nacimiento" value="{{ $user->fecha_nacimiento }}" required>
            </div>
            <div>
              <label class="form-label">Sexo</label>
              <select class="form-select" name="sexo" required>
                <option value="Femenino" selected>Femenino</option>
                <option value="Masculino">Masculino</option>
                <option value="Otro">Otro</option>
              </select>
            </div>
          </div>
        </section>

        <section class="border-t border-gray-100 pt-6">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <h4 class="text-sm font-semibold text-gray-700">Seguridad</h4>
          </div>
          <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div>
              <label class="form-label">Contraseña actual</label>
              <input class="form-input" type="password" name="current_password" placeholder="••••••••">
            </div>
            <div>
              <label class="form-label">Nueva contraseña</label>
              <input class="form-input" type="password" name="password" placeholder="Mín. 8 caracteres">
            </div>
          </div>
        </section>

        <x-ui.form-actions>
          <button type="submit" class="btn btn-primary">Guardar cambios</button>
        </x-ui.form-actions>
      </form>
    </section>
  </div>
</div>
@endsection
