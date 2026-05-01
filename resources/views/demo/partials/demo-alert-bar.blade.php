<div class="demo-banner border-b border-blue-700 bg-blue-600 text-sm font-medium text-white">
    <div class="page-shell min-w-0 px-4 py-2 sm:px-6">
        <div class="flex min-w-0 flex-wrap items-start justify-between gap-2 sm:items-center">
            <div class="flex min-w-0 flex-1 items-start gap-2 text-left">
                <i class="ri-information-line mt-0.5 shrink-0 text-base"></i>
                <p class="min-w-0 flex-1 break-words">
                    Estas navegando una demo publica con datos simulados. Ninguna accion modifica la base de datos.
                </p>
            </div>
            @if(request('notice') === 'logout')
                <span class="max-w-full break-words rounded-full bg-white/15 px-3 py-0.5 text-xs font-semibold">
                    Estas en modo demostracion. No hay una sesion real activa.
                </span>
            @endif
        </div>
    </div>
</div>
