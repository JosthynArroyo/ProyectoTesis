<button
    type="button"
    data-theme-toggle
    class="theme-toggler inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-100/90 p-1 text-xs text-slate-500"
    aria-label="Cambiar tema"
    aria-pressed="false"
    title="Cambiar tema"
>
    <span
        data-theme-icon="light"
        class="inline-flex h-7 w-7 items-center justify-center rounded-full text-amber-500"
        aria-hidden="true"
    >
        <i class="ri-sun-line"></i>
    </span>
    <span
        data-theme-icon="dark"
        class="inline-flex h-7 w-7 items-center justify-center rounded-full text-slate-500"
        aria-hidden="true"
    >
        <i class="ri-moon-line"></i>
    </span>
    <span class="sr-only">Cambiar tema</span>
</button>

@once
    @push('modals')
        <div
            id="themePreferenceModal"
            class="modal theme-preference-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="themePreferenceTitle"
            aria-hidden="true"
            data-theme-preference-modal
        >
            <div class="modal-backdrop" data-theme-preference-dismiss></div>
            <div class="modal-dialog theme-preference-modal__dialog" role="document" tabindex="-1">
                <div class="card theme-preference-card mx-auto w-full max-w-md">
                    <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
                        <div class="flex items-start gap-3">
                            <span class="theme-preference-card__icon">
                                <i class="ri-moon-clear-line"></i>
                            </span>
                            <div>
                                <h3 id="themePreferenceTitle" class="text-lg font-semibold text-slate-900">Guardar modo oscuro como predeterminado</h3>
                                <p class="mt-1 text-sm text-slate-500">Si lo confirmas, este usuario entrar&aacute; siempre al panel en modo oscuro.</p>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="btn btn-ghost px-2"
                            aria-label="Cerrar confirmacion de tema"
                            data-theme-preference-dismiss
                        >
                            <i class="ri-close-line text-lg"></i>
                        </button>
                    </div>
                    <div class="space-y-4 px-6 py-5">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            Si prefieres mantener el modo claro como base, puedes seguir us&aacute;ndolo y cambiarlo luego cuando quieras.
                        </div>
                        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                            <button type="button" class="btn btn-outline" data-theme-preference-light>
                                Mantener claro
                            </button>
                            <button type="button" class="btn btn-primary" data-theme-preference-save-dark>
                                S&iacute;, usar oscuro
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endpush
@endonce
