@php
  $settings = $settings ?? [];
  $footerName = $siteSettings->get('branding.institutional_name', $siteSettings->get('branding.name', 'Nombre de la clinica'));
  $footerText = str_replace('{year}', date('Y'), $siteSettings->get('branding.footer_text', '© {year} - Todos los derechos reservados.'));
  $navigationOrder = array_values(array_filter(array_map('trim', explode(',', (string) $siteSettings->get('header.navigation_order', 'home,services,contact')))));
  $navigationOrder = empty($navigationOrder) ? ['home', 'services', 'contact'] : $navigationOrder;
  $navigationMap = [
    'home' => ['label' => 'Inicio', 'visible' => $siteSettings->getBool('header.show_home', true)],
    'services' => ['label' => 'Servicios', 'visible' => $siteSettings->getBool('header.show_services', true)],
    'contact' => ['label' => 'Contacto', 'visible' => $siteSettings->getBool('header.show_contact', true)],
  ];
  $navigationLabels = collect($navigationOrder)
    ->map(fn ($key) => $navigationMap[$key] ?? null)
    ->filter(fn ($item) => $item && $item['visible'])
    ->pluck('label')
    ->values()
    ->all();

  foreach ($navigationMap as $item) {
    if ($item['visible'] && ! in_array($item['label'], $navigationLabels, true)) {
      $navigationLabels[] = $item['label'];
    }
  }

  $footerLinks = [
    ['label' => $siteSettings->get('footer.home_label', 'Inicio'), 'visible' => $siteSettings->getBool('footer.show_home_link', false)],
    ['label' => $siteSettings->get('footer.services_label', 'Servicios'), 'visible' => $siteSettings->getBool('footer.show_services_link', true)],
    ['label' => $siteSettings->get('footer.contact_label', 'Contacto'), 'visible' => $siteSettings->getBool('footer.show_contact_link', true)],
    ['label' => $siteSettings->get('footer.assistant_label', 'Asistente virtual'), 'visible' => $siteSettings->getBool('footer.show_assistant_link', true)],
  ];
  $footerLegal = [
    ['label' => $siteSettings->get('footer.privacy_label', 'Politicas de privacidad'), 'visible' => $siteSettings->getBool('footer.show_privacy_link', true)],
    ['label' => $siteSettings->get('footer.terms_label', 'Terminos de servicio'), 'visible' => $siteSettings->getBool('footer.show_terms_link', true)],
  ];
  $contactPreviewConfig = [
    'branding' => [
      'name' => $siteSettings->get('branding.name', 'Nombre de la clinica'),
      'logo' => $clinicIdentity->logoUrl(),
      'navbar_text' => $siteSettings->get('branding.navbar_text', 'Sistema web de gestion medica'),
      'login_text' => $landingWelcome->get('header_login_text', 'Ingresar'),
    ],
    'navigation' => $navigationLabels,
    'footer' => [
      'name' => $footerName,
      'text' => $footerText,
      'address' => $siteSettings->get('contact.address', ''),
      'phone' => $siteSettings->get('contact.phone', ''),
      'email' => $siteSettings->get('contact.email', ''),
      'links' => collect($footerLinks)->where('visible', true)->pluck('label')->values()->all(),
      'legal' => collect($footerLegal)->where('visible', true)->pluck('label')->values()->all(),
    ],
  ];
@endphp

@include('shared.personalizacion-public-preview-styles')

<div class="personalizacion-public-editor" data-contacto-form>
  <div class="personalizacion-public-surface space-y-6">
    <section class="card border border-gray-200/80 bg-white/95 p-6 dark:border-gray-800/80 dark:bg-gray-900/95" data-public-preview-trigger-card>
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="min-w-0">
          <p class="text-xs uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500">Contacto publico</p>
          <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Configura la pagina y prueba la preview reactiva</h3>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">La vista previa permanece en tema claro, usa el estado actual del formulario y bloquea cualquier envio real.</p>
        </div>
        <button type="button" class="btn btn-outline dark:border-gray-700 dark:bg-gray-850 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-white" data-public-preview-open>
          <i class="ri-macbook-line"></i> Ver vista previa
        </button>
      </div>
    </section>

    <section class="card border border-gray-200/80 bg-white/95 p-6 dark:border-gray-800/80 dark:bg-gray-900/95" data-public-preview-form-card>
      <div>
        <p class="text-xs uppercase tracking-widest text-gray-500 dark:text-gray-450">Informacion</p>
        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">Bloque principal de contacto</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400">Titulo, subtitulo, datos visibles y mapa de referencia.</p>
      </div>

      <div class="mt-4 grid gap-4 md:grid-cols-2">
        <div>
          <label class="form-label">Badge</label>
          <input class="form-input" name="contact_info_badge" value="{{ old('contact_info_badge', $settings['contact.info_badge'] ?? '') }}">
          @error('contact_info_badge')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Titulo</label>
          <input class="form-input" name="contact_title" value="{{ old('contact_title', $settings['contact.title'] ?? '') }}">
          @error('contact_title')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
        </div>
        <div class="md:col-span-2">
          <label class="form-label">Subtitulo</label>
          <textarea class="form-textarea" name="contact_subtitle" rows="3">{{ old('contact_subtitle', $settings['contact.subtitle'] ?? '') }}</textarea>
          @error('contact_subtitle')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Etiqueta direccion</label>
          <input class="form-input" name="contact_address_label" value="{{ old('contact_address_label', $settings['contact.address_label'] ?? '') }}">
          @error('contact_address_label')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Direccion</label>
          <input class="form-input" name="contact_address" value="{{ old('contact_address', $settings['contact.address'] ?? '') }}">
          @error('contact_address')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Etiqueta telefono</label>
          <input class="form-input" name="contact_address_label" value="{{ old('contact_address_label', $settings['contact.address_label'] ?? '') }}">
          <input class="form-input" name="contact_phone_label" value="{{ old('contact_phone_label', $settings['contact.phone_label'] ?? '') }}">
          @error('contact_phone_label')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Telefono visible</label>
          <input class="form-input" name="contact_phone" value="{{ old('contact_phone', $settings['contact.phone'] ?? '') }}">
          @error('contact_phone')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Correo visible</label>
          <input class="form-input" name="contact_email" value="{{ old('contact_email', $settings['contact.email'] ?? '') }}">
          @error('contact_email')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Etiqueta horario</label>
          <input class="form-input" name="contact_hours_label" value="{{ old('contact_hours_label', $settings['contact.hours_label'] ?? '') }}">
          @error('contact_hours_label')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
        </div>
        <div class="md:col-span-2">
          <label class="form-label">Horario</label>
          <input class="form-input" name="contact_hours" value="{{ old('contact_hours', $settings['contact.hours'] ?? '') }}">
          @error('contact_hours')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Titulo del mapa</label>
          <input class="form-input" name="contact_map_title" value="{{ old('contact_map_title', $settings['contact.map_title'] ?? '') }}">
          @error('contact_map_title')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
        </div>
        <div class="md:col-span-2">
          <label class="form-label">URL embed de Google Maps</label>
          <input class="form-input" name="contact_map_embed" value="{{ old('contact_map_embed', $settings['contact.map_embed'] ?? '') }}">
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Se usa en la vista publica real. En la preview se muestra solo una representacion visual, sin iframe.</p>
          @error('contact_map_embed')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
        </div>
      </div>
    </section>

    @if (session('clinic_hours_conflicts'))
      @php
        $conflicts = session('clinic_hours_conflicts');
      @endphp
      <div class="card p-4 border-2 border-amber-500 bg-amber-50 dark:bg-amber-950/20 text-amber-800 dark:text-amber-200 space-y-3">
        <div class="flex items-start gap-3">
          <i class="ri-alert-line text-lg text-amber-600 dark:text-amber-400"></i>
          <div>
            <h4 class="font-semibold">Confirmación de conflictos requerida</h4>
            <p class="text-sm mt-1">
              La reducción del horario de la clínica afectará los siguientes elementos programados a partir de hoy:
            </p>
            <ul class="list-disc pl-5 text-xs mt-2 space-y-1">
              <li><strong>{{ $conflicts['schedules_count'] }} bloques</strong> de horarios de doctores existentes que quedarán fuera de rango.</li>
              <li><strong>{{ $conflicts['citas_count'] }} citas futuras activas</strong> que quedarán fuera de horario.</li>
              <li><strong>{{ $conflicts['days_count'] }} días</strong> con afectaciones.</li>
            </ul>
            <p class="text-sm mt-2 text-amber-700 dark:text-amber-300">
              Las citas no se cancelarán ni modificarán, pero los horarios fuera de rango de los doctores dejarán de generar slots disponibles automáticamente.
            </p>
          </div>
        </div>
        <label class="flex items-center gap-2 text-sm font-semibold cursor-pointer">
          <input type="hidden" name="confirmar_conflictos" value="0">
          <input type="checkbox" name="confirmar_conflictos" value="1" required class="rounded border-gray-300 text-teal-650 focus:ring-teal-550 dark:border-gray-700 dark:bg-gray-800">
          Entiendo y confirmo que deseo aplicar la reducción de horario institucional.
        </label>
      </div>
    @endif

    <section class="card border border-gray-200/80 bg-white/95 p-6 dark:border-gray-800/80 dark:bg-gray-900/95">
      <div>
        <p class="text-xs uppercase tracking-widest text-gray-500 dark:text-gray-450">Horario Institucional</p>
        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">Horario operativo de la clínica</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400">Configura los límites de atención institucionales. Los horarios de los doctores y laboratorios se validan e intersectan contra estas franjas.</p>
      </div>

      <div class="mt-6 space-y-4">
        @php
          $diasSemana = [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo'
          ];
        @endphp

        <div class="hidden md:grid md:grid-cols-12 md:gap-4 md:items-center text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-500 pb-2 border-b border-gray-100 dark:border-gray-800">
          <div class="col-span-4">Día</div>
          <div class="col-span-4">Hora de apertura</div>
          <div class="col-span-4">Hora de cierre</div>
        </div>

        @foreach ($diasSemana as $i => $diaNombre)
          @php
            $status = old("clinic_hours.{$i}.status", $settings["clinic_hours.{$i}.status"] ?? ($i === 7 ? '0' : '1'));
            $opening = old("clinic_hours.{$i}.opening", substr($settings["clinic_hours.{$i}.opening"] ?? '08:00', 0, 5));
            $closing = old("clinic_hours.{$i}.closing", substr($settings["clinic_hours.{$i}.closing"] ?? ($i === 6 ? '13:00' : '18:00'), 0, 5));
          @endphp
          <div class="grid grid-cols-1 gap-3 border-b border-gray-100 pb-4 last:border-0 last:pb-0 dark:border-gray-850 md:grid-cols-12 md:gap-4 md:items-center">
            <div class="col-span-4 flex items-center gap-3">
              <input type="hidden" name="clinic_hours[{{ $i }}][status]" value="0">
              <input type="checkbox" id="clinic_hours_{{ $i }}_status" name="clinic_hours[{{ $i }}][status]" value="1" class="h-4 w-4 rounded border-gray-300 text-teal-600 focus:ring-teal-500 dark:border-gray-700 dark:bg-gray-800" @checked($status == '1') onchange="toggleClinicHoursRow({{ $i }})">
              <label for="clinic_hours_{{ $i }}_status" class="text-sm font-medium text-gray-900 dark:text-white">
                {{ $diaNombre }}
              </label>
            </div>
            <div class="col-span-4">
              <label class="form-label md:hidden">Hora de apertura</label>
              <input type="time" id="clinic_hours_{{ $i }}_opening" name="clinic_hours[{{ $i }}][opening]" value="{{ $opening }}" class="form-input" @disabled($status != '1')>
              @error("clinic_hours.{$i}.opening")<div class="text-xs text-rose-600 dark:text-rose-450 mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-span-4">
              <label class="form-label md:hidden">Hora de cierre</label>
              <input type="time" id="clinic_hours_{{ $i }}_closing" name="clinic_hours[{{ $i }}][closing]" value="{{ $closing }}" class="form-input" @disabled($status != '1')>
              @error("clinic_hours.{$i}.closing")<div class="text-xs text-rose-600 dark:text-rose-450 mt-1">{{ $message }}</div>@enderror
            </div>
          </div>
        @endforeach
      </div>
    </section>

    <script>
      function toggleClinicHoursRow(day) {
        const checkbox = document.getElementById(`clinic_hours_${day}_status`);
        const opening = document.getElementById(`clinic_hours_${day}_opening`);
        const closing = document.getElementById(`clinic_hours_${day}_closing`);
        if (checkbox && opening && closing) {
          opening.disabled = !checkbox.checked;
          closing.disabled = !checkbox.checked;
        }
      }
    </script>

    <section class="card border border-gray-200/80 bg-white/95 p-6 dark:border-gray-800/80 dark:bg-gray-900/95" data-public-preview-form-card>
      <div>
        <p class="text-xs uppercase tracking-widest text-gray-500 dark:text-gray-450">Formulario</p>
        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">Textos, labels y placeholders</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400">El preview dibuja el formulario publico, pero nunca envia datos reales.</p>
      </div>

      <div class="mt-4 grid gap-4 md:grid-cols-2">
        <div>
          <label class="form-label">Badge del bloque</label>
          <input class="form-input" name="contact_form_section_badge" value="{{ old('contact_form_section_badge', $settings['contact.form_section_badge'] ?? '') }}">
          @error('contact_form_section_badge')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Titulo del formulario</label>
          <input class="form-input" name="contact_form_title" value="{{ old('contact_form_title', $settings['contact.form_title'] ?? '') }}">
          @error('contact_form_title')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Badge lateral</label>
          <input class="form-input" name="contact_form_badge" value="{{ old('contact_form_badge', $settings['contact.form_badge'] ?? '') }}">
          @error('contact_form_badge')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Texto del boton enviar</label>
          <input class="form-input" name="contact_form_submit_text" value="{{ old('contact_form_submit_text', $settings['contact.form_submit_text'] ?? '') }}">
          @error('contact_form_submit_text')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
        </div>

        <div>
          <label class="form-label">Label nombre</label>
          <input class="form-input" name="contact_form_name_label" value="{{ old('contact_form_name_label', $settings['contact.form_name_label'] ?? '') }}">
          @error('contact_form_name_label')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Placeholder nombre</label>
          <input class="form-input" name="contact_form_name_placeholder" value="{{ old('contact_form_name_placeholder', $settings['contact.form_name_placeholder'] ?? '') }}">
          @error('contact_form_name_placeholder')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Label correo</label>
          <input class="form-input" name="contact_form_email_label" value="{{ old('contact_form_email_label', $settings['contact.form_email_label'] ?? '') }}">
          @error('contact_form_email_label')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Placeholder correo</label>
          <input class="form-input" name="contact_form_email_placeholder" value="{{ old('contact_form_email_placeholder', $settings['contact.form_email_placeholder'] ?? '') }}">
          @error('contact_form_email_placeholder')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Label telefono</label>
          <input class="form-input" name="contact_form_phone_label" value="{{ old('contact_form_phone_label', $settings['contact.form_phone_label'] ?? '') }}">
          @error('contact_form_phone_label')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Placeholder telefono</label>
          <input class="form-input" name="contact_form_phone_placeholder" value="{{ old('contact_form_phone_placeholder', $settings['contact.form_phone_placeholder'] ?? '') }}">
          @error('contact_form_phone_placeholder')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Label asunto</label>
          <input class="form-input" name="contact_form_subject_label" value="{{ old('contact_form_subject_label', $settings['contact.form_subject_label'] ?? '') }}">
          @error('contact_form_subject_label')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Placeholder asunto</label>
          <input class="form-input" name="contact_form_subject_placeholder" value="{{ old('contact_form_subject_placeholder', $settings['contact.form_subject_placeholder'] ?? '') }}">
          @error('contact_form_subject_placeholder')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Label mensaje</label>
          <input class="form-input" name="contact_form_message_label" value="{{ old('contact_form_message_label', $settings['contact.form_message_label'] ?? '') }}">
          @error('contact_form_message_label')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Texto de ayuda</label>
          <input class="form-input" name="contact_form_message_help" value="{{ old('contact_form_message_help', $settings['contact.form_message_help'] ?? '') }}">
          @error('contact_form_message_help')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
        </div>
        <div class="md:col-span-2">
          <label class="form-label">Placeholder mensaje</label>
          <textarea class="form-textarea" name="contact_form_message_placeholder" rows="3">{{ old('contact_form_message_placeholder', $settings['contact.form_message_placeholder'] ?? '') }}</textarea>
          @error('contact_form_message_placeholder')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
        </div>
      </div>
    </section>
  </div>

  @include('shared.personalizacion-public-preview-modal', [
    'title' => 'Vista previa de contacto',
    'description' => 'Representacion reactiva de la pagina publica de contacto. El formulario del preview es solo visual y no envia nada.',
  ])

  <script type="application/json" data-contact-preview-config>
    {!! json_encode($contactPreviewConfig, JSON_UNESCAPED_UNICODE) !!}
  </script>
</div>
