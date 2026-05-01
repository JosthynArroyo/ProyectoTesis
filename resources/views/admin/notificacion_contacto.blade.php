{{-- resources/views/admin/notificacion_contacto.blade.php --}}
@extends('layouts.navbar')

@section('title','Formulario de Contacto')

@section('main')
  @php
    $infoBadge = $siteSettings->get('contact.info_badge', 'Contacto');
    $contactTitle = $siteSettings->get('contact.title', $clinicIdentity->name());
    $contactSubtitle = $siteSettings->get('contact.subtitle', 'Sistema de gestión médica para agendar citas fácilmente y recibir atención especializada.');
    $addressLabel = $siteSettings->get('contact.address_label', 'Dirección');
    $contactAddress = $siteSettings->get('contact.address', '');
    $phoneLabel = $siteSettings->get('contact.phone_label', 'Teléfono');
    $contactPhone = $siteSettings->get('contact.phone', '');
    $hoursLabel = $siteSettings->get('contact.hours_label', 'Horario');
    $contactHours = $siteSettings->get('contact.hours', '');

    $formSectionBadge = $siteSettings->get('contact.form_section_badge', 'Escríbenos');
    $formTitle = $siteSettings->get('contact.form_title', 'Formulario de contacto');
    $formBadge = $siteSettings->get('contact.form_badge', 'Respuesta en menos de 24h');
    $submitText = $siteSettings->get('contact.form_submit_text', 'Enviar');

    $nameLabel = $siteSettings->get('contact.form_name_label', 'Nombre');
    $emailLabel = $siteSettings->get('contact.form_email_label', 'Correo electrónico');
    $phoneFieldLabel = $siteSettings->get('contact.form_phone_label', 'Teléfono (10 dígitos)');
    $subjectLabel = $siteSettings->get('contact.form_subject_label', 'Asunto');
    $subjectPlaceholder = $siteSettings->get('contact.form_subject_placeholder', 'Ej. Consulta sobre horarios');
    $messageLabel = $siteSettings->get('contact.form_message_label', 'Mensaje');
    $messagePlaceholder = $siteSettings->get('contact.form_message_placeholder', 'Escribe el motivo de tu contacto y los detalles necesarios.');
    $messageHelp = $siteSettings->get('contact.form_message_help', 'Describe el motivo de tu contacto. Max. 1000 caracteres.');

    $mapTitle = $siteSettings->get('contact.map_title', 'Ubicacion de la clinica');
    $mapEmbed = $siteSettings->get('contact.map_embed', '');

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
            <p class="text-xs uppercase tracking-widest text-gray-500">{{ $infoBadge }}</p>
            <h2 class="mt-2 text-2xl font-semibold text-gray-900">{{ $contactTitle }}</h2>
            <p class="mt-2 text-gray-600">{{ $contactSubtitle }}</p>
          </div>

          <div class="space-y-4 text-sm text-gray-600">
            @if(filled($contactAddress))
              <div>
                <p class="text-xs uppercase tracking-wide text-gray-400">{{ $addressLabel }}</p>
                <p>{{ $contactAddress }}</p>
              </div>
            @endif
            @if(filled($contactPhone))
              <div>
                <p class="text-xs uppercase tracking-wide text-gray-400">{{ $phoneLabel }}</p>
                <p>{{ $contactPhone }}</p>
              </div>
            @endif
            @if(filled($contactHours))
              <div>
                <p class="text-xs uppercase tracking-wide text-gray-400">{{ $hoursLabel }}</p>
                <p>{{ $contactHours }}</p>
              </div>
            @endif
          </div>

          @if(filled($mapEmbed))
            <div class="overflow-hidden rounded-2xl border border-gray-200">
              <iframe
                title="{{ $mapTitle }}"
                src="{{ $mapEmbed }}"
                class="map-embed"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
                allowfullscreen
              ></iframe>
            </div>
          @endif
        </aside>

        <div class="card p-6">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p class="text-xs uppercase tracking-widest text-gray-500">{{ $formSectionBadge }}</p>
              <h1 class="mt-2 text-2xl font-semibold text-gray-900">{{ $formTitle }}</h1>
            </div>
            <span class="badge info">{{ $formBadge }}</span>
          </div>

          @if(session('success'))
            <div class="alert success mt-4" role="status">{{ session('success') }}</div>
          @endif

          <form method="POST" action="{{ route('contacto.mensaje') }}" novalidate id="contactoForm" class="mt-6 space-y-4">
            @csrf

            <input type="hidden" name="t0" value="{{ now()->timestamp }}">

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
                <small id="help-mensaje" class="hint text-xs text-gray-500">{{ $messageHelp }}</small>
                @error('mensaje')<small id="err-mensaje" class="err text-xs text-rose-600">{{ $message }}</small>@enderror
              </div>
            </div>

            <button type="submit" class="btn btn-primary w-full" id="btnSubmit">{{ $submitText }}</button>
          </form>
        </div>
      </div>
    </section>

    @include('partials.sidebar')
  </div>
@endsection

@push('scripts')
  @vite('resources/js/contacto.js')
@endpush
