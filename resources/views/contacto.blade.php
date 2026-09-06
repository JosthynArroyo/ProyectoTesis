{{-- resources/views/contacto.blade.php --}}
@extends('layouts.navbar')

@section('title','Formulario de Contacto')

@push('styles')
  @vite('resources/css/contacto.css')
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
    $scheduleService    = app(\App\Services\ProfessionalScheduleService::class);
    $formattedSchedule  = $scheduleService->getFormattedClinicSchedule();
    $hoursLabel         = $siteSettings->get('contact.hours_label', 'Horario de atención');
    $contactHours       = $formattedSchedule['summary'];
    $scheduleLines      = $formattedSchedule['lines'];
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

  <div class="ct-page-shell">
    <section class="ct-section">
      <div class="page-shell">

        {{-- ════ GRID 2 COLUMNAS ════ --}}
        <div class="ct-grid">

          {{-- ── COLUMNA IZQUIERDA: info ── --}}
          <aside class="ct-card">

            {{-- Cabecera + ilustración --}}
            <div class="ct-card-header">
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

              @if(filled($contactHours) || !empty($scheduleLines))
                <div class="ct-info-item ct-info-item--top">
                  <span class="ct-info-icon"><i class="ri-time-line" aria-hidden="true"></i></span>
                  <div class="ct-info-body">
                    <p class="ct-info-label">{{ $hoursLabel }}</p>
                    @if(count($scheduleLines) === 1)
                      <p class="ct-info-value font-medium">{{ $scheduleLines[0]['label'] }}, {{ $scheduleLines[0]['hours'] }}</p>
                    @else
                      <div class="space-y-1.5 mt-1">
                        @foreach($scheduleLines as $line)
                          <div class="flex flex-wrap items-center justify-between text-xs gap-2 leading-tight">
                            <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $line['label'] }}:</span>
                            <span class="{{ $line['is_closed'] ? 'text-rose-600 dark:text-rose-400 font-semibold' : 'text-gray-700 dark:text-gray-300 font-medium' }}">{{ $line['hours'] }}</span>
                          </div>
                        @endforeach
                      </div>
                    @endif
                    @if(filled($contactHours2))
                      <p class="ct-info-value text-xs text-gray-500 mt-1">{{ $contactHours2 }}</p>
                    @endif
                  </div>
                </div>
              @endif
            </div>

            {{-- Mapa --}}
            @if(filled($mapEmbed))
              <div class="ct-map-wrap">
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

            @php($contactErrors = $errors->getBag('contacto'))

            <form method="POST" action="{{ route('contacto.enviar') }}" novalidate id="contactoForm">
              @csrf
              <input type="hidden" name="t0" value="{{ now()->timestamp }}">
              <div class="hidden" aria-hidden="true">
                <label for="empresa">Empresa</label>
                <input id="empresa" type="text" name="empresa" value="" tabindex="-1" autocomplete="off">
              </div>

              <div class="ct-form-grid">

                <div class="ct-field">
                  <label for="nombre" class="ct-label">{{ $nameLabel }}</label>
                  <input id="nombre" type="text" name="nombre" value="{{ old('nombre') }}"
                    placeholder="{{ $namePlaceholder }}" required autocomplete="name"
                    aria-invalid="{{ $contactErrors->has('nombre') ? 'true' : 'false' }}"
                    aria-describedby="{{ $contactErrors->has('nombre') ? 'err-nombre' : '' }}"
                    class="ct-input">
                  @if($contactErrors->has('nombre'))<small id="err-nombre" class="ct-help ct-help--error">{{ $contactErrors->first('nombre') }}</small>@endif
                </div>

                <div class="ct-field">
                  <label for="email" class="ct-label">{{ $emailLabel }}</label>
                  <input id="email" type="email" name="email" value="{{ old('email') }}"
                    placeholder="{{ $emailPlaceholder }}" required autocomplete="email" inputmode="email"
                    aria-invalid="{{ $contactErrors->has('email') ? 'true' : 'false' }}"
                    aria-describedby="{{ $contactErrors->has('email') ? 'err-email' : '' }}"
                    class="ct-input">
                  @if($contactErrors->has('email'))<small id="err-email" class="ct-help ct-help--error">{{ $contactErrors->first('email') }}</small>@endif
                </div>

                <div class="ct-field">
                  <label for="telefono" class="ct-label">{{ $phoneFieldLabel }}</label>
                  <input id="telefono" type="tel" name="telefono" value="{{ old('telefono') }}"
                    placeholder="{{ $phonePlaceholder }}" autocomplete="tel" inputmode="numeric"
                    maxlength="10" pattern="[0-9]{10}" title="Debe contener exactamente 10 dígitos numéricos"
                    data-digits="10" required
                    aria-invalid="{{ $contactErrors->has('telefono') ? 'true' : 'false' }}"
                    aria-describedby="{{ $contactErrors->has('telefono') ? 'err-telefono' : '' }}"
                    class="ct-input">
                  @if($contactErrors->has('telefono'))<small id="err-telefono" class="ct-help ct-help--error">{{ $contactErrors->first('telefono') }}</small>@endif
                </div>

                <div class="ct-field">
                  <label for="asunto" class="ct-label">{{ $subjectLabel }}</label>
                  <input id="asunto" type="text" name="asunto" value="{{ old('asunto') }}"
                    autocomplete="off" placeholder="{{ $subjectPlaceholder }}" required
                    aria-invalid="{{ $contactErrors->has('asunto') ? 'true' : 'false' }}"
                    aria-describedby="{{ $contactErrors->has('asunto') ? 'err-asunto' : '' }}"
                    class="ct-input">
                  @if($contactErrors->has('asunto'))<small id="err-asunto" class="ct-help ct-help--error">{{ $contactErrors->first('asunto') }}</small>@endif
                </div>

                <div class="ct-field ct-field--full">
                  <label for="mensaje" class="ct-label">{{ $messageLabel }}</label>
                  <textarea id="mensaje" name="mensaje" rows="6" required spellcheck="true"
                    maxlength="1000" placeholder="{{ $messagePlaceholder }}"
                    aria-invalid="{{ $contactErrors->has('mensaje') ? 'true' : 'false' }}"
                    aria-describedby="help-mensaje{{ $contactErrors->has('mensaje') ? ' err-mensaje' : '' }}"
                    class="ct-textarea">{{ old('mensaje') }}</textarea>
                  <small id="help-mensaje" class="ct-help">{{ $messageHelp }}</small>
                  @if($contactErrors->has('mensaje'))<small id="err-mensaje" class="ct-help ct-help--error">{{ $contactErrors->first('mensaje') }}</small>@endif
                </div>

              </div>

              <button type="submit" class="ct-submit" id="btnSubmit">
                <span data-contacto-submit-label>{{ $submitText }}</span>
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
