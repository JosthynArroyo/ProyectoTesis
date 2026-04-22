<aside class="dashboard-sidebar fixed top-0 bottom-0 left-0 z-40 h-screen -translate-x-full overflow-y-auto border-r border-slate-200/70 bg-white px-4 pb-6 pt-0 shadow-xl lg:translate-x-0 lg:shrink-0">
    <div class="top flex items-center justify-between">
        <a href="{{ route('admin.dashboard') }}" class="sidebar-brand">
            <span class="sidebar-brand__icon"><i class="ri-hospital-line"></i></span>
            <div class="sidebar-brand__text">
                <small>Panel Admin</small>
                <strong>Clínica Don Bosco</strong>
            </div>
        </a>
        <button class="close btn btn-ghost px-2 lg:hidden" aria-label="Cerrar menú">
            <i class="ri-close-line text-lg"></i>
        </button>
    </div>

    @php
      $layoutMetrics = $adminLayoutMetrics ?? [];
      $featureStatus = $layoutMetrics['personalizacion'] ?? ['can_access' => false, 'pending' => false, 'expires_at' => null];
      $recordatoriosPendientes = (int) ($layoutMetrics['recordatoriosPendientes'] ?? 0);
      $canPersonalizacion = $featureStatus['can_access'] ?? false;
      $pendingPersonalizacion = $featureStatus['pending'] ?? false;
      $isUsuariosSection = request()->routeIs('admin.usuarios.*') && ! request()->routeIs('admin.usuarios.create');
      $personalizacionOpen = request()->routeIs('admin.personalizacion.*');
    @endphp

    <nav class="mt-7 flex flex-col gap-1.5 text-[0.95rem] font-semibold leading-6">
        <a href="{{ route('admin.dashboard') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('admin.dashboard') ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-slate-50' }}">
            <i class="ri-dashboard-line text-lg"></i> Inicio
        </a>

        <p class="mt-4 px-3 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Gestión</p>

        <a href="{{ route('admin.usuarios.index') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ $isUsuariosSection ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-slate-50' }}">
            <i class="ri-group-line text-lg"></i> Usuarios
        </a>

        <a href="{{ route('admin.usuarios.create') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('admin.usuarios.create') ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-slate-50' }}">
            <i class="ri-user-add-line text-lg"></i> Registrar usuario
        </a>

        <details class="group rounded-xl {{ $personalizacionOpen ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-slate-50' }}" {{ $personalizacionOpen ? 'open' : '' }}>
          <summary class="flex min-h-[44px] cursor-pointer list-none items-center justify-between gap-3 rounded-xl px-3 py-2.5 transition-colors">
            <span class="flex items-center gap-3"><i class="ri-palette-line text-lg"></i> Personalización</span>
            <span class="flex items-center gap-2">
              @if($pendingPersonalizacion)
                <span class="badge warning">Pendiente</span>
              @endif
              <i class="ri-arrow-down-s-line text-lg"></i>
            </span>
          </summary>
          <div class="mb-2 flex flex-col gap-1 pl-11 pr-3">
            @if($canPersonalizacion)
              <a href="{{ route('admin.personalizacion.bienvenida.edit') }}" class="panel-subaction-button {{ request()->routeIs('admin.personalizacion.bienvenida.*') ? 'border-slate-200 bg-white text-teal-700 shadow-sm' : '' }}">
                Bienvenida
              </a>
            @else
              <button type="button" data-open-personalizacion class="panel-subaction-button">
                Bienvenida
              </button>
            @endif
            @if($canPersonalizacion)
              <a href="{{ route('admin.personalizacion.servicios.edit') }}" class="panel-subaction-button {{ request()->routeIs('admin.personalizacion.servicios.*') ? 'border-slate-200 bg-white text-teal-700 shadow-sm' : '' }}">
                Servicios
              </a>
            @else
              <button type="button" data-open-personalizacion class="panel-subaction-button">
                Servicios
              </button>
            @endif
            @if($canPersonalizacion)
              <a href="{{ route('admin.personalizacion.contacto.edit') }}" class="panel-subaction-button {{ request()->routeIs('admin.personalizacion.contacto.*') ? 'border-slate-200 bg-white text-teal-700 shadow-sm' : '' }}">
                Contacto
              </a>
            @else
              <button type="button" data-open-personalizacion class="panel-subaction-button">
                Contacto
              </button>
            @endif
          </div>
        </details>

        <a href="{{ route('admin.historial.index') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('admin.historial.*') ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-slate-50' }}">
            <i class="ri-file-list-2-line text-lg"></i> Historial clínico
        </a>

        <p class="mt-4 px-3 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Citas</p>

        <a href="{{ route('admin.horarios.index') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('admin.horarios.*') ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-slate-50' }}">
            <i class="ri-time-line text-lg"></i> Horarios
        </a>

        <a href="{{ route('admin.recordatorios.index') }}" class="flex min-h-[44px] items-center justify-between gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('admin.recordatorios.*') ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-slate-50' }}">
            <span class="flex items-center gap-3">
                <i class="ri-whatsapp-line text-lg"></i> Recordatorios
            </span>
            @if($recordatoriosPendientes > 0)
                <span class="badge warning">{{ $recordatoriosPendientes }}</span>
            @endif
        </a>

        <a href="{{ route('admin.citas.override.create') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('admin.citas.override.*') ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-slate-50' }}">
            <i class="ri-calendar-check-line text-lg"></i> Agendar manualmente
        </a>

        <a href="{{ route('admin.cambios-citas.index') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('admin.cambios-citas.*') ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-slate-50' }}">
            <i class="ri-refresh-line text-lg"></i> Cambios de citas
        </a>

        <p class="mt-4 px-3 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Financiero</p>

        <a href="{{ route('admin.pagos.index') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('admin.pagos.*') ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-slate-50' }}">
            <i class="ri-wallet-3-line text-lg"></i> Gestión de pagos
        </a>

        <p class="mt-4 px-3 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Configuración</p>

        <a href="{{ route('admin.perfil.edit') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('admin.perfil.*') ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-slate-50' }}">
            <i class="ri-account-circle-line text-lg"></i> Perfil
        </a>

        <a href="{{ route('admin.contacto.mensajes') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('admin.contacto.mensajes*') ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-slate-50' }}">
            <i class="ri-mail-line text-lg"></i> Notificaciones de contacto
        </a>

        <form id="logout-form" action="{{ route('salir') }}" method="POST" class="hidden">@csrf</form>
        <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" class="mt-5 flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 text-rose-600 transition-colors hover:bg-rose-50">
            <i class="ri-logout-circle-r-line text-lg"></i> Cerrar sesión
        </a>
    </nav>
</aside>
