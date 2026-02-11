<div id="footer" style="margin-top:auto;background:#e0f2fe;padding:16px 0 24px;">
  @php
    $footerText = $siteSettings->get('branding.footer_text','Clínica Don Bosco (c) {year} - Todos los derechos reservados.');
    $footerText = str_replace('{year}', date('Y'), $footerText);
  @endphp
  <footer style="background:rgba(255,255,255,0.8);border-top:1px solid #e2e8f0;padding:16px 0;">
    <div class="page-shell flex items-center justify-center text-center text-sm text-slate-500">
      <p>{{ $footerText }}</p>
    </div>
  </footer>
</div>