<div
  id="global-action-lock"
  class="fixed inset-0 z-[9999] hidden items-center justify-center px-4 py-6 sm:px-6"
  aria-hidden="true"
  data-global-action-lock
  data-action-lock-title="Procesando solicitud..."
  data-action-lock-description="Por favor, espera. No cierres esta página."
  tabindex="-1"
>
  <div class="absolute inset-0 bg-white/55 backdrop-blur-[2px] dark:bg-slate-950/55" data-global-action-lock-backdrop></div>
  <div
    class="relative w-full max-w-md rounded-3xl border border-white/70 bg-white px-6 py-7 text-center shadow-[0_24px_60px_rgba(15,23,42,0.18)] dark:border-slate-700/80 dark:bg-slate-900 dark:shadow-[0_30px_70px_rgba(2,6,23,0.55)]"
    role="status"
    aria-live="polite"
    aria-atomic="true"
  >
    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full text-[var(--accent)]" data-global-action-lock-spinner>
      <svg class="h-10 w-10 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity="0.2" stroke-width="3"></circle>
        <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
      </svg>
    </div>
    <h2 id="global-action-lock-title" class="mt-5 text-xl font-bold text-gray-900 dark:text-white">
      Procesando solicitud...
    </h2>
    <p id="global-action-lock-description" class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">
      Por favor, espera. No cierres esta página.
    </p>
  </div>
</div>
