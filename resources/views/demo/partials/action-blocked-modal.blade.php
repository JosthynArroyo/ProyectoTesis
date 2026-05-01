<div id="demo-action-blocked-modal" tabindex="-1" aria-hidden="true" class="demo-modal-overlay hidden fixed inset-0 z-[100] flex items-center justify-center p-4">
    <div class="w-full max-w-md rounded-3xl border border-gray-200 bg-white p-6 shadow-2xl">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-600">Modo demo</p>
                <h3 class="mt-2 text-xl font-semibold text-gray-900">Accion bloqueada</h3>
            </div>
            <button id="close-demo-modal" type="button" class="btn btn-ghost px-2" aria-label="Cerrar aviso" data-demo-allow="true">
                <i class="ri-close-line text-lg"></i>
            </button>
        </div>

        <div class="mt-4 flex items-start gap-4 rounded-2xl border border-blue-100 bg-blue-50 px-4 py-4 text-blue-900">
            <i class="ri-shield-keyhole-line text-2xl"></i>
            <p id="demo-action-blocked-message" class="text-sm leading-6">
                Disponible solo para usuarios registrados. Esta es una demostracion con datos simulados.
            </p>
        </div>

        <div class="mt-6 flex justify-end">
            <button type="button" class="btn btn-primary" data-demo-allow="true" data-demo-close-modal>
                Entendido
            </button>
        </div>
    </div>
</div>
