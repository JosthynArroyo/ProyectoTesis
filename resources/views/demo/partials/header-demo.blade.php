<header @class([
    'dashboard-topbar border-b border-gray-200/80 bg-white',
    'dashboard-topbar--with-sidebar' => $hasSidebar,
]) data-dashboard-topbar>
    <div class="page-shell flex h-full min-w-0 flex-wrap items-center justify-between gap-3 px-4 py-3 sm:gap-4 sm:px-6">
        <div class="flex min-w-0 flex-1 items-center gap-3">
            @if($hasSidebar)
                <button type="button" class="btn btn-ghost px-2 lg:hidden" aria-label="Abrir menu" data-sidebar-toggle>
                    <i class="ri-menu-2-line text-lg"></i>
                </button>
            @endif
            <div class="min-w-0">
                <p class="text-xs uppercase tracking-wide text-gray-500">{{ $demoUser['roleLabel'] ?? 'Demo' }}</p>
                <h1 class="truncate text-lg font-semibold text-gray-900">{{ $headerTitle }}</h1>
                <p class="text-sm text-gray-500">{{ $headerSubtitle }}</p>
            </div>
        </div>

        <div class="flex min-w-0 flex-wrap items-center justify-end gap-3">
            @hasSection('header-actions')
                <div class="flex min-w-0 flex-wrap items-center justify-end gap-2">
                    @yield('header-actions')
                </div>
            @endif

            <x-layout.theme-toggle />

            <div class="flex items-center gap-2 rounded-full border border-gray-200 bg-gray-50 px-2 py-1.5">
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-teal-100 text-sm font-semibold text-teal-700">
                    {{ $demoUser['avatarInitials'] ?? 'DM' }}
                </span>
                <div class="hidden min-w-0 sm:block">
                    <p class="truncate text-sm font-semibold text-gray-800">{{ $demoUser['name'] ?? 'Usuario Demo' }}</p>
                    <p class="truncate text-xs text-gray-500">{{ $demoUser['email'] ?? 'demo@clinica.test' }}</p>
                </div>
            </div>
        </div>
    </div>
</header>
