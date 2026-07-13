@php
  use App\Support\ServicePageCatalog;

  $especialidades = collect($especialidades ?? []);
  $serviceSettings = $serviceSettings ?? [];
  $iconOptions = config('iconos.especialidades', []);
  $serviceCatalog = ServicePageCatalog::catalog();
  $fallbackCatalog = ServicePageCatalog::fallback();
  $heroImagePath = old('services_hero_image_path', $serviceSettings['services.hero_image'] ?? ServicePageCatalog::heroImagePath());
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

  foreach ($navigationMap as $key => $item) {
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
  $servicesPreviewConfig = [
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
    'catalog' => collect($serviceCatalog)->mapWithKeys(function ($meta, $key) {
      return [
        $key => array_merge($meta, [
          'image_url' => asset($meta['image_path']),
        ]),
      ];
    })->all(),
    'fallback' => array_merge($fallbackCatalog, [
      'image_url' => asset($fallbackCatalog['image_path']),
    ]),
    'hero_image_url' => asset(ServicePageCatalog::heroImagePath()),
  ];
@endphp

@include('shared.personalizacion-public-preview-styles')

<div
  class="personalizacion-public-editor"
  data-services-form
  data-asset-base="{{ asset('') }}"
  data-storage-base="{{ asset('storage') }}"
>
  <div class="personalizacion-public-surface space-y-6">
    <section class="card border border-gray-200/80 bg-white/95 p-6 dark:border-gray-800/80 dark:bg-gray-900/95" data-public-preview-trigger-card>
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="min-w-0">
          <p class="text-xs uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500">Servicios publicos</p>
          <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Edita el contenido y abre la preview reactiva</h3>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">La vista previa usa los datos actuales del formulario, sin guardar y sin navegar a la pagina real.</p>
        </div>
        <button type="button" class="btn btn-outline dark:border-gray-700 dark:bg-gray-850 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-white" data-public-preview-open>
          <i class="ri-macbook-line"></i> Ver vista previa
        </button>
      </div>
    </section>

    <section class="card border border-gray-200/80 bg-white/95 p-6 dark:border-gray-800/80 dark:bg-gray-900/95" data-public-preview-form-card>
      <div>
        <p class="text-xs uppercase tracking-widest text-gray-500 dark:text-gray-450">Hero</p>
        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">Cabecera publica de servicios</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400">Titulo principal, subtitulo, CTA e imagen lateral.</p>
      </div>

      <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <div>
          <label class="form-label">Titulo principal</label>
          <input class="form-input" name="services_title" value="{{ old('services_title', $serviceSettings['services.title'] ?? $siteSettings->get('services.title')) }}">
          @error('services_title')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Texto del boton principal</label>
          <input class="form-input" name="services_cta_text" value="{{ old('services_cta_text', $serviceSettings['services.cta_text'] ?? $siteSettings->get('services.cta_text')) }}">
          @error('services_cta_text')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
        </div>
        <div class="lg:col-span-2">
          <label class="form-label">Subtitulo / descripcion</label>
          <textarea class="form-textarea" name="services_subtitle" rows="3">{{ old('services_subtitle', $serviceSettings['services.subtitle'] ?? $siteSettings->get('services.subtitle')) }}</textarea>
          @error('services_subtitle')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
        </div>
        </div>

      <div class="mt-6">
        <div class="flex flex-col sm:flex-row items-start gap-5 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-950/20">
          <div class="w-full sm:w-[280px] shrink-0">
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-950" data-public-preview-inline-image>
              @php
                $heroPreview = $imageUrl->variants($heroImagePath, 'services', 'banner');
              @endphp
              <img
                src="{{ $heroPreview['thumb'] }}"
                @if($heroPreview['srcset']) srcset="{{ $heroPreview['srcset'] }}" sizes="(max-width: 640px) 100vw, 280px" @endif
                alt="Hero de servicios"
                class="w-full aspect-[16/9] object-cover"
                loading="lazy"
                decoding="async"
                data-services-inline-image="hero"
              >
            </div>
          </div>
          <div class="flex-1 w-full flex flex-col justify-center">
            <label class="form-label font-semibold text-gray-800 dark:text-white">Imagen principal del hero</label>
            <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">Formato valido: JPG, PNG, WEBP o SVG. La preview conserva la imagen anterior mientras no selecciones una nueva.</p>
            <input type="hidden" name="services_hero_image_path" value="{{ $heroImagePath }}">
            <input class="form-input w-full" type="file" name="services_hero_image" accept="image/*">
            @error('services_hero_image')<div class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
          </div>
        </div>
      </div>
    </section>

    <section class="card border border-gray-200/80 bg-white/95 p-6 dark:border-gray-800/80 dark:bg-gray-900/95" data-public-preview-form-card>
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p class="text-xs uppercase tracking-widest text-gray-500 dark:text-gray-455">Especialidades</p>
          <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">Cards de servicios destacados</h3>
          <p class="text-sm text-gray-500 dark:text-gray-400">Nombre, descripcion, icono, estado, orden e imagen por servicio.</p>
        </div>
        <button type="button" class="btn btn-outline dark:border-gray-700 dark:bg-gray-850 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-white" data-add-especialidad>
          <i class="ri-add-line"></i> Agregar especialidad
        </button>
      </div>

      <div class="mt-4 grid gap-4" data-especialidad-list data-next-index="{{ $especialidades->count() }}">
        @foreach($especialidades as $index => $esp)
          @php
            $prefix = "especialidades[{$esp->id}]";
            $oldPrefix = "especialidades.{$esp->id}";
            $normalizedName = ServicePageCatalog::normalizeName($esp->nombre);
            $catalogMeta = $serviceCatalog[$normalizedName] ?? $fallbackCatalog;
            $serviceImageKey = "services.specialty_image.{$esp->id}";
            $serviceImagePath = old($oldPrefix.'.image_path', $serviceSettings[$serviceImageKey] ?? $catalogMeta['image_path']);
            $serviceImage = $imageUrl->variants($serviceImagePath, 'services', 'banner');
            $currentIcon = old($oldPrefix.'.icono', $esp->icono);
            $currentOption = collect($iconOptions)->firstWhere('id', $currentIcon);
          @endphp
          <div class="rounded-3xl border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-800 dark:bg-gray-800/40" data-especialidad-row data-service-id="{{ $esp->id }}" data-public-preview-row-card>
            <div class="grid gap-6">
              <div class="flex flex-col sm:flex-row items-start gap-5 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-955/20">
                <div class="w-full sm:w-[240px] shrink-0">
                  <div class="overflow-hidden rounded-2xl border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-950" data-public-preview-inline-image>
                    <img
                      src="{{ $serviceImage['thumb'] }}"
                      @if($serviceImage['srcset']) srcset="{{ $serviceImage['srcset'] }}" sizes="(max-width: 640px) 100vw, 240px" @endif
                      alt="Imagen de {{ $esp->nombre }}"
                      class="w-full aspect-[4/3] object-cover"
                      loading="lazy"
                      decoding="async"
                      data-services-inline-image="service-{{ $esp->id }}"
                    >
                  </div>
                </div>
                <div class="flex-1 w-full flex flex-col justify-center">
                  <label class="form-label font-semibold text-gray-800 dark:text-white">Imagen de la card</label>
                  <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">La imagen se actualiza al instante en la vista previa. Se recomienda formato 4:3 para mantener la proporcion.</p>
                  <input type="hidden" name="{{ $prefix }}[image_path]" value="{{ $serviceImagePath }}">
                  <input class="form-input w-full" type="file" name="{{ $prefix }}[image]" accept="image/*">
                  @error($oldPrefix.'.image')<div class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
                </div>
              </div>

              <div class="grid gap-4 md:grid-cols-2">
                <div>
                  <label class="form-label">Nombre</label>
                  <input class="form-input" name="{{ $prefix }}[nombre]" value="{{ old($oldPrefix.'.nombre', $esp->nombre) }}">
                  @error($oldPrefix.'.nombre')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
                </div>
                <div>
                  <label class="form-label">Icono (Remixicon)</label>
                  <div class="space-y-2" data-icon-picker>
                    <input type="hidden" name="{{ $prefix }}[icono]" value="{{ $currentIcon }}" data-icon-value>
                    <input class="form-input" type="text" placeholder="Buscar icono: salud, piel, ninos, diente..." autocomplete="off" data-icon-search>
                    <div class="flex flex-wrap items-center gap-3">
                      <div class="flex h-12 w-12 items-center justify-center rounded-xl border border-gray-200 bg-gray-50 text-2xl text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" data-icon-preview>
                        @if($currentIcon)
                          <i class="{{ $currentIcon }}"></i>
                        @else
                          <span class="text-xs text-gray-400">Sin icono</span>
                        @endif
                      </div>
                      <div class="text-sm">
                        <p class="font-semibold text-gray-800 dark:text-white" data-icon-selected-label>{{ $currentOption['label'] ?? 'Usar icono por defecto' }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400" data-icon-selected-id>{{ $currentIcon ?? 'Defecto' }}</p>
                      </div>
                    </div>
                    <div class="max-h-52 overflow-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800" data-icon-list>
                      <div class="grid gap-2 p-2 sm:grid-cols-2">
                        @foreach($iconOptions as $option)
                          <button
                            type="button"
                            class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-left text-sm text-gray-700 hover:border-gray-400 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white"
                            data-icon-option
                            data-icon-id="{{ $option['id'] }}"
                            data-icon-label="{{ $option['label'] }}"
                            data-icon-keywords="{{ $option['keywords'] }}"
                          >
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-gray-50 text-lg text-gray-700 dark:bg-gray-900 dark:text-gray-300">
                              @if($option['id'])
                                <i class="{{ $option['id'] }}"></i>
                              @else
                                <span class="text-[10px] text-gray-400">Default</span>
                              @endif
                            </span>
                            <span class="flex-1">{{ $option['label'] }}</span>
                          </button>
                        @endforeach
                      </div>
                    </div>
                  </div>
                  @error($oldPrefix.'.icono')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
                </div>

                <div class="md:col-span-2">
                  <label class="form-label">Descripcion</label>
                  <textarea class="form-textarea" name="{{ $prefix }}[descripcion]" rows="2">{{ old($oldPrefix.'.descripcion', $esp->descripcion) }}</textarea>
                  @error($oldPrefix.'.descripcion')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
                </div>

                <div>
                  <label class="form-label">Orden</label>
                  <input class="form-input" type="number" min="0" name="{{ $prefix }}[orden]" value="{{ old($oldPrefix.'.orden', $esp->orden ?? 0) }}">
                  @error($oldPrefix.'.orden')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
                </div>
                <div class="flex flex-col justify-end gap-2 pb-2">
                  <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                    <input type="hidden" name="{{ $prefix }}[activo]" value="0">
                    <input type="checkbox" class="h-4 w-4 rounded border-gray-300" name="{{ $prefix }}[activo]" value="1" @checked(old($oldPrefix.'.activo', (bool) $esp->activo))>
                    Activa
                  </label>
                  @error($oldPrefix.'.activo')<div class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>@enderror
                </div>
              </div>
            </div>
          </div>
        @endforeach
      </div>

      <template data-especialidad-template>
        <div class="rounded-3xl border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-800 dark:bg-gray-800/40" data-especialidad-row data-row-key="new-__INDEX__" data-public-preview-row-card>
          <div class="grid gap-6">
            <div class="flex flex-col sm:flex-row items-start gap-5 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-955/20">
              <div class="w-full sm:w-[240px] shrink-0">
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-950" data-public-preview-inline-image>
                  <div class="flex aspect-[4/3] w-full items-center justify-center text-gray-400 dark:text-gray-500">
                    <i class="ri-image-line text-3xl"></i>
                  </div>
                </div>
              </div>
              <div class="flex-1 w-full flex flex-col justify-center">
                <label class="form-label font-semibold text-gray-800 dark:text-white">Imagen de la card</label>
                <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">Puedes probar la imagen en preview antes de guardar la nueva especialidad. Se recomienda formato 4:3.</p>
                <input type="hidden" name="nuevas[__INDEX__][image_path]" value="">
                <input class="form-input w-full" type="file" name="nuevas[__INDEX__][image]" accept="image/*">
              </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label class="form-label">Nombre</label>
                <input class="form-input" name="nuevas[__INDEX__][nombre]">
              </div>
              <div>
                <label class="form-label">Icono (Remixicon)</label>
                <div class="space-y-2" data-icon-picker>
                  <input type="hidden" name="nuevas[__INDEX__][icono]" value="" data-icon-value>
                  <input class="form-input" type="text" placeholder="Buscar icono: salud, piel, ninos, diente..." autocomplete="off" data-icon-search>
                  <div class="flex flex-wrap items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl border border-gray-200 bg-gray-50 text-2xl text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" data-icon-preview>
                      <span class="text-xs text-gray-400">Sin icono</span>
                    </div>
                    <div class="text-sm">
                      <p class="font-semibold text-gray-800 dark:text-white" data-icon-selected-label>Usar icono por defecto</p>
                      <p class="text-xs text-gray-500 dark:text-gray-400" data-icon-selected-id>Defecto</p>
                    </div>
                  </div>
                  <div class="max-h-52 overflow-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800" data-icon-list>
                    <div class="grid gap-2 p-2 sm:grid-cols-2">
                      @foreach($iconOptions as $option)
                        <button
                          type="button"
                          class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-left text-sm text-gray-700 hover:border-gray-400 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white"
                          data-icon-option
                          data-icon-id="{{ $option['id'] }}"
                          data-icon-label="{{ $option['label'] }}"
                          data-icon-keywords="{{ $option['keywords'] }}"
                        >
                          <span class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-gray-50 text-lg text-gray-700 dark:bg-gray-900 dark:text-gray-300">
                            @if($option['id'])
                              <i class="{{ $option['id'] }}"></i>
                            @else
                              <span class="text-[10px] text-gray-400">Default</span>
                            @endif
                          </span>
                          <span class="flex-1">{{ $option['label'] }}</span>
                        </button>
                      @endforeach
                    </div>
                  </div>
                </div>
              </div>

              <div class="md:col-span-2">
                <label class="form-label">Descripcion</label>
                <textarea class="form-textarea" name="nuevas[__INDEX__][descripcion]" rows="2"></textarea>
              </div>

              <div>
                <label class="form-label">Orden</label>
                <input class="form-input" type="number" min="0" name="nuevas[__INDEX__][orden]" value="0">
              </div>
              <div class="flex flex-col justify-end gap-2 pb-2">
                <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                  <input type="hidden" name="nuevas[__INDEX__][activo]" value="0">
                  <input type="checkbox" class="h-4 w-4 rounded border-gray-300" name="nuevas[__INDEX__][activo]" value="1" checked>
                  Activa
                </label>
              </div>

              <div class="md:col-span-2 mt-2">
                <button type="button" class="btn btn-ghost dark:text-gray-300 dark:hover:bg-gray-800" data-remove-item>Quitar especialidad</button>
              </div>
            </div>
          </div>
        </div>
      </template>
    </section>
  </div>

  @include('shared.personalizacion-public-preview-modal', [
    'title' => 'Vista previa de servicios',
    'description' => 'Representacion reactiva de la pagina publica de servicios. No usa iframe ni ejecuta navegacion real.',
  ])

  <script type="application/json" data-services-preview-config>
    {!! json_encode($servicesPreviewConfig, JSON_UNESCAPED_UNICODE) !!}
  </script>
</div>
