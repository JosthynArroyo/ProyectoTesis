@extends('layouts.paciente')
@section('title','Perfil del Paciente')

@push('head')
    @vite(['resources/css/paciente/perfil.css'])
@endpush

@section('main')
<div class="perfil-page">
  <div class="mobile-topbar">
    <button id="menu_bar" aria-label="Abrir menú">
      <span class="material-symbols-sharp">menu</span>
    </button>
  </div>

  <section class="perfil-hero">
    <div>
      <h2>Perfil del paciente</h2>
      <p>Mantén tu información actualizada para recibir recordatorios y comunicaciones de manera oportuna.</p>
    </div>
    <span class="badge"><span class="material-symbols-outlined">favorite</span>Atención personalizada</span>
  </section>

  <div class="perfil-layout">
    <aside class="perfil-aside">
      <div class="avatar" id="avatarBox" role="button" tabindex="0" aria-label="Cambiar foto de perfil">
        <img id="avatarPreview" src="{{ $user->avatar ? asset('storage/'.$user->avatar) : asset('img/paciente1.jpg') }}" alt="Avatar">
        <div id="changePhoto" class="overlay">Cambiar foto</div>
      </div>

      <div class="avatar-actions">
        <button type="button" id="changePhotoBtn" class="btn-avatar-change">
          <span class="material-symbols-outlined">photo_camera</span>
          Cambiar foto
        </button>
      </div>

      <div>
        <h3>{{ $user->name }}</h3>
        <span>{{ $user->email }}</span>
      </div>

      <div class="summary">
        @if($user->telefono)
          <div class="summary-item"><span class="material-symbols-outlined">call</span>{{ $user->telefono }}</div>
        @endif
        @if($user->direccion)
          <div class="summary-item"><span class="material-symbols-outlined">location_on</span>{{ $user->direccion }}</div>
        @endif
      </div>
    </aside>

    <section class="perfil-card">
      <div class="perfil-card__header">
        <h4>Datos personales</h4>
        <span>Actualiza tus datos de contacto y verificación.</span>
      </div>

      @if(session('success'))
        <div class="perfil-alerts"><div class="alert success">{{ session('success') }}</div></div>
      @endif

      @if ($errors->any())
        <div class="perfil-alerts">
          <div class="alert error">
            <ul style="margin:0 0 0 18px;padding:0;">
              @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
          </div>
        </div>
      @endif

      <form method="POST" action="{{ route('paciente.perfil.update') }}" enctype="multipart/form-data">
        @csrf
        <input id="avatarInput" type="file" name="avatar" accept="image/png,image/jpeg,image/jpg,image/webp" hidden>

        <div class="perfil-body">
          <section class="section">
            <h5 class="section-title"><span class="material-symbols-outlined">badge</span>Identificación</h5>
            <div class="grid">
              <div>
                <label>Nombre</label>
                <input class="input" type="text" name="name" value="{{ old('name', $user->name) }}" required>
                @error('name')<div class="error">{{ $message }}</div>@enderror
              </div>
              <div>
                <label>Correo</label>
                <input class="input" type="email" name="email" value="{{ old('email', $user->email) }}" required>
                @error('email')<div class="error">{{ $message }}</div>@enderror
              </div>
              <div>
                <label>Teléfono</label>
                <input class="input" type="tel" name="telefono" value="{{ old('telefono', $user->telefono) }}" inputmode="numeric" pattern="\d{10}" minlength="10" maxlength="10" placeholder="0991234567" title="Debe contener exactamente 10 dígitos">
                <div class="field-help">Formato: 10 dígitos.</div>
                @error('telefono')<div class="error">{{ $message }}</div>@enderror
              </div>
              <div>
                <label>Número de Cédula</label>
                <input class="input" type="text" name="dni" value="{{ old('dni', $user->dni) }}" inputmode="numeric" pattern="\d{10}" minlength="10" maxlength="10" placeholder="1723456789" title="Debe contener exactamente 10 dígitos">
                <div class="field-help">Exactamente 10 dígitos.</div>
                @error('dni')<div class="error">{{ $message }}</div>@enderror
              </div>
            </div>
          </section>

          <section class="section">
            <h5 class="section-title"><span class="material-symbols-outlined">home_pin</span>Información adicional</h5>
            <div class="grid">
              <div>
                <label>Dirección</label>
                <input class="input" type="text" name="direccion" value="{{ old('direccion', $user->direccion) }}">
                @error('direccion')<div class="error">{{ $message }}</div>@enderror
              </div>
              <div>
                <label>Fecha de nacimiento</label>
                <input class="input" type="date" name="fecha_nacimiento" value="{{ old('fecha_nacimiento', optional($user->fecha_nacimiento)->toDateString()) }}">
                @error('fecha_nacimiento')<div class="error">{{ $message }}</div>@enderror
              </div>
              <div>
                <label>Sexo</label>
                <select class="select" name="sexo">
                  <option value="">Seleccionar</option>
                  <option value="Masculino" {{ old('sexo', $user->sexo)=='Masculino'?'selected':'' }}>Masculino</option>
                  <option value="Femenino" {{ old('sexo', $user->sexo)=='Femenino'?'selected':'' }}>Femenino</option>
                  <option value="Otro" {{ old('sexo', $user->sexo)=='Otro'?'selected':'' }}>Otro</option>
                </select>
                @error('sexo')<div class="error">{{ $message }}</div>@enderror
              </div>
            </div>
          </section>

          <section class="section">
            <h5 class="section-title"><span class="material-symbols-outlined">lock</span>Seguridad</h5>
            <div class="grid">
              <div>
                <label>Contraseña actual</label>
                <div class="password-field">
                  <input class="input" type="password" name="current_password" id="current_password" autocomplete="current-password" placeholder="********">
                  <button type="button" class="btn-eye" data-target="#current_password" aria-label="Mostrar u ocultar"><span class="material-symbols-outlined">visibility</span></button>
                </div>
                @error('current_password')<div class="error">{{ $message }}</div>@enderror
              </div>
              <div>
                <label>Nueva contraseña</label>
                <div class="password-field">
                  <input class="input" type="password" name="password" id="password" autocomplete="new-password" minlength="8" placeholder="Min. 8 caracteres">
                  <button type="button" class="btn-eye" data-target="#password" aria-label="Mostrar u ocultar"><span class="material-symbols-outlined">visibility</span></button>
                </div>
                <div class="field-help">Mínimo 8 caracteres e incluir letras, números y un carácter especial.</div>
                @error('password')<div class="error">{{ $message }}</div>@enderror
              </div>
              <div>
                <label>Confirmar nueva contraseña</label>
                <div class="password-field">
                  <input class="input" type="password" name="password_confirmation" id="password_confirmation" autocomplete="new-password" minlength="8" placeholder="Repite la contraseña">
                  <button type="button" class="btn-eye" data-target="#password_confirmation" aria-label="Mostrar u ocultar"><span class="material-symbols-outlined">visibility</span></button>
                </div>
              </div>
            </div>
          </section>

          <section class="section">
            <h5 class="section-title"><span class="material-symbols-outlined">verified_user</span>Reconocimiento facial</h5>
            <p class="section-description">Escanea tu rostro para iniciar sesión sin contraseña. Necesitas permitir el uso de la cámara.</p>
            <div class="face-enroll-card">
              <div class="face-enroll-video">
                <video id="faceEnrollVideo" autoplay muted playsinline></video>
                <div id="faceEnrollOverlay">Haz clic en “Guardar rostro” para activar la cámara.</div>
              </div>
              <div class="face-enroll-actions">
                <button type="button" class="btn btn-primary" id="faceEnrollButton">Guardar rostro</button>
                <p id="faceEnrollStatus" class="face-enroll-status"></p>
              </div>
            </div>
          </section>
        </div>

        <div class="perfil-actions">
          <a href="{{ route('paciente.dashboard') }}" class="btn btn-secondary">Ir al panel</a>
          <button type="submit" class="btn btn-primary">Guardar cambios</button>
        </div>
      </form>
    </section>
  </div>
</div>
@endsection

@push('scripts')
    @vite(['resources/js/paciente/perfil.js','resources/js/face-auth.js'])
    <script type="module">
      import { initFaceApi, captureDescriptor } from '/build/assets/face-auth.js';

      document.addEventListener('DOMContentLoaded', () => {
        const video   = document.getElementById('faceEnrollVideo');
        const overlay = document.getElementById('faceEnrollOverlay');
        const button  = document.getElementById('faceEnrollButton');
        const status  = document.getElementById('faceEnrollStatus');
        if (!video || !button) return;

        let stream = null;
        let loading = false;

        async function requestCamera() {
          if (stream) return stream;
          try {
            await initFaceApi();
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
            video.srcObject = stream;
            video.classList.add('is-active');
            overlay?.classList.add('is-hidden');
            return stream;
          } catch (err) {
            const message = err.name === 'NotAllowedError'
              ? 'Debes permitir el uso de la cámara para registrar tu rostro.'
              : err.name === 'NotFoundError'
                ? 'No se encontró una cámara disponible.'
                : err.message ?? 'No se pudo activar la cámara.';
            throw new Error(message);
          }
        }

        async function handleEnroll() {
          if (loading) return;
          loading = true;
          button.disabled = true;
          status.textContent = 'Activando cámara...';

          try {
            await requestCamera();
            status.textContent = 'Escaneando rostro...';
            const descriptor = await captureDescriptor(video);

            status.textContent = 'Guardando...';
            const response = await fetch('{{ route('face.enroll') }}', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
              },
              body: JSON.stringify({ descriptor }),
            });

            if (!response.ok) {
              const error = await response.json().catch(() => ({}));
              throw new Error(error.message ?? 'No se pudo guardar el rostro.');
            }

            status.textContent = 'Rostro guardado correctamente.';
          } catch (err) {
            status.textContent = err.message ?? 'No se detectó el rostro. Intenta de nuevo.';
          } finally {
            button.disabled = false;
            loading = false;
          }
        }

        button.addEventListener('click', handleEnroll);
      });
    </script>
@endpush
