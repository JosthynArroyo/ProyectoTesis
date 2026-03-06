@extends('layouts.admin')
@section('title','Perfil del administrador')
@section('header-title','Perfil')
@section('header-subtitle','Actualiza tu informaciÃ³n y seguridad')

@section('main')
  <div class="space-y-6">
    <section class="card p-6">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p class="text-xs uppercase tracking-widest text-slate-500">Cuenta del administrador</p>
          <h1 class="mt-2 text-2xl font-semibold text-slate-900">Perfil del administrador</h1>
          <p class="text-slate-600">Actualiza tus datos personales y gestiona la seguridad.</p>
        </div>
        <span class="badge info"><i class="ri-shield-check-line"></i> Cuenta protegida</span>
      </div>
    </section>

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
            <button type="button" id="changePhoto" class="absolute inset-x-2 bottom-2 rounded-full bg-slate-900/70 px-3 py-1 text-xs font-semibold text-white">Cambiar foto</button>
          </div>
          <h2 class="mt-4 text-lg font-semibold text-slate-900">{{ $user->name }}</h2>
          <p class="text-sm text-slate-500">{{ $user->email }}</p>
          <span class="mt-3 badge info">Rol: Administrador</span>
        </div>

        <div class="mt-6 space-y-3 text-sm text-slate-600">
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
          <p class="text-xs uppercase tracking-widest text-slate-500">InformaciÃ³n y seguridad</p>
          <h3 class="mt-2 text-lg font-semibold text-slate-900">Datos del administrador</h3>
          <p class="text-sm text-slate-500">Los cambios se aplican inmediatamente despuÃ©s de guardar.</p>
        </div>

        @if(session('success'))
          <x-ui.alert tone="success" class="mt-4">{{ session('success') }}</x-ui.alert>
        @endif

        <form method="POST" action="{{ route('admin.perfil.update') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
          @csrf
          <input id="avatarInput" type="file" name="avatar" accept="image/png,image/jpeg,image/jpg,image/webp,image/svg+xml" hidden>

          <section>
            <h4 class="text-sm font-semibold text-slate-700">Datos principales</h4>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
              <div>
                <label class="form-label">Nombre completo</label>
                <input class="form-input" type="text" name="name" value="{{ old('name', $user->name) }}" required>
                @error('name')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
              <div>
                <label class="form-label">Correo electrÃ³nico</label>
                <input class="form-input" type="email" name="email" value="{{ old('email', $user->email) }}" required>
                @error('email')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
              <div>
                <label class="form-label">TelÃ©fono</label>
                <input class="form-input" type="tel" name="telefono" value="{{ old('telefono', $user->telefono) }}" inputmode="numeric" pattern="\d{10}" minlength="10" maxlength="10" data-digits="10" placeholder="0998740927" required>
                <div class="text-xs text-slate-500">Formato: 10 dÃ­gitos.</div>
                @error('telefono')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
              <div>
                <label class="form-label">CÃ©dula</label>
                <input class="form-input" type="text" name="dni" value="{{ old('dni', $user->dni) }}" inputmode="numeric" pattern="\d{10}" minlength="10" maxlength="10" data-digits="10" placeholder="1723456789" required>
                <div class="text-xs text-slate-500">Exactamente 10 dÃ­gitos.</div>
                @error('dni')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
            </div>
          </section>

          <section>
            <h4 class="text-sm font-semibold text-slate-700">InformaciÃ³n adicional</h4>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
              <div>
                <label class="form-label">DirecciÃ³n</label>
                <input class="form-input" type="text" name="direccion" value="{{ old('direccion', $user->direccion) }}" required>
                @error('direccion')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
              <div>
                <label class="form-label">Fecha de nacimiento</label>
                <input class="form-input" type="date" name="fecha_nacimiento" value="{{ old('fecha_nacimiento', optional($user->fecha_nacimiento)->toDateString()) }}" required>
                @error('fecha_nacimiento')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
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
              <h4 class="text-sm font-semibold text-slate-700">Seguridad</h4>
              <span class="text-xs text-slate-500">Protege tu cuenta con contraseÃ±a y rostro.</span>
            </div>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
              <div>
                <label class="form-label">ContraseÃ±a actual</label>
                <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
                  <input class="flex-1 bg-transparent text-sm" type="password" name="current_password" id="current_password" autocomplete="current-password" placeholder="â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢">
                  <button type="button" class="btn-eye" data-target="#current_password" aria-label="Mostrar u ocultar"><i class="ri-eye-line"></i></button>
                </div>
                @error('current_password')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
              <div>
                <label class="form-label">Nueva contraseÃ±a</label>
                <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
                  <input class="flex-1 bg-transparent text-sm" type="password" name="password" id="password" autocomplete="new-password" minlength="8" placeholder="MÃ­n. 8 caracteres">
                  <button type="button" class="btn-eye" data-target="#password" aria-label="Mostrar u ocultar"><i class="ri-eye-line"></i></button>
                </div>
                <div class="text-xs text-slate-500">MÃ­nimo 8 caracteres e incluye letras, nÃºmeros y un carÃ¡cter especial.</div>
                @error('password')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
              <div>
                <label class="form-label">Confirmar nueva contraseÃ±a</label>
                <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
                  <input class="flex-1 bg-transparent text-sm" type="password" name="password_confirmation" id="password_confirmation" autocomplete="new-password" minlength="8" placeholder="Repite la contraseÃ±a">
                  <button type="button" class="btn-eye" data-target="#password_confirmation" aria-label="Mostrar u ocultar"><i class="ri-eye-line"></i></button>
                </div>
                @error('password_confirmation')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
            </div>
          </section>

          <section id="perfil-face">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div>
                <h4 class="text-sm font-semibold text-slate-700">Reconocimiento facial</h4>
                <p class="text-xs text-slate-500">Registro facial para inicio de sesiÃ³n.</p>
              </div>
            </div>
            <div class="mt-4 grid gap-4 md:grid-cols-[1.2fr_0.8fr]">
              <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-slate-900">
                <video id="faceEnrollVideo" autoplay muted playsinline class="h-56 w-full object-cover"></video>
                <div id="faceEnrollOverlay" class="absolute inset-0 flex items-center justify-center bg-slate-900/60 text-sm text-white">
                  {{ $user->faceProfile ? 'Rostro registrado.' : 'CÃ¡mara lista para captura.' }}
                </div>
              </div>
              <div class="space-y-3">
                <button
                  type="button"
                  class="btn btn-primary w-full"
                  id="faceEnrollButton"
                  data-enroll-url="{{ route('face.enroll') }}"
                  data-csrf="{{ csrf_token() }}"
                  data-models-url="{{ asset('models') }}"
                  data-has-face="{{ $user->faceProfile ? '1' : '0' }}"
                  data-saved-status="{{ $user->faceProfile ? 'Rostro registrado.' : '' }}"
                  data-return-url="{{ route('admin.perfil.edit') }}"
                  data-return-anchor="perfil-face"
                >
                  {{ $user->faceProfile ? 'Actualizar rostro' : 'Guardar rostro' }}
                </button>
                <div id="faceEnrollProgress" class="h-2 overflow-hidden rounded-full bg-slate-200" aria-hidden="true">
                  <span class="face-enroll-progress__bar block h-2 w-0 bg-teal-600"></span>
                </div>
                <p id="faceEnrollStatus" class="text-xs text-slate-500">
                  @if(request('face') === 'ok')
                    Rostro registrado.
                  @elseif(request('face') === 'error')
                    No se pudo completar la captura, intenta de nuevo.
                  @endif
                </p>
              </div>
            </div>
          </section>

          <x-ui.form-actions>
            <x-slot:left>
              <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost">Volver</a>
            </x-slot>
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
          </x-ui.form-actions>
        </form>
      </section>
    </div>
  </div>
@endsection

@push('scripts')
  @vite(['resources/js/admin/perfil.js'])
@endpush

