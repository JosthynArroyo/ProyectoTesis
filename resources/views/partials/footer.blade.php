@php
  $footerText = $siteSettings->get('branding.footer_text', 'Clínica Don Bosco (c) {year} - Todos los derechos reservados.');
  $footerText = str_replace('{year}', date('Y'), $footerText);
  $contactPhone = $siteSettings->get('contact.phone', '0998742410');
  $contactAddress = $siteSettings->get('contact.address', 'Quito, Av. Colón y 6 de Diciembre');
@endphp

<footer id="footer" class="mt-auto border-t border-slate-200 bg-white/90 py-8">
  <div class="page-shell grid gap-6 md:grid-cols-2 lg:grid-cols-4">
    <div class="space-y-2">
      <p class="text-sm font-semibold text-slate-900">Clínica Don Bosco</p>
      <p class="text-sm text-slate-500">{{ $footerText }}</p>
    </div>

    <div class="space-y-2">
      <p class="text-sm font-semibold text-slate-900">Enlaces</p>
      <div class="grid gap-1 text-sm text-slate-500">
        @if(\Illuminate\Support\Facades\Route::has('servicios.index'))
          <a class="hover:text-slate-800" href="{{ route('servicios.index') }}">Servicios</a>
        @endif
        @if(\Illuminate\Support\Facades\Route::has('contacto.form'))
          <a class="hover:text-slate-800" href="{{ route('contacto.form') }}">Contacto</a>
        @endif
        <button type="button"
                class="text-left hover:text-slate-800"
                onclick="window.toggleChatbotWidget && window.toggleChatbotWidget()">
          Asistente virtual
        </button>
      </div>
    </div>

    <div class="space-y-2">
      <p class="text-sm font-semibold text-slate-900">Legales</p>
      <div class="grid gap-1 text-sm text-slate-500">
        <button type="button"
                class="cursor-pointer text-left hover:text-slate-800"
                data-legal-open="privacy-policy-modal"
                aria-controls="privacy-policy-modal"
                aria-haspopup="dialog">
          Políticas de privacidad
        </button>
        <button type="button"
                class="cursor-pointer text-left hover:text-slate-800"
                data-legal-open="terms-service-modal"
                aria-controls="terms-service-modal"
                aria-haspopup="dialog">
          Términos de servicio
        </button>
      </div>
    </div>

    <div class="space-y-2">
      <p class="text-sm font-semibold text-slate-900">Contacto</p>
      <p class="text-sm text-slate-500">{{ $contactAddress }}</p>
      <p class="text-sm text-slate-500">Tel: {{ $contactPhone }}</p>
      <div class="flex items-center gap-2 pt-1 text-slate-400">
        <a href="https://www.instagram.com/" target="_blank" aria-label="Instagram" class="hover:text-slate-600"><i class="ri-instagram-line"></i></a>
        <a href="https://wa.me/593998740927" target="_blank" aria-label="WhatsApp" class="hover:text-slate-600"><i class="ri-whatsapp-line"></i></a>
        <a href="https://www.facebook.com/" target="_blank" aria-label="Facebook" class="hover:text-slate-600"><i class="ri-facebook-circle-line"></i></a>
      </div>
    </div>
  </div>
</footer>
