{{-- resources/views/contacto.blade.php --}}
@extends('layouts.navbar')

@section('title','Formulario de Contacto')

@section('main')
  @php
    $infoBadge = $siteSettings->get('contact.info_badge', 'Contacto');
    $contactTitle = $siteSettings->get('contact.title', 'Clínica Don Bosco');
    $contactSubtitle = $siteSettings->get('contact.subtitle', 'Canales de contacto de la clínica para consultas administrativas, horarios y orientación sobre el uso del sistema de citas.');
    $addressLabel = $siteSettings->get('contact.address_label', 'Dirección');
    $contactAddress = $siteSettings->get('contact.address', 'Quito, Av. Colon y 6 de Diciembre');
    $phoneLabel = $siteSettings->get('contact.phone_label', 'Teléfono');
    $contactPhone = $siteSettings->get('contact.phone', '0998742410');
    $hoursLabel = $siteSettings->get('contact.hours_label', 'Horario');
    $contactHours = $siteSettings->get('contact.hours', 'Lunes a Viernes, 08:00 - 18:00');

    $formSectionBadge = $siteSettings->get('contact.form_section_badge', 'Escríbenos');
    $formTitle = $siteSettings->get('contact.form_title', 'Formulario de contacto');
    $formBadge = $siteSettings->get('contact.form_badge', 'Mensaje para la clínica');
    $submitText = $siteSettings->get('contact.form_submit_text', 'Enviar');

    $nameLabel = $siteSettings->get('contact.form_name_label', 'Nombre');
    $emailLabel = $siteSettings->get('contact.form_email_label', 'Correo electrónico');
    $phoneFieldLabel = $siteSettings->get('contact.form_phone_label', 'Teléfono (10 dígitos)');
    $subjectLabel = $siteSettings->get('contact.form_subject_label', 'Asunto');
    $subjectPlaceholder = $siteSettings->get('contact.form_subject_placeholder', 'Ej. Consulta sobre horarios');
    $messageLabel = $siteSettings->get('contact.form_message_label', 'Mensaje');
    $messagePlaceholder = $siteSettings->get('contact.form_message_placeholder', 'Escribe el motivo de tu contacto y los detalles necesarios.');
    $messageHelp = $siteSettings->get('contact.form_message_help', 'Describe el motivo de tu contacto. Max. 1000 caracteres.');

    $mapTitle = $siteSettings->get('contact.map_title', 'Ubicación Clínica Don Bosco');
    $mapEmbed = $siteSettings->get('contact.map_embed', 'https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d207.47773878695978!2d-78.47943247794669!3d-0.1385355730076882!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x91d5855715695e7b%3A0x2f91853277ceb246!2sConsultorio%20De%20Especialidades!5e1!3m2!1ses!2sus!4v1760994198832!5m2!1ses!2sus');

    if (is_string($mapEmbed)) {
      $mapEmbed = trim($mapEmbed);
      if (str_contains($mapEmbed, '/maps/embedpb=')) {
        $mapEmbed = str_replace('/maps/embedpb=', '/maps/embed?pb=', $mapEmbed);
      }
      if (str_starts_with($mapEmbed, '/maps/')) {
        $mapEmbed = 'https://www.google.com'.$mapEmbed;
      }
    }
  @endphp

  <div style="min-height:calc(100vh - 4.5rem);display:flex;flex-direction:column;">
    <section class="section-pad section-pad--first">
      <div class="page-shell grid gap-8 lg:grid-cols-[0.9fr_1.1fr]">
        <aside class="card space-y-6 p-6">
          <div>
            <p class="text-xs uppercase tracking-widest text-slate-500">{{ $infoBadge }}</p>
            <h2 class="mt-2 text-2xl font-semibold text-slate-900">{{ $contactTitle }}</h2>
            <p class="mt-2 text-slate-600">{{ $contactSubtitle }}</p>
          </div>

          <div class="space-y-4 text-sm text-slate-600">
            <div>
              <p class="text-xs uppercase tracking-wide text-slate-400">{{ $addressLabel }}</p>
              <p>{{ $contactAddress }}</p>
            </div>
            <div>
              <p class="text-xs uppercase tracking-wide text-slate-400">{{ $phoneLabel }}</p>
              <p>{{ $contactPhone }}</p>
            </div>
            <div>
              <p class="text-xs uppercase tracking-wide text-slate-400">{{ $hoursLabel }}</p>
              <p>{{ $contactHours }}</p>
            </div>
          </div>

          <div class="overflow-hidden rounded-2xl border border-slate-200">
            <iframe
              title="{{ $mapTitle }}"
              src="{{ $mapEmbed }}"
              class="map-embed"
              loading="lazy"
              referrerpolicy="no-referrer-when-downgrade"
              allowfullscreen
            ></iframe>
          </div>
        </aside>

        <div class="card p-6">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p class="text-xs uppercase tracking-widest text-slate-500">{{ $formSectionBadge }}</p>
              <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $formTitle }}</h1>
            </div>
            <span class="badge info">{{ $formBadge }}</span>
          </div>

          @if(session('success'))
            <div class="alert success mt-4" role="status">{{ session('success') }}</div>
          @endif

          <form method="POST" action="{{ route('contacto.enviar') }}" novalidate id="contactoForm" class="mt-6 space-y-4">
            @csrf

            <input type="hidden" name="t0" value="{{ now()->timestamp }}">
            <div class="hidden" aria-hidden="true">
              <label for="empresa">Empresa</label>
              <input id="empresa" type="text" name="empresa" value="" tabindex="-1" autocomplete="off">
            </div>

            <div class="grid gap-4 md:grid-cols-2">
              <div class="form-group space-y-1">
                <label for="nombre" class="form-label">{{ $nameLabel }}</label>
                <input
                  id="nombre"
                  type="text"
                  name="nombre"
                  value="{{ old('nombre') }}"
                  required
                  autocomplete="name"
                  aria-invalid="{{ $errors->has('nombre') ? 'true' : 'false' }}"
                  aria-describedby="{{ $errors->has('nombre') ? 'err-nombre' : '' }}"
                  class="form-input"
                >
                @error('nombre')<small id="err-nombre" class="err text-xs text-rose-600">{{ $message }}</small>@enderror
              </div>

              <div class="form-group space-y-1">
                <label for="email" class="form-label">{{ $emailLabel }}</label>
                <input
                  id="email"
                  type="email"
                  name="email"
                  value="{{ old('email') }}"
                  required
                  autocomplete="email"
                  inputmode="email"
                  aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                  aria-describedby="{{ $errors->has('email') ? 'err-email' : '' }}"
                  class="form-input"
                >
                @error('email')<small id="err-email" class="err text-xs text-rose-600">{{ $message }}</small>@enderror
              </div>

              <div class="form-group space-y-1">
                <label for="telefono" class="form-label">{{ $phoneFieldLabel }}</label>
                <input
                  id="telefono"
                  type="tel"
                  name="telefono"
                  value="{{ old('telefono') }}"
                  autocomplete="tel"
                  inputmode="numeric"
                  maxlength="10"
                  pattern="[0-9]{10}"
                  title="Debe contener exactamente 10 digitos numericos"
                  data-digits="10"
                  required
                  aria-invalid="{{ $errors->has('telefono') ? 'true' : 'false' }}"
                  aria-describedby="{{ $errors->has('telefono') ? 'err-telefono' : '' }}"
                  class="form-input"
                >
                @error('telefono')<small id="err-telefono" class="err text-xs text-rose-600">{{ $message }}</small>@enderror
              </div>

              <div class="form-group space-y-1">
                <label for="asunto" class="form-label">{{ $subjectLabel }}</label>
                <input
                  id="asunto"
                  type="text"
                  name="asunto"
                  value="{{ old('asunto') }}"
                  autocomplete="off"
                  placeholder="{{ $subjectPlaceholder }}"
                  required
                  aria-invalid="{{ $errors->has('asunto') ? 'true' : 'false' }}"
                  aria-describedby="{{ $errors->has('asunto') ? 'err-asunto' : '' }}"
                  class="form-input"
                >
                @error('asunto')<small id="err-asunto" class="err text-xs text-rose-600">{{ $message }}</small>@enderror
              </div>

              <div class="form-group space-y-1 md:col-span-2">
                <label for="mensaje" class="form-label">{{ $messageLabel }}</label>
                <textarea
                  id="mensaje"
                  name="mensaje"
                  rows="6"
                  required
                  spellcheck="true"
                  maxlength="1000"
                  placeholder="{{ $messagePlaceholder }}"
                  aria-invalid="{{ $errors->has('mensaje') ? 'true' : 'false' }}"
                  aria-describedby="help-mensaje{{ $errors->has('mensaje') ? ' err-mensaje' : '' }}"
                  class="form-textarea"
                >{{ old('mensaje') }}</textarea>
                <small id="help-mensaje" class="hint text-xs text-slate-500">{{ $messageHelp }}</small>
                @error('mensaje')<small id="err-mensaje" class="err text-xs text-rose-600">{{ $message }}</small>@enderror
              </div>
            </div>

            <button type="submit" class="btn btn-primary w-full" id="btnSubmit">{{ $submitText }}</button>
          </form>
        </div>
      </div>
    </section>

    @include('partials.footer')
  </div>
@endsection

@push('scripts')
  @vite('resources/js/contacto.js')
@endpush
