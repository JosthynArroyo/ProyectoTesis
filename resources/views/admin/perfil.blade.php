@extends('layouts.admin')
@section('title','Perfil del administrador')
@section('header-title','Perfil')
@section('header-subtitle','Actualiza tu información y seguridad')

@section('main')
  <div class="space-y-6">
    <div class="grid gap-6 lg:grid-cols-[0.7fr_1.3fr]">
      <aside class="card p-6">
        <div class="avatar flex flex-col items-center text-center">
          @php
            $imageUrlService = $imageUrl ?? app(\App\Support\ImageUrl::class);
            $avatarImage = $imageUrlService->variants($user->avatar, 'users', 'user');
          @endphp
          <div class="relative">
            <img
              id="avatarPreview"
              src="{{ $avatarImage['thumb'] }}"
              @if($avatarImage['srcset']) srcset="{{ $avatarImage['srcset'] }}" sizes="128px" @endif
              alt="Avatar"
              class="h-32 w-32 rounded-3xl object-cover"
              loading="eager"
              decoding="async"
            >
            <button type="button" id="changePhoto" class="btn btn-primary btn-sm absolute inset-x-2 bottom-2">Cambiar foto</button>
          </div>
          <h2 class="mt-4 text-lg font-semibold text-gray-900">{{ $user->name }}</h2>
          <p class="text-sm text-gray-500">{{ $user->email }}</p>
          <span class="mt-3 badge info">Rol: Administrador</span>
        </div>

        <div class="mt-6 space-y-3 text-sm text-gray-600">
          @if($user->telefono)
            <div class="flex items-center gap-2"><i class="ri-phone-line"></i> {{ $user->telefono }}</div>
          @endif
          @if($user->direccion)
            <div class="flex items-center gap-2"><i class="ri-map-pin-line"></i> {{ $user->direccion }}</div>
          @endif
        </div>
      </aside>

      <section class="card p-6">
        <div>
          <p class="text-xs uppercase tracking-widest text-gray-500">Información y seguridad</p>
          <h3 class="mt-2 text-lg font-semibold text-gray-900">Datos del administrador</h3>
          <p class="text-sm text-gray-500">Los cambios se aplican inmediatamente después de guardar.</p>
        </div>

        @if(session('success'))
          <x-ui.alert tone="success" class="mt-4">{{ session('success') }}</x-ui.alert>
        @endif

        <form method="POST" action="{{ route('admin.perfil.update') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
          @csrf
          <input id="avatarInput" type="file" name="avatar" accept="image/png,image/jpeg,image/jpg,image/webp,image/svg+xml" hidden>

          <section>
            <h4 class="text-sm font-semibold text-gray-700">Datos principales</h4>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
              <div>
                <label class="form-label">Nombre completo</label>
                <input class="form-input" type="text" name="name" value="{{ old('name', $user->name) }}" required>
                @error('name')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
              <div>
                <label class="form-label">Correo electrónico</label>
                <input class="form-input" type="email" name="email" value="{{ old('email', $user->email) }}" required>
                @error('email')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
              <div>
                <label class="form-label">Teléfono</label>
                <input class="form-input" type="tel" name="telefono" value="{{ old('telefono', $user->telefono) }}" inputmode="numeric" pattern="\d{10}" minlength="10" maxlength="10" data-digits="10" placeholder="0991234567" required>
                <div class="text-xs text-gray-500">Formato: 10 dígitos.</div>
                @error('telefono')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
              <div>
                <label class="form-label">Cédula</label>
                <input class="form-input" type="text" name="dni" value="{{ old('dni', $user->dni) }}" inputmode="numeric" pattern="\d{10}" minlength="10" maxlength="10" data-digits="10" placeholder="1723456789" required>
                <div class="text-xs text-gray-500">Exactamente 10 dígitos.</div>
                @error('dni')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
            </div>
          </section>

          <section>
            <h4 class="text-sm font-semibold text-gray-700">Información adicional</h4>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
              <div>
                <label class="form-label">Dirección</label>
                <input class="form-input" type="text" name="direccion" value="{{ old('direccion', $user->direccion) }}" required>
                @error('direccion')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
              <x-ui.date-parts
                field="fecha_nacimiento"
                label="Fecha de nacimiento"
                :value="optional($user->fecha_nacimiento)->toDateString()"
                required
                help="Completa la fecha con día, mes y año."
              />
              <div>
                <label class="form-label">Sexo</label>
                <select class="form-select" name="sexo" required>
                  <option value="">Seleccionar</option>
                  <option value="Masculino" {{ old('sexo', $user->sexo) === 'Masculino' ? 'selected' : '' }}>Masculino</option>
                  <option value="Femenino"  {{ old('sexo', $user->sexo) === 'Femenino' ? 'selected' : '' }}>Femenino</option>
                  <option value="Otro"      {{ old('sexo', $user->sexo) === 'Otro' ? 'selected' : '' }}>Otro</option>
                </select>
                @error('sexo')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
            </div>
          </section>

          <section>
            <div class="flex flex-wrap items-center justify-between gap-3">
              <h4 class="text-sm font-semibold text-gray-700">Seguridad</h4>
              <span class="text-xs text-gray-500">Protege tu cuenta con contraseña y rostro.</span>
            </div>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
              <div>
                <label class="form-label">Contraseña actual</label>
                <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2">
                  <input class="flex-1 bg-transparent text-sm" type="password" name="current_password" id="current_password" autocomplete="current-password" placeholder="••••••••">
                  <button type="button" class="btn-eye" data-target="#current_password" aria-label="Mostrar u ocultar"><i class="ri-eye-line"></i></button>
                </div>
                @error('current_password')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
              <div>
                <label class="form-label">Nueva contraseña</label>
                <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2">
                  <input class="flex-1 bg-transparent text-sm" type="password" name="password" id="password" autocomplete="new-password" minlength="8" placeholder="Mín. 8 caracteres">
                  <button type="button" class="btn-eye" data-target="#password" aria-label="Mostrar u ocultar"><i class="ri-eye-line"></i></button>
                </div>
                <div class="text-xs text-gray-500">Mínimo 8 caracteres e incluye letras, números y un carácter especial.</div>
                @error('password')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
              <div>
                <label class="form-label">Confirmar nueva contraseña</label>
                <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2">
                  <input class="flex-1 bg-transparent text-sm" type="password" name="password_confirmation" id="password_confirmation" autocomplete="new-password" minlength="8" placeholder="Repite la contraseña">
                  <button type="button" class="btn-eye" data-target="#password_confirmation" aria-label="Mostrar u ocultar"><i class="ri-eye-line"></i></button>
                </div>
                @error('password_confirmation')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
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

@push('scripts')
  @vite(['resources/js/shared/profile-avatar.js'])
@endpush
