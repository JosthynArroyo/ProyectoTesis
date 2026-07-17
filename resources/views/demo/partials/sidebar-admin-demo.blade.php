<aside class="dashboard-sidebar fixed top-0 bottom-0 left-0 z-40 h-screen -translate-x-full overflow-y-auto border-r border-gray-200/70 bg-white px-4 pb-6 pt-0 shadow-xl lg:translate-x-0 lg:shrink-0">
    <div class="top flex items-center justify-between">
        <a href="{{ route('demo.admin.dashboard') }}" class="sidebar-brand">
            <span class="sidebar-brand__icon"><i class="ri-hospital-line"></i></span>
            <div class="sidebar-brand__text">
                <small>Panel Admin</small>
                <strong>{{ $clinicIdentity->name() }}</strong>
            </div>
        </a>
        <button type="button" class="close btn btn-ghost px-2 lg:hidden" aria-label="Cerrar menu" data-sidebar-close>
            <i class="ri-close-line text-lg"></i>
        </button>
    </div>

    <nav class="mt-7 flex flex-col gap-1.5 text-[0.95rem] font-semibold leading-6">
        <a href="{{ route('demo.admin.dashboard') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('demo.admin.dashboard') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            <i class="ri-dashboard-line text-lg"></i> Inicio
        </a>

        <p class="mt-4 px-3 text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-400">Operacion diaria</p>

        <a href="{{ route('demo.admin.agendar-manualmente') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('demo.admin.agendar-manualmente') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            <i class="ri-calendar-check-line text-lg"></i> Agendar manualmente
        </a>
        <a href="{{ route('demo.admin.horarios') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('demo.admin.horarios') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            <i class="ri-time-line text-lg"></i> Horarios
        </a>
        <a href="{{ route('demo.admin.cambios-citas') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('demo.admin.cambios-citas') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            <i class="ri-refresh-line text-lg"></i> Cambios de citas
        </a>
        <a href="{{ route('demo.admin.recordatorios') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('demo.admin.recordatorios') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            <i class="ri-whatsapp-line text-lg"></i> Recordatorios
        </a>

        <p class="mt-4 px-3 text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-400">Gestion clinica</p>

        <a href="{{ route('demo.admin.historial.index') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('demo.admin.historial.index') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            <i class="ri-file-list-2-line text-lg"></i> Historial clinico
        </a>

        <p class="mt-4 px-3 text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-400">Gestion administrativa</p>

        <a href="{{ route('demo.admin.usuarios.index') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('demo.admin.usuarios.index') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            <i class="ri-group-line text-lg"></i> Usuarios
        </a>
        <a href="{{ route('demo.admin.usuarios.create') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('demo.admin.usuarios.create') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            <i class="ri-user-add-line text-lg"></i> Registrar usuario
        </a>
        <a href="{{ route('demo.admin.gestion-pagos') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('demo.admin.gestion-pagos') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            <i class="ri-wallet-3-line text-lg"></i> Gestion de pagos
        </a>

        <p class="mt-4 px-3 text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-400">Sistema</p>

        <details class="group rounded-xl {{ request()->routeIs('demo.admin.personalizacion*') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}" {{ request()->routeIs('demo.admin.personalizacion*') ? 'open' : '' }}>
            <summary class="flex min-h-[44px] cursor-pointer list-none items-center justify-between gap-3 rounded-xl px-3 py-2.5 transition-colors">
                <span class="flex items-center gap-3"><i class="ri-palette-line text-lg"></i> Personalizacion</span>
                <i class="ri-arrow-down-s-line text-lg"></i>
            </summary>
            <div class="mb-2 flex flex-col gap-1 pl-11 pr-3">
                <a href="{{ route('demo.admin.personalizacion.index') }}#bienvenida" class="panel-subaction-button">Bienvenida</a>
                <a href="{{ route('demo.admin.personalizacion.index') }}#servicios" class="panel-subaction-button">Servicios</a>
                <a href="{{ route('demo.admin.personalizacion.index') }}#contacto" class="panel-subaction-button">Contacto</a>
            </div>
        </details>

        <a href="{{ route('demo.admin.perfil') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('demo.admin.perfil') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            <i class="ri-account-circle-line text-lg"></i> Perfil
        </a>
        <a href="{{ route('demo.admin.notificaciones-contacto') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('demo.admin.notificaciones-contacto') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            <i class="ri-mail-line text-lg"></i> Notificaciones de contacto
        </a>

        <a href="{{ $logoutUrl }}" class="mt-5 flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 text-rose-600 transition-colors hover:bg-rose-50">
            <i class="ri-logout-circle-r-line text-lg"></i> Cerrar sesion
        </a>
    </nav>
</aside>
