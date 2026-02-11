<aside class="dashboard-sidebar fixed top-0 bottom-0 left-0 z-40 h-screen -translate-x-full overflow-y-auto border-r border-slate-200/70 bg-white px-4 pb-6 pt-0 shadow-xl lg:translate-x-0 lg:shrink-0">
    <div class="top flex items-center justify-between">
        <a href="{{ route('laboratorio.dashboard') }}" class="flex items-center gap-3">
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-500">Panel</p>
                <p class="text-sm font-semibold text-slate-800">Laboratorio Don Bosco</p>
            </div>
        </a>
        <button class="close btn btn-ghost px-2 lg:hidden" aria-label="Cerrar menú">
            <i class="ri-close-line text-lg"></i>
        </button>
    </div>

    <nav class="mt-8 flex flex-col gap-2 text-sm font-semibold">
        @php($current = $active ?? '')
        <a href="{{ route('laboratorio.dashboard') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 {{ $current === 'dashboard' ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-slate-50' }}">
            <i class="ri-dashboard-line"></i> Inicio
        </a>

        <a href="{{ route('laboratorio.ordenes.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 {{ $current === 'ordenes' ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-slate-50' }}">
            <i class="ri-flask-line"></i> Citas y resultados
        </a>

        <form id="laboratorio-logout-form" action="{{ route('salir') }}" method="POST" class="hidden">@csrf</form>
        <a href="#" onclick="event.preventDefault(); document.getElementById('laboratorio-logout-form').submit();" class="mt-4 flex items-center gap-3 rounded-xl px-3 py-2 text-rose-600 hover:bg-rose-50">
            <i class="ri-logout-circle-r-line"></i> Cerrar sesión
        </a>
    </nav>
</aside>
