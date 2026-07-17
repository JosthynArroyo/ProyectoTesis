@extends('layouts.demo')
@section('title', 'Perfil del doctor - Demo')
@section('activeSidebar', 'perfil')
@section('header-title','Mi perfil')
@section('header-subtitle','Actualiza tus datos y seguridad (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-doctor-demo')
@endsection

@php
  $user = new \App\Models\User([
      'name' => $doctorProfile['name'],
      'email' => $doctorProfile['email'],
      'telefono' => $doctorProfile['phone'],
      'dni' => $doctorProfile['license'], // Use license as DNI or similar mock
      'direccion' => 'Av. de los Shyris, Quito',
      'precio_consulta' => 45.00,
      'sexo' => 'Femenino',
      'fecha_nacimiento' => '1984-11-20',
  ]);
  $user->id = 11;
  $user->p12_path = 'firma.p12';
@endphp

@section('main')
  <div class="space-y-6">
    <div class="grid gap-6 lg:grid-cols-[0.7fr_1.3fr]">
      <!-- Aside -->
      <aside class="card p-6">
        <div class="profile-avatar flex flex-col items-center text-center">
          <div class="relative">
            <div class="flex h-32 w-32 items-center justify-center rounded-2xl bg-emerald-100 text-3xl font-bold text-emerald-700">
              SC
            </div>
            <button type="button" class="btn btn-primary btn-sm absolute inset-x-2 bottom-2 demo-action-blocked">Cambiar foto</button>
          </div>
          <h2 class="mt-4 text-lg font-semibold text-gray-900">{{ $user->name }}</h2>
          <span class="text-sm text-gray-500">{{ $user->email }}</span>
        </div>
        <div class="mt-4 flex flex-wrap gap-2 justify-center">
          <span class="badge neutral">{{ $doctorProfile['specialty'] }}</span>
        </div>
        <div class="mt-6 space-y-2 text-sm text-gray-650">
          <div class="flex items-center gap-2"><i class="ri-phone-line"></i>{{ $user->telefono }}</div>
          <div class="flex items-center gap-2"><i class="ri-map-pin-line"></i>{{ $user->direccion }}</div>
          <div class="flex items-center gap-2"><i class="ri-money-dollar-circle-line"></i>Precio: ${{ number_format($user->precio_consulta,2) }} USD</div>
        </div>
      </aside>

      <!-- Form -->
      <section class="card p-6">
        <div>
          <h3 class="text-lg font-semibold text-gray-900">Datos personales y profesionales</h3>
          <span class="text-sm text-gray-500">Todos los campos pueden editarse en cualquier momento (Simulado).</span>
        </div>

        @if(session('success'))
          <x-ui.alert tone="success" class="mt-4">{{ session('success') }}</x-ui.alert>
        @endif

        <form method="POST" action="{{ route('demo.doctor.perfil.update') }}" enctype="multipart/form-data" id="perfilForm" class="mt-6 space-y-6">
          @csrf

          <section>
            <h4 class="text-sm font-semibold text-gray-700">Identidad</h4>
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
              <div>
                <label class="form-label">Precio de consulta (USD)</label>
                <input class="form-input" type="number" name="precio_consulta" step="0.01" min="0" value="{{ $user->precio_consulta }}" required>
              </div>
            </div>
          </section>

          <section class="border-t border-gray-100 pt-6">
            <h4 class="text-sm font-semibold text-gray-700">Firma Electrónica</h4>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
              <div>
                <label class="form-label">Archivo de Firma (.p12)</label>
                <input class="form-input" type="file" name="p12_file" accept=".p12">
                <div class="text-xs text-gray-500">Suba su certificado de firma electrónica (.p12) para poder firmar documentos.</div>
                @if($user->p12_path)
                  <div class="text-xs text-emerald-600 mt-1.5 flex items-center gap-1 font-semibold">
                    <i class="ri-checkbox-circle-line text-sm"></i> Firma electrónica cargada y activa
                  </div>
                @endif
              </div>
            </div>
          </section>

          <section class="border-t border-gray-100 pt-6">
            <h4 class="text-sm font-semibold text-gray-700">Seguridad</h4>
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

          <div class="flex justify-end mt-6">
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
          </div>
        </form>
      </section>
    </div>
  </div>
@endsection
