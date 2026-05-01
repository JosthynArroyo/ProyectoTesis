<aside class="dashboard-sidebar fixed top-0 bottom-0 left-0 z-40 h-screen -translate-x-full overflow-y-auto border-r border-gray-200/70 bg-white px-4 pb-6 pt-0 shadow-xl lg:translate-x-0 lg:shrink-0">
    <div class="top flex items-center justify-between">
        <a href="{{ route('demo.paciente.dashboard') }}" class="sidebar-brand">
            <span class="sidebar-brand__icon"><i class="ri-user-heart-line"></i></span>
            <div class="sidebar-brand__text">
                <small>Portal Paciente</small>
                <strong>{{ $clinicName }}</strong>
            </div>
        </a>
        <button type="button" class="close btn btn-ghost px-2 lg:hidden" aria-label="Cerrar menu" data-sidebar-close>
            <i class="ri-close-line text-lg"></i>
        </button>
    </div>

    <nav class="mt-7 flex flex-col gap-1.5 text-[0.95rem] font-semibold leading-6">
        <a href="{{ route('demo.paciente.dashboard') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('demo.paciente.dashboard') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            <i class="ri-dashboard-line text-lg"></i> Inicio
        </a>
        <a href="{{ route('demo.paciente.agendar-cita') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('demo.paciente.agendar-cita') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            <i class="ri-add-circle-line text-lg"></i> Agendar cita
        </a>
        <a href="{{ route('demo.paciente.citas') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('demo.paciente.citas') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            <i class="ri-calendar-line text-lg"></i> Mis citas
        </a>
        <a href="{{ route('demo.paciente.resultados') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('demo.paciente.resultados') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            <i class="ri-test-tube-line text-lg"></i> Resultados
        </a>
        <a href="{{ route('demo.paciente.historial-clinico') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('demo.paciente.historial-clinico') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            <i class="ri-file-list-2-line text-lg"></i> Historial clinico
        </a>
        <a href="{{ route('demo.paciente.pagos') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('demo.paciente.pagos') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            <i class="ri-wallet-3-line text-lg"></i> Ordenes de cobro
        </a>
        <a href="{{ route('demo.paciente.solicitar-examen') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('demo.paciente.solicitar-examen') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            <i class="ri-flask-line text-lg"></i> Solicitar examen
        </a>
        <a href="{{ route('demo.paciente.perfil') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('demo.paciente.perfil') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            <i class="ri-account-circle-line text-lg"></i> Perfil
        </a>
        <a href="{{ $logoutUrl }}" class="mt-5 flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 text-rose-600 transition-colors hover:bg-rose-50">
            <i class="ri-logout-circle-r-line text-lg"></i> Cerrar sesion
        </a>
    </nav>
</aside>
