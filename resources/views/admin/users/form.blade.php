@php($u = $user ?? new \App\Models\User)
@php($presetRole = in_array(request('role'), ['administrador','superadmin'], true) ? null : request('role'))
@php($selectedRoleId = old('role_id', optional($u->roles->first())->id ?? optional($roles->firstWhere('name', $presetRole))->id))
@csrf

<div class="space-y-6">
  <section class="card p-6">
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
        <p class="mt-1 text-xs text-gray-500" data-email-availability aria-live="polite"></p>
        @error('email')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
      </div>

      @if(!$user)
      <div>
        <label for="password" class="form-label">Contraseña</label>
        <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2" data-password-wrap>
          <input class="flex-1 bg-transparent text-sm" name="password" id="password" type="password" autocomplete="new-password" minlength="8" required>
          <button type="button" class="btn-eye" data-target="#password" aria-label="Mostrar u ocultar"><i class="ri-eye-line"></i></button>
        </div>
        @error('password')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
      </div>
      <div>
        <label for="password_confirmation" class="form-label">Confirmación</label>
        <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2" data-password-wrap>
          <input class="flex-1 bg-transparent text-sm" name="password_confirmation" id="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
          <button type="button" class="btn-eye" data-target="#password_confirmation" aria-label="Mostrar u ocultar"><i class="ri-eye-line"></i></button>
        </div>
        @error('password_confirmation')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
      </div>
      @endif
    </div>
  </section>

  <section class="card p-6">
    <div>
      <p class="text-xs uppercase tracking-widest text-gray-500">Información personal y contacto</p>
      <h3 class="mt-2 text-lg font-semibold text-gray-900">Datos básicos</h3>
      <p class="text-sm text-gray-500">Teléfono, documento y datos demográficos para el expediente.</p>
    </div>
    <div class="mt-4 form-grid form-grid--2">
      <div>
        <label for="telefono" class="form-label">Teléfono</label>
        <input class="form-input" id="telefono" name="telefono" value="{{ old('telefono', $u->telefono) }}" inputmode="numeric" pattern="\d{10}" minlength="10" maxlength="10" data-digits="10" required>
        @error('telefono')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
      </div>
      <x-ui.document-fields :model="$u" />
      <div class="col-span-full">
        <label for="direccion" class="form-label">Dirección</label>
        <input class="form-input" id="direccion" name="direccion" value="{{ old('direccion', $u->direccion) }}" required>
        @error('direccion')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
      </div>
      <x-ui.date-parts
        field="fecha_nacimiento"
        label="Fecha de nacimiento"
        :value="optional($u->fecha_nacimiento)->format('Y-m-d')"
        required
        help="Ingresa día, mes y año sin abrir un calendario."
      />
      <div>
        <label for="sexo" class="form-label">Sexo</label>
        <select class="form-select" id="sexo" name="sexo" required>
          @foreach(['Masculino','Femenino','Otro'] as $sx)
            <option value="{{ $sx }}" @selected(old('sexo', $u->sexo)===$sx)>{{ $sx }}</option>
          @endforeach
        </select>
        @error('sexo')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
      </div>
    </div>
  </section>

  <section class="card p-6 hidden" id="patient-flags-section">
    <div>
      <p class="text-xs uppercase tracking-widest text-gray-500">Prioridad del paciente</p>
      <h3 class="mt-2 text-lg font-semibold text-gray-900">Indicadores clínicos</h3>
      <p class="text-sm text-gray-500">Solo aplica a pacientes. Marca condiciones relevantes.</p>
    </div>
    <div class="mt-4 form-grid form-grid--2">
      <label class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2 text-sm">
        <input type="hidden" name="adulto_mayor" value="0">
        <input type="checkbox" name="adulto_mayor" value="1" @checked(old('adulto_mayor', optional($u->patientFlag)->adulto_mayor))>
        <span>Adulto mayor</span>
      </label>
      <label class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2 text-sm">
        <input type="hidden" name="embarazo" value="0">
        <input type="checkbox" name="embarazo" value="1" @checked(old('embarazo', optional($u->patientFlag)->embarazo))>
        <span>Embarazo</span>
      </label>
      <label class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2 text-sm">
        <input type="hidden" name="discapacidad" value="0">
        <input type="checkbox" name="discapacidad" value="1" @checked(old('discapacidad', optional($u->patientFlag)->discapacidad))>
        <span>Discapacidad</span>
      </label>
      <label class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2 text-sm">
        <input type="hidden" name="cronico" value="0">
        <input type="checkbox" name="cronico" value="1" @checked(old('cronico', optional($u->patientFlag)->cronico))>
        <span>Crónico</span>
      </label>
      @error('adulto_mayor')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
      @error('embarazo')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
      @error('discapacidad')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
      @error('cronico')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
    </div>
  </section>

  <section class="card p-6">
    <div>
      <p class="text-xs uppercase tracking-widest text-gray-500">Rol y seguridad</p>
      <h3 class="mt-2 text-lg font-semibold text-gray-900">Permisos y especialidad</h3>
      <p class="text-sm text-gray-500">Selecciona rol y define especialidad y tarifa si aplica.</p>
    </div>
    <div class="mt-4 form-grid form-grid--2">
      <div>
        <label for="role_id" class="form-label">Rol</label>
        <select class="form-select" name="role_id" id="role_id" required data-preset-role="{{ strtolower((string)$presetRole) }}" {{ $presetRole ? 'disabled' : '' }}>
          @foreach($roles as $r)
            <option value="{{ $r->id }}" @selected((string)$selectedRoleId === (string)$r->id)>
              {{ ucfirst($r->name) }}
            </option>
          @endforeach
        </select>
        @if($presetRole)
          <input type="hidden" name="role_id" value="{{ optional($roles->firstWhere('name',$presetRole))->id }}">
        @endif
        @error('role_id')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
      </div>

      <div id="doctor-only-esp" class="hidden">
        <label for="especialidad_id" class="form-label">Especialidad (solo doctor)</label>
        <select class="form-select" id="especialidad_id" name="especialidad_id">
          <option value="">Seleccione</option>
          @foreach(($especialidades ?? []) as $e)
            <option value="{{ $e->id }}" @selected(old('especialidad_id', optional($u->especialidades->first())->id) == $e->id)>{{ $e->nombre }}</option>
          @endforeach
        </select>
        @error('especialidad_id')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
      </div>

      <div id="doctor-only-precio" class="hidden">
        <label for="precio_consulta" class="form-label">Precio de consulta (USD)</label>
        <input class="form-input" id="precio_consulta" name="precio_consulta" type="number" step="0.01" min="0" value="{{ old('precio_consulta', $u->precio_consulta) }}" required>
        @error('precio_consulta')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
      </div>
    </div>
  </section>
</div>

@push('scripts')
  @vite('resources/js/admin/users/form.js')
@endpush
