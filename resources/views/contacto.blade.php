{{-- resources/views/contacto.blade.php --}}
@extends('layouts.navbar')

@section('title','Formulario de Contacto')

@push('styles')
<style>
  /* ── Contacto page tokens ── */
  .ct-section { padding: 2rem 0 3rem; }
  .ct-grid {
    display: grid;
    gap: 1.5rem;
    align-items: start;
  }
  @media (min-width: 1024px) {
    .ct-grid { grid-template-columns: 0.9fr 1.1fr; }
  }

  /* ── Left card ── */
  .ct-card {
    background: rgba(255,255,255,0.97);
    border: 1px solid rgba(148,163,184,0.55);
    border-radius: 14px;
    box-shadow: 0 10px 30px rgba(15,23,42,0.07);
    padding: 1.75rem;
  }
  .ct-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.3rem 0.875rem;
    border-radius: 999px;
    background: var(--accent-soft);
    color: var(--accent);
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    margin-bottom: 0.75rem;
  }
  .ct-title {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--ink);
    margin: 0 0 0.5rem;
    line-height: 1.2;
  }
  .ct-subtitle {
    font-size: 0.875rem;
    color: var(--muted);
    line-height: 1.65;
    margin: 0 0 1.5rem;
  }
  /* Ilustración decorativa */
  .ct-illustration {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
  }
  .ct-illustration span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 3rem;
    height: 3rem;
    border-radius: 12px;
    background: var(--accent-soft);
    color: var(--accent);
    font-size: 1.4rem;
  }
  /* Bloques de info */
  .ct-info-list { display: flex; flex-direction: column; gap: 0; }
  .ct-info-item {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    padding: 0.875rem 0;
    border-bottom: 1px solid rgba(148,163,184,0.28);
    cursor: default;
  }
  .ct-info-item:last-child { border-bottom: none; }
  .ct-info-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 10px;
    background: var(--accent-soft);
    color: var(--accent);
    font-size: 1.1rem;
    flex-shrink: 0;
  }
  .ct-info-body { flex: 1; min-width: 0; }
  .ct-info-label {
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--ink);
    margin: 0 0 0.15rem;
  }
  .ct-info-value {
    font-size: 0.775rem;
    color: var(--muted);
    line-height: 1.5;
    margin: 0;
  }
  .ct-info-arrow {
    font-size: 1rem;
    color: rgba(148,163,184,0.7);
    flex-shrink: 0;
  }
  /* Info segura */
  .ct-secure {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    background: var(--accent-soft);
    border-radius: 10px;
    padding: 0.875rem 1rem;
    margin-top: 1.25rem;
  }
  .ct-secure i { color: var(--accent); font-size: 1.1rem; flex-shrink: 0; margin-top: 2px; }
  .ct-secure-title { font-size: 0.8125rem; font-weight: 700; color: var(--ink); margin: 0 0 0.15rem; }
  .ct-secure-text  { font-size: 0.75rem; color: var(--muted); margin: 0; line-height: 1.5; }

  /* ── Form card ── */
  .ct-form-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
  }
  .ct-form-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.3rem 0.875rem;
    border-radius: 999px;
    background: var(--accent-soft);
    border: 1px solid rgba(15,118,110,0.2);
    color: var(--accent);
    font-size: 0.72rem;
    font-weight: 600;
  }
  /* Inputs */
  .ct-field { display: flex; flex-direction: column; gap: 0.35rem; }
  .ct-label {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--ink);
  }
  .ct-input, .ct-textarea {
    width: 100%;
    padding: 0.65rem 0.875rem;
    border: 1.5px solid rgba(148,163,184,0.55);
    border-radius: 8px;
    font-size: 0.875rem;
    color: var(--ink);
    background: #fff;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
    box-sizing: border-box;
    font-family: inherit;
  }
  .ct-input:focus, .ct-textarea:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(15,118,110,0.12);
  }
  .ct-input::placeholder, .ct-textarea::placeholder { color: #94a3b8; }
  .ct-textarea { resize: vertical; min-height: 130px; }
  .ct-help { font-size: 0.72rem; color: var(--muted); margin: 0.25rem 0 0; }
  /* Submit */
  .ct-submit {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.85rem 1.5rem;
    background: var(--accent);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 0.9375rem;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 10px 24px rgba(15,118,110,0.22);
    transition: background 0.2s, transform 0.15s;
    margin-top: 0.25rem;
  }
  .ct-submit:hover { background: var(--accent-strong); transform: translateY(-1px); }

  /* ── 3 bottom cards ── */
  .ct-features { display: grid; grid-template-columns: repeat(3,1fr); gap: 1rem; margin-top: 1.5rem; }
  .ct-feat {
    display: flex;
    align-items: flex-start;
    gap: 0.875rem;
    background: rgba(255,255,255,0.97);
    border: 1px solid rgba(148,163,184,0.55);
    border-radius: 12px;
    padding: 1.25rem;
    box-shadow: 0 6px 18px rgba(15,23,42,0.05);
  }
  .ct-feat__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 10px;
    background: var(--accent-soft);
    color: var(--accent);
    font-size: 1.1rem;
    flex-shrink: 0;
  }
  .ct-feat__title { font-size: 0.875rem; font-weight: 700; color: var(--ink); margin: 0 0 0.2rem; }
  .ct-feat__text  { font-size: 0.775rem; color: var(--muted); line-height: 1.5; margin: 0; }

  /* ── Responsive ── */
  @media (max-width: 1023px) { .ct-features { grid-template-columns: 1fr; } }
  @media (max-width: 639px)  { .ct-features { grid-template-columns: 1fr; } }
</style>
@endpush

@section('main')
  @php
    $infoBadge          = $siteSettings->get('contact.info_badge', 'Contacto');
    $contactTitle       = $siteSettings->get('contact.title', 'Estamos para ayudarte');
    $contactSubtitle    = $siteSettings->get('contact.subtitle', 'Utiliza nuestros canales de contacto para consultas administrativas, horarios u orientación sobre el uso del sistema de citas.');
    $addressLabel       = $siteSettings->get('contact.address_label', 'Ubicación');
    $contactAddress     = $siteSettings->get('contact.address', '');
    $contactAddress2    = $siteSettings->get('contact.address2', '');
    $phoneLabel         = $siteSettings->get('contact.phone_label', 'Teléfono');
    $contactPhone       = $siteSettings->get('contact.phone', '');
    $contactPhone2      = $siteSettings->get('contact.phone2', '');
    $contactEmail       = $siteSettings->get('contact.email', '');
    $contactEmailNote   = $siteSettings->get('contact.email_note', 'Respondemos en menos de 24 horas');
    $hoursLabel         = $siteSettings->get('contact.hours_label', 'Horario de atención');
    $contactHours       = $siteSettings->get('contact.hours', '');
    $contactHours2      = $siteSettings->get('contact.hours2', '');

    $formSectionBadge   = $siteSettings->get('contact.form_section_badge', 'Escríbenos');
    $formTitle          = $siteSettings->get('contact.form_title', 'Formulario de contacto');
    $formBadge          = $siteSettings->get('contact.form_badge', 'Mensaje para la clínica');
    $submitText         = $siteSettings->get('contact.form_submit_text', 'Enviar mensaje');

    $nameLabel          = $siteSettings->get('contact.form_name_label', 'Nombre');
    $namePlaceholder    = $siteSettings->get('contact.form_name_placeholder', 'Ej. Nombre del paciente');
    $emailLabel         = $siteSettings->get('contact.form_email_label', 'Correo electrónico');
    $emailPlaceholder   = $siteSettings->get('contact.form_email_placeholder', 'Ej. contacto@clinica.test');
    $phoneFieldLabel    = $siteSettings->get('contact.form_phone_label', 'Teléfono');
    $phonePlaceholder   = $siteSettings->get('contact.form_phone_placeholder', 'Ej. 0991234567');
    $subjectLabel       = $siteSettings->get('contact.form_subject_label', 'Asunto');
    $subjectPlaceholder = $siteSettings->get('contact.form_subject_placeholder', 'Ej. Consulta sobre horarios');
    $messageLabel       = $siteSettings->get('contact.form_message_label', 'Mensaje');
    $messagePlaceholder = $siteSettings->get('contact.form_message_placeholder', 'Escribe el motivo de tu contacto y los detalles necesarios.');
    $messageHelp        = $siteSettings->get('contact.form_message_help', 'Describe el motivo de tu contacto. Máx. 1000 caracteres.');

    $mapTitle           = $siteSettings->get('contact.map_title', 'Ubicacion de la clinica');
    $mapEmbed           = $siteSettings->get('contact.map_embed', '');

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

  <div style="min-height:calc(100vh - 4.5rem); display:flex; flex-direction:column;">
    <section class="ct-section">
      <div class="page-shell">

        {{-- ════ GRID 2 COLUMNAS ════ --}}
        <div class="ct-grid">

          {{-- ── COLUMNA IZQUIERDA: info ── --}}
          <aside class="ct-card">

            {{-- Cabecera + ilustración --}}
            <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; margin-bottom:1rem;">
              <div>
                <span class="ct-badge">{{ $infoBadge }}</span>
                <h2 class="ct-title">{{ $contactTitle }}</h2>
                <p class="ct-subtitle">{{ $contactSubtitle }}</p>
              </div>
              <div class="ct-illustration" aria-hidden="true">
                <span><i class="ri-mail-line"></i></span>
                <span><i class="ri-phone-line"></i></span>
              </div>
            </div>

            {{-- Bloques de contacto --}}
            <div class="ct-info-list">
              @if(filled($contactPhone))
                <div class="ct-info-item">
                  <span class="ct-info-icon"><i class="ri-phone-line" aria-hidden="true"></i></span>
                  <div class="ct-info-body">
                    <p class="ct-info-label">{{ $phoneLabel }}</p>
                    <p class="ct-info-value">{{ $contactPhone }}@if(filled($contactPhone2))<br>{{ $contactPhone2 }}@endif</p>
                  </div>
                  <i class="ri-arrow-right-s-line ct-info-arrow" aria-hidden="true"></i>
                </div>
              @endif

              @if(filled($contactEmail))
                <div class="ct-info-item">
                  <span class="ct-info-icon"><i class="ri-mail-line" aria-hidden="true"></i></span>
                  <div class="ct-info-body">
                    <p class="ct-info-label">Correo electrónico</p>
                    <p class="ct-info-value">{{ $contactEmail }}<br>{{ $contactEmailNote }}</p>
                  </div>
                  <i class="ri-arrow-right-s-line ct-info-arrow" aria-hidden="true"></i>
                </div>
              @endif

              @if(filled($contactAddress))
                <div class="ct-info-item">
                  <span class="ct-info-icon"><i class="ri-map-pin-line" aria-hidden="true"></i></span>
                  <div class="ct-info-body">
                    <p class="ct-info-label">{{ $addressLabel }}</p>
                    <p class="ct-info-value">{{ $contactAddress }}@if(filled($contactAddress2))<br>{{ $contactAddress2 }}@endif</p>
                  </div>
                  <i class="ri-arrow-right-s-line ct-info-arrow" aria-hidden="true"></i>
                </div>
              @endif

              @if(filled($contactHours))
                <div class="ct-info-item">
                  <span class="ct-info-icon"><i class="ri-time-line" aria-hidden="true"></i></span>
                  <div class="ct-info-body">
                    <p class="ct-info-label">{{ $hoursLabel }}</p>
                    <p class="ct-info-value">{{ $contactHours }}@if(filled($contactHours2))<br>{{ $contactHours2 }}@endif</p>
                  </div>
                  <i class="ri-arrow-right-s-line ct-info-arrow" aria-hidden="true"></i>
                </div>
              @endif
            </div>

            {{-- Mapa --}}
            @if(filled($mapEmbed))
              <div style="margin-top:1.25rem; border-radius:10px; overflow:hidden; border:1px solid rgba(148,163,184,0.45);">
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

            {{-- Info segura --}}
            <div class="ct-secure">
              <i class="ri-shield-check-line" aria-hidden="true"></i>
              <div>
                <p class="ct-secure-title">Información segura</p>
                <p class="ct-secure-text">Tus datos están protegidos y solo serán utilizados para responder tu consulta.</p>
              </div>
            </div>

          </aside>

          {{-- ── COLUMNA DERECHA: formulario ── --}}
          <div class="ct-card">
            <div class="ct-form-header">
              <div>
                <span class="ct-badge">{{ $formSectionBadge }}</span>
                <h1 class="ct-title">{{ $formTitle }}</h1>
              </div>
              <span class="ct-form-badge">
                <i class="ri-message-3-line" aria-hidden="true"></i>
                {{ $formBadge }}
              </span>
            </div>

            @if(session('success'))
              <div class="alert success mb-4" role="status">{{ session('success') }}</div>
            @endif

            <form method="POST" action="{{ route('contacto.enviar') }}" novalidate id="contactoForm">
              @csrf
              <input type="hidden" name="t0" value="{{ now()->timestamp }}">
              <div class="hidden" aria-hidden="true">
                <label for="empresa">Empresa</label>
                <input id="empresa" type="text" name="empresa" value="" tabindex="-1" autocomplete="off">
              </div>

              <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">

                <div class="ct-field">
                  <label for="nombre" class="ct-label">{{ $nameLabel }}</label>
                  <input id="nombre" type="text" name="nombre" value="{{ old('nombre') }}"
                    placeholder="{{ $namePlaceholder }}" required autocomplete="name"
                    aria-invalid="{{ $errors->has('nombre') ? 'true' : 'false' }}"
                    aria-describedby="{{ $errors->has('nombre') ? 'err-nombre' : '' }}"
                    class="ct-input">
                  @error('nombre')<small id="err-nombre" class="ct-help" style="color:#e11d48;">{{ $message }}</small>@enderror
                </div>

                <div class="ct-field">
                  <label for="email" class="ct-label">{{ $emailLabel }}</label>
                  <input id="email" type="email" name="email" value="{{ old('email') }}"
                    placeholder="{{ $emailPlaceholder }}" required autocomplete="email" inputmode="email"
                    aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                    aria-describedby="{{ $errors->has('email') ? 'err-email' : '' }}"
                    class="ct-input">
                  @error('email')<small id="err-email" class="ct-help" style="color:#e11d48;">{{ $message }}</small>@enderror
                </div>

                <div class="ct-field">
                  <label for="telefono" class="ct-label">{{ $phoneFieldLabel }}</label>
                  <input id="telefono" type="tel" name="telefono" value="{{ old('telefono') }}"
                    placeholder="{{ $phonePlaceholder }}" autocomplete="tel" inputmode="numeric"
                    maxlength="10" pattern="[0-9]{10}" title="Debe contener exactamente 10 dígitos numéricos"
                    data-digits="10" required
                    aria-invalid="{{ $errors->has('telefono') ? 'true' : 'false' }}"
                    aria-describedby="{{ $errors->has('telefono') ? 'err-telefono' : '' }}"
                    class="ct-input">
                  @error('telefono')<small id="err-telefono" class="ct-help" style="color:#e11d48;">{{ $message }}</small>@enderror
                </div>

                <div class="ct-field">
                  <label for="asunto" class="ct-label">{{ $subjectLabel }}</label>
                  <input id="asunto" type="text" name="asunto" value="{{ old('asunto') }}"
                    autocomplete="off" placeholder="{{ $subjectPlaceholder }}" required
                    aria-invalid="{{ $errors->has('asunto') ? 'true' : 'false' }}"
                    aria-describedby="{{ $errors->has('asunto') ? 'err-asunto' : '' }}"
                    class="ct-input">
                  @error('asunto')<small id="err-asunto" class="ct-help" style="color:#e11d48;">{{ $message }}</small>@enderror
                </div>

                <div class="ct-field" style="grid-column:1/-1;">
                  <label for="mensaje" class="ct-label">{{ $messageLabel }}</label>
                  <textarea id="mensaje" name="mensaje" rows="6" required spellcheck="true"
                    maxlength="1000" placeholder="{{ $messagePlaceholder }}"
                    aria-invalid="{{ $errors->has('mensaje') ? 'true' : 'false' }}"
                    aria-describedby="help-mensaje{{ $errors->has('mensaje') ? ' err-mensaje' : '' }}"
                    class="ct-textarea">{{ old('mensaje') }}</textarea>
                  <small id="help-mensaje" class="ct-help">{{ $messageHelp }}</small>
                  @error('mensaje')<small id="err-mensaje" class="ct-help" style="color:#e11d48;">{{ $message }}</small>@enderror
                </div>

              </div>

              <button type="submit" class="ct-submit" id="btnSubmit" style="margin-top:1.25rem;">
                {{ $submitText }}
                <i class="ri-send-plane-line" aria-hidden="true"></i>
              </button>
            </form>
          </div>

        </div>{{-- /ct-grid --}}

        {{-- ════ 3 CARDS INFERIORES ════ --}}
        <div class="ct-features">
          <div class="ct-feat">
            <span class="ct-feat__icon"><i class="ri-headphone-line" aria-hidden="true"></i></span>
            <div>
              <p class="ct-feat__title">Atención rápida</p>
              <p class="ct-feat__text">Respondemos tus consultas en el menor tiempo posible.</p>
            </div>
          </div>
          <div class="ct-feat">
            <span class="ct-feat__icon"><i class="ri-lock-line" aria-hidden="true"></i></span>
            <div>
              <p class="ct-feat__title">Confidencialidad</p>
              <p class="ct-feat__text">Tu información está segura con nosotros.</p>
            </div>
          </div>
          <div class="ct-feat">
            <span class="ct-feat__icon"><i class="ri-checkbox-circle-line" aria-hidden="true"></i></span>
            <div>
              <p class="ct-feat__title">Soporte confiable</p>
              <p class="ct-feat__text">Te ayudamos con cualquier duda que tengas.</p>
            </div>
          </div>
        </div>

      </div>
    </section>

    @include('partials.footer')
  </div>
@endsection

@push('scripts')
  @vite('resources/js/contacto.js')
@endpush
