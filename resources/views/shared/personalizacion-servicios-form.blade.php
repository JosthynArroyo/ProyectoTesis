@php($especialidades = collect($especialidades ?? []))
@php($iconOptions = config('iconos.especialidades', []))

<div class="space-y-6">
  <section class="card p-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Servicios</p>
        <h3 class="mt-2 text-lg font-semibold text-slate-900">Especialidades destacadas</h3>
        <p class="text-sm text-slate-500">Nombre, descripción, icono, estado y orden.</p>
      </div>
      <button type="button" class="btn btn-outline" data-add-especialidad>
        <i class="ri-add-line"></i> Agregar especialidad
      </button>
    </div>

    <div class="mt-4 grid gap-4" data-especialidad-list data-next-index="{{ $especialidades->count() }}">
      @foreach($especialidades as $index => $esp)
        @php($prefix = "especialidades[{$esp->id}]")
        @php($oldPrefix = "especialidades.{$esp->id}")
        <div class="card border border-slate-200 p-4" data-especialidad-row>
          <div class="grid gap-4 md:grid-cols-2">
            <div>
              <label class="form-label">Nombre</label>
              <input class="form-input" name="{{ $prefix }}[nombre]" value="{{ old($oldPrefix.'.nombre', $esp->nombre) }}">
              @error($oldPrefix.'.nombre')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
            </div>
            <div>
              <label class="form-label">Icono (Remixicon)</label>
              @php($currentIcon = old($oldPrefix.'.icono', $esp->icono))
              @php($currentOption = collect($iconOptions)->firstWhere('id', $currentIcon))
              <div class="space-y-2" data-icon-picker>
                <input type="hidden" name="{{ $prefix }}[icono]" value="{{ $currentIcon }}" data-icon-value>
                <input class="form-input" type="text" placeholder="Buscar icono: corazón, piel, niños, diente..." autocomplete="off" data-icon-search>
                <div class="flex flex-wrap items-center gap-3">
                  <div class="flex h-12 w-12 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-2xl text-slate-700" data-icon-preview>
                    @if($currentIcon)
                      <i class="{{ $currentIcon }}"></i>
                    @else
                      <span class="text-xs text-slate-400">Sin icono</span>
                    @endif
                  </div>
                  <div class="text-sm">
                    <p class="font-semibold text-slate-800" data-icon-selected-label>
                      {{ $currentOption['label'] ?? 'Usar icono por defecto' }}
                    </p>
                    <p class="text-xs text-slate-500" data-icon-selected-id>
                      {{ $currentIcon ?? 'Defecto' }}
                    </p>
                  </div>
                </div>
                <div class="max-h-52 overflow-auto rounded-xl border border-slate-200 bg-white shadow-sm" data-icon-list>
                  <div class="grid gap-2 p-2 sm:grid-cols-2">
                    @foreach($iconOptions as $option)
                      <button
                        type="button"
                        class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-left text-sm text-slate-700 hover:border-teal-400 hover:bg-teal-50"
                        data-icon-option
                        data-icon-id="{{ $option['id'] }}"
                        data-icon-label="{{ $option['label'] }}"
                        data-icon-keywords="{{ $option['keywords'] }}"
                      >
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-slate-50 text-lg text-slate-700">
                          @if($option['id'])
                            <i class="{{ $option['id'] }}"></i>
                          @else
                            <span class="text-[10px] text-slate-400">Default</span>
                          @endif
                        </span>
                        <span class="flex-1">{{ $option['label'] }}</span>
                      </button>
                    @endforeach
                  </div>
                </div>
              </div>
              @error($oldPrefix.'.icono')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
            </div>
            <div class="md:col-span-2">
              <label class="form-label">Descripción</label>
              <textarea class="form-textarea" name="{{ $prefix }}[descripcion]" rows="2">{{ old($oldPrefix.'.descripcion', $esp->descripcion) }}</textarea>
              @error($oldPrefix.'.descripcion')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
              <div>
                <label class="form-label">Orden</label>
                <input class="form-input" type="number" min="0" name="{{ $prefix }}[orden]" value="{{ old($oldPrefix.'.orden', $esp->orden ?? 0) }}">
                @error($oldPrefix.'.orden')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
              <div class="flex flex-col gap-2">
                <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                  <input type="hidden" name="{{ $prefix }}[activo]" value="0">
                  <input type="checkbox" class="h-4 w-4 rounded border-slate-300" name="{{ $prefix }}[activo]" value="1" @checked(old($oldPrefix.'.activo', (bool) $esp->activo))>
                  Activa
                </label>
                @error($oldPrefix.'.activo')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
              </div>
            </div>
          </div>
        </div>
      @endforeach
    </div>

    <template data-especialidad-template>
      <div class="card border border-slate-200 p-4" data-especialidad-row>
        <div class="grid gap-4 md:grid-cols-2">
          <div>
            <label class="form-label">Nombre</label>
            <input class="form-input" name="nuevas[__INDEX__][nombre]">
          </div>
          <div>
            <label class="form-label">Icono (Remixicon)</label>
            <div class="space-y-2" data-icon-picker>
              <input type="hidden" name="nuevas[__INDEX__][icono]" value="" data-icon-value>
              <input class="form-input" type="text" placeholder="Buscar icono: corazón, piel, niños, diente..." autocomplete="off" data-icon-search>
              <div class="flex flex-wrap items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-2xl text-slate-700" data-icon-preview>
                  <span class="text-xs text-slate-400">Sin icono</span>
                </div>
                <div class="text-sm">
                  <p class="font-semibold text-slate-800" data-icon-selected-label>Usar icono por defecto</p>
                  <p class="text-xs text-slate-500" data-icon-selected-id>Defecto</p>
                </div>
              </div>
              <div class="max-h-52 overflow-auto rounded-xl border border-slate-200 bg-white shadow-sm" data-icon-list>
                <div class="grid gap-2 p-2 sm:grid-cols-2">
                  @foreach($iconOptions as $option)
                    <button
                      type="button"
                      class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-left text-sm text-slate-700 hover:border-teal-400 hover:bg-teal-50"
                      data-icon-option
                      data-icon-id="{{ $option['id'] }}"
                      data-icon-label="{{ $option['label'] }}"
                      data-icon-keywords="{{ $option['keywords'] }}"
                    >
                      <span class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-slate-50 text-lg text-slate-700">
                        @if($option['id'])
                          <i class="{{ $option['id'] }}"></i>
                        @else
                          <span class="text-[10px] text-slate-400">Default</span>
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
              <label class="form-label">Descripción</label>
            <textarea class="form-textarea" name="nuevas[__INDEX__][descripcion]" rows="2"></textarea>
          </div>
          <div class="grid gap-3 sm:grid-cols-2">
            <div>
              <label class="form-label">Orden</label>
              <input class="form-input" type="number" min="0" name="nuevas[__INDEX__][orden]" value="0">
            </div>
            <div class="flex flex-col gap-2">
              <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                <input type="hidden" name="nuevas[__INDEX__][activo]" value="0">
                <input type="checkbox" class="h-4 w-4 rounded border-slate-300" name="nuevas[__INDEX__][activo]" value="1" checked>
                Activa
              </label>
            </div>
          </div>
        </div>
        <button type="button" class="btn btn-ghost mt-3" data-remove-item>Quitar</button>
      </div>
    </template>
  </section>
</div>

