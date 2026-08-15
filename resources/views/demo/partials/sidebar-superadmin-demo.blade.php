<aside class="dashboard-sidebar fixed top-0 bottom-0 left-0 z-40 h-screen -translate-x-full overflow-y-auto border-r border-gray-200/70 bg-white px-4 pb-6 pt-0 shadow-xl lg:translate-x-0 lg:shrink-0">
    <div class="top flex items-center justify-between">
        <a href="{{ route('demo.superadmin.dashboard') }}" class="sidebar-brand">
            <span class="sidebar-brand__icon"><i class="ri-shield-star-line"></i></span>
            <div class="sidebar-brand__text">
                <small>Superadmin</small>
                <strong>{{ $clinicIdentity->name() }}</strong>
            </div>
        </a>
        <button type="button" class="close btn btn-ghost px-2 lg:hidden" aria-label="Cerrar menu" data-sidebar-close>
            <i class="ri-close-line text-lg"></i>
        </button>
    </div>

    <nav class="mt-7 flex flex-col gap-1.5 text-[0.95rem] font-semibold leading-6">
        <a href="{{ route('demo.superadmin.dashboard') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('demo.superadmin.dashboard') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            <i class="ri-dashboard-line text-lg"></i> Inicio
        </a>
        <a href="{{ route('demo.superadmin.administradores') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('demo.superadmin.administradores') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            <i class="ri-shield-user-line text-lg"></i> Administradores
        </a>
        <a href="{{ route('demo.superadmin.usuarios') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('demo.superadmin.usuarios') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            <i class="ri-group-line text-lg"></i> Usuarios
        </a>
        <a href="{{ route('demo.superadmin.solicitudes') }}" class="flex min-h-[44px] items-center justify-between gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('demo.superadmin.solicitudes') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            <span class="flex items-center gap-3"><i class="ri-notification-4-line text-lg"></i> Solicitudes</span>
            @if(($pendingPersonalizacion ?? 0) > 0)
                <span class="badge warning">{{ $pendingPersonalizacion }}</span>
            @endif
        </a>
        <a href="{{ route('demo.superadmin.personalizacion') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('demo.superadmin.personalizacion') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            <i class="ri-palette-line text-lg"></i> Personalización
        </a>
        <a href="{{ route('demo.superadmin.mantenimiento') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('demo.superadmin.mantenimiento') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            <i class="ri-tools-line text-lg"></i> Mantenimiento
        </a>
        <a href="{{ route('demo.superadmin.respaldos') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('demo.superadmin.respaldos*') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            <i class="ri-database-2-line text-lg"></i> Respaldos DB
        </a>
        <a href="{{ $logoutUrl }}" class="mt-5 flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 text-rose-600 transition-colors hover:bg-rose-50">
            <i class="ri-logout-circle-r-line text-lg"></i> Cerrar sesión
        </a>
    </nav>
</aside>
