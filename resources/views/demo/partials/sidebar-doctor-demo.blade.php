<aside class="dashboard-sidebar fixed top-0 bottom-0 left-0 z-40 h-screen -translate-x-full overflow-y-auto border-r border-gray-200/70 bg-white px-4 pb-6 pt-0 shadow-xl lg:translate-x-0 lg:shrink-0">
    <div class="top flex items-center justify-between">
        <a href="{{ route('demo.doctor.dashboard') }}" class="sidebar-brand">
            <span class="sidebar-brand__icon"><i class="ri-stethoscope-line"></i></span>
            <div class="sidebar-brand__text">
                <small>Panel Medico</small>
                <strong>{{ $clinicIdentity->name() }}</strong>
            </div>
        </a>
        <button type="button" class="close btn btn-ghost px-2 lg:hidden" aria-label="Cerrar menu" data-sidebar-close>
            <i class="ri-close-line text-lg"></i>
        </button>
    </div>

    <nav class="mt-7 flex flex-col gap-1.5 text-[0.95rem] font-semibold leading-6">
        <a href="{{ route('demo.doctor.dashboard') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('demo.doctor.dashboard') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            <i class="ri-dashboard-line text-lg"></i> Inicio
        </a>
        <a href="{{ route('demo.doctor.citas') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('demo.doctor.citas') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            <i class="ri-calendar-line text-lg"></i> Mis citas
        </a>
        <a href="{{ route('demo.doctor.agenda-semanal') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('demo.doctor.agenda-semanal') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            <i class="ri-calendar-event-line text-lg"></i> Agenda semanal
        </a>
        <a href="{{ route('demo.doctor.mi-horario') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('demo.doctor.mi-horario') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            <i class="ri-time-line text-lg"></i> Mi horario
        </a>
        <a href="{{ route('demo.doctor.pacientes') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('demo.doctor.pacientes') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            <i class="ri-group-line text-lg"></i> Pacientes
        </a>
        <a href="{{ route('demo.doctor.historial-recetas') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('demo.doctor.historial-recetas') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            <i class="ri-file-list-3-line text-lg"></i> Historial de recetas
        </a>
        <a href="#" data-demo-blocked class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors text-gray-600 hover:bg-gray-50">
            <i class="ri-flask-line text-lg"></i> Pedidos de laboratorio
        </a>
        <a href="{{ route('demo.doctor.perfil') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('demo.doctor.perfil') ? 'bg-gray-100 text-gray-900 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            <i class="ri-account-circle-line text-lg"></i> Perfil
        </a>
        <a href="{{ $logoutUrl }}" class="mt-5 flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 text-rose-600 transition-colors hover:bg-rose-50">
            <i class="ri-logout-circle-r-line text-lg"></i> Cerrar sesion
        </a>
    </nav>
</aside>
