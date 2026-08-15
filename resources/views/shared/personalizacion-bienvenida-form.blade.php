@php
  $settings = $settings ?? [];
  $welcomeSiteSettings = $welcomeSiteSettings ?? [];
  $slidesInput = is_array(old('slides', $slides ?? [])) ? array_values(old('slides', $slides ?? [])) : [];
  $previewSlides = collect($slidesInput)->map(function ($slide, $index) use ($imageUrl) {
    $slidePath = $slide['image_path'] ?? null;
    $slideImage = $imageUrl->variants($slidePath, 'banners', 'banner', 'public_hero');

    return [
      'image_url' => $slideImage['thumb'] ?? $slideImage['src'] ?? '',
      'image_srcset' => $slideImage['srcset'] ?? '',
      'image_sizes' => '(max-width: 640px) 100vw, 50vw',
      'alt' => $slide['alt'] ?? ('Imagen '.($index + 1)),
      'title' => $slide['title'] ?? '',
      'subtitle' => $slide['subtitle'] ?? '',
      'text' => $slide['text'] ?? '',
      'is_active' => (bool) ($slide['is_active'] ?? true),
      'sort_order' => (int) ($slide['sort_order'] ?? 0),
    ];
  })->values();
  $doctorsInput = is_array(old('doctors', $doctors ?? [])) ? array_values(old('doctors', $doctors ?? [])) : [];
  $previewDoctors = collect($doctorsInput)->map(function ($doctor, $index) use ($imageUrl) {
    $doctorPath = $doctor['photo_path'] ?? null;
    $doctorImage = $imageUrl->variants($doctorPath, 'doctors', 'doctor', 'public_doctor');

    return [
      'key' => filled($doctor['id'] ?? null) ? (string) $doctor['id'] : 'doctor-'.$index,
      'image_url' => $doctorImage['thumb'] ?? $doctorImage['src'] ?? '',
      'image_srcset' => $doctorImage['srcset'] ?? '',
      'image_sizes' => '176px',
      'alt' => $doctor['name'] ?? ('Doctor '.($index + 1)),
    ];
  })->values();
  $pricesInput = is_array(old('prices', $prices ?? [])) ? array_values(old('prices', $prices ?? [])) : [];
  $featuredInput = old('featured_specialties', $featuredIds ?? []);
  $featuredInput = is_array($featuredInput) ? array_values(array_filter($featuredInput)) : [];
  $featuredInput = count($featuredInput) >= 3 ? array_slice($featuredInput, 0, 3) : array_pad($featuredInput, 3, null);
  $especialidadesActivas = collect($especialidadesActivas ?? []);
  $featuredDescriptionDefaults = old('featured_specialty_descriptions');
  if (! is_array($featuredDescriptionDefaults)) {
    $featuredDescriptionDefaults = [];
    foreach ($featuredInput as $index => $featuredId) {
      $featuredDescriptionDefaults[$index] = optional($especialidadesActivas->firstWhere('id', (int) $featuredId))->descripcion ?? '';
    }
  }
  $featuredDescriptionDefaults = array_values($featuredDescriptionDefaults);
  $featuredDescriptionDefaults = count($featuredDescriptionDefaults) >= 3
    ? array_slice($featuredDescriptionDefaults, 0, 3)
    : array_pad($featuredDescriptionDefaults, 3, '');
  $doctorSpecialties = collect(['Medicina General', 'Dermatología', 'Pediatría', 'Ginecología', 'Odontología', 'Laboratorio Clínico'])
    ->merge($especialidadesActivas->pluck('nombre'))
    ->filter()
    ->unique()
    ->values();
  $iconOptions = [
    'ri-heart-pulse-line' => 'Salud',
    'ri-stethoscope-line' => 'Estetoscopio',
    'ri-shield-check-line' => 'Protección',
    'ri-notification-4-line' => 'Recordatorio',
    'ri-calendar-check-line' => 'Agenda',
    'ri-hospital-line' => 'Clínica',
    'ri-test-tube-line' => 'Laboratorio',
    'ri-team-line' => 'Equipo',
  ];
  $headerLogoPath = old('header_logo_path', $settings['header_logo'] ?? ($welcomeSiteSettings['branding.logo'] ?? ''));
  $faviconPath = old('branding_favicon_path', $welcomeSiteSettings['branding.favicon'] ?? '');
  $headerNavigationOrder = old('header_navigation_order', array_values(array_filter(array_map('trim', explode(',', (string) ($welcomeSiteSettings['header.navigation_order'] ?? 'home,services,contact'))))));
  $headerNavigationOrder = count($headerNavigationOrder) >= 3 ? array_slice($headerNavigationOrder, 0, 3) : array_pad($headerNavigationOrder, 3, null);
  $navigationOptions = [
    'home' => 'Inicio',
    'services' => 'Servicios',
    'contact' => 'Contacto',
  ];
  $visualSoftPrimary = old('visual_soft_primary', $welcomeSiteSettings['visual.soft_primary'] ?? '#e2e8f0');
  $visualSoftSecondary = old('visual_soft_secondary', $welcomeSiteSettings['visual.soft_secondary'] ?? '#f1f5f9');
  $visualGradientStart = old('visual_gradient_start', $welcomeSiteSettings['visual.gradient_start'] ?? '#e2e8f0');
  $visualGradientEnd = old('visual_gradient_end', $welcomeSiteSettings['visual.gradient_end'] ?? '#cbd5e1');
  $visualBadgeSoft = old('visual_badge_soft', $welcomeSiteSettings['visual.badge_soft'] ?? '#e2e8f0');
  $brandingAccent = old('branding_accent', $welcomeSiteSettings['branding.accent'] ?? '#334155');
  $brandingAccentStrong = old('branding_accent_strong', $welcomeSiteSettings['branding.accent_strong'] ?? '#475569');
  $brandingAccentSoft = old('branding_accent_soft', $welcomeSiteSettings['branding.accent_soft'] ?? '#e2e8f0');
  $assetBase = asset('');
  $storageBase = asset('storage');
  $tabDefinitions = [
    ['id' => 'identidad', 'label' => 'Identidad', 'icon' => 'ri-shield-keyhole-line'],
    ['id' => 'header', 'label' => 'Header', 'icon' => 'ri-layout-top-line'],
    ['id' => 'hero', 'label' => 'Hero / Bienvenida', 'icon' => 'ri-slideshow-line'],
    ['id' => 'funcionalidades', 'label' => 'Funcionalidades', 'icon' => 'ri-function-line'],
    ['id' => 'servicios', 'label' => 'Servicios', 'icon' => 'ri-stethoscope-line'],
    ['id' => 'valores', 'label' => 'Valores referenciales', 'icon' => 'ri-money-dollar-circle-line'],
    ['id' => 'equipo', 'label' => 'Equipo médico', 'icon' => 'ri-team-line'],
    ['id' => 'footer', 'label' => 'Footer', 'icon' => 'ri-layout-bottom-line'],
    ['id' => 'visual', 'label' => 'Estilo visual', 'icon' => 'ri-palette-line'],
  ];
  $footerLinkFields = [
    ['toggle' => 'footer_show_home_link', 'label' => 'Inicio', 'field' => 'footer_home_label', 'default' => $welcomeSiteSettings['footer.show_home_link'] ?? false, 'text' => $welcomeSiteSettings['footer.home_label'] ?? 'Inicio'],
    ['toggle' => 'footer_show_services_link', 'label' => 'Servicios', 'field' => 'footer_services_label', 'default' => $welcomeSiteSettings['footer.show_services_link'] ?? true, 'text' => $welcomeSiteSettings['footer.services_label'] ?? 'Servicios'],
    ['toggle' => 'footer_show_contact_link', 'label' => 'Contacto', 'field' => 'footer_contact_label', 'default' => $welcomeSiteSettings['footer.show_contact_link'] ?? true, 'text' => $welcomeSiteSettings['footer.contact_label'] ?? 'Contacto'],
    ['toggle' => 'footer_show_assistant_link', 'label' => 'Asistente virtual', 'field' => 'footer_assistant_label', 'default' => $welcomeSiteSettings['footer.show_assistant_link'] ?? true, 'text' => $welcomeSiteSettings['footer.assistant_label'] ?? 'Asistente virtual'],
    ['toggle' => 'footer_show_privacy_link', 'label' => 'Políticas de privacidad', 'field' => 'footer_privacy_label', 'default' => $welcomeSiteSettings['footer.show_privacy_link'] ?? true, 'text' => $welcomeSiteSettings['footer.privacy_label'] ?? 'Políticas de privacidad'],
    ['toggle' => 'footer_show_terms_link', 'label' => 'Términos de servicio', 'field' => 'footer_terms_label', 'default' => $welcomeSiteSettings['footer.show_terms_link'] ?? true, 'text' => $welcomeSiteSettings['footer.terms_label'] ?? 'Términos de servicio'],
  ];
  $errorMeta = function (string $field) {
    $map = [
      'branding_name' => ['tab' => 'identidad', 'section' => 'Identidad', 'label' => 'nombre visible de la clínica'],
      'branding_navbar_text' => ['tab' => 'identidad', 'section' => 'Identidad', 'label' => 'texto corto del navbar'],
      'branding_institutional_name' => ['tab' => 'identidad', 'section' => 'Identidad', 'label' => 'nombre institucional'],
      'branding_institutional_badge' => ['tab' => 'identidad', 'section' => 'Identidad', 'label' => 'badge institucional'],
      'header_logo' => ['tab' => 'identidad', 'section' => 'Identidad', 'label' => 'logo principal'],
      'branding_favicon' => ['tab' => 'identidad', 'section' => 'Identidad', 'label' => 'favicon'],
      'header_login_text' => ['tab' => 'header', 'section' => 'Header', 'label' => 'texto del botón Ingresar'],
      'header_navigation_order' => ['tab' => 'header', 'section' => 'Header', 'label' => 'orden de navegación'],
      'hero_title' => ['tab' => 'hero', 'section' => 'Hero / Bienvenida', 'label' => 'título principal'],
      'hero_subtitle' => ['tab' => 'hero', 'section' => 'Hero / Bienvenida', 'label' => 'subtítulo'],
      'hero_primary_text' => ['tab' => 'hero', 'section' => 'Hero / Bienvenida', 'label' => 'texto del botón principal'],
      'hero_secondary_text' => ['tab' => 'hero', 'section' => 'Hero / Bienvenida', 'label' => 'texto del botón secundario'],
      'intro_badge' => ['tab' => 'hero', 'section' => 'Hero / Bienvenida', 'label' => 'badge superior'],
      'services_title' => ['tab' => 'servicios', 'section' => 'Servicios', 'label' => 'título de la sección'],
      'services_badge' => ['tab' => 'servicios', 'section' => 'Servicios', 'label' => 'badge'],
      'services_subtitle' => ['tab' => 'servicios', 'section' => 'Servicios', 'label' => 'subtítulo'],
      'services_button_text' => ['tab' => 'servicios', 'section' => 'Servicios', 'label' => 'texto del botón principal'],
      'featured_specialties' => ['tab' => 'servicios', 'section' => 'Servicios', 'label' => 'especialidades destacadas'],
      'prices_badge' => ['tab' => 'valores', 'section' => 'Valores referenciales', 'label' => 'badge'],
      'prices_title' => ['tab' => 'valores', 'section' => 'Valores referenciales', 'label' => 'título'],
      'prices_subtitle' => ['tab' => 'valores', 'section' => 'Valores referenciales', 'label' => 'subtítulo'],
      'prices_highlight_title' => ['tab' => 'valores', 'section' => 'Valores referenciales', 'label' => 'título de la tarjeta informativa'],
      'prices_highlight_subtitle' => ['tab' => 'valores', 'section' => 'Valores referenciales', 'label' => 'descripción de la tarjeta informativa'],
      'prices_visit_title' => ['tab' => 'valores', 'section' => 'Valores referenciales', 'label' => 'título de Agenda paso a paso'],
      'prices_visit_subtitle' => ['tab' => 'valores', 'section' => 'Valores referenciales', 'label' => 'descripción de Agenda paso a paso'],
      'doctors_badge' => ['tab' => 'equipo', 'section' => 'Equipo médico', 'label' => 'badge'],
      'doctors_title' => ['tab' => 'equipo', 'section' => 'Equipo médico', 'label' => 'título'],
      'doctors_subtitle' => ['tab' => 'equipo', 'section' => 'Equipo médico', 'label' => 'subtítulo'],
      'doctors_pill' => ['tab' => 'equipo', 'section' => 'Equipo médico', 'label' => 'texto institucional inferior'],
      'footer_legal_text' => ['tab' => 'footer', 'section' => 'Footer', 'label' => 'texto legal'],
      'footer_institutional_text' => ['tab' => 'footer', 'section' => 'Footer', 'label' => 'texto final institucional'],
      'footer_contact_address' => ['tab' => 'footer', 'section' => 'Footer', 'label' => 'dirección'],
      'footer_contact_phone' => ['tab' => 'footer', 'section' => 'Footer', 'label' => 'teléfono'],
      'footer_contact_email' => ['tab' => 'footer', 'section' => 'Footer', 'label' => 'correo electrónico'],
      'legal_privacy_title' => ['tab' => 'footer', 'section' => 'Footer', 'label' => 'título de política de privacidad'],
      'legal_privacy_updated_at' => ['tab' => 'footer', 'section' => 'Footer', 'label' => 'fecha de política de privacidad'],
      'legal_terms_title' => ['tab' => 'footer', 'section' => 'Footer', 'label' => 'título de términos de servicio'],
      'legal_terms_updated_at' => ['tab' => 'footer', 'section' => 'Footer', 'label' => 'fecha de términos de servicio'],
    ];

    if (isset($map[$field])) {
      return $map[$field];
    }

    if (str_starts_with($field, 'slides.')) {
      return ['tab' => 'hero', 'section' => 'Hero / Bienvenida', 'label' => 'carrusel del hero'];
    }

    if (str_starts_with($field, 'intro_feature_1_')) {
      return ['tab' => 'funcionalidades', 'section' => 'Funcionalidades', 'label' => 'mini card 1 del hero'];
    }

    if (str_starts_with($field, 'intro_feature_4_')) {
      return ['tab' => 'funcionalidades', 'section' => 'Funcionalidades', 'label' => 'mini card 2 del hero'];
    }

    if (str_starts_with($field, 'featured_specialty_descriptions.')) {
      $parts = explode('.', $field);
      $index = ((int) end($parts)) + 1;

      return ['tab' => 'servicios', 'section' => 'Servicios', 'label' => "descripción breve de la especialidad {$index}"];
    }

    if (str_starts_with($field, 'prices.')) {
      return ['tab' => 'valores', 'section' => 'Valores referenciales', 'label' => 'fila del tarifario'];
    }

    if (str_starts_with($field, 'doctors.')) {
      return ['tab' => 'equipo', 'section' => 'Equipo médico', 'label' => 'tarjeta del equipo médico'];
    }

    return ['tab' => 'identidad', 'section' => 'Identidad', 'label' => $field];
  };
  $errorMeta = function (string $field) {
    $map = [
      'branding_name' => ['tab' => 'identidad', 'section' => 'Identidad', 'label' => 'nombre visible de la clínica'],
      'branding_navbar_text' => ['tab' => 'identidad', 'section' => 'Identidad', 'label' => 'texto corto del navbar'],
      'branding_institutional_name' => ['tab' => 'identidad', 'section' => 'Identidad', 'label' => 'nombre institucional'],
      'branding_institutional_badge' => ['tab' => 'identidad', 'section' => 'Identidad', 'label' => 'badge institucional'],
      'header_logo' => ['tab' => 'identidad', 'section' => 'Identidad', 'label' => 'logo principal'],
      'branding_favicon' => ['tab' => 'identidad', 'section' => 'Identidad', 'label' => 'favicon'],
      'header_login_text' => ['tab' => 'header', 'section' => 'Header', 'label' => 'texto del botón Iniciar sesión'],
      'header_navigation_order' => ['tab' => 'header', 'section' => 'Header', 'label' => 'orden de navegación'],
      'hero_title' => ['tab' => 'hero', 'section' => 'Hero / Bienvenida', 'label' => 'título principal'],
      'hero_subtitle' => ['tab' => 'hero', 'section' => 'Hero / Bienvenida', 'label' => 'subtítulo principal'],
      'hero_primary_text' => ['tab' => 'hero', 'section' => 'Hero / Bienvenida', 'label' => 'texto del botón principal'],
      'hero_secondary_text' => ['tab' => 'hero', 'section' => 'Hero / Bienvenida', 'label' => 'texto del botón secundario'],
      'intro_badge' => ['tab' => 'hero', 'section' => 'Hero / Bienvenida', 'label' => 'badge superior'],
      'services_title' => ['tab' => 'servicios', 'section' => 'Servicios', 'label' => 'título de la sección'],
      'services_badge' => ['tab' => 'servicios', 'section' => 'Servicios', 'label' => 'badge'],
      'services_subtitle' => ['tab' => 'servicios', 'section' => 'Servicios', 'label' => 'subtítulo'],
      'services_button_text' => ['tab' => 'servicios', 'section' => 'Servicios', 'label' => 'texto del botón principal'],
      'featured_specialties' => ['tab' => 'servicios', 'section' => 'Servicios', 'label' => 'especialidades destacadas'],
      'prices_badge' => ['tab' => 'valores', 'section' => 'Valores referenciales', 'label' => 'badge'],
      'prices_title' => ['tab' => 'valores', 'section' => 'Valores referenciales', 'label' => 'título'],
      'prices_subtitle' => ['tab' => 'valores', 'section' => 'Valores referenciales', 'label' => 'subtítulo'],
      'prices_highlight_title' => ['tab' => 'valores', 'section' => 'Valores referenciales', 'label' => 'título de la tarjeta informativa'],
      'prices_highlight_subtitle' => ['tab' => 'valores', 'section' => 'Valores referenciales', 'label' => 'descripción de la tarjeta informativa'],
      'prices_visit_title' => ['tab' => 'valores', 'section' => 'Valores referenciales', 'label' => 'título de Agenda paso a paso'],
      'prices_visit_subtitle' => ['tab' => 'valores', 'section' => 'Valores referenciales', 'label' => 'descripción de Agenda paso a paso'],
      'doctors_badge' => ['tab' => 'equipo', 'section' => 'Equipo médico', 'label' => 'badge'],
      'doctors_title' => ['tab' => 'equipo', 'section' => 'Equipo médico', 'label' => 'título'],
      'doctors_subtitle' => ['tab' => 'equipo', 'section' => 'Equipo médico', 'label' => 'subtítulo'],
      'doctors_pill' => ['tab' => 'equipo', 'section' => 'Equipo médico', 'label' => 'texto institucional inferior'],
      'branding_accent' => ['tab' => 'visual', 'section' => 'Estilo visual', 'label' => 'color principal'],
      'branding_accent_strong' => ['tab' => 'visual', 'section' => 'Estilo visual', 'label' => 'color principal intenso'],
      'branding_accent_soft' => ['tab' => 'visual', 'section' => 'Estilo visual', 'label' => 'color principal suave'],
      'visual_soft_primary' => ['tab' => 'visual', 'section' => 'Estilo visual', 'label' => 'color suave 1'],
      'visual_soft_secondary' => ['tab' => 'visual', 'section' => 'Estilo visual', 'label' => 'color suave 2'],
      'visual_gradient_start' => ['tab' => 'visual', 'section' => 'Estilo visual', 'label' => 'inicio del degradado'],
      'visual_gradient_end' => ['tab' => 'visual', 'section' => 'Estilo visual', 'label' => 'fin del degradado'],
      'visual_badge_soft' => ['tab' => 'visual', 'section' => 'Estilo visual', 'label' => 'color de badges suaves'],
      'footer_legal_text' => ['tab' => 'footer', 'section' => 'Footer', 'label' => 'texto legal'],
      'footer_institutional_text' => ['tab' => 'footer', 'section' => 'Footer', 'label' => 'texto final institucional'],
      'footer_contact_address' => ['tab' => 'footer', 'section' => 'Footer', 'label' => 'dirección'],
      'footer_contact_phone' => ['tab' => 'footer', 'section' => 'Footer', 'label' => 'teléfono'],
      'footer_contact_email' => ['tab' => 'footer', 'section' => 'Footer', 'label' => 'correo electrónico'],
      'legal_privacy_title' => ['tab' => 'footer', 'section' => 'Footer', 'label' => 'título de política de privacidad'],
      'legal_privacy_updated_at' => ['tab' => 'footer', 'section' => 'Footer', 'label' => 'fecha de política de privacidad'],
      'legal_terms_title' => ['tab' => 'footer', 'section' => 'Footer', 'label' => 'título de términos de servicio'],
      'legal_terms_updated_at' => ['tab' => 'footer', 'section' => 'Footer', 'label' => 'fecha de términos de servicio'],
    ];

    if (isset($map[$field])) {
      return $map[$field];
    }

    if (str_starts_with($field, 'slides.')) {
      $segments = explode('.', $field);
      $slideNumber = isset($segments[1]) ? ((int) $segments[1]) + 1 : null;
      $slideField = $segments[2] ?? null;
      $slideLabels = [
        'image' => 'imagen del slide',
        'image_path' => 'imagen actual del slide',
        'alt' => 'texto alternativo del slide',
        'title' => 'título del slide',
        'subtitle' => 'subtítulo del slide',
        'text' => 'descripción breve del slide',
        'sort_order' => 'orden del slide',
        'is_active' => 'visibilidad del slide',
      ];

      return [
        'tab' => 'hero',
        'section' => 'Hero / Bienvenida',
        'label' => trim(($slideLabels[$slideField] ?? 'slide del carrusel').($slideNumber ? " {$slideNumber}" : '')),
      ];
    }

    if (str_starts_with($field, 'intro_feature_1_')) {
      return ['tab' => 'funcionalidades', 'section' => 'Funcionalidades', 'label' => 'mini card 1 del hero'];
    }

    if (str_starts_with($field, 'intro_feature_4_')) {
      return ['tab' => 'funcionalidades', 'section' => 'Funcionalidades', 'label' => 'mini card 2 del hero'];
    }

    if (str_starts_with($field, 'featured_specialty_descriptions.')) {
      $parts = explode('.', $field);
      $index = ((int) end($parts)) + 1;

      return ['tab' => 'servicios', 'section' => 'Servicios', 'label' => "descripción breve de la especialidad {$index}"];
    }

    if (str_starts_with($field, 'prices.')) {
      return ['tab' => 'valores', 'section' => 'Valores referenciales', 'label' => 'fila del tarifario'];
    }

    if ($field === 'doctors') {
      return ['tab' => 'equipo', 'section' => 'Equipo médico', 'label' => 'límite de doctores destacados'];
    }

    if (str_starts_with($field, 'doctors.')) {
      $segments = explode('.', $field);
      $doctorNumber = isset($segments[1]) ? ((int) $segments[1]) + 1 : null;
      $doctorField = $segments[2] ?? null;
      $doctorLabels = [
        'name' => 'nombre del doctor',
        'specialty' => 'especialidad del doctor',
        'photo' => 'foto del doctor',
        'photo_path' => 'foto actual del doctor',
        'experience_label' => 'experiencia del doctor',
        'featured_label' => 'badge destacado del doctor',
        'attendance_label' => 'texto de atención presencial',
        'availability_label' => 'texto de agenda disponible',
        'cta_text' => 'texto del botón del doctor',
        'pill_text' => 'pill inferior del doctor',
        'sort_order' => 'orden visual del doctor',
        'is_active' => 'visibilidad del doctor',
      ];

      return [
        'tab' => 'equipo',
        'section' => 'Equipo médico',
        'label' => trim(($doctorLabels[$doctorField] ?? 'tarjeta del equipo médico').($doctorNumber ? " {$doctorNumber}" : '')),
      ];
    }

    return ['tab' => 'identidad', 'section' => 'Identidad', 'label' => $field];
  };

  $activeTab = old('active_tab', $tabDefinitions[0]['id']);
  if ($errors->any()) {
    $firstErrorField = array_key_first($errors->getMessages());
    $activeTab = $errorMeta((string) $firstErrorField)['tab'];
  }
@endphp

@once
  <style>
    .welcome-cms-editor button,
    .welcome-cms-editor summary,
    .welcome-cms-editor input[type='file'],
    .welcome-cms-editor input[type='checkbox'],
    .welcome-cms-editor select,
    .welcome-cms-editor [role='tab'],
    .welcome-cms-editor [data-remove-item],
    .welcome-cms-editor [data-add-slide],
    .welcome-cms-editor [data-add-price],
    .welcome-cms-editor [data-add-doctor],
    .welcome-cms-editor [data-preview-open],
    .welcome-cms-editor [data-preview-close],
    .welcome-cms-editor [data-live-preview-device],
    .welcome-cms-editor input[type='color'] {
      cursor: pointer;
    }

    .welcome-cms-editor label:has(input[type='checkbox']),
    .welcome-cms-editor label:has(input[type='file']) {
      cursor: pointer;
    }

    .welcome-cms-panel[hidden] {
      display: none;
    }

    .welcome-cms-media-frame {
      position: relative;
      overflow: hidden;
      border-radius: 1.25rem;
      border: 1px solid rgba(226, 232, 240, 0.95);
      background:
        linear-gradient(145deg, rgba(255, 255, 255, 0.98), rgba(241, 245, 249, 0.94));
      box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.85),
        0 14px 34px rgba(15, 23, 42, 0.08);
    }

    html.panel-theme-dark .welcome-cms-media-frame {
      border-color: rgba(75, 85, 99, 0.8);
      background: linear-gradient(145deg, #111827, #1f2937);
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05), 0 14px 34px rgba(0, 0, 0, 0.4);
    }

    .welcome-cms-media-frame--slide {
      aspect-ratio: 16 / 10;
      min-height: 10rem;
    }

    .welcome-cms-media-frame--doctor {
      aspect-ratio: 4 / 5;
      min-height: 11rem;
    }

    .welcome-cms-media-frame img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    .welcome-cms-media-placeholder {
      display: flex;
      height: 100%;
      width: 100%;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 0.65rem;
      padding: 1rem;
      color: #94a3b8;
      text-align: center;
    }

    .welcome-cms-media-placeholder i {
      font-size: 1.7rem;
      color: #64748b;
    }

    .welcome-cms-media-placeholder span {
      font-size: 0.78rem;
      line-height: 1.45;
    }

    .welcome-cms-tab,
    .welcome-cms-preview-device,
    .welcome-cms-action-button {
      cursor: pointer;
    }

    .welcome-cms-tab.is-active {
      background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
      border-color: color-mix(in srgb, var(--preview-accent) 24%, white);
      color: var(--preview-accent);
      box-shadow: 0 18px 32px rgba(15, 23, 42, 0.1);
    }

    html.panel-theme-dark .welcome-cms-tab.is-active {
      background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
      border-color: color-mix(in srgb, var(--preview-accent) 28%, #334155);
      color: var(--preview-accent);
      box-shadow: 0 18px 32px rgba(15, 23, 42, 0.18);
    }

    .welcome-cms-preview-modal[hidden] {
      display: none;
    }

    .welcome-cms-preview-modal {
      position: fixed;
      inset: 0;
      z-index: 200;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.25rem;
      isolation: isolate;
    }

    .welcome-cms-preview-backdrop {
      position: absolute;
      inset: 0;
      background: rgba(15, 23, 42, 0.72);
      backdrop-filter: blur(10px);
    }

    .welcome-cms-preview-dialog {
      position: relative;
      z-index: 1;
      width: min(1440px, 100%);
      height: min(92vh, 980px);
      max-height: calc(100dvh - 2rem);
      display: flex;
      flex-direction: column;
      overflow: hidden;
      border-radius: 2rem;
      border: 1px solid rgba(226, 232, 240, 0.9);
      background: rgba(255, 255, 255, 0.98);
      box-shadow: 0 32px 80px rgba(15, 23, 42, 0.32);
    }

    html.panel-theme-dark .welcome-cms-preview-dialog {
      border-color: rgba(75, 85, 99, 0.8);
      background: #111827;
      box-shadow: 0 32px 80px rgba(0, 0, 0, 0.6);
    }

    .welcome-cms-preview-scroll {
      flex: 1;
      padding: 1rem;
      overflow-y: auto;
      overflow-x: hidden;
      min-width: 0;
      overscroll-behavior: contain;
      background:
        radial-gradient(circle at top left, rgba(191, 219, 254, 0.26), transparent 32%),
        radial-gradient(circle at top right, rgba(167, 243, 208, 0.2), transparent 24%),
        linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
    }

    html.panel-theme-dark .welcome-cms-preview-scroll {
      background:
        radial-gradient(circle at top left, rgba(30, 41, 59, 0.4), transparent 32%),
        radial-gradient(circle at top right, rgba(20, 83, 45, 0.2), transparent 24%),
        linear-gradient(180deg, #0f172a 0%, #030712 100%);
    }

    .welcome-cms-preview-stage {
      --preview-scale: 1;
      --preview-desktop-width: 1180px;
      --preview-frame-height: 0px;
      width: 100%;
      min-width: 0;
      margin: 0 auto;
      display: flex;
      align-items: flex-start;
      justify-content: center;
      overflow-x: hidden;
    }

    .welcome-cms-preview-scale {
      position: relative;
      display: flex;
      justify-content: center;
      width: calc(var(--preview-desktop-width) * var(--preview-scale));
      height: calc(var(--preview-frame-height) * var(--preview-scale));
      min-height: calc(var(--preview-frame-height) * var(--preview-scale));
      flex: none;
      margin-inline: auto;
    }

    .welcome-cms-preview-frame {
      position: absolute;
      top: 0;
      left: 0;
      width: var(--preview-desktop-width);
      transform: scale(var(--preview-scale));
      transform-origin: top left;
      will-change: transform;
    }

    .welcome-cms-preview-device.is-active {
      background: #ffffff;
      color: var(--preview-accent);
      box-shadow: 0 12px 24px rgba(15, 23, 42, 0.12);
    }

    .welcome-cms-live-preview-surface {
      min-height: 760px;
      border-radius: 1.75rem;
      border: 1px solid rgba(203, 213, 225, 0.9);
      background: #ffffff;
      box-shadow: 0 18px 45px rgba(15, 23, 42, 0.12);
      overflow: hidden;
    }

    .welcome-cms-color-input {
      width: 4rem;
      height: 4rem;
      border: 0;
      padding: 0;
      background: transparent;
      border-radius: 1rem;
      overflow: hidden;
      flex-shrink: 0;
    }

    .welcome-cms-color-input::-webkit-color-swatch-wrapper {
      padding: 0;
    }

    .welcome-cms-color-input::-webkit-color-swatch,
    .welcome-cms-color-input::-moz-color-swatch {
      border: 0;
      border-radius: 1rem;
    }

    .welcome-live-preview {
      --preview-accent: {{ $brandingAccent }};
      --preview-accent-strong: color-mix(in srgb, var(--preview-accent) 82%, black);
      --preview-accent-soft: color-mix(in srgb, var(--preview-accent) 14%, white);
      --preview-soft-primary: {{ $visualSoftPrimary }};
      --preview-soft-secondary: {{ $visualSoftSecondary }};
      --preview-gradient-start: {{ $visualGradientStart }};
      --preview-gradient-end: {{ $visualGradientEnd }};
      --preview-badge-soft: {{ $visualBadgeSoft }};
      background:
        radial-gradient(circle at top left, color-mix(in srgb, var(--preview-gradient-start) 48%, transparent), transparent 38%),
        radial-gradient(circle at top right, color-mix(in srgb, var(--preview-gradient-end) 38%, transparent), transparent 28%),
        linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
      min-height: 100%;
      color: #0f172a;
    }

    .welcome-live-preview__inner {
      display: flex;
      flex-direction: column;
      gap: 1rem;
      padding: 1rem;
    }

    .welcome-live-preview__section {
      border: 1px solid rgba(226, 232, 240, 0.9);
      border-radius: 1.5rem;
      background: rgba(255, 255, 255, 0.94);
      box-shadow: 0 18px 35px rgba(15, 23, 42, 0.07);
      overflow: hidden;
    }

    .welcome-live-preview__topbar,
    .welcome-live-preview__hero,
    .welcome-live-preview__section-header,
    .welcome-live-preview__prices-header,
    .welcome-live-preview__footer-grid {
      padding: 1rem 1.1rem;
    }

    .welcome-live-preview__topbar {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      border-bottom: 1px solid rgba(226, 232, 240, 0.8);
      background: rgba(255, 255, 255, 0.95);
    }

    .welcome-live-preview__brand {
      display: flex;
      align-items: center;
      gap: 0.8rem;
      min-width: 0;
      flex: 1 1 220px;
    }

    .welcome-live-preview__brand-logo,
    .welcome-live-preview__brand-favicon,
    .welcome-live-preview__image-placeholder {
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      border-radius: 1rem;
      background: #f8fafc;
      border: 1px solid rgba(226, 232, 240, 0.9);
      color: #64748b;
      flex-shrink: 0;
    }

    .welcome-live-preview__brand-favicon {
      width: 2.75rem;
      height: 2.75rem;
    }

    .welcome-live-preview__brand-logo {
      width: 3.6rem;
      height: 3.6rem;
      background: #ffffff;
    }

    .welcome-live-preview__brand-logo img,
    .welcome-live-preview__brand-favicon img,
    .welcome-live-preview__hero-media img,
    .welcome-live-preview__doctor-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    .welcome-live-preview__brand-copy {
      min-width: 0;
    }

    .welcome-live-preview__brand-title,
    .welcome-live-preview__footer-title {
      margin: 0;
      font-size: 0.95rem;
      font-weight: 700;
      color: #0f172a;
      line-height: 1.3;
    }

    .welcome-live-preview__brand-subtitle,
    .welcome-live-preview__footer-subtitle,
    .welcome-live-preview__microcopy {
      margin: 0.15rem 0 0;
      font-size: 0.76rem;
      color: #64748b;
      line-height: 1.5;
    }

    .welcome-live-preview__nav {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 0.5rem;
      flex: 1 1 240px;
    }

    .welcome-live-preview__nav-item,
    .welcome-live-preview__cta,
    .welcome-live-preview__ghost-button {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.4rem;
      border-radius: 999px;
      font-size: 0.78rem;
      font-weight: 600;
      line-height: 1;
      white-space: nowrap;
    }

    .welcome-live-preview__nav-item {
      padding: 0.7rem 0.95rem;
      background: color-mix(in srgb, var(--preview-soft-primary) 42%, white);
      color: var(--preview-accent);
    }

    .welcome-live-preview__ghost-button {
      padding: 0.75rem 1rem;
      border: 1px solid color-mix(in srgb, var(--preview-accent) 18%, white);
      background: rgba(255, 255, 255, 0.94);
      color: var(--preview-accent);
    }

    .welcome-live-preview__hero {
      display: grid;
      gap: 1rem;
      background:
        linear-gradient(135deg, color-mix(in srgb, var(--preview-gradient-start) 58%, white), color-mix(in srgb, var(--preview-gradient-end) 62%, white));
    }

    .welcome-live-preview__hero-badge,
    .welcome-live-preview__section-kicker,
    .welcome-live-preview__doctor-pill,
    .welcome-live-preview__meta-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      width: fit-content;
      padding: 0.5rem 0.8rem;
      border-radius: 999px;
      background: color-mix(in srgb, var(--preview-badge-soft) 76%, white);
      color: var(--preview-accent);
      font-size: 0.72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }

    .welcome-live-preview__meta-badge {
      margin-top: 0.7rem;
      text-transform: none;
      letter-spacing: 0;
      font-size: 0.76rem;
      background: rgba(255, 255, 255, 0.82);
      color: #334155;
    }

    .welcome-live-preview__hero-title {
      margin: 0.9rem 0 0;
      font-size: clamp(1.9rem, 3vw, 2.8rem);
      line-height: 1.08;
      font-weight: 800;
      color: #0f172a;
    }

    .welcome-live-preview__hero-text,
    .welcome-live-preview__section-text,
    .welcome-live-preview__card-text,
    .welcome-live-preview__footer-text,
    .welcome-live-preview__contact-text {
      margin: 0.8rem 0 0;
      color: #475569;
      font-size: 0.88rem;
      line-height: 1.65;
    }

    .welcome-live-preview__cta-row,
    .welcome-live-preview__footer-links,
    .welcome-live-preview__doctor-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 0.65rem;
      margin-top: 1rem;
    }

    .welcome-live-preview__cta {
      padding: 0.85rem 1rem;
    }

    .welcome-live-preview__cta--primary {
      background: linear-gradient(135deg, var(--preview-accent), var(--preview-accent-strong));
      color: #ffffff;
      box-shadow: 0 16px 28px color-mix(in srgb, var(--preview-accent) 24%, transparent);
    }

    .welcome-live-preview__cta--secondary {
      border: 1px solid color-mix(in srgb, var(--preview-accent) 16%, white);
      background: rgba(255, 255, 255, 0.92);
      color: var(--preview-accent);
    }

    .welcome-live-preview__support-grid,
    .welcome-live-preview__stats,
    .welcome-live-preview__cards,
    .welcome-live-preview__services,
    .welcome-live-preview__prices-list,
    .welcome-live-preview__doctors,
    .welcome-live-preview__footer-grid,
    .welcome-live-preview__prices-grid {
      display: grid;
      gap: 0.75rem;
    }

    .welcome-live-preview__support-grid {
      margin-top: 1rem;
    }

    .welcome-live-preview__support-card,
    .welcome-live-preview__stat,
    .welcome-live-preview__card,
    .welcome-live-preview__service-card,
    .welcome-live-preview__price-row,
    .welcome-live-preview__doctor-card,
    .welcome-live-preview__footer-column {
      border: 1px solid rgba(226, 232, 240, 0.85);
      border-radius: 1.25rem;
      background: rgba(255, 255, 255, 0.95);
    }

    .welcome-live-preview__support-card,
    .welcome-live-preview__card,
    .welcome-live-preview__service-card,
    .welcome-live-preview__doctor-copy,
    .welcome-live-preview__footer-column,
    .welcome-live-preview__prices-highlight-copy {
      padding: 1rem;
    }

    .welcome-live-preview__stats,
    .welcome-live-preview__cards,
    .welcome-live-preview__services,
    .welcome-live-preview__prices-grid,
    .welcome-live-preview__doctors {
      padding: 0 1.1rem 1.1rem;
    }

    .welcome-live-preview__stat,
    .welcome-live-preview__price-row {
      padding: 0.9rem 1rem;
    }

    .welcome-live-preview__hero-media,
    .welcome-live-preview__prices-highlight-image,
    .welcome-live-preview__doctor-image {
      overflow: hidden;
      background: linear-gradient(
        135deg,
        color-mix(in srgb, var(--preview-accent-soft) 70%, white),
        color-mix(in srgb, var(--preview-gradient-end) 55%, white)
      );
    }

    .welcome-live-preview__hero-media {
      min-height: 14rem;
      border-radius: 1.35rem;
      border: 1px solid rgba(226, 232, 240, 0.9);
      padding: 1rem;
    }

    .welcome-live-preview__hero-media img {
      min-height: 14rem;
      object-fit: cover;
    }

    .welcome-live-preview__hero-gallery {
      display: grid;
      grid-template-columns: minmax(0, 1.25fr) minmax(0, 0.85fr);
      gap: 0.75rem;
      min-height: 100%;
    }

    .welcome-live-preview__hero-gallery-item {
      display: flex;
      flex-direction: column;
      overflow: hidden;
      min-height: 11rem;
      border-radius: 1.2rem;
      border: 1px solid rgba(226, 232, 240, 0.92);
      background: rgba(255, 255, 255, 0.96);
      box-shadow: 0 20px 40px rgba(15, 23, 42, 0.08);
    }

    .welcome-live-preview__hero-gallery-item--primary {
      grid-row: span 2;
      min-height: 100%;
    }

    .welcome-live-preview__hero-gallery-media {
      min-height: 8.5rem;
      flex: 1 1 auto;
      overflow: hidden;
      background: linear-gradient(
        135deg,
        color-mix(in srgb, var(--preview-accent-soft) 70%, white),
        color-mix(in srgb, var(--preview-gradient-start) 58%, white)
      );
    }

    .welcome-live-preview__hero-gallery-media img {
      width: 100%;
      height: 100%;
      min-height: 8.5rem;
      object-fit: cover;
      display: block;
    }

    .welcome-live-preview__hero-gallery-copy {
      padding: 0.85rem 0.9rem 0.95rem;
    }

    .welcome-live-preview__section-title,
    .welcome-live-preview__doctor-name,
    .welcome-live-preview__price-title {
      margin: 0.35rem 0 0;
      color: #0f172a;
      font-size: 1.05rem;
      font-weight: 700;
      line-height: 1.35;
    }

    .welcome-live-preview__stat-value {
      font-size: 1.15rem;
      font-weight: 800;
      color: #0f172a;
      margin-top: 0.2rem;
    }

    .welcome-live-preview__stat-label,
    .welcome-live-preview__eyebrow,
    .welcome-live-preview__price-value {
      font-size: 0.76rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: #64748b;
    }

    .welcome-live-preview__price-value {
      color: var(--preview-accent);
      text-transform: none;
      letter-spacing: 0;
      font-size: 0.92rem;
    }

    .welcome-live-preview__cards,
    .welcome-live-preview__services,
    .welcome-live-preview__doctors,
    .welcome-live-preview__footer-grid {
      grid-template-columns: repeat(auto-fit, minmax(0, 1fr));
    }

    .welcome-live-preview__card-icon,
    .welcome-live-preview__service-icon,
    .welcome-live-preview__doctor-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 2.75rem;
      height: 2.75rem;
      border-radius: 1rem;
      background: color-mix(in srgb, var(--preview-soft-primary) 64%, white);
      color: var(--preview-accent);
      font-size: 1.2rem;
      box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--preview-accent) 8%, white);
    }

    .welcome-live-preview__service-card {
      display: flex;
      flex-direction: column;
      gap: 0.8rem;
    }

    .welcome-live-preview__prices-highlight {
      overflow: hidden;
      border-radius: 1.35rem;
      border: 1px solid rgba(226, 232, 240, 0.9);
      background: rgba(255, 255, 255, 0.96);
    }

    .welcome-live-preview__prices-highlight-image {
      min-height: 12rem;
    }

    .welcome-live-preview__doctor-card {
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }

    .welcome-live-preview__doctor-image {
      height: 11rem;
    }

    .welcome-live-preview__doctor-meta span {
      border-radius: 999px;
      padding: 0.45rem 0.7rem;
      background: #f8fafc;
      color: #475569;
      font-size: 0.76rem;
      font-weight: 600;
    }

    .welcome-live-preview__footer {
      background: #ffffff;
    }

    .welcome-live-preview__footer-grid {
      gap: 1.5rem;
      grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .welcome-live-preview__footer-column {
      padding: 0;
      border: 0;
      border-radius: 0;
      background: transparent;
      box-shadow: none;
    }

    .welcome-live-preview__footer-title {
      margin: 0;
      font-size: 0.88rem;
      letter-spacing: -0.01em;
    }

    .welcome-live-preview__footer .welcome-live-preview__section-title {
      margin-top: 0;
      font-size: 0.82rem;
    }

    .welcome-live-preview__footer-text,
    .welcome-live-preview__contact-text,
    .welcome-live-preview__footer-link {
      font-size: 0.76rem;
      line-height: 1.7;
    }

    .welcome-live-preview__footer-link {
      color: #475569;
      font-weight: 500;
    }

    .welcome-live-preview__empty {
      padding: 1rem;
      border-radius: 1.1rem;
      border: 1px dashed rgba(148, 163, 184, 0.5);
      background: rgba(248, 250, 252, 0.9);
      color: #64748b;
      font-size: 0.84rem;
      text-align: center;
    }

    @media (min-width: 900px) {
      .welcome-live-preview__hero,
      .welcome-live-preview__prices-grid {
        grid-template-columns: minmax(0, 1.02fr) minmax(220px, 0.98fr);
      }

      .welcome-live-preview__support-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    @media (max-width: 768px) {
      .welcome-cms-preview-modal {
        padding: 0.75rem;
      }

      .welcome-cms-preview-dialog {
        width: 100%;
        height: min(94vh, 100%);
        border-radius: 1.5rem;
      }

      .welcome-cms-preview-scroll {
        padding: 0.75rem;
      }
    }

    .welcome-cms-swatch { background: linear-gradient(135deg, var(--swatch-start), var(--swatch-end)); }
  </style>
@endonce

@if ($errors->any())
  <x-ui.alert tone="danger">
    <div class="space-y-2">
      <p class="font-medium">Corrige los campos con error antes de guardar.</p>
      <ul class="list-disc space-y-1 pl-5">
        @foreach($errors->messages() as $field => $messages)
          @php($errorLocation = $errorMeta((string) $field))
          @foreach($messages as $message)
            <li>Error en {{ $errorLocation['section'] }} > {{ $errorLocation['label'] }}: {{ \Illuminate\Support\Str::lcfirst($message) }}</li>
          @endforeach
        @endforeach
      </ul>
    </div>
  </x-ui.alert>
@endif

<input type="hidden" name="active_tab" value="{{ $activeTab }}" data-active-tab-input>
<div class="welcome-cms-editor space-y-6" data-bienvenida-form data-asset-base="{{ $assetBase }}" data-storage-base="{{ $storageBase }}" data-r2-url="{{ rtrim(config('filesystems.disks.r2_public.url', ''), '/') }}" data-current-year="{{ now()->year }}" data-initial-tab="{{ $activeTab }}" @isset($batchStatusRouteName) data-batch-status-url-template="{{ route($batchStatusRouteName, ['uuid' => '__UUID__']) }}" @endisset>
  <section class="card overflow-hidden border border-gray-200/80 bg-white/95 dark:border-gray-800/80 dark:bg-gray-900/95">
    <div class="border-b border-gray-200/80 bg-gradient-to-r from-gray-50 via-white to-gray-100/40 px-6 py-5 dark:border-gray-800/80 dark:bg-gradient-to-r dark:from-gray-950/70 dark:via-gray-900/40 dark:to-gray-950/70">
      <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
        <div class="space-y-2">
          <div class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-gray-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-gray-700 dark:border-gray-850 dark:bg-gray-800 dark:text-gray-300">
            <i class="ri-dashboard-line"></i>
            Personalización de Bienvenida
          </div>
          <div>
            <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">Editor CMS para la página de inicio</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Organiza identidad, navegación, hero, servicios, equipo, footer y estilo visual sin tocar la vista pública.</p>
          </div>
        </div>
        <div class="grid gap-2 text-sm text-gray-500 sm:grid-cols-3">
          <div class="rounded-2xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-950/40">
            <p class="text-xs uppercase tracking-[0.18em] text-gray-400 dark:text-gray-500">Modo</p>
            <p class="mt-1 font-medium text-gray-700 dark:text-gray-200">CMS escalable</p>
          </div>
          <div class="rounded-2xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-950/40">
            <p class="text-xs uppercase tracking-[0.18em] text-gray-400 dark:text-gray-500">Guardado</p>
            <p class="mt-1 font-medium text-gray-700 dark:text-gray-200">Compatible con admin/superadmin</p>
          </div>
          <div class="rounded-2xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-950/40">
            <p class="text-xs uppercase tracking-[0.18em] text-gray-400 dark:text-gray-500">Vista</p>
            <p class="mt-1 font-medium text-gray-700 dark:text-gray-200">Vista previa en vivo</p>
          </div>
        </div>
      </div>
    </div>

    <div class="overflow-x-auto px-4 py-4">
      <div class="flex min-w-max gap-2" role="tablist" aria-label="Secciones de personalización de bienvenida">
        @foreach($tabDefinitions as $tab)
          <button
            type="button"
            class="welcome-cms-tab inline-flex items-center gap-2 rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-600 transition {{ $loop->first ? 'is-active' : '' }} dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200"
            data-tab-target="{{ $tab['id'] }}"
            role="tab"
            aria-selected="{{ $loop->first ? 'true' : 'false' }}"
            aria-controls="welcome-tab-{{ $tab['id'] }}"
          >
            <i class="{{ $tab['icon'] }}"></i>
            {{ $tab['label'] }}
          </button>
        @endforeach
      </div>
    </div>
  </section>

  <section class="card border border-gray-200/80 bg-white/95 px-6 py-5 dark:border-gray-800/80 dark:bg-gray-900/95">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <p class="text-xs uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500">Acciones</p>
        <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Gestiona la bienvenida y revisa la vista previa reactiva</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">La vista previa se abre en un modal grande y muestra tus cambios sin guardar, sin cargar la página real.</p>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <button type="button" class="welcome-cms-action-button btn btn-outline dark:border-gray-700 dark:bg-gray-850 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-white" data-preview-open>
          <i class="ri-macbook-line"></i> Ver vista previa
        </button>
        <button type="submit" class="welcome-cms-action-button btn btn-primary">
          <i class="ri-save-line"></i> Guardar cambios
        </button>
      </div>
    </div>
  </section>

  <div class="space-y-6">
      <section id="welcome-tab-identidad" data-tab-panel="identidad" class="welcome-cms-panel space-y-5" role="tabpanel">
        <details open class="card overflow-hidden border border-gray-200/80 bg-white/95 dark:border-gray-800/80 dark:bg-gray-900/95">
          <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-6 py-5">
            <div>
              <p class="text-xs uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500">Identidad</p>
              <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Logo principal, favicon y naming institucional</h3>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Desde aquí se gestiona el branding visible del header, del favicon y del contenido institucional.</p>
            </div>
            <i class="ri-arrow-down-s-line text-xl text-gray-400"></i>
          </summary>
          <div class="border-t border-gray-200/80 px-6 py-6 dark:border-gray-800/80">
            <div class="grid gap-5 lg:grid-cols-3">
              <div class="rounded-3xl border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-800 dark:bg-gray-800/40">
                <p class="text-sm font-semibold text-gray-900 dark:text-white">Logo principal del header</p>
                <div class="mt-4 flex h-36 items-center justify-center rounded-2xl border border-dashed border-gray-300 bg-white dark:border-gray-700 dark:bg-gray-950/20">
                  @php($headerLogoPreview = $imageUrl->variants($headerLogoPath, 'branding', 'banner', 'branding_asset'))
                  <img src="{{ $headerLogoPreview['thumb'] }}" @if($headerLogoPreview['srcset']) srcset="{{ $headerLogoPreview['srcset'] }}" sizes="180px" @endif alt="Logo principal" class="max-h-20 w-auto" loading="lazy" decoding="async" data-form-preview-image="header-logo">
                </div>
                <input type="hidden" name="header_logo_path" value="{{ $headerLogoPath }}">
                <label class="form-label mt-4">Reemplazar archivo</label>
                <input class="form-input" type="file" name="header_logo" accept="image/png,image/jpeg,image/webp,image/svg+xml">
              </div>

              <div class="rounded-3xl border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-800 dark:bg-gray-800/40">
                <p class="text-sm font-semibold text-gray-900 dark:text-white">Favicon</p>
                <div class="mt-4 flex h-36 items-center justify-center rounded-2xl border border-dashed border-gray-300 bg-white dark:border-gray-700 dark:bg-gray-950/20">
                  <div data-form-preview-favicon-wrap>
                    @if($faviconPath)
                      @php($faviconPreview = $imageUrl->variants($faviconPath, 'branding', 'banner', 'branding_asset'))
                      <img src="{{ $faviconPreview['thumb'] }}" alt="Favicon" class="h-14 w-14 rounded-2xl object-cover" loading="lazy" decoding="async" data-form-preview-image="favicon">
                    @else
                      <div class="flex h-14 w-14 items-center justify-center rounded-2xl border border-gray-200 bg-gray-50 text-gray-400 dark:border-gray-750 dark:bg-gray-800">
                        <i class="ri-global-line text-xl"></i>
                      </div>
                    @endif
                  </div>
                </div>
                <input type="hidden" name="branding_favicon_path" value="{{ $faviconPath }}">
                <label class="form-label mt-4">Reemplazar archivo</label>
                <input class="form-input" type="file" name="branding_favicon" accept="image/png,image/webp,image/svg+xml">
              </div>
            </div>

            <div class="mt-6 grid gap-4 lg:grid-cols-2">
              <div>
                <label class="form-label">Nombre visible de la clínica</label>
                <input class="form-input" name="branding_name" value="{{ old('branding_name', $welcomeSiteSettings['branding.name'] ?? ($settings['header_name'] ?? '')) }}">
              </div>
              <div>
                <label class="form-label">Texto corto del navbar</label>
                <input class="form-input" name="branding_navbar_text" value="{{ old('branding_navbar_text', $welcomeSiteSettings['branding.navbar_text'] ?? '') }}">
              </div>
              <div>
                <label class="form-label">Nombre institucional</label>
                <input class="form-input" name="branding_institutional_name" value="{{ old('branding_institutional_name', $welcomeSiteSettings['branding.institutional_name'] ?? ($welcomeSiteSettings['branding.name'] ?? '')) }}">
              </div>
              <div>
                <label class="form-label">Badge institucional</label>
                <input class="form-input" name="branding_institutional_badge" value="{{ old('branding_institutional_badge', $welcomeSiteSettings['branding.institutional_badge'] ?? '') }}">
              </div>
            </div>
          </div>
        </details>
      </section>

      <section id="welcome-tab-header" data-tab-panel="header" class="welcome-cms-panel space-y-5" role="tabpanel" hidden>
        <details open class="card overflow-hidden border border-gray-200/80 bg-white/95 dark:border-gray-800/80 dark:bg-gray-900/95">
          <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-6 py-5">
            <div>
              <p class="text-xs uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500">Header</p>
              <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Navegación principal</h3>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Controla visibilidad, orden y comportamiento del navbar público.</p>
            </div>
            <i class="ri-arrow-down-s-line text-xl text-gray-400"></i>
          </summary>
          <div class="border-t border-gray-200/80 px-6 py-6 dark:border-gray-800/80">
            <div class="grid gap-4 lg:grid-cols-2">
              <label class="inline-flex items-start gap-3 rounded-2xl border border-gray-200 bg-gray-50/80 px-4 py-4 text-sm text-gray-600 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-300">
                <input type="hidden" name="header_show_home" value="0">
                <input type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300" name="header_show_home" value="1" @checked(old('header_show_home', $welcomeSiteSettings['header.show_home'] ?? true))>
                <span><span class="block font-medium text-gray-900 dark:text-white">Mostrar Inicio</span><span class="block text-xs text-gray-500 dark:text-gray-400">Mantiene el acceso a la landing desde el header.</span></span>
              </label>
              <label class="inline-flex items-start gap-3 rounded-2xl border border-gray-200 bg-gray-50/80 px-4 py-4 text-sm text-gray-600 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-300">
                <input type="hidden" name="header_show_services" value="0">
                <input type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300" name="header_show_services" value="1" @checked(old('header_show_services', $welcomeSiteSettings['header.show_services'] ?? true))>
                <span><span class="block font-medium text-gray-900 dark:text-white">Mostrar Servicios</span><span class="block text-xs text-gray-500 dark:text-gray-400">Expone el catálogo público de especialidades.</span></span>
              </label>
              <label class="inline-flex items-start gap-3 rounded-2xl border border-gray-200 bg-gray-50/80 px-4 py-4 text-sm text-gray-600 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-300">
                <input type="hidden" name="header_show_contact" value="0">
                <input type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300" name="header_show_contact" value="1" @checked(old('header_show_contact', $welcomeSiteSettings['header.show_contact'] ?? true))>
                <span><span class="block font-medium text-gray-900 dark:text-white">Mostrar Contacto</span><span class="block text-xs text-gray-500 dark:text-gray-400">Permite acceso directo a la página de contacto.</span></span>
              </label>
              <label class="inline-flex items-start gap-3 rounded-2xl border border-gray-200 bg-gray-50/80 px-4 py-4 text-sm text-gray-600 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-300">
                <input type="hidden" name="header_sticky_enabled" value="0">
                <input type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300" name="header_sticky_enabled" value="1" @checked(old('header_sticky_enabled', $welcomeSiteSettings['header.sticky_enabled'] ?? true))>
                <span><span class="block font-medium text-gray-900 dark:text-white">Header fijo</span><span class="block text-xs text-gray-500 dark:text-gray-400">Mantiene el header visible al hacer scroll.</span></span>
              </label>
            </div>
            <div class="mt-6 grid gap-4 lg:grid-cols-2">
              <div>
                <label class="form-label">Texto del botón Ingresar</label>
                <input class="form-input" name="header_login_text" maxlength="25" value="{{ old('header_login_text', $settings['header_login_text'] ?? 'Ingresar') }}">
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Máximo 25 caracteres.</p>
              </div>
            </div>
            <div class="mt-6">
              <p class="text-sm font-semibold text-gray-900 dark:text-white">Orden de navegación</p>
              <div class="mt-4 grid gap-4 sm:grid-cols-3">
                @for($i = 0; $i < 3; $i++)
                  <div>
                    <label class="form-label">Posición {{ $i + 1 }}</label>
                    <select class="form-input" name="header_navigation_order[]" data-navigation-select>
                      <option value="">Seleccionar</option>
                      @foreach($navigationOptions as $navigationKey => $navigationLabel)
                        <option value="{{ $navigationKey }}" @selected(($headerNavigationOrder[$i] ?? null) === $navigationKey)>{{ $navigationLabel }}</option>
                      @endforeach
                    </select>
                  </div>
                @endfor
              </div>
              <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Cada opción solo puede aparecer una vez.</p>
            </div>
          </div>
        </details>
      </section>

      <section id="welcome-tab-hero" data-tab-panel="hero" class="welcome-cms-panel space-y-5" role="tabpanel" hidden>
        <details open class="card overflow-hidden border border-gray-200/80 bg-white/95 dark:border-gray-800/80 dark:bg-gray-900/95">
          <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-6 py-5">
            <div>
              <p class="text-xs uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500">Hero / Bienvenida</p>
              <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Contenido principal y llamadas a la acción</h3>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Edita el contenido visible del hero real: badge, título, subtítulo, botones y carrusel.</p>
            </div>
            <i class="ri-arrow-down-s-line text-xl text-gray-400"></i>
          </summary>
          <div class="border-t border-gray-200/80 px-6 py-6 dark:border-gray-800/80">
            <div class="grid gap-4 lg:grid-cols-2">
              <div class="lg:col-span-2">
                <label class="form-label">Badge superior</label>
                <input class="form-input" name="intro_badge" maxlength="60" value="{{ old('intro_badge', $settings['intro_badge'] ?? '') }}">
              </div>
              <div class="lg:col-span-2">
                <label class="form-label">Título principal</label>
                <input class="form-input" name="hero_title" value="{{ old('hero_title', $settings['hero_title'] ?? '') }}">
              </div>
              <div class="lg:col-span-2">
                <label class="form-label">Subtítulo</label>
                <textarea class="form-textarea" name="hero_subtitle" rows="3">{{ old('hero_subtitle', $settings['hero_subtitle'] ?? '') }}</textarea>
              </div>
              <div>
                <label class="form-label">Texto del botón principal</label>
                <input class="form-input" name="hero_primary_text" maxlength="25" value="{{ old('hero_primary_text', $settings['hero_primary_text'] ?? '') }}">
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Máximo 25 caracteres.</p>
                <label class="mt-3 inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                  <input type="hidden" name="hero_show_primary" value="0">
                  <input type="checkbox" class="h-4 w-4 rounded border-gray-300" name="hero_show_primary" value="1" @checked(old('hero_show_primary', $settings['hero_show_primary'] ?? true))>
                  Mostrar botón principal
                </label>
              </div>
              <div>
                <label class="form-label">Texto del botón secundario</label>
                <input class="form-input" name="hero_secondary_text" maxlength="25" value="{{ old('hero_secondary_text', $settings['hero_secondary_text'] ?? '') }}">
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Máximo 25 caracteres.</p>
                <label class="mt-3 inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                  <input type="hidden" name="hero_show_secondary" value="0">
                  <input type="checkbox" class="h-4 w-4 rounded border-gray-300" name="hero_show_secondary" value="1" @checked(old('hero_show_secondary', $settings['hero_show_secondary'] ?? true))>
                  Mostrar botón secundario
                </label>
              </div>
            </div>
          </div>
        </details>

        <details open class="card overflow-hidden border border-gray-200/80 bg-white/95 dark:border-gray-800/80 dark:bg-gray-900/95">
          <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-6 py-5">
            <div>
              <p class="text-xs uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500">Carrusel</p>
              <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Imágenes reales del hero</h3>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Administra solo los elementos que hoy existen en la vista pública: imagen, texto alternativo, orden y visibilidad.</p>
            </div>
            <i class="ri-arrow-down-s-line text-xl text-gray-400"></i>
          </summary>
          <div class="border-t border-gray-200/80 px-6 py-6 dark:border-gray-800/80">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div>
                <p class="text-sm font-semibold text-gray-900 dark:text-white">Slides del hero</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Activa, ordena y reemplaza imágenes del carrusel.</p>
              </div>
              <button type="button" class="btn btn-outline dark:border-gray-700 dark:bg-gray-850 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-white" data-add-slide><i class="ri-add-line"></i> Agregar slide</button>
            </div>
            <div class="mt-4 grid gap-4" data-slide-list data-next-index="{{ count($slidesInput) }}">
              @foreach($slidesInput as $index => $slide)
                @php($slidePath = $slide['image_path'] ?? null)
                <div class="rounded-3xl border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-800 dark:bg-gray-800/40" data-slide-row data-row-key="slide-{{ $index }}">
                  <div class="grid gap-6">
                    <div class="flex flex-col sm:flex-row items-start gap-5 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-950/20">
                      <div class="w-full sm:w-[240px] shrink-0">
                        <div class="welcome-cms-media-frame welcome-cms-media-frame--slide" data-inline-image-preview data-preview-icon="ri-image-line" data-preview-placeholder="Vista previa del slide">
                          @php($slideImage = $imageUrl->variants($slidePath, 'banners', 'banner', 'public_hero'))
                          @if($slidePath)
                            <img src="{{ $slideImage['thumb'] }}" @if($slideImage['srcset']) srcset="{{ $slideImage['srcset'] }}" sizes="(max-width: 640px) 100vw, 240px" @endif alt="Slide" loading="lazy" decoding="async">
                          @else
                            <div class="welcome-cms-media-placeholder">
                              <i class="ri-image-line"></i>
                              <span>Vista previa del slide</span>
                            </div>
                          @endif
                        </div>
                      </div>
                      <div class="flex-1 w-full flex flex-col justify-center min-h-[10rem]">
                        <label class="form-label font-semibold text-gray-800 dark:text-white">Imagen del slide</label>
                        <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">Selecciona una imagen horizontal de alta calidad. Formatos recomendados: JPG, WEBP, PNG.</p>
                        <input class="form-input w-full" type="file" name="slides[{{ $index }}][image]" accept="image/*">
                        <input type="hidden" name="slides[{{ $index }}][image_path]" value="{{ $slidePath }}">
                      </div>
                    </div>
                    <div class="grid gap-4 lg:grid-cols-2">
                      <div><label class="form-label">Título visible</label><input class="form-input" name="slides[{{ $index }}][title]" maxlength="120" value="{{ $slide['title'] ?? '' }}"></div>
                      <div><label class="form-label">Subtítulo visible</label><input class="form-input" name="slides[{{ $index }}][subtitle]" maxlength="120" value="{{ $slide['subtitle'] ?? '' }}"></div>
                      <div class="lg:col-span-2"><label class="form-label">Descripción breve visible</label><textarea class="form-textarea" name="slides[{{ $index }}][text]" rows="3" maxlength="240">{{ $slide['text'] ?? '' }}</textarea></div>
                      <div class="lg:col-span-2"><label class="form-label">Texto alternativo accesible</label><input class="form-input" name="slides[{{ $index }}][alt]" value="{{ $slide['alt'] ?? '' }}"></div>
                      <div><label class="form-label">Orden</label><input class="form-input" type="number" min="0" name="slides[{{ $index }}][sort_order]" value="{{ $slide['sort_order'] ?? 0 }}"></div>
                      <div class="flex items-end pb-2">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                          <input type="hidden" name="slides[{{ $index }}][is_active]" value="0">
                          <input type="checkbox" class="h-4 w-4 rounded border-gray-300" name="slides[{{ $index }}][is_active]" value="1" @checked($slide['is_active'] ?? true)>
                          Activo
                        </label>
                      </div>
                    </div>
                  </div>
                  <button type="button" class="btn btn-ghost mt-4 dark:text-gray-300 dark:hover:bg-gray-800" data-remove-item>Quitar</button>
                </div>
              @endforeach
            </div>
          </div>
        </details>
      </section>

      <section id="welcome-tab-funcionalidades" data-tab-panel="funcionalidades" class="welcome-cms-panel space-y-5" role="tabpanel" hidden>
        <details open class="card overflow-hidden border border-gray-200/80 bg-white/95 dark:border-gray-800/80 dark:bg-gray-900/95">
          <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-6 py-5">
            <div>
              <p class="text-xs uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500">Funcionalidades</p>
              <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Mini cards visibles del hero</h3>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Solo se editan las dos cards que hoy aparecen debajo del subtítulo del hero.</p>
            </div>
            <i class="ri-arrow-down-s-line text-xl text-gray-400"></i>
          </summary>
          <div class="border-t border-gray-200/80 px-6 py-6 dark:border-gray-800/80">
            <div class="grid gap-4 lg:grid-cols-2">
              <div class="rounded-3xl border border-gray-200 bg-gray-50/80 p-4 dark:border-gray-800 dark:bg-gray-800/40">
                <p class="text-sm font-semibold text-gray-900 dark:text-white">Mini card 1</p>
                <div class="mt-4 space-y-4">
                  <div><label class="form-label">Título</label><input class="form-input" name="intro_feature_1_title" value="{{ old('intro_feature_1_title', $settings['intro_feature_1_title'] ?? '') }}"></div>
                  <div><label class="form-label">Descripción</label><textarea class="form-textarea" name="intro_feature_1_text" rows="3">{{ old('intro_feature_1_text', $settings['intro_feature_1_text'] ?? '') }}</textarea></div>
                </div>
              </div>
              <div class="rounded-3xl border border-gray-200 bg-gray-50/80 p-4 dark:border-gray-800 dark:bg-gray-800/40">
                <p class="text-sm font-semibold text-gray-900 dark:text-white">Mini card 2</p>
                <div class="mt-4 space-y-4">
                  <div><label class="form-label">Título</label><input class="form-input" name="intro_feature_4_title" value="{{ old('intro_feature_4_title', $settings['intro_feature_4_title'] ?? '') }}"></div>
                  <div><label class="form-label">Descripción</label><textarea class="form-textarea" name="intro_feature_4_text" rows="3">{{ old('intro_feature_4_text', $settings['intro_feature_4_text'] ?? '') }}</textarea></div>
                </div>
              </div>
            </div>
          </div>
        </details>
      </section>

      <section id="welcome-tab-servicios" data-tab-panel="servicios" class="welcome-cms-panel space-y-5" role="tabpanel" hidden>
        <details open class="card overflow-hidden border border-gray-200/80 bg-white/95 dark:border-gray-800/80 dark:bg-gray-900/95">
          <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-6 py-5">
            <div>
              <p class="text-xs uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500">Servicios</p>
              <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Bloque de especialidades destacadas</h3>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Define textos, CTA, especialidades exactas y la descripción breve visible debajo de cada una.</p>
            </div>
            <i class="ri-arrow-down-s-line text-xl text-gray-400"></i>
          </summary>
          <div class="border-t border-gray-200/80 px-6 py-6 dark:border-gray-800/80">
            <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
              <input type="hidden" name="show_services_block" value="0">
              <input type="checkbox" class="h-4 w-4 rounded border-gray-300" name="show_services_block" value="1" @checked(old('show_services_block', $settings['show_services_block'] ?? true))>
              Mostrar bloque de servicios en la landing
            </label>
            <div class="mt-6 grid gap-4 lg:grid-cols-2">
              <div><label class="form-label">Título de la sección</label><input class="form-input" name="services_title" value="{{ old('services_title', $settings['services_title'] ?? '') }}"></div>
              <div><label class="form-label">Badge</label><input class="form-input" name="services_badge" value="{{ old('services_badge', $settings['services_badge'] ?? '') }}"></div>
              <div class="lg:col-span-2"><label class="form-label">Subtítulo</label><textarea class="form-textarea" name="services_subtitle" rows="3">{{ old('services_subtitle', $settings['services_subtitle'] ?? '') }}</textarea></div>
              <div>
                <label class="form-label">Texto del botón principal</label>
                <input class="form-input" name="services_button_text" maxlength="25" value="{{ old('services_button_text', $settings['services_button_text'] ?? '') }}">
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Máximo 25 caracteres.</p>
              </div>
            </div>
            <div class="mt-6 grid gap-4 md:grid-cols-3">
              @for($i = 0; $i < 3; $i++)
                <div class="rounded-3xl border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-800 dark:bg-gray-800/40" data-featured-specialty-slot>
                  <label class="form-label">Especialidad {{ $i + 1 }}</label>
                  <select class="form-input" name="featured_specialties[]" data-featured-specialty-select>
                    <option value="">Seleccionar especialidad</option>
                    @foreach($especialidadesActivas as $esp)
                      <option value="{{ $esp->id }}" @selected(($featuredInput[$i] ?? null) == $esp->id)>{{ $esp->nombre }}</option>
                    @endforeach
                  </select>
                  <div class="mt-4">
                    <label class="form-label">Descripción breve visible</label>
                    <textarea class="form-textarea" name="featured_specialty_descriptions[]" rows="3" maxlength="240" data-featured-specialty-description>{{ $featuredDescriptionDefaults[$i] ?? '' }}</textarea>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Se guardará en la especialidad seleccionada y se mostrará en Welcome.</p>
                  </div>
                </div>
              @endfor
            </div>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Una especialidad no puede repetirse en más de una posición.</p>
          </div>
        </details>
      </section>

      <section id="welcome-tab-valores" data-tab-panel="valores" class="welcome-cms-panel space-y-5" role="tabpanel" hidden>
        <details open class="card overflow-hidden border border-gray-200/80 bg-white/95 dark:border-gray-800/80 dark:bg-gray-900/95">
          <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-6 py-5">
            <div>
              <p class="text-xs uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500">Valores referenciales</p>
              <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Tarifario, tarjeta informativa y agenda paso a paso</h3>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Organiza el bloque actual de valores sin imágenes laterales obsoletas.</p>
            </div>
            <i class="ri-arrow-down-s-line text-xl text-gray-400"></i>
          </summary>
          <div class="border-t border-gray-200/80 px-6 py-6 dark:border-gray-800/80">
            <div class="grid gap-4 lg:grid-cols-2">
              <div><label class="form-label">Badge</label><input class="form-input" name="prices_badge" value="{{ old('prices_badge', $settings['prices_badge'] ?? '') }}"></div>
              <div><label class="form-label">Título</label><input class="form-input" name="prices_title" value="{{ old('prices_title', $settings['prices_title'] ?? '') }}"></div>
              <div class="lg:col-span-2"><label class="form-label">Subtítulo</label><textarea class="form-textarea" name="prices_subtitle" rows="3">{{ old('prices_subtitle', $settings['prices_subtitle'] ?? '') }}</textarea></div>
            </div>
            <div class="mt-6 grid gap-4 lg:grid-cols-2">
              <div><label class="form-label">Tarjeta informativa: título</label><input class="form-input" name="prices_highlight_title" value="{{ old('prices_highlight_title', $settings['prices_highlight_title'] ?? '') }}"></div>
              <div><label class="form-label">Agenda paso a paso: título</label><input class="form-input" name="prices_visit_title" value="{{ old('prices_visit_title', $settings['prices_visit_title'] ?? '') }}"></div>
              <div><label class="form-label">Tarjeta informativa: descripción</label><textarea class="form-textarea" name="prices_highlight_subtitle" rows="3">{{ old('prices_highlight_subtitle', $settings['prices_highlight_subtitle'] ?? '') }}</textarea></div>
              <div><label class="form-label">Agenda paso a paso: descripción</label><textarea class="form-textarea" name="prices_visit_subtitle" rows="3">{{ old('prices_visit_subtitle', $settings['prices_visit_subtitle'] ?? '') }}</textarea></div>
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
              <div>
                <p class="text-sm font-semibold text-gray-900 dark:text-white">Lista de precios</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Puedes usar valores o el texto Consultar.</p>
              </div>
              <button type="button" class="btn btn-outline dark:border-gray-700 dark:bg-gray-850 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-white" data-add-price><i class="ri-add-line"></i> Agregar fila</button>
            </div>
            <div class="mt-4 grid gap-4" data-price-list data-next-index="{{ count($pricesInput) }}">
              @foreach($pricesInput as $index => $price)
                <div class="rounded-3xl border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-800 dark:bg-gray-800/40" data-price-row data-row-key="price-{{ $index }}">
                  <div class="grid gap-4 lg:grid-cols-2">
                    <div><label class="form-label">Especialidad / servicio</label><input class="form-input" name="prices[{{ $index }}][service]" value="{{ $price['service'] ?? '' }}"></div>
                    <div><label class="form-label">Valor</label><input class="form-input" name="prices[{{ $index }}][price]" value="{{ $price['price'] ?? '' }}"></div>
                    <div><label class="form-label">Orden</label><input class="form-input" type="number" min="0" name="prices[{{ $index }}][sort_order]" value="{{ $price['sort_order'] ?? 0 }}"></div>
                    <div class="flex items-end">
                      <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                        <input type="hidden" name="prices[{{ $index }}][is_active]" value="0">
                        <input type="checkbox" class="h-4 w-4 rounded border-gray-300" name="prices[{{ $index }}][is_active]" value="1" @checked($price['is_active'] ?? true)>
                        Mostrar fila
                      </label>
                    </div>
                  </div>
                  <button type="button" class="btn btn-ghost mt-4 dark:text-gray-300 dark:hover:bg-gray-800" data-remove-item>Quitar</button>
                </div>
              @endforeach
            </div>
          </div>
        </details>
      </section>

      <section id="welcome-tab-equipo" data-tab-panel="equipo" class="welcome-cms-panel space-y-5" role="tabpanel" hidden>
        <details open class="card overflow-hidden border border-gray-200/80 bg-white/95 dark:border-gray-800/80 dark:bg-gray-900/95">
          <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-6 py-5">
            <div>
              <p class="text-xs uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500">Equipo médico</p>
              <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Directorio visual de doctores</h3>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Incluye nombre, especialidad real, experiencia, badges y estados visibles en el home.</p>
            </div>
            <i class="ri-arrow-down-s-line text-xl text-gray-400"></i>
          </summary>
          <div class="border-t border-gray-200/80 px-6 py-6 dark:border-gray-800/80">
            <div class="grid gap-4 lg:grid-cols-2">
              <div><label class="form-label">Badge</label><input class="form-input" name="doctors_badge" value="{{ old('doctors_badge', $settings['doctors_badge'] ?? '') }}"></div>
              <div><label class="form-label">Título</label><input class="form-input" name="doctors_title" value="{{ old('doctors_title', $settings['doctors_title'] ?? '') }}"></div>
              <div class="lg:col-span-2"><label class="form-label">Subtítulo</label><textarea class="form-textarea" name="doctors_subtitle" rows="3">{{ old('doctors_subtitle', $settings['doctors_subtitle'] ?? '') }}</textarea></div>
              <div class="lg:col-span-2"><label class="form-label">Texto institucional inferior</label><input class="form-input" name="doctors_pill" value="{{ old('doctors_pill', $settings['doctors_pill'] ?? '') }}"></div>
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
              <div>
                <p class="text-sm font-semibold text-gray-900 dark:text-white">Lista de doctores</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Usa especialidades reales del sistema y evita textos desalineados con la landing actual.</p>
              </div>
              <button type="button" class="btn btn-outline dark:border-gray-700 dark:bg-gray-850 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-white" data-add-doctor><i class="ri-add-line"></i> Agregar doctor</button>
            </div>
            <div class="mt-3 rounded-2xl border border-gray-200 bg-gray-50/80 px-4 py-3 text-sm text-gray-600 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-300" data-doctor-active-summary>
              Máximo 3 doctores pueden estar marcados como visibles en la landing pública.
            </div>
            @error('doctors')
              <div class="mt-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/20 dark:text-rose-300">{{ $message }}</div>
            @enderror
            <div class="mt-4 grid gap-4" data-doctor-list data-next-index="{{ count($doctorsInput) }}">
              @foreach($doctorsInput as $index => $doctor)
                @php($doctorPhoto = $doctor['photo_path'] ?? null)
                <div class="rounded-3xl border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-800 dark:bg-gray-800/40" data-doctor-row data-row-key="doctor-{{ $index }}" data-doctor-id="{{ $doctor['id'] ?? '' }}">
                  <div class="grid gap-4 xl:grid-cols-[176px_minmax(0,1fr)]">
                    <div class="welcome-cms-media-frame welcome-cms-media-frame--doctor" data-inline-image-preview data-preview-icon="ri-user-3-line" data-preview-placeholder="Vista previa del doctor">
                      @php($doctorImage = $imageUrl->variants($doctorPhoto, 'doctors', 'doctor', 'public_doctor'))
                      @if($doctorPhoto)
                        <img src="{{ $doctorImage['thumb'] }}" @if($doctorImage['srcset']) srcset="{{ $doctorImage['srcset'] }}" sizes="176px" @endif alt="Doctor" loading="lazy" decoding="async">
                      @else
                        <div class="welcome-cms-media-placeholder">
                          <i class="ri-user-3-line"></i>
                          <span>Vista previa del doctor</span>
                        </div>
                      @endif
                    </div>
                    <div class="grid gap-4 lg:grid-cols-2">
                      <div><label class="form-label">Nombre</label><input class="form-input" name="doctors[{{ $index }}][name]" value="{{ $doctor['name'] ?? '' }}"></div>
                      <div>
                        <label class="form-label">Especialidad</label>
                        <select class="form-input" name="doctors[{{ $index }}][specialty]">
                          <option value="">Seleccionar especialidad</option>
                          @foreach($doctorSpecialties as $specialtyOption)
                            <option value="{{ $specialtyOption }}" @selected(($doctor['specialty'] ?? '') === $specialtyOption)>{{ $specialtyOption }}</option>
                          @endforeach
                          @if(filled($doctor['specialty'] ?? null) && ! $doctorSpecialties->contains($doctor['specialty']))
                            <option value="{{ $doctor['specialty'] }}" selected>{{ $doctor['specialty'] }}</option>
                          @endif
                        </select>
                      </div>
                      <div class="lg:col-span-2">
                        <label class="form-label">Foto</label>
                        <input class="form-input" type="file" name="doctors[{{ $index }}][photo]" accept="image/*">
                        <input type="hidden" name="doctors[{{ $index }}][id]" value="{{ $doctor['id'] ?? '' }}">
                        <input type="hidden" name="doctors[{{ $index }}][photo_path]" value="{{ $doctorPhoto }}">
                      </div>
                      <div><label class="form-label">Experiencia</label><input class="form-input" name="doctors[{{ $index }}][experience_label]" value="{{ $doctor['experience_label'] ?? '' }}"></div>
                      <div><label class="form-label">Badge destacado</label><input class="form-input" name="doctors[{{ $index }}][featured_label]" value="{{ $doctor['featured_label'] ?? '' }}"></div>
                      <div><label class="form-label">Atención presencial</label><input class="form-input" name="doctors[{{ $index }}][attendance_label]" value="{{ $doctor['attendance_label'] ?? '' }}"></div>
                      <div><label class="form-label">Agenda disponible</label><input class="form-input" name="doctors[{{ $index }}][availability_label]" value="{{ $doctor['availability_label'] ?? '' }}"></div>
                      <div>
                        <label class="form-label">Texto del botón</label>
                        <input class="form-input" name="doctors[{{ $index }}][cta_text]" maxlength="25" value="{{ $doctor['cta_text'] ?? '' }}">
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Máximo 25 caracteres.</p>
                      </div>
                      <div>
                        <label class="form-label">Pill inferior</label>
                        <input class="form-input" name="doctors[{{ $index }}][pill_text]" maxlength="60" value="{{ $doctor['pill_text'] ?? '' }}">
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Máximo 60 caracteres.</p>
                      </div>
                      <div><label class="form-label">Orden visual</label><input class="form-input" type="number" min="0" name="doctors[{{ $index }}][sort_order]" value="{{ $doctor['sort_order'] ?? 0 }}"></div>
                      <div class="flex items-end">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                          <input type="hidden" name="doctors[{{ $index }}][is_active]" value="0">
                          <input type="checkbox" class="h-4 w-4 rounded border-gray-300" name="doctors[{{ $index }}][is_active]" value="1" @checked($doctor['is_active'] ?? true)>
                          Mostrar doctor
                        </label>
                      </div>
                    </div>
                  </div>
                  <button type="button" class="btn btn-ghost mt-4 dark:text-gray-300 dark:hover:bg-gray-800" data-remove-item>Quitar</button>
                </div>
              @endforeach
            </div>
          </div>
        </details>
      </section>

      <section id="welcome-tab-footer" data-tab-panel="footer" class="welcome-cms-panel space-y-5" role="tabpanel" hidden>
        <details open class="card overflow-hidden border border-gray-200/80 bg-white/95 dark:border-gray-800/80 dark:bg-gray-900/95">
          <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-6 py-5">
            <div>
              <p class="text-xs uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500">Footer</p>
              <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Texto legal, enlaces, contacto y legales</h3>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">La vista pública sigue usando los mismos partials, ahora alimentados desde este panel.</p>
            </div>
            <i class="ri-arrow-down-s-line text-xl text-gray-400"></i>
          </summary>
          <div class="border-t border-gray-200/80 px-6 py-6 dark:border-gray-800/80">
            <div class="grid gap-4 lg:grid-cols-2">
              <div><label class="form-label">Texto legal</label><input class="form-input" name="footer_legal_text" value="{{ old('footer_legal_text', $welcomeSiteSettings['branding.footer_text'] ?? '') }}"></div>
              <div><label class="form-label">Texto final institucional</label><input class="form-input" name="footer_institutional_text" value="{{ old('footer_institutional_text', $welcomeSiteSettings['footer.institutional_text'] ?? '') }}"></div>
              <div><label class="form-label">Dirección</label><input class="form-input" name="footer_contact_address" value="{{ old('footer_contact_address', $welcomeSiteSettings['contact.address'] ?? '') }}"></div>
              <div><label class="form-label">Teléfono</label><input class="form-input" name="footer_contact_phone" value="{{ old('footer_contact_phone', $welcomeSiteSettings['contact.phone'] ?? '') }}"></div>
              <div><label class="form-label">Correo electrónico</label><input class="form-input" name="footer_contact_email" value="{{ old('footer_contact_email', $welcomeSiteSettings['contact.email'] ?? '') }}"></div>
            </div>
            <div class="mt-6">
              <p class="text-sm font-semibold text-gray-900 dark:text-white">Enlaces visibles</p>
              <div class="mt-4 grid gap-4 lg:grid-cols-2">
                @foreach($footerLinkFields as $linkField)
                  <div class="rounded-3xl border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-800 dark:bg-gray-800/40">
                    <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-900 dark:text-white">
                      <input type="hidden" name="{{ $linkField['toggle'] }}" value="0">
                      <input type="checkbox" class="h-4 w-4 rounded border-gray-300" name="{{ $linkField['toggle'] }}" value="1" @checked(old($linkField['toggle'], $linkField['default']))>
                      Mostrar {{ $linkField['label'] }}
                    </label>
                    <div class="mt-3">
                      <label class="form-label">Texto visible</label>
                      <input class="form-input" name="{{ $linkField['field'] }}" value="{{ old($linkField['field'], $linkField['text']) }}">
                    </div>
                  </div>
                @endforeach
              </div>
            </div>
            <div class="mt-6 grid gap-4 lg:grid-cols-2">
              <div class="rounded-3xl border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-800 dark:bg-gray-800/40">
                <label class="form-label">Título de política de privacidad</label>
                <input class="form-input" name="legal_privacy_title" value="{{ old('legal_privacy_title', $welcomeSiteSettings['legal.privacy_title'] ?? '') }}">
                <label class="form-label mt-4">Texto de actualización</label>
                <input class="form-input" name="legal_privacy_updated_at" value="{{ old('legal_privacy_updated_at', $welcomeSiteSettings['legal.privacy_updated_at'] ?? '') }}">
                <label class="form-label mt-4">Texto legal completo</label>
                <textarea class="form-textarea" name="legal_privacy_body" rows="8">{{ old('legal_privacy_body', $welcomeSiteSettings['legal.privacy_body'] ?? '') }}</textarea>
              </div>
              <div class="rounded-3xl border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-800 dark:bg-gray-800/40">
                <label class="form-label">Título de términos de servicio</label>
                <input class="form-input" name="legal_terms_title" value="{{ old('legal_terms_title', $welcomeSiteSettings['legal.terms_title'] ?? '') }}">
                <label class="form-label mt-4">Texto de actualización</label>
                <input class="form-input" name="legal_terms_updated_at" value="{{ old('legal_terms_updated_at', $welcomeSiteSettings['legal.terms_updated_at'] ?? '') }}">
                <label class="form-label mt-4">Texto legal completo</label>
                <textarea class="form-textarea" name="legal_terms_body" rows="8">{{ old('legal_terms_body', $welcomeSiteSettings['legal.terms_body'] ?? '') }}</textarea>
              </div>
            </div>
          </div>
        </details>
      </section>

      <section id="welcome-tab-visual" data-tab-panel="visual" class="welcome-cms-panel space-y-5" role="tabpanel" hidden>
        <details open class="card overflow-hidden border border-gray-200/80 bg-white/95 dark:border-gray-800/80 dark:bg-gray-900/95">
          <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-6 py-5">
            <div>
              <p class="text-xs uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500">Estilo visual</p>
              <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Fondos suaves y gradientes secundarios</h3>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Solo se permiten tonos suaves decorativos. El blanco base del sistema no se modifica.</p>
            </div>
            <i class="ri-arrow-down-s-line text-xl text-gray-400"></i>
          </summary>
          <div class="border-t border-gray-200/80 px-6 py-6 dark:border-gray-800/80">
            <div class="grid gap-4 lg:grid-cols-3">
              @foreach([
                ['name' => 'branding_accent', 'label' => 'Color principal', 'value' => $brandingAccent],
                ['name' => 'branding_accent_strong', 'label' => 'Color fuerte', 'value' => $brandingAccentStrong],
                ['name' => 'branding_accent_soft', 'label' => 'Color suave del sistema', 'value' => $brandingAccentSoft],
              ] as $colorField)
                <div class="rounded-3xl border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-800 dark:bg-gray-800/40">
                  <label class="form-label">{{ $colorField['label'] }}</label>
                  <div class="mt-3 flex items-center gap-3">
                    <input class="welcome-cms-color-input" type="color" name="{{ $colorField['name'] }}" value="{{ $colorField['value'] }}" data-color-input>
                    <div class="min-w-0">
                      <p class="text-xs uppercase tracking-[0.18em] text-gray-400 dark:text-gray-500">Color actual</p>
                      <p class="text-sm font-semibold text-gray-700 dark:text-gray-200" data-color-value-for="{{ $colorField['name'] }}">{{ $colorField['value'] }}</p>
                    </div>
                  </div>
                  <div class="welcome-cms-swatch mt-4 h-12 rounded-2xl border border-white/70 shadow-inner" data-preview-swatch="{{ $colorField['name'] }}" style="--swatch-start: {{ $colorField['value'] }}; --swatch-end: {{ $colorField['value'] }};"></div>
                </div>
              @endforeach
            </div>
            <div class="mt-6 grid gap-4 lg:grid-cols-2 xl:grid-cols-5">
              @foreach([
                ['name' => 'visual_soft_primary', 'label' => 'Fondo suave principal', 'value' => $visualSoftPrimary],
                ['name' => 'visual_soft_secondary', 'label' => 'Fondo suave secundario', 'value' => $visualSoftSecondary],
                ['name' => 'visual_gradient_start', 'label' => 'Gradiente inicio', 'value' => $visualGradientStart],
                ['name' => 'visual_gradient_end', 'label' => 'Gradiente fin', 'value' => $visualGradientEnd],
                ['name' => 'visual_badge_soft', 'label' => 'Badges suaves', 'value' => $visualBadgeSoft],
              ] as $colorField)
                <div class="rounded-3xl border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-800 dark:bg-gray-800/40">
                  <label class="form-label">{{ $colorField['label'] }}</label>
                  <div class="mt-3 flex items-center gap-3">
                    <input class="welcome-cms-color-input" type="color" name="{{ $colorField['name'] }}" value="{{ $colorField['value'] }}" data-color-input>
                    <div class="min-w-0">
                      <p class="text-xs uppercase tracking-[0.18em] text-gray-400 dark:text-gray-500">Color actual</p>
                      <p class="text-sm font-semibold text-gray-700 dark:text-gray-200" data-color-value-for="{{ $colorField['name'] }}">{{ $colorField['value'] }}</p>
                    </div>
                  </div>
                  <div class="welcome-cms-swatch mt-4 h-12 rounded-2xl border border-white/70 shadow-inner" data-preview-swatch="{{ $colorField['name'] }}" style="--swatch-start: {{ $colorField['value'] }}; --swatch-end: {{ $colorField['value'] }};"></div>
                </div>
              @endforeach
            </div>
            <div class="mt-6 rounded-3xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-800 dark:border-amber-950/40 dark:bg-amber-950/10 dark:text-amber-300">Los tres primeros colores actualizan los tokens reales de Welcome. Los cinco tonos inferiores controlan fondos suaves, badges y gradientes decorativos de la landing.</div>
          </div>
        </details>
      </section>

  </div>

  <div class="welcome-cms-preview-modal" data-preview-modal hidden>
    <div class="welcome-cms-preview-backdrop" data-preview-close></div>
    <section class="welcome-cms-preview-dialog" role="dialog" aria-modal="true" aria-labelledby="welcomePreviewTitle">
      <div class="border-b border-gray-200/80 px-5 py-5 sm:px-6 dark:border-gray-800/80">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <p class="text-xs uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500">Vista previa reactiva</p>
            <h3 id="welcomePreviewTitle" class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Vista previa de bienvenida</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Modal de vista previa visual. No usa iframe, no usa navegación real y refleja cambios sin guardar.</p>
          </div>
          <div class="flex flex-wrap items-center gap-3">
            <div class="rounded-2xl border border-gray-200 bg-gray-50 px-3 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-300">
              Vista de escritorio
            </div>
            <button type="button" class="welcome-cms-action-button btn btn-ghost dark:text-gray-300 dark:hover:bg-gray-800" data-preview-close>
              <i class="ri-close-line"></i> Cerrar
            </button>
          </div>
        </div>
      </div>

      <div class="welcome-cms-preview-scroll">
        <div class="welcome-cms-preview-stage" data-live-preview-stage>
          <div class="welcome-cms-preview-scale">
            <div class="welcome-cms-preview-frame" data-live-preview-frame>
              <div class="welcome-cms-live-preview-surface" data-live-preview-surface>
                <div data-live-preview-root></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>

  <template data-slide-template>
    <div class="rounded-3xl border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-800 dark:bg-gray-800/40" data-slide-row data-row-key="slide-__INDEX__">
      <div class="grid gap-6">
        <div class="flex flex-col sm:flex-row items-start gap-5 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-950/20">
          <div class="w-full sm:w-[240px] shrink-0">
            <div class="welcome-cms-media-frame welcome-cms-media-frame--slide" data-inline-image-preview data-preview-icon="ri-image-line" data-preview-placeholder="Vista previa del slide">
              <div class="welcome-cms-media-placeholder">
                <i class="ri-image-line"></i>
                <span>Vista previa del slide</span>
              </div>
            </div>
          </div>
          <div class="flex-1 w-full flex flex-col justify-center min-h-[10rem]">
            <label class="form-label font-semibold text-gray-800 dark:text-white">Imagen del slide</label>
            <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">Selecciona una imagen horizontal de alta calidad. Formatos recomendados: JPG, WEBP, PNG.</p>
            <input class="form-input w-full" type="file" name="slides[__INDEX__][image]" accept="image/*">
            <input type="hidden" name="slides[__INDEX__][image_path]" value="">
          </div>
        </div>
        <div class="grid gap-4 lg:grid-cols-2">
          <div><label class="form-label">Título visible</label><input class="form-input" name="slides[__INDEX__][title]" maxlength="120"></div>
          <div><label class="form-label">Subtítulo visible</label><input class="form-input" name="slides[__INDEX__][subtitle]" maxlength="120"></div>
          <div class="lg:col-span-2"><label class="form-label">Descripción breve visible</label><textarea class="form-textarea" name="slides[__INDEX__][text]" rows="3" maxlength="240"></textarea></div>
          <div class="lg:col-span-2"><label class="form-label">Texto alternativo accesible</label><input class="form-input" name="slides[__INDEX__][alt]"></div>
          <div><label class="form-label">Orden</label><input class="form-input" type="number" min="0" name="slides[__INDEX__][sort_order]" value="0"></div>
          <div class="flex items-end pb-2">
            <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
              <input type="hidden" name="slides[__INDEX__][is_active]" value="0">
              <input type="checkbox" class="h-4 w-4 rounded border-gray-300" name="slides[__INDEX__][is_active]" value="1" checked>
              Activo
            </label>
          </div>
        </div>
      </div>
      <button type="button" class="btn btn-ghost mt-4 dark:text-gray-300 dark:hover:bg-gray-800" data-remove-item>Quitar</button>
    </div>
  </template>

  <template data-price-template>
    <div class="rounded-3xl border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-800 dark:bg-gray-800/40" data-price-row data-row-key="price-__INDEX__">
      <div class="grid gap-4 lg:grid-cols-2">
        <div><label class="form-label">Especialidad / servicio</label><input class="form-input" name="prices[__INDEX__][service]"></div>
        <div><label class="form-label">Valor</label><input class="form-input" name="prices[__INDEX__][price]"></div>
        <div><label class="form-label">Orden</label><input class="form-input" type="number" min="0" name="prices[__INDEX__][sort_order]" value="0"></div>
        <div class="flex items-end">
          <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
            <input type="hidden" name="prices[__INDEX__][is_active]" value="0">
            <input type="checkbox" class="h-4 w-4 rounded border-gray-300" name="prices[__INDEX__][is_active]" value="1" checked>
            Mostrar fila
          </label>
        </div>
      </div>
      <button type="button" class="btn btn-ghost mt-4 dark:text-gray-300 dark:hover:bg-gray-800" data-remove-item>Quitar</button>
    </div>
  </template>

  <template data-doctor-template>
    <div class="rounded-3xl border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-800 dark:bg-gray-800/40" data-doctor-row data-row-key="doctor-__INDEX__" data-doctor-id="">
      <div class="grid gap-4 xl:grid-cols-[176px_minmax(0,1fr)]">
        <div class="welcome-cms-media-frame welcome-cms-media-frame--doctor" data-inline-image-preview data-preview-icon="ri-user-3-line" data-preview-placeholder="Vista previa del doctor">
          <div class="welcome-cms-media-placeholder">
            <i class="ri-user-3-line"></i>
            <span>Vista previa del doctor</span>
          </div>
        </div>
        <div class="grid gap-4 lg:grid-cols-2">
          <div><label class="form-label">Nombre</label><input class="form-input" name="doctors[__INDEX__][name]"></div>
          <div>
            <label class="form-label">Especialidad</label>
            <select class="form-input" name="doctors[__INDEX__][specialty]">
              <option value="">Seleccionar especialidad</option>
              @foreach($doctorSpecialties as $specialtyOption)
                <option value="{{ $specialtyOption }}">{{ $specialtyOption }}</option>
              @endforeach
            </select>
          </div>
          <div class="lg:col-span-2">
            <label class="form-label">Foto</label>
            <input class="form-input" type="file" name="doctors[__INDEX__][photo]" accept="image/*">
            <input type="hidden" name="doctors[__INDEX__][id]" value="">
            <input type="hidden" name="doctors[__INDEX__][photo_path]" value="">
          </div>
          <div><label class="form-label">Experiencia</label><input class="form-input" name="doctors[__INDEX__][experience_label]"></div>
          <div><label class="form-label">Badge destacado</label><input class="form-input" name="doctors[__INDEX__][featured_label]"></div>
          <div><label class="form-label">Atención presencial</label><input class="form-input" name="doctors[__INDEX__][attendance_label]"></div>
          <div><label class="form-label">Agenda disponible</label><input class="form-input" name="doctors[__INDEX__][availability_label]"></div>
          <div><label class="form-label">Texto del botón</label><input class="form-input" name="doctors[__INDEX__][cta_text]" maxlength="25"></div>
          <div><label class="form-label">Pill inferior</label><input class="form-input" name="doctors[__INDEX__][pill_text]" maxlength="60"></div>
          <div><label class="form-label">Orden visual</label><input class="form-input" type="number" min="0" name="doctors[__INDEX__][sort_order]" value="0"></div>
          <div class="flex items-end">
            <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
              <input type="hidden" name="doctors[__INDEX__][is_active]" value="0">
              <input type="checkbox" class="h-4 w-4 rounded border-gray-300" name="doctors[__INDEX__][is_active]" value="1" checked>
              Mostrar doctor
            </label>
          </div>
        </div>
      </div>
      <button type="button" class="btn btn-ghost mt-4 dark:text-gray-300 dark:hover:bg-gray-800" data-remove-item>Quitar</button>
    </div>
  </template>

  <script type="application/json" data-featured-options>
{!! json_encode($especialidadesActivas->map(fn($esp) => [
  'id' => $esp->id,
  'nombre' => $esp->nombre,
  'descripcion' => $esp->descripcion,
  'icono' => $esp->icono,
])->values(), JSON_UNESCAPED_UNICODE) !!}
  </script>
  <script type="application/json" data-welcome-preview-config>
{!! json_encode([
  'slides' => $previewSlides,
], JSON_UNESCAPED_UNICODE) !!}
  </script>
  <script type="application/json" data-welcome-doctors-preview-config>
{!! json_encode([
  'doctors' => $previewDoctors,
], JSON_UNESCAPED_UNICODE) !!}
  </script>
</div>
