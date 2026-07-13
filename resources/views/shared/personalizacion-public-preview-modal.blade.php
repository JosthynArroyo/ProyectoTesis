@php
  $title = $title ?? 'Vista previa';
  $description = $description ?? 'Preview reactiva sin guardar cambios.';
@endphp

<div class="personalizacion-public-preview-modal" data-public-preview-modal hidden>
  <div class="personalizacion-public-preview-backdrop" data-public-preview-close></div>
  <section class="personalizacion-public-preview-dialog" role="dialog" aria-modal="true" aria-labelledby="publicPreviewTitle">
    <div class="personalizacion-public-preview-header">
      <div class="min-w-0">
        <p class="text-xs uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500">Vista previa reactiva</p>
        <h3 id="publicPreviewTitle" class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $title }}</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <div class="rounded-2xl border border-gray-200 bg-gray-50 px-3 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-300">
          Vista escritorio
        </div>
        <button type="button" class="btn btn-ghost dark:text-gray-300 dark:hover:bg-gray-800" data-public-preview-close>
          <i class="ri-close-line"></i> Cerrar
        </button>
      </div>
    </div>

    <div class="personalizacion-public-preview-scroll">
      <div class="personalizacion-public-preview-stage" data-public-preview-stage>
        <div class="personalizacion-public-preview-scale">
          <div class="personalizacion-public-preview-frame">
            <div class="personalizacion-public-preview-surface" data-public-preview-surface>
              <div data-public-preview-root></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>
