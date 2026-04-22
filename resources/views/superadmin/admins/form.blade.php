@php($admin = $admin ?? new \App\Models\User)
@php($isEditing = $admin->exists)

<div class="space-y-6">
  <section class="card p-6">
    <div>
      <p class="text-xs uppercase tracking-widest text-slate-500">Cuenta</p>
      <h3 class="mt-2 text-lg font-semibold text-slate-900">Datos de acceso</h3>
      <p class="text-sm text-slate-500">Credenciales del administrador.</p>
    </div>
    <div class="mt-4 grid gap-4 md:grid-cols-2">
      <div>
        <label for="name" class="form-label">Nombre</label>
        <input class="form-input" id="name" name="name" value="{{ old('name', $admin->name) }}" required>
        @error('name')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
      </div>
      <div>
        <label for="email" class="form-label">Correo electrónico</label>
        <input class="form-input" id="email" name="email" type="email" value="{{ old('email', $admin->email) }}" required>
        @error('email')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
      </div>

      <div>
        <label for="password" class="form-label">Contraseña</label>
        <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2" data-password-wrap>
          <input class="flex-1 bg-transparent text-sm" id="password" name="password" type="password" autocomplete="new-password" minlength="8" @required(! $isEditing)>
          <button type="button" class="btn-eye" data-target="#password" aria-label="Mostrar u ocultar"><i class="ri-eye-line"></i></button>
        </div>
        @if($isEditing)
          <p class="mt-1 text-xs text-slate-500">Déjala vacía si no vas a cambiar la contraseña.</p>
        @endif
        @error('password')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
      </div>
      <div>
        <label for="password_confirmation" class="form-label">Confirmación</label>
        <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2" data-password-wrap>
          <input class="flex-1 bg-transparent text-sm" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" @required(! $isEditing)>
          <button type="button" class="btn-eye" data-target="#password_confirmation" aria-label="Mostrar u ocultar"><i class="ri-eye-line"></i></button>
        </div>
        @error('password_confirmation')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
      </div>
    </div>
  </section>

  <section class="card p-6">
    <div>
      <p class="text-xs uppercase tracking-widest text-slate-500">Contacto</p>
      <h3 class="mt-2 text-lg font-semibold text-slate-900">Datos personales</h3>
      <p class="text-sm text-slate-500">Información básica del administrador.</p>
    </div>
    <div class="mt-4 grid gap-4 md:grid-cols-2">
      <div>
        <label for="telefono" class="form-label">Teléfono</label>
        <input class="form-input" id="telefono" name="telefono" value="{{ old('telefono', $admin->telefono) }}" inputmode="numeric" pattern="\d{10}" minlength="10" maxlength="10" data-digits="10" required>
        @error('telefono')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
      </div>
      <div>
        <label for="dni" class="form-label">Cédula</label>
        <input class="form-input" id="dni" name="dni" value="{{ old('dni', $admin->dni) }}" inputmode="numeric" pattern="\d{10}" minlength="10" maxlength="10" data-digits="10" required>
        @error('dni')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
      </div>
      <div class="md:col-span-2">
        <label for="direccion" class="form-label">Dirección</label>
        <input class="form-input" id="direccion" name="direccion" value="{{ old('direccion', $admin->direccion) }}" required>
        @error('direccion')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
      </div>
      <x-ui.date-parts
        field="fecha_nacimiento"
        label="Fecha de nacimiento"
        :value="optional($admin->fecha_nacimiento)->format('Y-m-d')"
        required
        help="Usa día, mes y año para completar la fecha más rápido."
      />
      <div>
        <label for="sexo" class="form-label">Sexo</label>
        <select class="form-select" id="sexo" name="sexo" required>
          <option value="">Seleccione</option>
          @foreach(['Masculino','Femenino','Otro'] as $sx)
            <option value="{{ $sx }}" @selected(old('sexo', $admin->sexo) === $sx)>{{ $sx }}</option>
          @endforeach
        </select>
        @error('sexo')<small class="text-xs text-rose-600">{{ $message }}</small>@enderror
      </div>
    </div>
  </section>
</div>
