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

            <button id="user-menu-button" data-dropdown-toggle="user-dropdown" class="header-user-button min-w-0">
                <span class="hidden max-w-[160px] truncate text-sm font-semibold text-gray-700 sm:inline lg:max-w-[220px]">{{ $demoUser['name'] ?? 'Usuario Demo' }}</span>
                <span class="relative flex h-9 w-9 items-center justify-center overflow-hidden rounded-full bg-gray-100">
                    <span class="flex h-full w-full items-center justify-center bg-teal-100 text-sm font-semibold text-teal-700">
                        {{ $demoUser['avatarInitials'] ?? 'DM' }}
                    </span>
                </span>
                <i class="ri-arrow-down-s-line text-gray-400"></i>
            </button>

            <div id="user-dropdown" class="z-50 hidden w-56 max-w-[calc(100vw-2rem)] rounded-2xl border border-gray-200 bg-white p-2 shadow-xl">
                <div class="px-3 py-2">
                    <p class="text-sm font-semibold text-gray-800">{{ $demoUser['name'] ?? 'Usuario Demo' }}</p>
                    <p class="text-xs text-gray-500">{{ $demoUser['email'] ?? 'demo@clinica.test' }}</p>
                </div>
                <div class="my-2 h-px bg-gray-100"></div>
                @if(\Illuminate\Support\Facades\Route::has('demo.' . $demoRole . '.perfil'))
                    <a href="{{ route('demo.' . $demoRole . '.perfil') }}" class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">
                        <i class="ri-user-line"></i> Perfil
                    </a>
                @endif
                <button type="button" class="flex w-full cursor-pointer items-center gap-2 rounded-xl px-3 py-2 text-left text-sm text-gray-600 hover:bg-gray-50" data-legal-open="privacy-policy-modal">
                    <i class="ri-shield-check-line"></i> Políticas de privacidad
                </button>
                <button type="button" class="flex w-full cursor-pointer items-center gap-2 rounded-xl px-3 py-2 text-left text-sm text-gray-600 hover:bg-gray-50" data-legal-open="terms-service-modal">
                    <i class="ri-file-text-line"></i> Términos de servicio
                </button>
                <a href="{{ $logoutUrl }}" class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm text-rose-600 hover:bg-rose-50">
                    <i class="ri-logout-circle-r-line"></i> Cerrar sesión
                </a>
            </div>
        </div>
    </div>
</header>
