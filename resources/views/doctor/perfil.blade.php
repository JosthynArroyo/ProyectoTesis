{{-- resources/views/doctor/perfil.blade.php --}}
@extends('layouts.doctor')
@section('title', 'Perfil del Doctor')
@section('activeSidebar', 'perfil')
@section('body-class', 'profile-body')

@push('head')
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
  @vite('resources/css/doctor/perfil.css')
@endpush

@section('content')
  <div class="profile-wrapper">
    <section class="profile-hero">
      <div>
        <h1>Perfil del doctor</h1>
        <p>Actualiza tu información profesional y de contacto para mantener informados a tus pacientes.</p>
      </div>
      <span class="badge"><span class="material-symbols-outlined">workspace_premium</span>Especialista activo</span>
    </section>

    <div class="profile-layout">
      <aside class="profile-aside">
        <div class="profile-avatar">
          <img id="avatarPreview" src="{{ $user->avatar ? asset('storage/'.$user->avatar) : asset('img/doctor1.jpg') }}" alt="Avatar">
          <div class="profile-avatar__overlay" id="changePhoto">Cambiar foto</div>
        </div>
        <div>
          <h2>{{ $user->name }}</h2>
          <span>{{ $user->email }}</span>
        </div>
        @php $especialidades = ($user->especialidades ?? collect())->pluck('nombre')->all(); @endphp
        @if(count($especialidades))
          <div class="chips">
            @foreach($especialidades as $esp)
              <span class="chip">{{ $esp }}</span>
            @endforeach
          </div>
        @endif
        <div class="summary">
          @if($user->telefono)
            <div class="summary-item"><span class="material-symbols-outlined">call</span>{{ $user->telefono }}</div>
          @endif
          @if($user->direccion)
            <div class="summary-item"><span class="material-symbols-outlined">location_on</span>{{ $user->direccion }}</div>
          @endif
          @if(!is_null($user->precio_consulta))
            <div class="summary-item"><span class="material-symbols-outlined">payments</span>Precio: ${{ number_format($user->precio_consulta,2) }} USD</div>
          @endif
        </div>
      </aside>

      <section class="profile-card">
        <div class="profile-card__header">
          <h3>Datos personales y profesionales</h3>
          <span>Todos los campos pueden editarse en cualquier momento.</span>
        </div>

        @if(session('success'))
          <div class="alerts"><div class="alert success">{{ session('success') }}</div></div>
        @endif
        @if ($errors->any())
          <div class="alerts">
            <div class="alert danger">
              <ul style="margin:0 0 0 18px;padding:0;">
                @foreach ($errors->all() as $e)
                  <li>{{ $e }}</li>
                @endforeach
              </ul>
            </div>
          </div>
        @endif

        <form method="POST" action="{{ route('doctor.perfil.update') }}" enctype="multipart/form-data" id="perfilForm">
          @csrf
          <input id="avatarInput" class="hidden" type="file" name="avatar" accept="image/png,image/jpeg,image/jpg,image/webp">

          <div class="profile-card__body">
            <section class="section">
              <h4 class="section-title"><span class="material-symbols-outlined">badge</span>Identidad</h4>
              <div class="profile-grid">
                <div class="field-box">
                  <label>Nombre</label>
                  <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
                  @error('name')<div class="error">{{ $message }}</div>@enderror
                </div>
                <div class="field-box">
                  <label>Correo</label>
                  <input type="email" name="email" value="{{ old('email', $user->email) }}" required>
                  @error('email')<div class="error">{{ $message }}</div>@enderror
                </div>
                <div class="field-box">
                  <label>Teléfono</label>
                  <input type="tel" name="telefono" value="{{ old('telefono', $user->telefono) }}"
                         inputmode="numeric" pattern="\d{10}" minlength="10" maxlength="10"
                         placeholder="0998740927" title="Debe contener exactamente 10 dígitos">
                  <div class="help">Formato: 10 dígitos.</div>
                  @error('telefono')<div class="error">{{ $message }}</div>@enderror
                </div>
                <div class="field-box">
                  <label>Número de Cédula</label>
                  <input type="text" name="dni" value="{{ old('dni', $user->dni) }}"
                         inputmode="numeric" pattern="\d{10}" minlength="10" maxlength="10"
                         placeholder="1723456789" title="Debe contener exactamente 10 dígitos">
                  <div class="help">Exactamente 10 dígitos.</div>
                  @error('dni')<div class="error">{{ $message }}</div>@enderror
                </div>
              </div>
            </section>

            <section class="section">
              <h4 class="section-title"><span class="material-symbols-outlined">home_pin</span>Información adicional</h4>
              <div class="profile-grid">
                <div class="field-box">
                  <label>Dirección</label>
                  <input type="text" name="direccion" value="{{ old('direccion', $user->direccion) }}">
                  @error('direccion')<div class="error">{{ $message }}</div>@enderror
                </div>
                <div class="field-box">
                  <label>Fecha de nacimiento</label>
                  <input type="date" name="fecha_nacimiento" value="{{ old('fecha_nacimiento', optional($user->fecha_nacimiento)->toDateString()) }}">
                  @error('fecha_nacimiento')<div class="error">{{ $message }}</div>@enderror
                </div>
                <div class="field-box">
                  <label>Sexo</label>
                  <select name="sexo">
                    <option value="">Seleccionar</option>
                    <option value="Masculino" {{ old('sexo', $user->sexo)=='Masculino'?'selected':'' }}>Masculino</option>
                    <option value="Femenino"  {{ old('sexo', $user->sexo)=='Femenino'?'selected':'' }}>Femenino</option>
                    <option value="Otro"      {{ old('sexo', $user->sexo)=='Otro'?'selected':'' }}>Otro</option>
                  </select>
                  @error('sexo')<div class="error">{{ $message }}</div>@enderror
                </div>
                <div class="field-box">
                  <label>Precio de consulta (USD)</label>
                  <input type="number" name="precio_consulta" step="0.01" min="0"
                         value="{{ old('precio_consulta', $user->precio_consulta) }}"
                         placeholder="Ej: 25.00">
                  <div class="help">Moneda fija: USD.</div>
                  @error('precio_consulta')<div class="error">{{ $message }}</div>@enderror
                </div>
                <input type="hidden" name="moneda" value="USD">
              </div>
            </section>

            {{-- SEGURIDAD: Cambio de contraseña --}}
            <section class="section">
              <h4 class="section-title"><span class="material-symbols-outlined">lock</span>Seguridad</h4>
              <div class="profile-grid">
                <div class="field-box password-box">
                  <label>Contraseña actual</label>
                  <div class="password-field">
                    <input type="password" name="current_password" id="current_password" autocomplete="current-password" placeholder="••••••••">
                    <button type="button" class="btn-eye" data-target="#current_password" aria-label="Mostrar u ocultar">
                      <span class="material-symbols-outlined">visibility</span>
                    </button>
                  </div>
                  <div class="help">Necesaria para confirmar el cambio.</div>
                  @error('current_password')<div class="error">{{ $message }}</div>@enderror
                </div>

                <div class="field-box password-box">
                  <label>Nueva contraseña</label>
                  <div class="password-field">
                    <input type="password" name="password" id="password" autocomplete="new-password" minlength="8" placeholder="Min. 8 caracteres">
                    <button type="button" class="btn-eye" data-target="#password" aria-label="Mostrar u ocultar">
                      <span class="material-symbols-outlined">visibility</span>
                    </button>
                  </div>
                  <div class="help">Mínimo 8 caracteres. Debe ser distinta a la actual.</div>
                  @error('password')<div class="error">{{ $message }}</div>@enderror
                </div>

                <div class="field-box password-box">
                  <label>Confirmar nueva contraseña</label>
                  <div class="password-field">
                    <input type="password" name="password_confirmation" id="password_confirmation" autocomplete="new-password" minlength="8" placeholder="Repite la contraseña">
                    <button type="button" class="btn-eye" data-target="#password_confirmation" aria-label="Mostrar u ocultar">
                      <span class="material-symbols-outlined">visibility</span>
                    </button>
                  </div>
                </div>
              </div>
            </section>

            <section class="section">
              <h4 class="section-title"><span class="material-symbols-outlined">verified_user</span>Reconocimiento facial</h4>
              <p class="section-description">Escanea tu rostro para iniciar sesión sin contraseña. Necesitas permitir el uso de la cámara.</p>
              <div class="face-enroll-card">
                <div class="face-enroll-video">
                  <video id="faceEnrollVideo" autoplay muted playsinline></video>
                  <div id="faceEnrollOverlay">
                    {{ $user->faceProfile ? 'Rostro registrado. Si quieres actualizarlo, haz clic en "Actualizar rostro".' : 'Haz clic en "Guardar rostro" para activar la cámara.' }}
                  </div>
                </div>
                <div class="face-enroll-actions">
                  <button
                    type="button"
                    class="btn btn-primary"
                    id="faceEnrollButton"
                    data-enroll-url="{{ route('face.enroll') }}"
                    data-csrf="{{ csrf_token() }}"
                    data-models-url="{{ asset('models') }}"
                    data-has-face="{{ $user->faceProfile ? '1' : '0' }}"
                    data-saved-status="{{ $user->faceProfile ? 'Rostro registrado. Puedes actualizarlo si cambias de look.' : '' }}"
                  >
                    {{ $user->faceProfile ? 'Actualizar rostro' : 'Guardar rostro' }}
                  </button>
                  <p id="faceEnrollStatus" class="face-enroll-status"></p>
                </div>
              </div>
            </section>
          </div>

          <div class="profile-actions">
            <a class="btn btn-secondary" href="{{ route('doctor.dashboard') }}">Regresar</a>
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
          </div>
        </form>
      </section>
    </div>
  </div>
@endsection

@push('scripts')
  @vite('resources/js/doctor/perfil.js')
@endpush
