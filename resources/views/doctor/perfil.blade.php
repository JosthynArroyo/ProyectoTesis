{{-- resources/views/doctor/perfil.blade.php --}}
@extends('layouts.doctor')
@section('title', 'Perfil del doctor')
@section('activeSidebar', 'perfil')
@section('body-class', 'profile-body')
@section('header-title','Mi perfil')
@section('header-subtitle','Actualiza tus datos y seguridad')

@section('main')
  <div class="space-y-6">
    <div class="grid gap-6 lg:grid-cols-[0.7fr_1.3fr]">
      <aside class="card p-6">
        <div class="profile-avatar flex flex-col items-center text-center">
          @php
            $imageUrlService = $imageUrl ?? app(\App\Support\ImageUrl::class);
            $avatarImage = $imageUrlService->variants($user->avatar, 'doctors', 'doctor');
          @endphp
          <div class="relative">
            <img
              id="avatarPreview"
              src="{{ $avatarImage['thumb'] }}"
              @if($avatarImage['srcset']) srcset="{{ $avatarImage['srcset'] }}" sizes="128px" @endif
              alt="Avatar"
              class="doctor-avatar-photo h-32 w-32 rounded-lg border border-slate-200"
              loading="eager"
              decoding="async"
            >
            <button type="button" class="btn btn-primary btn-sm absolute inset-x-2 bottom-2" id="changePhoto">Cambiar foto</button>
          </div>
          <h2 class="mt-4 text-lg font-semibold text-slate-900">{{ $user->name }}</h2>
          <span class="text-sm text-slate-500">{{ $user->email }}</span>
        </div>
        @php $especialidades = collect($user->especialidades ?? [])->pluck('nombre')->all(); @endphp
        @if(count($especialidades))
          <div class="mt-4 flex flex-wrap gap-2">
            @foreach($especialidades as $esp)
              <span class="badge neutral">{{ $esp }}</span>
            @endforeach
          </div>
        @endif
        <div class="mt-6 space-y-2 text-sm text-slate-600">
          @if($user->telefono)
            <div class="flex items-center gap-2"><i class="ri-phone-line"></i>{{ $user->telefono }}</div>
          @endif
          @if($user->direccion)
            <div class="flex items-center gap-2"><i class="ri-map-pin-line"></i>{{ $user->direccion }}</div>
          @endif
          @if(!is_null($user->precio_consulta))
            <div class="flex items-center gap-2"><i class="ri-money-dollar-circle-line"></i>Precio: ${{ number_format($user->precio_consulta,2) }} USD</div>
          @endif
        </div>
      </aside>

      <section class="card p-6">
        <div>
          <h3 class="text-lg font-semibold text-slate-900">Datos personales y profesionales</h3>
          <span class="text-sm text-slate-500">Todos los campos pueden editarse en cualquier momento.</span>
        </div>

        @if(session('success'))
          <x-ui.alert tone="success" class="mt-4">{{ session('success') }}</x-ui.alert>
        @endif

        <form method="POST" action="{{ route('doctor.perfil.update') }}" enctype="multipart/form-data" id="perfilForm" class="mt-6 space-y-6">
          @csrf
          <input id="avatarInput" class="hidden" type="file" name="avatar" accept="image/png,image/jpeg,image/jpg,image/webp,image/svg+xml">

          <section>
            <h4 class="text-sm font-semibold text-slate-700">Identidad</h4>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
              <div>
                <label class="form-label">Nombre</label>
                <input class="form-input" type="text" name="name" value="{{ old('name', $user->name) }}" required>
                @error('name')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
              <div>
                <label class="form-label">Correo</label>
                <input class="form-input" type="email" name="email" value="{{ old('email', $user->email) }}" required>
                @error('email')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
              <div>
                <label class="form-label">Teléfono</label>
                <input class="form-input" type="tel" name="telefono" value="{{ old('telefono', $user->telefono) }}"
                       inputmode="numeric" pattern="\d{10}" minlength="10" maxlength="10" data-digits="10"
                       placeholder="0998740927" title="Debe contener exactamente 10 dígitos" required>
                <div class="text-xs text-slate-500">Formato: 10 dígitos.</div>
                @error('telefono')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
              <div>
                <label class="form-label">Cédula</label>
                <input class="form-input" type="text" name="dni" value="{{ old('dni', $user->dni) }}"
                       inputmode="numeric" pattern="\d{10}" minlength="10" maxlength="10" data-digits="10"
                       placeholder="1723456789" title="Debe contener exactamente 10 dígitos" required>
                <div class="text-xs text-slate-500">Exactamente 10 dígitos.</div>
                @error('dni')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
            </div>
          </section>

          <section>
            <h4 class="text-sm font-semibold text-slate-700">Información adicional</h4>
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
                help="Completa la fecha manualmente para evitar navegación innecesaria."
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
              <div>
                <label class="form-label">Precio de consulta (USD)</label>
                <input class="form-input" type="number" name="precio_consulta" step="0.01" min="0"
                       value="{{ old('precio_consulta', $user->precio_consulta) }}"
                       placeholder="Ej: 25.00" required>
                <div class="text-xs text-slate-500">Moneda fija: USD.</div>
                @error('precio_consulta')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
              <input type="hidden" name="moneda" value="USD">
            </div>
          </section>

          <section>
            <h4 class="text-sm font-semibold text-slate-700">Seguridad</h4>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
              <div>
                <label class="form-label">Contraseña actual</label>
                <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
                  <input class="flex-1 bg-transparent text-sm" type="password" name="current_password" id="current_password" autocomplete="current-password" placeholder="••••••••">
                  <button type="button" class="btn-eye" data-target="#current_password" aria-label="Mostrar u ocultar">
                    <i class="ri-eye-line"></i>
                  </button>
                </div>
                <div class="text-xs text-slate-500">Necesaria para confirmar el cambio.</div>
                @error('current_password')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>

              <div>
                <label class="form-label">Nueva contraseña</label>
                <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
                  <input class="flex-1 bg-transparent text-sm" type="password" name="password" id="password" autocomplete="new-password" minlength="8" placeholder="Mín. 8 caracteres">
                  <button type="button" class="btn-eye" data-target="#password" aria-label="Mostrar u ocultar">
                    <i class="ri-eye-line"></i>
                  </button>
                </div>
                <div class="text-xs text-slate-500">Mínimo 8 caracteres. Debe ser distinta a la actual.</div>
                @error('password')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>

              <div>
                <label class="form-label">Confirmar nueva contraseña</label>
                <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
                  <input class="flex-1 bg-transparent text-sm" type="password" name="password_confirmation" id="password_confirmation" autocomplete="new-password" minlength="8" placeholder="Repite la contraseña">
                  <button type="button" class="btn-eye" data-target="#password_confirmation" aria-label="Mostrar u ocultar">
                    <i class="ri-eye-line"></i>
                  </button>
                </div>
                @error('password_confirmation')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
            </div>
          </section>

          <section id="perfil-face">
            <h4 class="text-sm font-semibold text-slate-700">Reconocimiento facial</h4>
            <p class="text-xs text-slate-500">Registro facial para inicio de sesión. Se requiere acceso a la cámara.</p>
            <div class="mt-4 grid gap-4 md:grid-cols-[1.2fr_0.8fr]">
              <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-slate-900">
                <video id="faceEnrollVideo" autoplay muted playsinline class="h-56 w-full object-cover"></video>
                <div id="faceEnrollOverlay" class="absolute inset-0 flex items-center justify-center bg-slate-900/60 text-sm text-white">
                  {{ $user->faceProfile ? 'Rostro registrado.' : 'Cámara lista para captura.' }}
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
                  data-return-url="{{ route('doctor.perfil.edit') }}"
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
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
          </x-ui.form-actions>
        </form>
      </section>
    </div>
  </div>
@endsection

@push('scripts')
  @vite('resources/js/doctor/perfil.js')
@endpush

