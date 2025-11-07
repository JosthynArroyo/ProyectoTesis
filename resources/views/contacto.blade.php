{{-- resources/views/contacto.blade.php --}}
@extends('layouts.navbar')

@section('title','Formulario de Contacto')

@push('head')
  @vite(['resources/css/contacto.css'])
@endpush

@section('main')
  <section class="contacto">
    <div class="contacto__grid">

      {{-- Info + Mapa --}}
      <aside class="info">
        <h2 class="info__title">Clínica Los Ángeles</h2>
        <p class="info__desc">
          Sistema de gestión médica para agendar citas fácilmente y recibir atención especializada.
        </p>

        <div class="info__list">
          <div>
            <h3>Dirección</h3>
            <p>Quito, Av. Colón y 6 de Diciembre</p>
          </div>
          <div>
            <h3>Teléfono</h3>
            <p>0998742410</p>
          </div>
          <div>
            <h3>Horario</h3>
            <p>Lunes a Viernes, 08:00 - 18:00</p>
          </div>
        </div>

        <div class="mapa">
          <iframe
            title="Ubicación Clínica Los Ángeles"
            src="https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d207.47773878695978!2d-78.47943247794669!3d-0.1385355730076882!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x91d5855715695e7b%3A0x2f91853277ceb246!2sConsultorio%20De%20Especialidades!5e1!3m2!1ses!2sus!4v1760994198832!5m2!1ses!2sus"
            width="100%" height="320" style="border:0"
            loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen>
          </iframe>
        </div>
      </aside>

      {{-- Formulario --}}
      <div class="contacto__card">
        <h1 class="contacto__title">Formulario de Contacto</h1>

        @if(session('success'))
          <div class="alert-success" role="status">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('contacto.enviar') }}" novalidate id="contactoForm">
          @csrf

          {{-- honeypot + tiempo mínimo --}}
          <input type="text" name="empresa" class="hp" autocomplete="off" tabindex="-1" aria-hidden="true">
          <input type="hidden" name="t0" value="{{ now()->timestamp }}">

          <div class="form-group">
            <label for="nombre">Nombre</label>
            <input
              id="nombre" type="text" name="nombre" value="{{ old('nombre') }}" required
              autocomplete="name"
              aria-invalid="{{ $errors->has('nombre') ? 'true' : 'false' }}"
              aria-describedby="{{ $errors->has('nombre') ? 'err-nombre' : '' }}">
            @error('nombre')<small id="err-nombre" class="err">{{ $message }}</small>@enderror
          </div>

          <div class="form-group">
            <label for="email">Correo electrónico</label>
            <input
              id="email" type="email" name="email" value="{{ old('email') }}" required
              autocomplete="email" inputmode="email"
              aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
              aria-describedby="{{ $errors->has('email') ? 'err-email' : '' }}">
            @error('email')<small id="err-email" class="err">{{ $message }}</small>@enderror
          </div>

          <div class="form-group">
            <label for="telefono">Teléfono (opcional)</label>
            <input
              id="telefono" type="tel" name="telefono" value="{{ old('telefono') }}"
              autocomplete="tel" inputmode="tel"
              aria-invalid="{{ $errors->has('telefono') ? 'true' : 'false' }}"
              aria-describedby="{{ $errors->has('telefono') ? 'err-telefono' : '' }}">
            @error('telefono')<small id="err-telefono" class="err">{{ $message }}</small>@enderror
          </div>

          <div class="form-group">
            <label for="motivo">Motivo</label>
            <select
              id="motivo" name="motivo"
              aria-invalid="{{ $errors->has('motivo') ? 'true' : 'false' }}"
              aria-describedby="{{ $errors->has('motivo') ? 'err-motivo' : '' }}">
              <option value="">Selecciona una opción</option>
              <option value="consulta_general" @selected(old('motivo')==='consulta_general')>Consulta general</option>
              <option value="agendar_cita" @selected(old('motivo')==='agendar_cita')>Agendar cita</option>
              <option value="reprogramacion" @selected(old('motivo')==='reprogramacion')>Reprogramación</option>
              <option value="facturacion" @selected(old('motivo')==='facturacion')>Facturación</option>
              <option value="otros" @selected(old('motivo')==='otros')>Otros</option>
            </select>
            @error('motivo')<small id="err-motivo" class="err">{{ $message }}</small>@enderror
          </div>

          <div class="form-group">
            <label for="asunto">Asunto</label>
            <input
              id="asunto" type="text" name="asunto" value="{{ old('asunto') }}"
              autocomplete="off"
              aria-invalid="{{ $errors->has('asunto') ? 'true' : 'false' }}"
              aria-describedby="{{ $errors->has('asunto') ? 'err-asunto' : '' }}">
            @error('asunto')<small id="err-asunto" class="err">{{ $message }}</small>@enderror
          </div>

          <div class="form-group">
            <label for="mensaje">Mensaje</label>
            <textarea
              id="mensaje" name="mensaje" rows="6" required spellcheck="true" maxlength="1000"
              aria-invalid="{{ $errors->has('mensaje') ? 'true' : 'false' }}"
              aria-describedby="help-mensaje{{ $errors->has('mensaje') ? ' err-mensaje' : '' }}">{{ old('mensaje') }}</textarea>
            <small id="help-mensaje" class="hint">Máx. 1000 caracteres.</small>
            @error('mensaje')<small id="err-mensaje" class="err">{{ $message }}</small>@enderror
          </div>

          <p class="privacy">
            Deseas que usemos tus datos de este formulario para generarte un usuario en nuestro sistema de gestión médica y así facilitar la atención en tu próxima visita?
            
          </p>

          <button type="submit" class="btn-submit" id="btnSubmit">Enviar</button>
        </form>
      </div>

    </div>
  </section>
@endsection

@push('scripts')
<script>
  (function(){
    const f = document.getElementById('contactoForm');
    const b = document.getElementById('btnSubmit');
    if (f && b) { f.addEventListener('submit', () => { b.disabled = true; }); }
    const err = document.querySelector('.err');
    if (err) {
      const input = err.closest('.form-group')?.querySelector('input,textarea,select');
      if (input) {
        input.focus();
        input.scrollIntoView({behavior:'smooth', block:'center'});
      }
    }
  })();
</script>
@endpush
