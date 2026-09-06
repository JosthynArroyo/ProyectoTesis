@props([
    'title' => null,
    'subtitle' => null,
    'role' => null,
    'profileRoute' => null,
    'user' => null,
    'breadcrumbs' => null,
])

@php
    $user = $user ?? Auth::user();
    $avatarFolder = 'users';
    $avatarEntity = 'user';
    if ($user && ($user->hasRole('doctor') || $user->hasRole('laboratorio'))) {
        $avatarFolder = 'doctors';
        $avatarEntity = 'doctor';
    } elseif ($user && $user->hasRole('paciente')) {
        $avatarFolder = 'patients';
        $avatarEntity = 'patient';
    }
    $avatarImage = $imageUrl->variants($user?->avatar, $avatarFolder, $avatarEntity);

    $labelMap = [
        'admin' => 'Inicio',
        'superadmin' => 'Inicio',
        'paciente' => 'Inicio',
        'doctor' => 'Inicio',
        'laboratorio' => 'Inicio',
        'dashboard' => 'Dashboard',
        'perfil' => 'Perfil',
        'usuarios' => 'Usuarios',
        'historial' => 'Historial clínico',
        'horarios' => 'Horarios',
        'pagos' => 'Pagos',
        'citas' => 'Citas',
        'crear-cita' => 'Agendar cita',
        'editar-cita' => 'Editar cita',
        'mensajes' => 'Mensajes',
        'personalizacion' => 'Personalización',
        'solicitudes' => 'Solicitudes',
        'maintenance' => 'Mantenimiento',
        'admins' => 'Administradores',
        'ordenes' => 'Órdenes',
    ];
    $dashboardRouteMap = [
        'admin' => 'admin.dashboard',
        'superadmin' => 'superadmin.dashboard',
        'paciente' => 'paciente.dashboard',
        'doctor' => 'doctor.dashboard',
        'laboratorio' => 'laboratorio.dashboard',
    ];
    $currentRouteName = request()->route()?->getName();
    $isDashboardRoute = in_array($currentRouteName, array_values($dashboardRouteMap), true);

    $resolvedBreadcrumbs = is_array($breadcrumbs) ? $breadcrumbs : [];
    if (empty($resolvedBreadcrumbs)) {
        $segments = request()->segments();
        if (!empty($segments)) {
            $panelSegment = strtolower((string) ($segments[0] ?? ''));
            $panelDashboardRoute = $dashboardRouteMap[$panelSegment] ?? null;
            $panelDashboardUrl = $panelDashboardRoute && \Illuminate\Support\Facades\Route::has($panelDashboardRoute)
                ? route($panelDashboardRoute)
                : null;
            $accumulated = '';
            foreach ($segments as $index => $segment) {
                if (is_numeric($segment)) {
                    continue;
                }
                $accumulated .= '/' . $segment;
                $normalized = str_replace(['_', '-'], ' ', strtolower($segment));
                $label = $labelMap[strtolower($segment)] ?? ucfirst($normalized);
                $resolvedBreadcrumbs[] = [
                    'label' => $label,
                    'url' => $index === 0 ? ($panelDashboardUrl ?? url('/' . $segment)) : url($accumulated),
                ];
            }
        }
    }
    $showBreadcrumbs = count($resolvedBreadcrumbs) > 1 && !$isDashboardRoute;

    $headerMetrics = $dashboardHeaderMetrics ?? [];
    $pendingPagos = (int) ($headerMetrics['pendingPagos'] ?? 0);
    $conflictosHorarios = (int) ($headerMetrics['conflictosHorarios'] ?? 0);
    $notificacionesTotal = (int) ($headerMetrics['notificacionesTotal'] ?? ($pendingPagos + $conflictosHorarios));
@endphp

<x-layout.demo-preview-bar />

<header class="dashboard-topbar dashboard-topbar--with-sidebar border-b border-gray-200/80 bg-white" data-dashboard-topbar>
    <div class="page-shell flex h-full min-w-0 flex-wrap items-center justify-between gap-3 px-3 py-2 sm:gap-4">
        <div class="flex min-w-0 flex-1 items-center gap-3">
            <button id="menu_bar" data-open-sidebar class="btn btn-ghost px-2 lg:hidden" aria-label="Abrir menú">
                <i class="ri-menu-2-line text-lg"></i>
            </button>
            <div class="min-w-0">
                @if($role)
                    <p class="text-xs uppercase tracking-wide text-gray-500">Panel {{ $role }}</p>
                @endif
                @if($title)
                    <h1 class="text-lg font-semibold text-gray-900">{{ $title }}</h1>
                @endif
                @if($subtitle)
                    <p class="text-sm text-gray-500">{{ $subtitle }}</p>
                @endif
                @if($showBreadcrumbs)
                    <nav aria-label="Breadcrumb" class="mt-1 lg:hidden">
                        <ol class="flex flex-wrap items-center gap-1 text-xs text-gray-500">
                            @foreach($resolvedBreadcrumbs as $crumb)
                                <li class="flex items-center gap-1">
                                    @if(!$loop->last)
                                        <a href="{{ $crumb['url'] }}" class="hover:text-gray-700">{{ $crumb['label'] }}</a>
                                        <i class="ri-arrow-right-s-line text-gray-300"></i>
                                    @else
                                        <span class="font-semibold text-gray-700">{{ $crumb['label'] }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    </nav>
                @endif
            </div>
        </div>

        <div class="flex min-w-0 flex-wrap items-center justify-end gap-2 sm:gap-3">
            <x-layout.theme-toggle />

            @if($user && $user->hasRole('administrador'))
                <div class="relative">
                    <button id="admin-notifications-button"
                            data-dropdown-toggle="admin-notifications-dropdown"
                            type="button"
                            class="header-action-button relative"
                            aria-label="Notificaciones de administración">
                        <i class="ri-notification-3-line text-lg"></i>
                        @if($notificacionesTotal > 0)
                            <span class="absolute -right-1 -top-1 min-w-[18px] rounded-full bg-rose-500 px-1.5 text-center text-[10px] font-semibold text-white">
                                {{ $notificacionesTotal }}
                            </span>
                        @endif
                    </button>
                    <div id="admin-notifications-dropdown" class="z-50 hidden w-72 max-w-[calc(100vw-2rem)] rounded-2xl border border-gray-200 bg-white p-3 shadow-xl">
                        <p class="text-sm font-semibold text-gray-900">Centro de notificaciones</p>
                        <p class="mt-1 text-xs text-gray-500">Alertas operativas del panel admin.</p>
                        <div class="mt-3 space-y-2">
                            <a href="{{ route('admin.pagos.index', ['estado' => 'en_verificacion']) }}" class="flex items-center justify-between rounded-xl border border-gray-100 px-3 py-2 text-sm hover:bg-gray-50">
                                <span>Pagos por verificar</span>
                                <span class="badge warning">{{ $pendingPagos }}</span>
                            </a>
                            <a href="{{ route('admin.cambios-citas.index') }}" class="flex items-center justify-between rounded-xl border border-gray-100 px-3 py-2 text-sm hover:bg-gray-50">
                                <span>Conflictos de horario</span>
                                <span class="badge danger">{{ $conflictosHorarios }}</span>
                            </a>
                        </div>
                    </div>
                </div>
            @endif

            <button id="user-menu-button" data-dropdown-toggle="user-dropdown" class="header-user-button min-w-0">
                <span class="hidden max-w-[160px] truncate text-sm font-semibold text-gray-700 sm:inline lg:max-w-[220px]">{{ optional($user)->name ?? 'Usuario' }}</span>
                <span class="relative flex h-9 w-9 items-center justify-center overflow-hidden rounded-full bg-gray-100">
                    <img
                        class="h-full w-full {{ $avatarEntity === 'doctor' ? 'doctor-avatar-photo' : 'object-cover' }}"
                        src="{{ $avatarImage['thumb'] }}"
                        @if($avatarImage['srcset']) srcset="{{ $avatarImage['srcset'] }}" sizes="36px" @endif
                        alt="Avatar"
                        loading="eager"
                        decoding="async"
                    >
                </span>
                <i class="ri-arrow-down-s-line text-gray-400"></i>
            </button>

            <div id="user-dropdown" class="z-50 hidden w-56 max-w-[calc(100vw-2rem)] rounded-2xl border border-gray-200 bg-white p-2 shadow-xl">
                <div class="px-3 py-2">
                    <p class="text-sm font-semibold text-gray-800">{{ optional($user)->name ?? 'Usuario' }}</p>
                    <p class="text-xs text-gray-500">{{ optional($user)->email ?? '' }}</p>
                </div>
                <div class="my-2 h-px bg-gray-100"></div>
                @if($profileRoute)
                    <a href="{{ $profileRoute }}" class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">
                        <i class="ri-user-line"></i> Perfil
                    </a>
                @endif
                <button type="button" class="flex w-full cursor-pointer items-center gap-2 rounded-xl px-3 py-2 text-left text-sm text-gray-600 hover:bg-gray-50" data-legal-open="privacy-policy-modal">
                    <i class="ri-shield-check-line"></i> Políticas de privacidad
                </button>
                <button type="button" class="flex w-full cursor-pointer items-center gap-2 rounded-xl px-3 py-2 text-left text-sm text-gray-600 hover:bg-gray-50" data-legal-open="terms-service-modal">
                    <i class="ri-file-text-line"></i> Términos de servicio
                </button>
                <a href="#"
                   data-logout-trigger
                   data-logout-form="logout-form-header"
                   class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm text-rose-600 hover:bg-rose-50">
                    <i class="ri-logout-circle-r-line"></i> Cerrar sesión
                </a>
                <form id="logout-form-header" action="{{ route('salir') }}" method="POST" class="hidden">@csrf</form>
            </div>
        </div>
    </div>
</header>
