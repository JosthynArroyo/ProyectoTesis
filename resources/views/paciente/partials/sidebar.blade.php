<aside class="dashboard-sidebar fixed top-0 bottom-0 left-0 z-40 h-screen -translate-x-full overflow-y-auto border-r border-slate-200/70 bg-white px-4 pb-6 pt-0 shadow-xl lg:translate-x-0 lg:shrink-0">
    @php($bloqueoPagosPendientes = auth()->check() && auth()->user()->hasRole('paciente') ? auth()->user()->hasPendingPaymentBlocks() : false)
    <div class="top flex items-center justify-between">
        <a href="{{ route('paciente.dashboard') }}" class="sidebar-brand">
            <span class="sidebar-brand__icon"><i class="ri-user-heart-line"></i></span>
            <div class="sidebar-brand__text">
                <small>Portal Paciente</small>
                <strong>Clínica Don Bosco</strong>
            </div>
        </a>
        <button class="close btn btn-ghost px-2 lg:hidden" aria-label="Cerrar menú">
            <i class="ri-close-line text-lg"></i>
        </button>
    </div>

    <nav class="mt-7 flex flex-col gap-1.5 text-[0.95rem] font-semibold leading-6">
        <a href="{{ route('paciente.dashboard') }}" @class(['flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors', 'bg-teal-50 text-teal-700' => request()->routeIs('paciente.dashboard'), 'text-slate-600 hover:bg-slate-50' => !request()->routeIs('paciente.dashboard')])>
            <i class="ri-dashboard-line text-lg"></i> Inicio
        </a>

        <a href="{{ route('paciente.citas') }}" @class(['flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors', 'bg-teal-50 text-teal-700' => request()->routeIs('paciente.citas', 'paciente.citas.*', 'paciente.editar-cita'), 'text-slate-600 hover:bg-slate-50' => !request()->routeIs('paciente.citas', 'paciente.citas.*', 'paciente.editar-cita')])>
            <i class="ri-calendar-line text-lg"></i> Mis citas
        </a>

        <a href="{{ route('paciente.pagos.index') }}" @class(['flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors', 'bg-teal-50 text-teal-700' => request()->routeIs('paciente.pagos.*'), 'text-slate-600 hover:bg-slate-50' => !request()->routeIs('paciente.pagos.*')])>
            <i class="ri-wallet-3-line text-lg"></i> Mis pagos
        </a>

        <a href="{{ route('paciente.historial') }}" @class(['flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors', 'bg-teal-50 text-teal-700' => request()->routeIs('paciente.historial*'), 'text-slate-600 hover:bg-slate-50' => !request()->routeIs('paciente.historial*')])>
            <i class="ri-file-list-2-line text-lg"></i> Historial clínico
        </a>

        <a href="{{ route('paciente.laboratorio.index') }}" @class(['flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors', 'bg-teal-50 text-teal-700' => request()->routeIs('paciente.laboratorio.index', 'paciente.laboratorio.download'), 'text-slate-600 hover:bg-slate-50' => !request()->routeIs('paciente.laboratorio.index', 'paciente.laboratorio.download')])>
            <i class="ri-test-tube-line text-lg"></i> Resultados
        </a>

        <a href="{{ route('paciente.laboratorio.solicitar') }}" @class(['flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors', 'bg-teal-50 text-teal-700' => request()->routeIs('paciente.laboratorio.solicitar*'), 'text-slate-600 hover:bg-slate-50' => !request()->routeIs('paciente.laboratorio.solicitar*')])>
            <i class="ri-flask-line text-lg"></i> Solicitar examen
        </a>

        @if($bloqueoPagosPendientes)
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-amber-800">
                <p class="flex items-center gap-3 font-semibold"><i class="ri-lock-2-line text-lg"></i> Agendar cita</p>
                <p class="mt-2 text-xs font-medium">Tiene pagos pendientes. Regularice su cuenta para agendar una nueva cita.</p>
            </div>
        @else
            <a href="{{ route('paciente.crear-cita') }}" @class(['flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors', 'bg-teal-50 text-teal-700' => request()->routeIs('paciente.crear-cita', 'paciente.crear-cita.*'), 'text-slate-600 hover:bg-slate-50' => !request()->routeIs('paciente.crear-cita', 'paciente.crear-cita.*')])>
                <i class="ri-add-circle-line text-lg"></i> Agendar cita
            </a>
        @endif

        <a href="{{ route('paciente.perfil.edit') }}" @class(['flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors', 'bg-teal-50 text-teal-700' => request()->routeIs('paciente.perfil.*'), 'text-slate-600 hover:bg-slate-50' => !request()->routeIs('paciente.perfil.*')])>
            <i class="ri-account-circle-line text-lg"></i> Perfil
        </a>

        <form id="logout-form" action="{{ route('salir') }}" method="POST" class="hidden">@csrf</form>
        <a href="#" class="mt-5 flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors text-rose-600 hover:bg-rose-50" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
            <i class="ri-logout-circle-r-line text-lg"></i> Cerrar sesión
        </a>
    </nav>
</aside>
