@extends('layouts.demo')
@section('title','Editar usuario - Demo')
@section('header-title','Editar usuario #'.$u->id)
@section('header-subtitle','Actualiza información y permisos (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-admin-demo')
@endsection

@php
  $roles = collect([
      new \App\Models\Role(['id' => 1, 'name' => 'paciente']),
      new \App\Models\Role(['id' => 2, 'name' => 'doctor']),
      new \App\Models\Role(['id' => 3, 'name' => 'laboratorio']),
  ]);
  $especialidades = collect([
      new \App\Models\Especialidad(['id' => 1, 'nombre' => 'Pediatría']),
      new \App\Models\Especialidad(['id' => 2, 'nombre' => 'Medicina general']),
  ]);

  $statusLabel = ['active' => 'Activo', 'inactive' => 'Inactivo', 'blocked' => 'Bloqueado'][$u->status ?? 'active'] ?? 'Activo';
  $roleNombre = optional($u->roles->first())->name;
  $presetRole = $roleNombre;
  $selectedRoleId = old('role_id', optional($u->roles->first())->id);
@endphp

@section('main')
<div class="space-y-6">
  <div class="panel-action-bar">
    <span class="badge neutral">{{ ucfirst($roleNombre) }}</span>
    <span class="badge {{ ($u->status ?? 'active') === 'active' ? 'success' : (($u->status ?? 'active') === 'inactive' ? 'warning' : 'danger') }}">{{ $statusLabel }}</span>
  </div>

  @if (session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  <div class="grid gap-6 lg:grid-cols-[1.4fr_0.6fr]">
    <form class="card p-6 form space-y-6" method="POST" action="{{ route('demo.admin.usuarios.update', $u->id) }}">
      @csrf
      @method('PUT')

      <div class="md:hidden space-y-4">
        <div class="card p-4">
          <div class="flex items-center gap-3">
            <i class="ri-user-line text-gray-400"></i>
            <div>
              <p class="text-xs uppercase tracking-widest text-gray-500">Resumen</p>
              <h3 class="text-base font-semibold text-gray-900">{{ $u->name }}</h3>
            </div>
          </div>
          <ul class="mt-3 space-y-1 text-sm text-gray-600">
            <li>ID: <strong>#{{ $u->id }}</strong></li>
            <li>Creado: <strong>{{ optional($u->created_at)->format('Y-m-d H:i') ?? '2026-04-01 08:00' }}</strong></li>
            <li>Último acceso: <strong>{{ optional($u->last_login_at)->format('Y-m-d H:i') ?? 'N/D' }}</strong></li>
            <li>Estado: <strong>{{ $statusLabel }}</strong></li>
          </ul>
        </div>
      </div>

      <div class="space-y-6">
        <!-- Credenciales -->
        <section>
          <div>
            <p class="text-xs uppercase tracking-widest text-gray-500">Datos de cuenta</p>
            <h3 class="mt-2 text-lg font-semibold text-gray-900">Credenciales de acceso</h3>
            <p class="text-sm text-gray-500">Nombre visible y credenciales iniciales para el inicio de sesión.</p>
          </div>
          <div class="mt-4 form-grid form-grid--2">
            <div>
              <label for="name" class="form-label">Nombre</label>
              <input class="form-input" id="name" name="name" value="{{ old('name', $u->name) }}" required>
              @error('name')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
            </div>
            <div>
              <label for="email" class="form-label">Correo electrónico</label>
              <input class="form-input" id="email" name="email" type="email" value="{{ old('email', $u->email) }}" required>
              @error('email')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
            </div>
          </div>
        </section>

        <!-- Datos básicos -->
        <section class="border-t border-gray-100 pt-6">
          <div>
            <p class="text-xs uppercase tracking-widest text-gray-500">Información personal y contacto</p>
            <h3 class="mt-2 text-lg font-semibold text-gray-900">Datos básicos</h3>
            <p class="text-sm text-gray-500">Teléfono, documento y datos demográficos para el expediente.</p>
          </div>
          <div class="mt-4 form-grid form-grid--2">
            <div>
              <label for="telefono" class="form-label">Teléfono</label>
              <input class="form-input" id="telefono" name="telefono" value="{{ old('telefono', $u->telefono) }}" required>
              @error('telefono')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
            </div>
            <div>
              <label for="dni" class="form-label">Cédula</label>
              <input class="form-input" id="dni" name="dni" value="{{ old('dni', $u->dni) }}" required>
              @error('dni')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
            </div>
            <div class="col-span-full">
              <label for="direccion" class="form-label">Dirección</label>
              <input class="form-input" id="direccion" name="direccion" value="{{ old('direccion', $u->direccion ?? 'Quito, Ecuador') }}" required>
            </div>
          </div>
        </section>

        <!-- Rol y seguridad -->
        <section class="border-t border-gray-100 pt-6">
          <div>
            <p class="text-xs uppercase tracking-widest text-gray-500">Rol y seguridad</p>
            <h3 class="mt-2 text-lg font-semibold text-gray-900">Permisos y especialidad</h3>
            <p class="text-sm text-gray-500">Selecciona rol y define especialidad y tarifa si aplica.</p>
          </div>
          <div class="mt-4 form-grid form-grid--2">
            <div>
              <label for="role_id" class="form-label">Rol</label>
              <select class="form-select" name="role_id" id="role_id" disabled>
                @foreach($roles as $r)
                  <option value="{{ $r->id }}" @selected((string)$selectedRoleId === (string)$r->id)>
                    {{ ucfirst($r->name) }}
                  </option>
                @endforeach
              </select>
            </div>
            @if($roleNombre === 'doctor')
              <div>
                <label for="especialidad" class="form-label">Especialidad</label>
                <input class="form-input" id="especialidad" name="especialidad" value="{{ $u->especialidades->first()->nombre ?? 'Pediatría' }}" readonly>
              </div>
            @endif
          </div>
        </section>
      </div>

      <div class="mt-6 flex justify-end">
        <button class="btn btn-primary" type="submit">
          <i class="ri-save-line"></i> Guardar
        </button>
      </div>
    </form>

    <aside class="hidden space-y-4 lg:block">
      <div class="card p-4">
        <div class="flex items-center gap-3">
          <i class="ri-user-line text-gray-400"></i>
          <div>
            <p class="text-xs uppercase tracking-widest text-gray-500">Resumen</p>
            <h3 class="text-base font-semibold text-gray-900">{{ $u->name }}</h3>
          </div>
        </div>
        <ul class="mt-3 space-y-1 text-sm text-gray-600">
          <li>ID: <strong>#{{ $u->id }}</strong></li>
          <li>Creado: <strong>2026-04-01 08:00</strong></li>
          <li>Último acceso: <strong>{{ optional($u->last_login_at)->format('Y-m-d H:i') ?? 'N/D' }}</strong></li>
          <li>Estado: <strong>{{ $statusLabel }}</strong></li>
        </ul>
      </div>
    </aside>
  </div>
</div>
@endsection
