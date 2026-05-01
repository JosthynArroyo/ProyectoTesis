<button
    type="button"
    data-theme-toggle
    class="theme-toggler inline-flex items-center gap-1 rounded-full border border-gray-200 bg-gray-100/90 p-1 text-xs text-gray-500"
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
        class="inline-flex h-7 w-7 items-center justify-center rounded-full text-gray-500"
        aria-hidden="true"
    >
        <i class="ri-moon-line"></i>
    </span>
    <span class="sr-only">Cambiar tema</span>
</button>

@once
    @push('modals')
        <div
            id="themePreferenceToast"
            class="toast theme-preference-toast"
            role="region"
            aria-label="Preferencia de tema"
            aria-live="polite"
            aria-atomic="true"
            hidden
            data-theme-preference-toast
        >
            <div class="toast-h border-b border-gray-100">
                <div class="flex min-w-0 items-start gap-3">
                    <span class="theme-preference-card__icon theme-preference-toast__icon">
                        <i class="ri-contrast-2-line" data-theme-preference-icon></i>
                    </span>
                    <div class="theme-preference-toast__summary min-w-0">
                        <h3 id="themePreferenceTitle" class="text-sm font-semibold text-gray-900" data-theme-preference-title>Preferencia de tema</h3>
                        <p class="mt-1 text-xs text-gray-500" data-theme-preference-description>
                            Puedes seguir navegando mientras decides si quieres dejar este cambio solo por ahora o guardarlo.
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    class="btn btn-ghost px-2"
                    aria-label="Cerrar notificacion de tema"
                    data-theme-preference-dismiss
                >
                    <i class="ri-close-line text-lg"></i>
                </button>
            </div>
            <div class="toast-b">
                <p class="text-xs text-gray-500" data-theme-preference-note>
                    Si no eliges guardar, este cambio se mantendr&aacute; solo durante la sesi&oacute;n actual.
                </p>
                <div class="theme-preference-toast__actions">
                    <button type="button" class="btn btn-outline px-3 py-2 text-xs" data-theme-preference-temporary>
                        Solo esta sesi&oacute;n
                    </button>
                    <button type="button" class="btn btn-primary px-3 py-2 text-xs" data-theme-preference-persist>
                        Guardar como predeterminado
                    </button>
                </div>
            </div>
        </div>
    @endpush
@endonce
