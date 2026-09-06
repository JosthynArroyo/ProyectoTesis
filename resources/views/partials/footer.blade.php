@php
  $footerText = $clinicIdentity->footerText();
  $contactPhone = $clinicIdentity->phone();
  $contactEmail = $clinicIdentity->email();
  $contactAddress = $clinicIdentity->address();
  $footerName = $clinicIdentity->institutionalName();
  $showHomeLink = $siteSettings->getBool('footer.show_home_link', false);
  $showServicesLink = $siteSettings->getBool('footer.show_services_link', true);
  $showContactLink = $siteSettings->getBool('footer.show_contact_link', true);
  $showAssistantLink = $siteSettings->getBool('footer.show_assistant_link', true);
  $showPrivacyLink = $siteSettings->getBool('footer.show_privacy_link', true);
  $showTermsLink = $siteSettings->getBool('footer.show_terms_link', true);
  $hasContact = (bool) ($contactAddress || $contactPhone || $contactEmail);
@endphp

<footer id="footer" class="mt-auto border-t border-gray-200 bg-white py-6">
  <div class="page-shell footer-shell">
    <div @class([
      'footer-grid',
      'footer-grid--with-contact' => $hasContact,
      'footer-grid--without-contact' => ! $hasContact,
    ])>
    <div class="footer-column footer-column--brand">
      <p class="text-xs font-semibold tracking-tight text-gray-900">{{ $footerName }}</p>
      <p class="max-w-xs text-[11px] leading-5 text-gray-500">{{ $footerText }}</p>
    </div>

    <div class="footer-column">
      <p class="text-xs font-semibold tracking-tight text-gray-900">Enlaces</p>
      <div class="footer-links text-[11px] leading-5 text-gray-500">
        @if($showHomeLink)
          <a class="transition-colors hover:text-gray-800" href="{{ url('/') }}">{{ $siteSettings->get('footer.home_label', 'Inicio') }}</a>
        @endif
        @if($showServicesLink && \Illuminate\Support\Facades\Route::has('servicios.index'))
          <a class="transition-colors hover:text-gray-800" href="{{ route('servicios.index') }}">{{ $siteSettings->get('footer.services_label', 'Servicios') }}</a>
        @endif
        @if($showContactLink && \Illuminate\Support\Facades\Route::has('contacto.form'))
          <a class="transition-colors hover:text-gray-800" href="{{ route('contacto.form') }}">{{ $siteSettings->get('footer.contact_label', 'Contacto') }}</a>
        @endif
        @if($showAssistantLink)
          <button
            type="button"
            class="cursor-pointer text-left transition-colors hover:text-gray-800"
            data-chatbot-toggle
          >
            {{ $siteSettings->get('footer.assistant_label', 'Asistente virtual') }}
          </button>
        @endif
      </div>
    </div>

    <div class="footer-column">
      <p class="text-xs font-semibold tracking-tight text-gray-900">Legales</p>
      <div class="footer-links text-[11px] leading-5 text-gray-500">
        @if($showPrivacyLink)
          <button
            type="button"
            class="cursor-pointer text-left transition-colors hover:text-gray-800"
            data-legal-open="privacy-policy-modal"
            aria-controls="privacy-policy-modal"
            aria-haspopup="dialog"
          >
            {{ $siteSettings->get('footer.privacy_label', 'Políticas de privacidad') }}
          </button>
        @endif
        @if($showTermsLink)
          <button
            type="button"
            class="cursor-pointer text-left transition-colors hover:text-gray-800"
            data-legal-open="terms-service-modal"
            aria-controls="terms-service-modal"
            aria-haspopup="dialog"
          >
            {{ $siteSettings->get('footer.terms_label', 'Términos de servicio') }}
          </button>
        @endif
      </div>
    </div>

    @if($hasContact)
      <div class="footer-column footer-column--contact">
        <p class="text-xs font-semibold tracking-tight text-gray-900">Contacto</p>
        @if($contactAddress)
          <p class="max-w-xs break-words text-[11px] leading-5 text-gray-500">{{ $contactAddress }}</p>
        @endif
        @if($contactPhone)
          <p class="break-words text-[11px] leading-5 text-gray-500">Tel: {{ $contactPhone }}</p>
        @endif
        @if($contactEmail)
          <p class="break-words text-[11px] leading-5 text-gray-500">{{ $contactEmail }}</p>
        @endif
      </div>
    @endif
    </div>
  </div>
</footer>
