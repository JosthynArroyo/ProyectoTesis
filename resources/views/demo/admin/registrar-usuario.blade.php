@extends('layouts.demo')
@section('title','Crear usuario - Demo')
@section('header-title','Nuevo usuario')
@section('header-subtitle','Alta rápida de usuarios (Demo)')

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
  
  $presetRole = in_array(request('preset_role'), ['administrador','superadmin'], true) ? null : request('preset_role');
  $selectedRoleId = old('role_id', optional($roles->firstWhere('name', $presetRole))->id);
@endphp

@section('main')
<div class="space-y-6">
  @if (session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  <form class="form space-y-6" method="POST" action="{{ route('demo.admin.usuarios.store') }}" enctype="multipart/form-data" novalidate>
    @csrf
    @if($presetRole)
      <input type="hidden" name="role_id" value="{{ optional($roles->firstWhere('name',$presetRole))->id }}">
    @endif

    <div class="space-y-6">
      <!-- Datos de cuenta -->
      <section class="card p-6">
        <div>
          <p class="text-xs uppercase tracking-widest text-gray-500">Datos de cuenta</p>
          <h3 class="mt-2 text-lg font-semibold text-gray-900">Credenciales de acceso</h3>
          <p class="text-sm text-gray-500">Nombre visible y credenciales iniciales para el inicio de sesión.</p>
        </div>
        <div class="mt-4 form-grid form-grid--2">
          <div>
            <label for="name" class="form-label">Nombre</label>
            <input class="form-input" id="name" name="name" value="{{ old('name') }}" required>
            @error('name')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
          </div>
          <div>
            <label for="email" class="form-label">Correo electrónico</label>
            <input class="form-input" id="email" name="email" type="email" value="{{ old('email') }}" required>
            @error('email')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
          </div>
          <div>
            <label for="password" class="form-label">Contraseña</label>
            <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2">
              <input class="flex-1 bg-transparent text-sm" name="password" id="password" type="password" autocomplete="new-password" minlength="8" required>
            </div>
            @error('password')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
          </div>
          <div>
            <label for="password_confirmation" class="form-label">Confirmación</label>
            <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2">
              <input class="flex-1 bg-transparent text-sm" name="password_confirmation" id="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
            </div>
            @error('password_confirmation')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
          </div>
        </div>
      </section>

      <!-- Datos básicos -->
      <section class="card p-6">
        <div>
          <p class="text-xs uppercase tracking-widest text-gray-500">Información personal y contacto</p>
          <h3 class="mt-2 text-lg font-semibold text-gray-900">Datos básicos</h3>
          <p class="text-sm text-gray-500">Teléfono, documento y datos demográficos para el expediente.</p>
        </div>
        <div class="mt-4 form-grid form-grid--2">
          <div>
            <label for="telefono" class="form-label">Teléfono</label>
            <input class="form-input" id="telefono" name="telefono" value="{{ old('telefono') }}" inputmode="numeric" pattern="\d{10}" minlength="10" maxlength="10" required>
            @error('telefono')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
          </div>
          <div>
            <label for="dni" class="form-label">Cédula</label>
            <input class="form-input" id="dni" name="dni" value="{{ old('dni') }}" inputmode="numeric" pattern="\d{10}" minlength="10" maxlength="10" required>
            @error('dni')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
          </div>
          <div class="col-span-full">
            <label for="direccion" class="form-label">Dirección</label>
            <input class="form-input" id="direccion" name="direccion" value="{{ old('direccion') }}" required>
            @error('direccion')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
          </div>
          <div>
            <label for="fecha_nacimiento" class="form-label">Fecha de nacimiento</label>
            <input class="form-input" id="fecha_nacimiento" name="fecha_nacimiento" type="date" value="{{ old('fecha_nacimiento') }}" required>
          </div>
          <div>
            <label for="sexo" class="form-label">Sexo</label>
            <select class="form-select" id="sexo" name="sexo" required>
              @foreach(['Masculino','Femenino','Otro'] as $sx)
                <option value="{{ $sx }}" @selected(old('sexo')===$sx)>{{ $sx }}</option>
              @endforeach
            </select>
            @error('sexo')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
          </div>
        </div>
      </section>

      <!-- Prioridad del paciente -->
      <section class="card p-6" id="patient-flags-section">
        <div>
          <p class="text-xs uppercase tracking-widest text-gray-500">Prioridad del paciente</p>
          <h3 class="mt-2 text-lg font-semibold text-gray-900">Indicadores clínicos</h3>
          <p class="text-sm text-gray-500">Solo aplica a pacientes. Marca condiciones relevantes.</p>
        </div>
        <div class="mt-4 form-grid form-grid--2">
          <label class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2 text-sm">
            <input type="checkbox" name="adulto_mayor" value="1">
            <span>Adulto mayor</span>
          </label>
          <label class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2 text-sm">
            <input type="checkbox" name="embarazo" value="1">
            <span>Embarazo</span>
          </label>
          <label class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2 text-sm">
            <input type="checkbox" name="discapacidad" value="1">
            <span>Discapacidad</span>
          </label>
          <label class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2 text-sm">
            <input type="checkbox" name="cronico" value="1">
            <span>Crónico</span>
          </label>
        </div>
      </section>

      <!-- Rol y seguridad -->
      <section class="card p-6">
        <div>
          <p class="text-xs uppercase tracking-widest text-gray-500">Rol y seguridad</p>
          <h3 class="mt-2 text-lg font-semibold text-gray-900">Permisos y especialidad</h3>
          <p class="text-sm text-gray-500">Selecciona rol y define especialidad y tarifa si aplica.</p>
        </div>
        <div class="mt-4 form-grid form-grid--2">
          <div>
            <label for="role_id" class="form-label">Rol</label>
            <select class="form-select" name="role_id" id="role_id" required>
              @foreach($roles as $r)
                <option value="{{ $r->id }}" data-name="{{ $r->name }}" @selected((string)$selectedRoleId === (string)$r->id)>
                  {{ ucfirst($r->name) }}
                </option>
              @endforeach
            </select>
          </div>

          <div id="doctor-only-esp" style="display:none">
            <label for="especialidad" class="form-label">Especialidad (solo doctor)</label>
            <select class="form-select" id="especialidad" name="especialidad">
              <option value="">Seleccione</option>
              @foreach($especialidades as $e)
                <option value="{{ $e->nombre }}">{{ $e->nombre }}</option>
              @endforeach
            </select>
          </div>

          <div id="doctor-only-precio" style="display:none">
            <label for="precio_consulta" class="form-label">Precio de consulta (USD)</label>
            <input class="form-input" id="precio_consulta" name="precio_consulta" type="number" step="0.01" min="0" value="40.00">
          </div>
        </div>
      </section>
    </div>

    <div class="flex flex-wrap items-center justify-end gap-3">
      <button class="btn btn-primary" type="submit" aria-label="Registrar usuario">
        <i class="ri-save-line"></i> Registrar
      </button>
      <button class="btn btn-ghost" type="reset">
        <i class="ri-refresh-line"></i> Limpiar
      </button>
    </div>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('role_id');
    const patientFlags = document.getElementById('patient-flags-section');
    const doctorEsp = document.getElementById('doctor-only-esp');
    const doctorPrecio = document.getElementById('doctor-only-precio');

    function toggleFields() {
        const selectedOpt = roleSelect.options[roleSelect.selectedIndex];
        const roleName = selectedOpt ? selectedOpt.getAttribute('data-name') : '';
        
        if (roleName === 'paciente') {
            patientFlags.style.display = 'block';
            doctorEsp.style.display = 'none';
            doctorPrecio.style.display = 'none';
        } else if (roleName === 'doctor') {
            patientFlags.style.display = 'none';
            doctorEsp.style.display = 'block';
            doctorPrecio.style.display = 'block';
        } else {
            patientFlags.style.display = 'none';
            doctorEsp.style.display = 'none';
            doctorPrecio.style.display = 'none';
        }
    }

    roleSelect.addEventListener('change', toggleFields);
    toggleFields();
});
</script>
@endsection
