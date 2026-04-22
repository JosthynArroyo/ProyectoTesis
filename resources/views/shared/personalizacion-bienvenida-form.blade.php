@php($settings = $settings ?? [])
@php($statsInput = old('stats', $stats ?? []))
@php($slidesInput = old('slides', $slides ?? []))
@php($cardsInput = old('cards', $cards ?? []))
@php($doctorsInput = old('doctors', $doctors ?? []))
@php($pricesInput = old('prices', $prices ?? []))
@php($featuredInput = old('featured_specialties', $featuredIds ?? []))
@php($especialidadesActivas = collect($especialidadesActivas ?? []))

@php($statsInput = is_array($statsInput) ? array_values($statsInput) : [])
@php($slidesInput = is_array($slidesInput) ? array_values($slidesInput) : [])
@php($cardsInput = is_array($cardsInput) ? array_values($cardsInput) : [])
@php($doctorsInput = is_array($doctorsInput) ? array_values($doctorsInput) : [])
@php($pricesInput = is_array($pricesInput) ? array_values($pricesInput) : [])
@php($featuredInput = is_array($featuredInput) ? array_values(array_filter($featuredInput)) : [])
@if(empty($featuredInput) && $especialidadesActivas->count() >= 3)
  @php($featuredInput = $especialidadesActivas->take(3)->pluck('id')->all())
@endif
@php($featuredInput = count($featuredInput) >= 3 ? array_slice($featuredInput, 0, 3) : array_pad($featuredInput, 3, null))
@php($pricesHighlightImagePath = old('prices_highlight_image_path', $settings['prices_highlight_image'] ?? 'img/doctor2.jpg'))

@php($assetBase = asset(''))
@php($storageBase = asset('storage'))

<div class="grid gap-6 lg:grid-cols-[1.05fr_0.95fr]" data-bienvenida-form>
  <div class="space-y-6">
    <section class="card p-6">
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Hero</p>
        <h3 class="mt-2 text-lg font-semibold text-slate-900">Bienvenida</h3>
        <p class="text-sm text-slate-500">Personaliza textos y botones principales.</p>
      </div>
      <div class="mt-4 grid gap-4 md:grid-cols-2">
        <div class="md:col-span-2">
          <label class="form-label">Badge superior</label>
          <input class="form-input" name="hero_badge" value="{{ old('hero_badge', $settings['hero_badge'] ?? '') }}">
          @error('hero_badge')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div class="md:col-span-2">
          <label class="form-label">Título principal</label>
          <input class="form-input" name="hero_title" value="{{ old('hero_title', $settings['hero_title'] ?? '') }}">
          @error('hero_title')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div class="md:col-span-2">
          <label class="form-label">Subtítulo / descripción</label>
          <textarea class="form-textarea" name="hero_subtitle" rows="2">{{ old('hero_subtitle', $settings['hero_subtitle'] ?? '') }}</textarea>
          @error('hero_subtitle')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Tarjeta lateral: título</label>
          <input class="form-input" name="hero_followup_title" value="{{ old('hero_followup_title', $settings['hero_followup_title'] ?? '') }}">
          @error('hero_followup_title')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Tarjeta lateral: descripción</label>
          <textarea class="form-textarea" name="hero_followup_subtitle" rows="2">{{ old('hero_followup_subtitle', $settings['hero_followup_subtitle'] ?? '') }}</textarea>
          @error('hero_followup_subtitle')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Texto botón primario</label>
          <input class="form-input" name="hero_primary_text" value="{{ old('hero_primary_text', $settings['hero_primary_text'] ?? '') }}">
          @error('hero_primary_text')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
          <label class="mt-2 inline-flex items-center gap-2 text-sm text-slate-600">
            <input type="hidden" name="hero_show_primary" value="0">
            <input type="checkbox" class="h-4 w-4 rounded border-slate-300" name="hero_show_primary" value="1" @checked(old('hero_show_primary', $settings['hero_show_primary'] ?? true))>
            Mostrar botón primario
          </label>
          @error('hero_show_primary')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Texto botón secundario</label>
          <input class="form-input" name="hero_secondary_text" value="{{ old('hero_secondary_text', $settings['hero_secondary_text'] ?? '') }}">
          @error('hero_secondary_text')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
          <label class="mt-2 inline-flex items-center gap-2 text-sm text-slate-600">
            <input type="hidden" name="hero_show_secondary" value="0">
            <input type="checkbox" class="h-4 w-4 rounded border-slate-300" name="hero_show_secondary" value="1" @checked(old('hero_show_secondary', $settings['hero_show_secondary'] ?? true))>
            Mostrar botón secundario
          </label>
          @error('hero_show_secondary')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
      </div>
    </section>

    <section class="card p-6">
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Sección intermedia</p>
        <h3 class="mt-2 text-lg font-semibold text-slate-900">Bloque Bienvenida</h3>
        <p class="text-sm text-slate-500">Edita el título, descripción y tarjetas del bloque Gestiona tus citas.</p>
      </div>
      <div class="mt-4 grid gap-4 md:grid-cols-2">
        <div>
          <label class="form-label">Etiqueta superior</label>
          <input class="form-input" name="intro_badge" value="{{ old('intro_badge', $settings['intro_badge'] ?? '') }}">
          @error('intro_badge')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Título principal</label>
          <input class="form-input" name="intro_title" value="{{ old('intro_title', $settings['intro_title'] ?? '') }}">
          @error('intro_title')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div class="md:col-span-2">
          <label class="form-label">Descripcion principal</label>
          <textarea class="form-textarea" name="intro_subtitle" rows="2">{{ old('intro_subtitle', $settings['intro_subtitle'] ?? '') }}</textarea>
          @error('intro_subtitle')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
      </div>

      <div class="mt-4 grid gap-4 md:grid-cols-2">
        <div>
          <label class="form-label">Tarjeta 1: título</label>
          <input class="form-input" name="intro_feature_1_title" value="{{ old('intro_feature_1_title', $settings['intro_feature_1_title'] ?? '') }}">
          @error('intro_feature_1_title')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Tarjeta 1: descripción</label>
          <textarea class="form-textarea" name="intro_feature_1_text" rows="2">{{ old('intro_feature_1_text', $settings['intro_feature_1_text'] ?? '') }}</textarea>
          @error('intro_feature_1_text')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <div>
          <label class="form-label">Tarjeta 2: título</label>
          <input class="form-input" name="intro_feature_2_title" value="{{ old('intro_feature_2_title', $settings['intro_feature_2_title'] ?? '') }}">
          @error('intro_feature_2_title')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Tarjeta 2: descripción</label>
          <textarea class="form-textarea" name="intro_feature_2_text" rows="2">{{ old('intro_feature_2_text', $settings['intro_feature_2_text'] ?? '') }}</textarea>
          @error('intro_feature_2_text')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <div>
          <label class="form-label">Tarjeta 3: título</label>
          <input class="form-input" name="intro_feature_3_title" value="{{ old('intro_feature_3_title', $settings['intro_feature_3_title'] ?? '') }}">
          @error('intro_feature_3_title')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Tarjeta 3: descripción</label>
          <textarea class="form-textarea" name="intro_feature_3_text" rows="2">{{ old('intro_feature_3_text', $settings['intro_feature_3_text'] ?? '') }}</textarea>
          @error('intro_feature_3_text')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <div>
          <label class="form-label">Tarjeta 4: título</label>
          <input class="form-input" name="intro_feature_4_title" value="{{ old('intro_feature_4_title', $settings['intro_feature_4_title'] ?? '') }}">
          @error('intro_feature_4_title')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Tarjeta 4: descripción</label>
          <textarea class="form-textarea" name="intro_feature_4_text" rows="2">{{ old('intro_feature_4_text', $settings['intro_feature_4_text'] ?? '') }}</textarea>
          @error('intro_feature_4_text')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
      </div>
    </section>

    <section class="card p-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p class="text-xs uppercase tracking-widest text-slate-500">Galería</p>
          <h3 class="mt-2 text-lg font-semibold text-slate-900">Imágenes del hero</h3>
          <p class="text-sm text-slate-500">Sube, ordena y activa las imágenes del slider.</p>
        </div>
        <button type="button" class="btn btn-outline" data-add-slide>
          <i class="ri-add-line"></i> Agregar imagen
        </button>
      </div>

      <div class="mt-4 grid gap-4" data-slide-list data-next-index="{{ count($slidesInput) }}">
        @foreach($slidesInput as $index => $slide)
          @php($slidePath = $slide['image_path'] ?? null)
          <div class="card border border-slate-200 p-4" data-slide-row data-row-key="slide-{{ $index }}">
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label class="form-label">Imagen</label>
                <input class="form-input" type="file" name="slides[{{ $index }}][image]" accept="image/*" data-image-input>
                <input type="hidden" name="slides[{{ $index }}][image_path]" value="{{ $slidePath }}">
                @error('slides.'.$index.'.image')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
                @error('slides.'.$index.'.image_path')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
              <div class="flex items-center gap-3">
                <div class="h-20 w-28 overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                  @if($slidePath)
                    @php($slideImage = $imageUrl->variants($slidePath, 'banners', 'banner'))
                    <img
                      class="h-full w-full object-cover"
                      src="{{ $slideImage['thumb'] }}"
                      @if($slideImage['srcset']) srcset="{{ $slideImage['srcset'] }}" sizes="112px" @endif
                      alt="Preview"
                      loading="lazy"
                      decoding="async"
                    >
                  @else
                    <div class="flex h-full items-center justify-center text-xs text-slate-400">Sin imagen</div>
                  @endif
                </div>
                <div class="grid gap-2">
                  <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                    <input type="hidden" name="slides[{{ $index }}][is_active]" value="0">
                    <input type="checkbox" class="h-4 w-4 rounded border-slate-300" name="slides[{{ $index }}][is_active]" value="1" @checked($slide['is_active'] ?? true)>
                    Activa
                  </label>
                  <div>
                    <label class="form-label">Orden</label>
                    <input class="form-input" type="number" min="0" name="slides[{{ $index }}][sort_order]" value="{{ $slide['sort_order'] ?? 0 }}">
                    @error('slides.'.$index.'.sort_order')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
                  </div>
                </div>
                @error('slides.'.$index.'.is_active')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
            </div>
            <button type="button" class="btn btn-ghost mt-3" data-remove-item>Quitar</button>
          </div>
        @endforeach
      </div>

      <template data-slide-template>
        <div class="card border border-slate-200 p-4" data-slide-row data-row-key="slide-__INDEX__">
          <div class="grid gap-4 md:grid-cols-2">
            <div>
              <label class="form-label">Imagen</label>
              <input class="form-input" type="file" name="slides[__INDEX__][image]" accept="image/*" data-image-input>
              <input type="hidden" name="slides[__INDEX__][image_path]" value="">
            </div>
            <div class="flex items-center gap-3">
              <div class="h-20 w-28 overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                <div class="flex h-full items-center justify-center text-xs text-slate-400">Sin imagen</div>
              </div>
              <div class="grid gap-2">
                <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                  <input type="hidden" name="slides[__INDEX__][is_active]" value="0">
                  <input type="checkbox" class="h-4 w-4 rounded border-slate-300" name="slides[__INDEX__][is_active]" value="1" checked>
                  Activa
                </label>
                <div>
                  <label class="form-label">Orden</label>
                  <input class="form-input" type="number" min="0" name="slides[__INDEX__][sort_order]" value="0">
                </div>
              </div>
            </div>
          </div>
          <button type="button" class="btn btn-ghost mt-3" data-remove-item>Quitar</button>
        </div>
      </template>
    </section>

    <section class="card p-6">
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Tarjetas</p>
        <h3 class="mt-2 text-lg font-semibold text-slate-900">Informativas debajo del hero</h3>
        <p class="text-sm text-slate-500">Edita título, valor y descripción. Puedes activar o desactivar cada tarjeta.</p>
      </div>

      <div class="mt-4 grid gap-4" data-card-list>
        @foreach($cardsInput as $index => $card)
          <div class="card border border-slate-200 p-4" data-card-row data-row-key="card-{{ $index }}">
            <input type="hidden" name="cards[{{ $index }}][icon]" value="{{ $card['icon'] ?? 'ri-information-line' }}">
            @error('cards.'.$index.'.icon')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label class="form-label">Título</label>
                <input class="form-input" name="cards[{{ $index }}][title]" value="{{ $card['title'] ?? '' }}">
                @error('cards.'.$index.'.title')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
              <div>
                <label class="form-label">Valor / numero</label>
                <input class="form-input" name="cards[{{ $index }}][value]" value="{{ $card['value'] ?? '' }}">
                @error('cards.'.$index.'.value')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
              <div class="md:col-span-2">
                <label class="form-label">Descripcion</label>
                <textarea class="form-textarea" name="cards[{{ $index }}][description]" rows="2">{{ $card['description'] ?? '' }}</textarea>
                @error('cards.'.$index.'.description')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
              <div class="grid gap-3 sm:grid-cols-2">
                <div>
                  <label class="form-label">Orden</label>
                  <input class="form-input" type="number" min="0" name="cards[{{ $index }}][sort_order]" value="{{ $card['sort_order'] ?? 0 }}">
                  @error('cards.'.$index.'.sort_order')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
                </div>
                <div class="flex flex-col gap-2">
                  <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                    <input type="hidden" name="cards[{{ $index }}][is_active]" value="0">
                    <input type="checkbox" class="h-4 w-4 rounded border-slate-300" name="cards[{{ $index }}][is_active]" value="1" @checked($card['is_active'] ?? true)>
                    Activa
                  </label>
                </div>
              </div>
              @error('cards.'.$index.'.is_active')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
            </div>
          </div>
        @endforeach
      </div>
    </section>

    <section class="card p-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p class="text-xs uppercase tracking-widest text-slate-500">Hero stats</p>
          <h3 class="mt-2 text-lg font-semibold text-slate-900">Numeros destacados</h3>
          <p class="text-sm text-slate-500">Agrega tarjetas con numero, texto y estado.</p>
        </div>
        <button type="button" class="btn btn-outline" data-add-stat>
          <i class="ri-add-line"></i> Agregar estadistica
        </button>
      </div>

      <div class="mt-4 grid gap-4" data-stat-list data-next-index="{{ count($statsInput) }}">
        @foreach($statsInput as $index => $stat)
          <div class="card border border-slate-200 p-4" data-stat-row data-row-key="stat-{{ $index }}">
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label class="form-label">Etiqueta</label>
                <input class="form-input" name="stats[{{ $index }}][label]" value="{{ $stat['label'] ?? '' }}">
                @error('stats.'.$index.'.label')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
              <div>
                <label class="form-label">Valor</label>
                <input class="form-input" name="stats[{{ $index }}][value]" value="{{ $stat['value'] ?? '' }}">
                @error('stats.'.$index.'.value')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
              <div class="md:col-span-2">
                <label class="form-label">Nota</label>
                <input class="form-input" name="stats[{{ $index }}][note]" value="{{ $stat['note'] ?? '' }}">
                @error('stats.'.$index.'.note')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
              <div class="grid gap-3 sm:grid-cols-2">
                <div>
                  <label class="form-label">Orden</label>
                  <input class="form-input" type="number" min="0" name="stats[{{ $index }}][sort_order]" value="{{ $stat['sort_order'] ?? 0 }}">
                  @error('stats.'.$index.'.sort_order')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
                </div>
                <div class="flex flex-col gap-2">
                  <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                    <input type="hidden" name="stats[{{ $index }}][is_active]" value="0">
                    <input type="checkbox" class="h-4 w-4 rounded border-slate-300" name="stats[{{ $index }}][is_active]" value="1" @checked($stat['is_active'] ?? true)>
                    Activa
                  </label>
                </div>
              </div>
            </div>
            @error('stats.'.$index.'.is_active')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
            <button type="button" class="btn btn-ghost mt-3" data-remove-item>Quitar</button>
          </div>
        @endforeach
      </div>

      <template data-stat-template>
        <div class="card border border-slate-200 p-4" data-stat-row data-row-key="stat-__INDEX__">
          <div class="grid gap-4 md:grid-cols-2">
            <div>
              <label class="form-label">Etiqueta</label>
              <input class="form-input" name="stats[__INDEX__][label]">
            </div>
            <div>
              <label class="form-label">Valor</label>
              <input class="form-input" name="stats[__INDEX__][value]">
            </div>
            <div class="md:col-span-2">
              <label class="form-label">Nota</label>
              <input class="form-input" name="stats[__INDEX__][note]">
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
              <div>
                <label class="form-label">Orden</label>
                <input class="form-input" type="number" min="0" name="stats[__INDEX__][sort_order]" value="0">
              </div>
              <div class="flex flex-col gap-2">
                <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                  <input type="hidden" name="stats[__INDEX__][is_active]" value="0">
                  <input type="checkbox" class="h-4 w-4 rounded border-slate-300" name="stats[__INDEX__][is_active]" value="1" checked>
                  Activa
                </label>
              </div>
            </div>
          </div>
          <button type="button" class="btn btn-ghost mt-3" data-remove-item>Quitar</button>
        </div>
      </template>
    </section>

    <section class="card p-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p class="text-xs uppercase tracking-widest text-slate-500">Doctores</p>
          <h3 class="mt-2 text-lg font-semibold text-slate-900">Nuestros doctores</h3>
          <p class="text-sm text-slate-500">Lista editable para la landing.</p>
        </div>
        <button type="button" class="btn btn-outline" data-add-doctor>
          <i class="ri-add-line"></i> Agregar doctor
        </button>
      </div>

      <div class="mt-4 grid gap-4 md:grid-cols-2">
        <div>
          <label class="form-label">Etiqueta superior</label>
          <input class="form-input" name="doctors_badge" value="{{ old('doctors_badge', $settings['doctors_badge'] ?? '') }}">
          @error('doctors_badge')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Título del bloque</label>
          <input class="form-input" name="doctors_title" value="{{ old('doctors_title', $settings['doctors_title'] ?? '') }}">
          @error('doctors_title')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div class="md:col-span-2">
          <label class="form-label">Descripcion</label>
          <textarea class="form-textarea" name="doctors_subtitle" rows="2">{{ old('doctors_subtitle', $settings['doctors_subtitle'] ?? '') }}</textarea>
          @error('doctors_subtitle')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div class="md:col-span-2">
          <label class="form-label">Texto de etiqueta lateral</label>
          <input class="form-input" name="doctors_pill" value="{{ old('doctors_pill', $settings['doctors_pill'] ?? '') }}">
          @error('doctors_pill')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
      </div>

      <div class="mt-4 grid gap-4" data-doctor-list data-next-index="{{ count($doctorsInput) }}">
        @foreach($doctorsInput as $index => $doctor)
          @php($doctorPhoto = $doctor['photo_path'] ?? null)
          <div class="card border border-slate-200 p-4" data-doctor-row data-row-key="doctor-{{ $index }}">
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label class="form-label">Nombre</label>
                <input class="form-input" name="doctors[{{ $index }}][name]" value="{{ $doctor['name'] ?? '' }}">
                @error('doctors.'.$index.'.name')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
              <div>
                <label class="form-label">Especialidad</label>
                <input class="form-input" name="doctors[{{ $index }}][specialty]" value="{{ $doctor['specialty'] ?? '' }}">
                @error('doctors.'.$index.'.specialty')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
              <div>
                <label class="form-label">Foto</label>
                <input class="form-input" type="file" name="doctors[{{ $index }}][photo]" accept="image/*" data-image-input>
                <input type="hidden" name="doctors[{{ $index }}][photo_path]" value="{{ $doctorPhoto }}">
                @error('doctors.'.$index.'.photo')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
                @error('doctors.'.$index.'.photo_path')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
              <div class="flex items-center gap-3">
                <div class="h-20 w-28 overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                  @if($doctorPhoto)
                    @php($doctorImage = $imageUrl->variants($doctorPhoto, 'doctors', 'doctor'))
                    <img
                      class="h-full w-full object-cover"
                      src="{{ $doctorImage['thumb'] }}"
                      @if($doctorImage['srcset']) srcset="{{ $doctorImage['srcset'] }}" sizes="112px" @endif
                      alt="Preview"
                      loading="lazy"
                      decoding="async"
                    >
                  @else
                    <div class="flex h-full items-center justify-center text-xs text-slate-400">Sin imagen</div>
                  @endif
                </div>
                <div class="grid gap-2">
                  <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                    <input type="hidden" name="doctors[{{ $index }}][is_active]" value="0">
                    <input type="checkbox" class="h-4 w-4 rounded border-slate-300" name="doctors[{{ $index }}][is_active]" value="1" @checked($doctor['is_active'] ?? true)>
                    Activo
                  </label>
                  <div>
                    <label class="form-label">Orden</label>
                    <input class="form-input" type="number" min="0" name="doctors[{{ $index }}][sort_order]" value="{{ $doctor['sort_order'] ?? 0 }}">
                    @error('doctors.'.$index.'.sort_order')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
                  </div>
                </div>
                @error('doctors.'.$index.'.is_active')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
            </div>
            <button type="button" class="btn btn-ghost mt-3" data-remove-item>Quitar</button>
          </div>
        @endforeach
      </div>

      <template data-doctor-template>
        <div class="card border border-slate-200 p-4" data-doctor-row data-row-key="doctor-__INDEX__">
          <div class="grid gap-4 md:grid-cols-2">
            <div>
              <label class="form-label">Nombre</label>
              <input class="form-input" name="doctors[__INDEX__][name]">
            </div>
            <div>
              <label class="form-label">Especialidad</label>
              <input class="form-input" name="doctors[__INDEX__][specialty]">
            </div>
            <div>
              <label class="form-label">Foto</label>
              <input class="form-input" type="file" name="doctors[__INDEX__][photo]" accept="image/*" data-image-input>
              <input type="hidden" name="doctors[__INDEX__][photo_path]" value="">
            </div>
            <div class="flex items-center gap-3">
              <div class="h-20 w-28 overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                <div class="flex h-full items-center justify-center text-xs text-slate-400">Sin imagen</div>
              </div>
              <div class="grid gap-2">
                <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                  <input type="hidden" name="doctors[__INDEX__][is_active]" value="0">
                  <input type="checkbox" class="h-4 w-4 rounded border-slate-300" name="doctors[__INDEX__][is_active]" value="1" checked>
                  Activo
                </label>
                <div>
                  <label class="form-label">Orden</label>
                  <input class="form-input" type="number" min="0" name="doctors[__INDEX__][sort_order]" value="0">
                </div>
              </div>
            </div>
          </div>
          <button type="button" class="btn btn-ghost mt-3" data-remove-item>Quitar</button>
        </div>
      </template>
    </section>

    <section class="card p-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p class="text-xs uppercase tracking-widest text-slate-500">Tarifario</p>
          <h3 class="mt-2 text-lg font-semibold text-slate-900">Precios transparentes</h3>
          <p class="text-sm text-slate-500">Edita filas y el texto descriptivo.</p>
        </div>
        <button type="button" class="btn btn-outline" data-add-price>
          <i class="ri-add-line"></i> Agregar fila
        </button>
      </div>

      <div class="mt-4 grid gap-4 md:grid-cols-2">
        <div>
          <label class="form-label">Etiqueta superior</label>
          <input class="form-input" name="prices_badge" value="{{ old('prices_badge', $settings['prices_badge'] ?? '') }}">
          @error('prices_badge')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Título del bloque</label>
          <input class="form-input" name="prices_title" value="{{ old('prices_title', $settings['prices_title'] ?? '') }}">
          @error('prices_title')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div class="md:col-span-2">
          <label class="form-label">Texto descriptivo</label>
          <textarea class="form-textarea" name="prices_subtitle" rows="2">{{ old('prices_subtitle', $settings['prices_subtitle'] ?? '') }}</textarea>
          @error('prices_subtitle')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div class="md:col-span-2">
          <label class="form-label">Texto botón principal</label>
          <input class="form-input" name="prices_button_text" value="{{ old('prices_button_text', $settings['prices_button_text'] ?? '') }}">
          @error('prices_button_text')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
      </div>

      <div class="mt-4 grid gap-4 md:grid-cols-2">
        <div>
          <label class="form-label">Tarjeta lateral: título</label>
          <input class="form-input" name="prices_highlight_title" value="{{ old('prices_highlight_title', $settings['prices_highlight_title'] ?? '') }}">
          @error('prices_highlight_title')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Tarjeta lateral: descripción</label>
          <textarea class="form-textarea" name="prices_highlight_subtitle" rows="2">{{ old('prices_highlight_subtitle', $settings['prices_highlight_subtitle'] ?? '') }}</textarea>
          @error('prices_highlight_subtitle')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Tarjeta lateral: imagen</label>
          <input class="form-input" type="file" name="prices_highlight_image" accept="image/*" data-image-input>
          <input type="hidden" name="prices_highlight_image_path" value="{{ $pricesHighlightImagePath }}">
          @error('prices_highlight_image')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
          @error('prices_highlight_image_path')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div class="flex items-center gap-3">
          <div class="h-20 w-28 overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
            @if($pricesHighlightImagePath)
              @php($highlightImage = $imageUrl->variants($pricesHighlightImagePath, 'banners', 'doctor'))
              <img
                class="h-full w-full object-cover"
                src="{{ $highlightImage['thumb'] }}"
                @if($highlightImage['srcset']) srcset="{{ $highlightImage['srcset'] }}" sizes="112px" @endif
                alt="Preview"
                loading="lazy"
                decoding="async"
              >
            @else
              <div class="flex h-full items-center justify-center text-xs text-slate-400">Sin imagen</div>
            @endif
          </div>
        </div>
        <div>
          <label class="form-label">Tarjeta inferior: título</label>
          <input class="form-input" name="prices_visit_title" value="{{ old('prices_visit_title', $settings['prices_visit_title'] ?? '') }}">
          @error('prices_visit_title')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Tarjeta inferior: descripción</label>
          <textarea class="form-textarea" name="prices_visit_subtitle" rows="2">{{ old('prices_visit_subtitle', $settings['prices_visit_subtitle'] ?? '') }}</textarea>
          @error('prices_visit_subtitle')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
      </div>

      <div class="mt-4 grid gap-4" data-price-list data-next-index="{{ count($pricesInput) }}">
        @foreach($pricesInput as $index => $price)
          <div class="card border border-slate-200 p-4" data-price-row data-row-key="price-{{ $index }}">
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label class="form-label">Servicio</label>
                <input class="form-input" name="prices[{{ $index }}][service]" value="{{ $price['service'] ?? '' }}">
                @error('prices.'.$index.'.service')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
              <div>
                <label class="form-label">Precio</label>
                <input class="form-input" name="prices[{{ $index }}][price]" value="{{ $price['price'] ?? '' }}">
                @error('prices.'.$index.'.price')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
              <div class="grid gap-3 sm:grid-cols-2 md:col-span-2">
                <div>
                  <label class="form-label">Orden</label>
                  <input class="form-input" type="number" min="0" name="prices[{{ $index }}][sort_order]" value="{{ $price['sort_order'] ?? 0 }}">
                  @error('prices.'.$index.'.sort_order')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
                </div>
                <div class="flex flex-col gap-2">
                  <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                    <input type="hidden" name="prices[{{ $index }}][is_active]" value="0">
                    <input type="checkbox" class="h-4 w-4 rounded border-slate-300" name="prices[{{ $index }}][is_active]" value="1" @checked($price['is_active'] ?? true)>
                    Activa
                  </label>
                </div>
              </div>
            </div>
            @error('prices.'.$index.'.is_active')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
            <button type="button" class="btn btn-ghost mt-3" data-remove-item>Quitar</button>
          </div>
        @endforeach
      </div>

      <template data-price-template>
        <div class="card border border-slate-200 p-4" data-price-row data-row-key="price-__INDEX__">
          <div class="grid gap-4 md:grid-cols-2">
            <div>
              <label class="form-label">Servicio</label>
              <input class="form-input" name="prices[__INDEX__][service]">
            </div>
            <div>
              <label class="form-label">Precio</label>
              <input class="form-input" name="prices[__INDEX__][price]">
            </div>
            <div class="grid gap-3 sm:grid-cols-2 md:col-span-2">
              <div>
                <label class="form-label">Orden</label>
                <input class="form-input" type="number" min="0" name="prices[__INDEX__][sort_order]" value="0">
              </div>
              <div class="flex flex-col gap-2">
                <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                  <input type="hidden" name="prices[__INDEX__][is_active]" value="0">
                  <input type="checkbox" class="h-4 w-4 rounded border-slate-300" name="prices[__INDEX__][is_active]" value="1" checked>
                  Activa
                </label>
              </div>
            </div>
          </div>
          <button type="button" class="btn btn-ghost mt-3" data-remove-item>Quitar</button>
        </div>
      </template>
    </section>

    <section class="card p-6">
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Especialidades</p>
        <h3 class="mt-2 text-lg font-semibold text-slate-900">Especialidades destacadas</h3>
        <p class="text-sm text-slate-500">Selecciona exactamente 3 especialidades activas.</p>
      </div>
      <div class="mt-4">
        <label class="inline-flex items-center gap-2 text-sm text-slate-600">
          <input type="hidden" name="show_services_block" value="0">
          <input type="checkbox" class="h-4 w-4 rounded border-slate-300" name="show_services_block" value="1" @checked(old('show_services_block', $settings['show_services_block'] ?? true))>
          Mostrar bloque de especialidades destacadas
        </label>
        @error('show_services_block')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
      <div class="mt-4 grid gap-4 md:grid-cols-2">
        <div>
          <label class="form-label">Etiqueta superior</label>
          <input class="form-input" name="services_badge" value="{{ old('services_badge', $settings['services_badge'] ?? '') }}">
          @error('services_badge')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Título del bloque</label>
          <input class="form-input" name="services_title" value="{{ old('services_title', $settings['services_title'] ?? '') }}">
          @error('services_title')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div class="md:col-span-2">
          <label class="form-label">Descripcion</label>
          <textarea class="form-textarea" name="services_subtitle" rows="2">{{ old('services_subtitle', $settings['services_subtitle'] ?? '') }}</textarea>
          @error('services_subtitle')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div class="md:col-span-2">
          <label class="form-label">Texto botón Ver todos</label>
          <input class="form-input" name="services_button_text" value="{{ old('services_button_text', $settings['services_button_text'] ?? '') }}">
          @error('services_button_text')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
      </div>
      <div class="mt-4 grid gap-3 md:grid-cols-3">
        @for($i = 0; $i < 3; $i++)
          <div>
            <label class="form-label">Especialidad {{ $i + 1 }}</label>
            <select class="form-input" name="featured_specialties[]">
              <option value="">Seleccionar especialidad</option>
              @foreach($especialidadesActivas as $esp)
                <option value="{{ $esp->id }}" @selected(($featuredInput[$i] ?? null) == $esp->id)>
                  {{ $esp->nombre }}
                </option>
              @endforeach
            </select>
            @error('featured_specialties.'.$i)<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
          </div>
        @endfor
      </div>
      @error('featured_specialties')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
    </section>
  </div>

  <section class="card p-6">
    <div>
      <p class="text-xs uppercase tracking-widest text-slate-500">Preview</p>
      <h3 class="mt-2 text-lg font-semibold text-slate-900">Vista previa en vivo</h3>
      <p class="text-sm text-slate-500">Los cambios se reflejan aquí sin guardar.</p>
    </div>

    <div class="mt-4 space-y-6" data-bienvenida-preview data-asset-base="{{ $assetBase }}" data-storage-base="{{ $storageBase }}">
      <section class="rounded-3xl border border-slate-200 bg-white p-6">
        <div class="grid gap-5 lg:grid-cols-[1.1fr_0.9fr]">
          <div class="space-y-4">
            <div class="inline-flex items-center gap-2 rounded-full bg-teal-50 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-teal-700">
              <span class="h-2 w-2 rounded-full bg-teal-500"></span>
              <span data-preview-hero-badge>{{ old('hero_badge', $settings['hero_badge'] ?? '') }}</span>
            </div>
            <h2 class="text-2xl font-semibold text-slate-900" data-preview-hero-title>{{ old('hero_title', $settings['hero_title'] ?? '') }}</h2>
            <p class="text-sm text-slate-600" data-preview-hero-subtitle>{{ old('hero_subtitle', $settings['hero_subtitle'] ?? '') }}</p>
            <div class="flex flex-wrap gap-3">
              <button class="btn btn-primary pointer-events-none" type="button" data-preview-hero-primary>{{ old('hero_primary_text', $settings['hero_primary_text'] ?? '') }}</button>
              <button class="btn btn-outline pointer-events-none" type="button" data-preview-hero-secondary>{{ old('hero_secondary_text', $settings['hero_secondary_text'] ?? '') }}</button>
            </div>
            <div class="grid gap-3 sm:grid-cols-2" data-preview-stats></div>
          </div>
          <div class="space-y-3">
            <div class="text-sm font-semibold text-slate-800">Galería (preview)</div>
            <div class="grid gap-3 sm:grid-cols-2" data-preview-slides></div>
          </div>
        </div>
      </section>

      <section class="rounded-3xl border border-slate-200 bg-white p-6">
        <div class="mb-3 text-sm font-semibold text-slate-800">Tarjetas informativas</div>
        <div class="grid gap-3 sm:grid-cols-2" data-preview-cards></div>
      </section>

      <section class="rounded-3xl border border-slate-200 bg-white p-6">
        <div class="mb-3 text-sm font-semibold text-slate-800">Tarifario</div>
        <p class="text-xs text-slate-500" data-preview-prices-subtitle>{{ old('prices_subtitle', $settings['prices_subtitle'] ?? '') }}</p>
        <div class="mt-3 space-y-2" data-preview-prices></div>
      </section>

      <section class="rounded-3xl border border-slate-200 bg-white p-6">
        <div class="mb-3 text-sm font-semibold text-slate-800">Nuestros doctores</div>
        <div class="grid gap-3 sm:grid-cols-2" data-preview-doctors></div>
      </section>

      <section class="rounded-3xl border border-slate-200 bg-white p-6">
        <div class="mb-3 text-sm font-semibold text-slate-800">Especialidades destacadas</div>
        <div class="grid gap-3 sm:grid-cols-2" data-preview-specialties></div>
      </section>
    </div>
  </section>
</div>

<script type="application/json" data-featured-options>
{!! json_encode($especialidadesActivas->map(fn($esp) => [
  'id' => $esp->id,
  'nombre' => $esp->nombre,
  'descripcion' => $esp->descripcion,
  'icono' => $esp->icono,
])->values(), JSON_UNESCAPED_UNICODE) !!}
</script>

