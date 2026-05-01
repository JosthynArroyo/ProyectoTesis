@php($settings = $settings ?? [])
@php($logo = $settings['branding.logo'] ?? null)

<div class="space-y-6">
  <section class="card p-6">
    <div>
      <p class="text-xs uppercase tracking-widest text-gray-500">Branding</p>
      <h3 class="mt-2 text-lg font-semibold text-gray-900">Identidad de marca</h3>
      <p class="text-sm text-gray-500">Nombre, logo y colores base.</p>
    </div>
    <div class="mt-4 grid gap-4 md:grid-cols-2">
      <div>
        <label class="form-label" for="branding_name">Nombre visible</label>
        <input class="form-input" id="branding_name" name="branding_name" value="{{ old('branding_name', $settings['branding.name'] ?? '') }}" required>
        @error('branding_name')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="form-label" for="branding_footer_text">Texto del pie</label>
        <input class="form-input" id="branding_footer_text" name="branding_footer_text" value="{{ old('branding_footer_text', $settings['branding.footer_text'] ?? '') }}" required>
        @error('branding_footer_text')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="form-label" for="branding_logo">Logo (PNG/JPG)</label>
        <input class="form-input" id="branding_logo" name="branding_logo" type="file" accept="image/png,image/jpeg,image/webp" @if(!$logo) required @endif>
        @error('branding_logo')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        @if($logo)
          <div class="mt-2 text-xs text-gray-500">Actual: {{ $logo }}</div>
        @endif
      </div>
      <div class="grid gap-3 sm:grid-cols-3">
        <div>
          <label class="form-label" for="branding_accent">Color base</label>
          <input class="form-input" id="branding_accent" name="branding_accent" value="{{ old('branding_accent', $settings['branding.accent'] ?? '') }}" placeholder="#0f766e" required>
          @error('branding_accent')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label" for="branding_accent_strong">Color fuerte</label>
          <input class="form-input" id="branding_accent_strong" name="branding_accent_strong" value="{{ old('branding_accent_strong', $settings['branding.accent_strong'] ?? '') }}" placeholder="#14b8a6" required>
          @error('branding_accent_strong')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label" for="branding_accent_soft">Color suave</label>
          <input class="form-input" id="branding_accent_soft" name="branding_accent_soft" value="{{ old('branding_accent_soft', $settings['branding.accent_soft'] ?? '') }}" placeholder="#ccfbf1" required>
          @error('branding_accent_soft')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
      </div>
    </div>
  </section>

  <section class="card p-6">
    <div>
      <p class="text-xs uppercase tracking-widest text-gray-500">Inicio</p>
      <h3 class="mt-2 text-lg font-semibold text-gray-900">Sección hero</h3>
      <p class="text-sm text-gray-500">Títulos y botones principales.</p>
    </div>
    <div class="mt-4 grid gap-4 md:grid-cols-2">
      <div>
        <label class="form-label" for="home_hero_badge">Badge</label>
        <input class="form-input" id="home_hero_badge" name="home_hero_badge" value="{{ old('home_hero_badge', $settings['home.hero_badge'] ?? '') }}" required>
        @error('home_hero_badge')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="form-label" for="home_hero_title">Título</label>
        <input class="form-input" id="home_hero_title" name="home_hero_title" value="{{ old('home_hero_title', $settings['home.hero_title'] ?? '') }}" required>
        @error('home_hero_title')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div class="md:col-span-2">
        <label class="form-label" for="home_hero_subtitle">Subtítulo</label>
        <input class="form-input" id="home_hero_subtitle" name="home_hero_subtitle" value="{{ old('home_hero_subtitle', $settings['home.hero_subtitle'] ?? '') }}" required>
        @error('home_hero_subtitle')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="form-label" for="home_hero_primary_text">Botón primario (texto)</label>
        <input class="form-input" id="home_hero_primary_text" name="home_hero_primary_text" value="{{ old('home_hero_primary_text', $settings['home.hero_primary_text'] ?? '') }}" required>
        @error('home_hero_primary_text')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="form-label" for="home_hero_secondary_text">Botón secundario (texto)</label>
        <input class="form-input" id="home_hero_secondary_text" name="home_hero_secondary_text" value="{{ old('home_hero_secondary_text', $settings['home.hero_secondary_text'] ?? '') }}" required>
        @error('home_hero_secondary_text')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
    </div>
  </section>

  <section class="card p-6">
    <div>
      <p class="text-xs uppercase tracking-widest text-gray-500">Home</p>
      <h3 class="mt-2 text-lg font-semibold text-gray-900">Estadisticas destacadas</h3>
    </div>
    <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      <div>
        <label class="form-label">Stat 1</label>
        <input class="form-input" name="home_hero_stat_1_label" value="{{ old('home_hero_stat_1_label', $settings['home.hero_stat_1_label'] ?? '') }}" placeholder="Etiqueta" required>
        @error('home_hero_stat_1_label')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        <input class="form-input mt-2" name="home_hero_stat_1_value" value="{{ old('home_hero_stat_1_value', $settings['home.hero_stat_1_value'] ?? '') }}" placeholder="Valor" required>
        @error('home_hero_stat_1_value')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        <input class="form-input mt-2" name="home_hero_stat_1_note" value="{{ old('home_hero_stat_1_note', $settings['home.hero_stat_1_note'] ?? '') }}" placeholder="Nota" required>
        @error('home_hero_stat_1_note')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="form-label">Stat 2</label>
        <input class="form-input" name="home_hero_stat_2_label" value="{{ old('home_hero_stat_2_label', $settings['home.hero_stat_2_label'] ?? '') }}" placeholder="Etiqueta" required>
        @error('home_hero_stat_2_label')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        <input class="form-input mt-2" name="home_hero_stat_2_value" value="{{ old('home_hero_stat_2_value', $settings['home.hero_stat_2_value'] ?? '') }}" placeholder="Valor" required>
        @error('home_hero_stat_2_value')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        <input class="form-input mt-2" name="home_hero_stat_2_note" value="{{ old('home_hero_stat_2_note', $settings['home.hero_stat_2_note'] ?? '') }}" placeholder="Nota" required>
        @error('home_hero_stat_2_note')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="form-label">Stat 3</label>
        <input class="form-input" name="home_hero_stat_3_label" value="{{ old('home_hero_stat_3_label', $settings['home.hero_stat_3_label'] ?? '') }}" placeholder="Etiqueta" required>
        @error('home_hero_stat_3_label')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        <input class="form-input mt-2" name="home_hero_stat_3_value" value="{{ old('home_hero_stat_3_value', $settings['home.hero_stat_3_value'] ?? '') }}" placeholder="Valor" required>
        @error('home_hero_stat_3_value')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        <input class="form-input mt-2" name="home_hero_stat_3_note" value="{{ old('home_hero_stat_3_note', $settings['home.hero_stat_3_note'] ?? '') }}" placeholder="Nota" required>
        @error('home_hero_stat_3_note')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
    </div>
  </section>

  <section class="card p-6">
    <div>
      <p class="text-xs uppercase tracking-widest text-gray-500">Home</p>
      <h3 class="mt-2 text-lg font-semibold text-gray-900">Sobre nosotros</h3>
    </div>
    <div class="mt-4 grid gap-4 md:grid-cols-2">
      <div>
        <label class="form-label" for="home_about_label">Etiqueta</label>
        <input class="form-input" id="home_about_label" name="home_about_label" value="{{ old('home_about_label', $settings['home.about_label'] ?? '') }}" required>
        @error('home_about_label')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="form-label" for="home_about_title">Título</label>
        <input class="form-input" id="home_about_title" name="home_about_title" value="{{ old('home_about_title', $settings['home.about_title'] ?? '') }}" required>
        @error('home_about_title')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div class="md:col-span-2">
        <label class="form-label" for="home_about_body">Descripción</label>
        <textarea class="form-textarea" id="home_about_body" name="home_about_body" rows="3" required>{{ old('home_about_body', $settings['home.about_body'] ?? '') }}</textarea>
        @error('home_about_body')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
    </div>
  </section>

  <section class="card p-6">
    <div>
      <p class="text-xs uppercase tracking-widest text-gray-500">Home</p>
      <h3 class="mt-2 text-lg font-semibold text-gray-900">Bloque de servicios</h3>
    </div>
    <div class="mt-4 grid gap-4 md:grid-cols-2">
      <div>
        <label class="form-label" for="home_services_label">Etiqueta</label>
        <input class="form-input" id="home_services_label" name="home_services_label" value="{{ old('home_services_label', $settings['home.services_label'] ?? '') }}" required>
        @error('home_services_label')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="form-label" for="home_services_title">Título</label>
        <input class="form-input" id="home_services_title" name="home_services_title" value="{{ old('home_services_title', $settings['home.services_title'] ?? '') }}" required>
        @error('home_services_title')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div class="md:col-span-2">
        <label class="form-label" for="home_services_subtitle">Subtítulo</label>
        <input class="form-input" id="home_services_subtitle" name="home_services_subtitle" value="{{ old('home_services_subtitle', $settings['home.services_subtitle'] ?? '') }}" required>
        @error('home_services_subtitle')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="form-label" for="home_services_cta_text">CTA texto</label>
        <input class="form-input" id="home_services_cta_text" name="home_services_cta_text" value="{{ old('home_services_cta_text', $settings['home.services_cta_text'] ?? '') }}" required>
        @error('home_services_cta_text')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
    </div>
  </section>

  <section class="card p-6">
    <div>
      <p class="text-xs uppercase tracking-widest text-gray-500">Home</p>
      <h3 class="mt-2 text-lg font-semibold text-gray-900">Equipo</h3>
    </div>
    <div class="mt-4 grid gap-4 md:grid-cols-2">
      <div>
        <label class="form-label" for="home_team_label">Etiqueta</label>
        <input class="form-input" id="home_team_label" name="home_team_label" value="{{ old('home_team_label', $settings['home.team_label'] ?? '') }}" required>
        @error('home_team_label')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="form-label" for="home_team_title">Título</label>
        <input class="form-input" id="home_team_title" name="home_team_title" value="{{ old('home_team_title', $settings['home.team_title'] ?? '') }}" required>
        @error('home_team_title')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div class="md:col-span-2">
        <label class="form-label" for="home_team_subtitle">Subtítulo</label>
        <input class="form-input" id="home_team_subtitle" name="home_team_subtitle" value="{{ old('home_team_subtitle', $settings['home.team_subtitle'] ?? '') }}" required>
        @error('home_team_subtitle')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div class="md:col-span-2">
        <label class="form-label" for="home_team_badge">Badge lateral</label>
        <input class="form-input" id="home_team_badge" name="home_team_badge" value="{{ old('home_team_badge', $settings['home.team_badge'] ?? '') }}" required>
        @error('home_team_badge')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
    </div>
  </section>

  <section class="card p-6">
    <div>
      <p class="text-xs uppercase tracking-widest text-gray-500">Servicios</p>
      <h3 class="mt-2 text-lg font-semibold text-gray-900">Página de servicios</h3>
    </div>
    <div class="mt-4 grid gap-4 md:grid-cols-2">
      <div>
        <label class="form-label" for="services_title">Título</label>
        <input class="form-input" id="services_title" name="services_title" value="{{ old('services_title', $settings['services.title'] ?? '') }}" required>
        @error('services_title')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="form-label" for="services_subtitle">Subtítulo</label>
        <input class="form-input" id="services_subtitle" name="services_subtitle" value="{{ old('services_subtitle', $settings['services.subtitle'] ?? '') }}" required>
        @error('services_subtitle')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="form-label" for="services_cta_text">CTA texto</label>
        <input class="form-input" id="services_cta_text" name="services_cta_text" value="{{ old('services_cta_text', $settings['services.cta_text'] ?? '') }}" required>
        @error('services_cta_text')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
    </div>
  </section>

  <section class="card p-6">
    <div>
      <p class="text-xs uppercase tracking-widest text-gray-500">Contacto</p>
      <h3 class="mt-2 text-lg font-semibold text-gray-900">Página de contacto</h3>
    </div>
    <div class="mt-4 grid gap-4 md:grid-cols-2">
      <div>
        <label class="form-label" for="contact_title">Título</label>
        <input class="form-input" id="contact_title" name="contact_title" value="{{ old('contact_title', $settings['contact.title'] ?? '') }}" required>
        @error('contact_title')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="form-label" for="contact_subtitle">Subtítulo</label>
        <input class="form-input" id="contact_subtitle" name="contact_subtitle" value="{{ old('contact_subtitle', $settings['contact.subtitle'] ?? '') }}" required>
        @error('contact_subtitle')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="form-label" for="contact_address">Dirección</label>
        <input class="form-input" id="contact_address" name="contact_address" value="{{ old('contact_address', $settings['contact.address'] ?? '') }}" required>
        @error('contact_address')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="form-label" for="contact_phone">Teléfono</label>
        <input class="form-input" id="contact_phone" name="contact_phone" value="{{ old('contact_phone', $settings['contact.phone'] ?? '') }}" inputmode="numeric" pattern="\d{10}" minlength="10" maxlength="10" data-digits="10" required>
        @error('contact_phone')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="form-label" for="contact_hours">Horario</label>
        <input class="form-input" id="contact_hours" name="contact_hours" value="{{ old('contact_hours', $settings['contact.hours'] ?? '') }}" required>
        @error('contact_hours')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="form-label" for="contact_form_title">Título formulario</label>
        <input class="form-input" id="contact_form_title" name="contact_form_title" value="{{ old('contact_form_title', $settings['contact.form_title'] ?? '') }}" required>
        @error('contact_form_title')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="form-label" for="contact_form_badge">Badge formulario</label>
        <input class="form-input" id="contact_form_badge" name="contact_form_badge" value="{{ old('contact_form_badge', $settings['contact.form_badge'] ?? '') }}" required>
        @error('contact_form_badge')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
    </div>
  </section>
</div>
