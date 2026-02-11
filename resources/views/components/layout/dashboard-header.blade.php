@props([
    'title' => null,
    'subtitle' => null,
    'role' => null,
    'profileRoute' => null,
    'user' => null,
])

@php
    $user = $user ?? Auth::user();
@endphp

<header class="dashboard-topbar border-b border-slate-200/80 bg-white">
    <div class="page-shell flex h-full flex-wrap items-center justify-between gap-4 px-3 py-2">
        <div class="flex min-w-0 items-center gap-3">
            <button id="menu_bar" data-open-sidebar class="btn btn-ghost px-2 lg:hidden" aria-label="Abrir menú">
                <i class="ri-menu-2-line text-lg"></i>
            </button>
            <div class="min-w-0">
                @if($role)
                    <p class="text-xs uppercase tracking-wide text-slate-500">Panel {{ $role }}</p>
                @endif
                @if($title)
                    <h1 class="text-lg font-semibold text-slate-900">{{ $title }}</h1>
                @endif
                @if($subtitle)
                    <p class="text-sm text-slate-500">{{ $subtitle }}</p>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-3">
            <div class="theme-toggler hidden items-center gap-1 rounded-full bg-slate-100 p-1 text-xs text-slate-500 md:flex">
                <span class="active inline-flex h-7 w-7 items-center justify-center rounded-full bg-white text-amber-500 shadow-sm">
                    <i class="ri-sun-line"></i>
                </span>
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-full text-slate-500">
                    <i class="ri-moon-line"></i>
                </span>
            </div>

            <button id="user-menu-button" data-dropdown-toggle="user-dropdown" class="flex items-center gap-3 rounded-full border border-slate-200 bg-white/80 px-2 py-1.5 shadow-sm">
                <span class="hidden max-w-[160px] truncate text-sm font-semibold text-slate-700 sm:inline lg:max-w-[220px]">{{ optional($user)->name ?? 'Usuario' }}</span>
                <span class="relative flex h-9 w-9 items-center justify-center overflow-hidden rounded-full bg-slate-100">
                    <img class="h-full w-full object-cover" src="{{ $user && $user->avatar ? asset('storage/' . $user->avatar) : asset('img/doctor1.jpg') }}" alt="Avatar">
                </span>
                <i class="ri-arrow-down-s-line text-slate-400"></i>
            </button>

            <div id="user-dropdown" class="z-50 hidden w-56 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl">
                <div class="px-3 py-2">
                    <p class="text-sm font-semibold text-slate-800">{{ optional($user)->name ?? 'Usuario' }}</p>
                    <p class="text-xs text-slate-500">{{ optional($user)->email ?? '' }}</p>
                </div>
                <div class="my-2 h-px bg-slate-100"></div>
                @if($profileRoute)
                    <a href="{{ $profileRoute }}" class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">
                        <i class="ri-user-line"></i> Perfil
                    </a>
                @endif
                <a href="{{ route('salir') }}"
                   onclick="event.preventDefault(); document.getElementById('logout-form-header').submit();"
                   class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm text-rose-600 hover:bg-rose-50">
                    <i class="ri-logout-circle-r-line"></i> Cerrar sesión
                </a>
                <form id="logout-form-header" action="{{ route('salir') }}" method="POST" class="hidden">@csrf</form>
            </div>
        </div>
    </div>
</header>
