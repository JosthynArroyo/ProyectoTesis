@extends('layouts.admin')
@section('title','Perfil del Administrador')

@push('head')
  @vite(['resources/css/admin/perfil.css'])
@endpush

@section('main')
  <div class="profile-page">
    <section class="profile-hero">
      <div>
        <h1>Perfil del administrador</h1>
        <p>Actualiza tus datos para mantener la comunicación y los accesos del equipo siempre al día.</p>
      </div>
      <span class="badge"><span class="material-symbols-outlined">verified_user</span>Cuenta protegida</span>
    </section>

    <div class="profile-layout">
      <aside class="profile-sidebar">
        <div class="avatar">
          <img id="avatarPreview" src="{{ $user->avatar ? asset('storage/'.$user->avatar) : asset('img/doctor1.jpg') }}" alt="Avatar">
          <div class="overlay" id="changePhoto">Cambiar foto</div>
        </div>
        <div>
          <h2>{{ $user->name }}</h2>
          <span>{{ $user->email }}</span>
        </div>
        <div class="summary">
          <div class="summary-item"><span class="material-symbols-outlined">shield_person</span> Rol: Administrador</div>
          @if($user->telefono)
            <div class="summary-item"><span class="material-symbols-outlined">call</span> {{ $user->telefono }}</div>
          @endif
          @if($user->direccion)
            <div class="summary-item"><span class="material-symbols-outlined">location_on</span> {{ $user->direccion }}</div>
          @endif
        </div>
      </aside>

      <section class="profile-card">
        <div class="profile-card__header">
          <h3>Información personal</h3>
          <span>Los cambios se aplican inmediatamente después de guardar.</span>
        </div>

        @if(session('success'))
          <div class="alerts"><div class="alert success">{{ session('success') }}</div></div>
        @endif
        @if ($errors->any())
          <div class="alerts">
            <div class="alert danger">
              <ul style="margin:0 0 0 18px;padding:0;">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
          </div>
        @endif

        <form method="POST" action="{{ route('admin.perfil.update') }}" enctype="multipart/form-data">
          @csrf
          <input id="avatarInput" type="file" name="avatar" accept="image/png,image/jpeg,image/jpg,image/webp" hidden>

          <div class="profile-card__body">
            <section class="section">
              <h4 class="section-title"><span class="material-symbols-outlined">badge</span>Datos principales</h4>
              <div class="grid">
                <div>
                  <label>Nombre completo</label>
                  <input class="input" type="text" name="name" value="{{ old('name', $user->name) }}" required>
                </div>
                <div>
                  <label>Correo electrónico</label>
                  <input class="input" type="email" name="email" value="{{ old('email', $user->email) }}" required>
                </div>
                <div>
                  <label>Teléfono</label>
                  <input class="input" type="tel" name="telefono" value="{{ old('telefono', $user->telefono) }}" inputmode="numeric" pattern="\d{10}" minlength="10" maxlength="10" placeholder="0998740927">
                  <div class="field-help">Formato: 10 dígitos.</div>
                </div>
                <div>
                  <label>Cédula</label>
                  <input class="input" type="text" name="dni" value="{{ old('dni', $user->dni) }}" inputmode="numeric" pattern="\d{10}" minlength="10" maxlength="10" placeholder="1723456789">
                  <div class="field-help">Exactamente 10 dígitos.</div>
                </div>
              </div>
            </section>

            <section class="section">
              <h4 class="section-title"><span class="material-symbols-outlined">home_pin</span>Información adicional</h4>
              <div class="grid">
                <div>
                  <label>Dirección</label>
                  <input class="input" type="text" name="direccion" value="{{ old('direccion', $user->direccion) }}">
                </div>
                <div>
                  <label>Fecha de nacimiento</label>
                  <input class="input" type="date" name="fecha_nacimiento" value="{{ old('fecha_nacimiento', optional($user->fecha_nacimiento)->toDateString()) }}">
                </div>
                <div>
                  <label>Sexo</label>
                  <select class="select" name="sexo">
                    <option value="">Seleccionar</option>
                    <option value="Masculino" {{ old('sexo', $user->sexo)=='Masculino'?'selected':'' }}>Masculino</option>
                    <option value="Femenino"  {{ old('sexo', $user->sexo)=='Femenino'?'selected':'' }}>Femenino</option>
                    <option value="Otro"      {{ old('sexo', $user->sexo)=='Otro'?'selected':'' }}>Otro</option>
                  </select>
                </div>
              </div>
            </section>

            <section class="section">
              <h4 class="section-title"><span class="material-symbols-outlined">lock</span>Seguridad</h4>
              <div class="grid">
                <div>
                  <label>Contraseña actual</label>
                  <div class="password-field">
                    <input class="input" type="password" name="current_password" id="current_password" autocomplete="current-password" placeholder="••••••••">
                    <button type="button" class="btn-eye" data-target="#current_password" aria-label="Mostrar u ocultar"><span class="material-symbols-outlined">visibility</span></button>
                  </div>
                  @error('current_password')<div class="field-help" style="color:#b91c1c">{{ $message }}</div>@enderror
                </div>
                <div>
                  <label>Nueva contraseña</label>
                  <div class="password-field">
                    <input class="input" type="password" name="password" id="password" autocomplete="new-password" minlength="8" placeholder="Min. 8 caracteres">
                    <button type="button" class="btn-eye" data-target="#password" aria-label="Mostrar u ocultar"><span class="material-symbols-outlined">visibility</span></button>
                  </div>
                  <div class="field-help">Mínimo 8 caracteres e incluir letras, números y un carácter especial.</div>
                  @error('password')<div class="field-help" style="color:#b91c1c">{{ $message }}</div>@enderror
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
          </div>

          <div class="profile-actions">
            <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
          </div>
        </form>
      </section>
    </div>
  </div>
@endsection

@push('scripts')
  @vite(['resources/js/admin/perfil.js'])
@endpush
