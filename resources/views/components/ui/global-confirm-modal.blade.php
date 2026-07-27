{{-- resources/views/components/ui/global-confirm-modal.blade.php --}}
<div id="global-confirm-modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="global-confirm-title" aria-describedby="global-confirm-message" class="fixed inset-0 z-50 hidden overflow-y-auto overflow-x-hidden p-4 bg-gray-900/60 backdrop-blur-sm flex items-center justify-center transition-opacity duration-200">
  <div class="relative w-full max-w-md mx-auto">
    <div class="card shadow-2xl border border-gray-200/80 bg-white dark:border-gray-800 dark:bg-gray-900 rounded-2xl overflow-hidden">
      <div class="p-6 space-y-4">
        <div class="flex items-start gap-4">
          <div id="global-confirm-icon-bg" class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-rose-100 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400">
            <i id="global-confirm-icon" class="ri-error-warning-line text-2xl"></i>
          </div>
          <div class="space-y-1 pr-4">
            <h3 id="global-confirm-title" class="text-lg font-bold text-gray-900 dark:text-white leading-snug">¿Confirmar acción?</h3>
            <p id="global-confirm-target" class="text-sm font-semibold text-rose-600 dark:text-rose-400 hidden"></p>
          </div>
        </div>

        <p id="global-confirm-message" class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed"></p>

        <div id="global-confirm-consequence" class="rounded-xl bg-amber-50 dark:bg-amber-950/30 p-3 text-xs text-amber-800 dark:text-amber-300 flex items-center gap-2 border border-amber-200/60 dark:border-amber-900/40 hidden">
          <i class="ri-alert-line text-base text-amber-600 dark:text-amber-400 shrink-0"></i>
          <span id="global-confirm-consequence-text"></span>
        </div>
      </div>

      <div class="flex items-center justify-end gap-3 bg-gray-50/80 dark:bg-gray-850/60 px-6 py-4 border-t border-gray-100 dark:border-gray-800">
        <button type="button" id="global-confirm-cancel-btn" class="btn btn-ghost text-sm font-medium">
          Cancelar
        </button>
        <button type="button" id="global-confirm-submit-btn" class="btn btn-danger text-sm font-semibold flex items-center gap-2">
          <i class="ri-check-line"></i> <span id="global-confirm-submit-text">Sí, eliminar</span>
        </button>
      </div>
    </div>
  </div>
</div>
