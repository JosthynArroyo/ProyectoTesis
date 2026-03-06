@php($settings = $settings ?? [])

@php
  $mapEmbed = old('contact_map_embed', $settings['contact.map_embed'] ?? '');
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

<div class="space-y-6">
  <section class="card p-6">
    <div>
      <p class="text-xs uppercase tracking-widest text-slate-500">Contacto</p>
      <h3 class="mt-2 text-lg font-semibold text-slate-900">Bloque informativo</h3>
      <p class="text-sm text-slate-500">Textos de la columna izquierda y mapa.</p>
    </div>

    <div class="mt-4 grid gap-4 md:grid-cols-2">
      <div>
        <label class="form-label">Badge</label>
        <input class="form-input" name="contact_info_badge" value="{{ old('contact_info_badge', $settings['contact.info_badge'] ?? '') }}" required>
        @error('contact_info_badge')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="form-label">Título</label>
        <input class="form-input" name="contact_title" value="{{ old('contact_title', $settings['contact.title'] ?? '') }}" required>
        @error('contact_title')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div class="md:col-span-2">
        <label class="form-label">Subtítulo</label>
        <textarea class="form-textarea" name="contact_subtitle" rows="2" required>{{ old('contact_subtitle', $settings['contact.subtitle'] ?? '') }}</textarea>
        @error('contact_subtitle')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>

      <div>
        <label class="form-label">Etiqueta dirección</label>
        <input class="form-input" name="contact_address_label" value="{{ old('contact_address_label', $settings['contact.address_label'] ?? '') }}" required>
        @error('contact_address_label')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="form-label">Dirección</label>
        <input class="form-input" name="contact_address" value="{{ old('contact_address', $settings['contact.address'] ?? '') }}" required>
        @error('contact_address')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>

      <div>
        <label class="form-label">Etiqueta teléfono</label>
        <input class="form-input" name="contact_phone_label" value="{{ old('contact_phone_label', $settings['contact.phone_label'] ?? '') }}" required>
        @error('contact_phone_label')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="form-label">Teléfono visible</label>
        <input class="form-input" name="contact_phone" value="{{ old('contact_phone', $settings['contact.phone'] ?? '') }}" required>
        @error('contact_phone')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>

      <div>
        <label class="form-label">Etiqueta horario</label>
        <input class="form-input" name="contact_hours_label" value="{{ old('contact_hours_label', $settings['contact.hours_label'] ?? '') }}" required>
        @error('contact_hours_label')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="form-label">Horario</label>
        <input class="form-input" name="contact_hours" value="{{ old('contact_hours', $settings['contact.hours'] ?? '') }}" required>
        @error('contact_hours')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>

      <div>
        <label class="form-label">Título del mapa (accesibilidad)</label>
        <input class="form-input" name="contact_map_title" value="{{ old('contact_map_title', $settings['contact.map_title'] ?? '') }}" required>
        @error('contact_map_title')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div class="md:col-span-2">
        <label class="form-label">URL embed de Google Maps</label>
        <input class="form-input" name="contact_map_embed" value="{{ old('contact_map_embed', $settings['contact.map_embed'] ?? '') }}" required>
        <p class="mt-1 text-xs text-slate-500">Ejemplo: <code>https://www.google.com/maps/embed?pb=...</code></p>
        @error('contact_map_embed')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>

      <div class="md:col-span-2 overflow-hidden rounded-2xl border border-slate-200">
        @if(!empty($mapEmbed))
          <iframe
            src="{{ $mapEmbed }}"
            title="{{ old('contact_map_title', $settings['contact.map_title'] ?? 'Mapa') }}"
            class="map-embed"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            allowfullscreen
          ></iframe>
        @else
          <div class="p-4 text-sm text-slate-500">Sin URL de mapa.</div>
        @endif
      </div>
    </div>
  </section>

  <section class="card p-6">
    <div>
      <p class="text-xs uppercase tracking-widest text-slate-500">Formulario</p>
      <h3 class="mt-2 text-lg font-semibold text-slate-900">Textos y etiquetas</h3>
      <p class="text-sm text-slate-500">Todo el contenido visible del formulario público.</p>
    </div>

    <div class="mt-4 grid gap-4 md:grid-cols-2">
      <div>
        <label class="form-label">Badge del bloque</label>
        <input class="form-input" name="contact_form_section_badge" value="{{ old('contact_form_section_badge', $settings['contact.form_section_badge'] ?? '') }}" required>
        @error('contact_form_section_badge')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="form-label">Título del formulario</label>
        <input class="form-input" name="contact_form_title" value="{{ old('contact_form_title', $settings['contact.form_title'] ?? '') }}" required>
        @error('contact_form_title')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="form-label">Badge lateral</label>
        <input class="form-input" name="contact_form_badge" value="{{ old('contact_form_badge', $settings['contact.form_badge'] ?? '') }}" required>
        @error('contact_form_badge')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="form-label">Texto botón enviar</label>
        <input class="form-input" name="contact_form_submit_text" value="{{ old('contact_form_submit_text', $settings['contact.form_submit_text'] ?? '') }}" required>
        @error('contact_form_submit_text')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>

      <div>
        <label class="form-label">Label nombre</label>
        <input class="form-input" name="contact_form_name_label" value="{{ old('contact_form_name_label', $settings['contact.form_name_label'] ?? '') }}" required>
        @error('contact_form_name_label')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="form-label">Label correo</label>
        <input class="form-input" name="contact_form_email_label" value="{{ old('contact_form_email_label', $settings['contact.form_email_label'] ?? '') }}" required>
        @error('contact_form_email_label')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="form-label">Label teléfono</label>
        <input class="form-input" name="contact_form_phone_label" value="{{ old('contact_form_phone_label', $settings['contact.form_phone_label'] ?? '') }}" required>
        @error('contact_form_phone_label')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="form-label">Label asunto</label>
        <input class="form-input" name="contact_form_subject_label" value="{{ old('contact_form_subject_label', $settings['contact.form_subject_label'] ?? '') }}" required>
        @error('contact_form_subject_label')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div class="md:col-span-2">
        <label class="form-label">Placeholder asunto</label>
        <input class="form-input" name="contact_form_subject_placeholder" value="{{ old('contact_form_subject_placeholder', $settings['contact.form_subject_placeholder'] ?? '') }}" required>
        @error('contact_form_subject_placeholder')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="form-label">Label mensaje</label>
        <input class="form-input" name="contact_form_message_label" value="{{ old('contact_form_message_label', $settings['contact.form_message_label'] ?? '') }}" required>
        @error('contact_form_message_label')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="form-label">Ayuda de mensaje</label>
        <input class="form-input" name="contact_form_message_help" value="{{ old('contact_form_message_help', $settings['contact.form_message_help'] ?? '') }}" required>
        @error('contact_form_message_help')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div class="md:col-span-2">
        <label class="form-label">Placeholder mensaje</label>
        <textarea class="form-textarea" name="contact_form_message_placeholder" rows="2" required>{{ old('contact_form_message_placeholder', $settings['contact.form_message_placeholder'] ?? '') }}</textarea>
        @error('contact_form_message_placeholder')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
    </div>
  </section>
</div>
