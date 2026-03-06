<aside class="dashboard-sidebar fixed top-0 bottom-0 left-0 z-40 h-screen -translate-x-full overflow-y-auto border-r border-slate-200/70 bg-white px-4 pb-6 pt-0 shadow-xl lg:translate-x-0 lg:shrink-0">
    <div class="top flex items-center justify-between">
        <a href="{{ route('laboratorio.dashboard') }}" class="sidebar-brand">
            <span class="sidebar-brand__icon"><i class="ri-test-tube-line"></i></span>
            <div class="sidebar-brand__text">
                <small>Laboratorio</small>
                <strong>Clínica Don Bosco</strong>
            </div>
        </a>
        <button class="close btn btn-ghost px-2 lg:hidden" aria-label="Cerrar menú">
            <i class="ri-close-line text-lg"></i>
        </button>
    </div>

    <nav class="mt-7 flex flex-col gap-1.5 text-[0.95rem] font-semibold leading-6">
        @php($current = $active ?? '')
        <a href="{{ route('laboratorio.dashboard') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ $current === 'dashboard' ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-slate-50' }}">
            <i class="ri-dashboard-line text-lg"></i> Inicio
        </a>

        <a href="{{ route('laboratorio.ordenes.index') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ $current === 'ordenes' ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-slate-50' }}">
            <i class="ri-flask-line text-lg"></i> Citas y resultados
        </a>

        <form id="laboratorio-logout-form" action="{{ route('salir') }}" method="POST" class="hidden">@csrf</form>
        <a href="#" onclick="event.preventDefault(); document.getElementById('laboratorio-logout-form').submit();" class="mt-5 flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 text-rose-600 transition-colors hover:bg-rose-50">
            <i class="ri-logout-circle-r-line text-lg"></i> Cerrar sesión
        </a>
    </nav>
</aside>
