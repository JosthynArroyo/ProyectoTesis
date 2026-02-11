<aside class="dashboard-sidebar fixed top-0 bottom-0 left-0 z-40 h-screen -translate-x-full overflow-y-auto border-r border-slate-200/70 bg-white px-4 pb-6 pt-0 shadow-xl lg:translate-x-0 lg:shrink-0">
    <div class="top flex items-center justify-between">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3">
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-500">Panel</p>
                <p class="text-sm font-semibold text-slate-800">Clínica Don Bosco</p>
            </div>
        </a>
        <button class="close btn btn-ghost px-2 lg:hidden" aria-label="Cerrar menú">
            <i class="ri-close-line text-lg"></i>
        </button>
    </div>

    @php
      $featureStatus = app(\App\Services\FeatureAccessService::class)->status(auth()->user(), 'personalizacion');
      $canPersonalizacion = $featureStatus['can_access'] ?? false;
      $pendingPersonalizacion = $featureStatus['pending'] ?? false;
    @endphp

    <nav class="mt-7 flex flex-col gap-1.5 text-[0.95rem] font-semibold leading-6">
        <a href="{{ route('admin.dashboard') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('admin.dashboard') ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-slate-50' }}">
            <i class="ri-dashboard-line text-lg"></i> Inicio
        </a>

        @php($isUsuariosSection = request()->routeIs('admin.usuarios.*') && !request()->routeIs('admin.usuarios.create'))
        <a href="{{ route('admin.usuarios.index') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ $isUsuariosSection ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-slate-50' }}">
            <i class="ri-group-line text-lg"></i> Usuarios
        </a>

        <a href="{{ route('admin.usuarios.create') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('admin.usuarios.create') ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-slate-50' }}">
            <i class="ri-user-add-line text-lg"></i> Registrar usuario
        </a>

        @php($personalizacionOpen = request()->routeIs('admin.personalizacion.*'))
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
              <a href="{{ route('admin.personalizacion.bienvenida.edit') }}" class="flex min-h-[40px] items-center rounded-lg px-3 py-2 text-sm font-semibold {{ request()->routeIs('admin.personalizacion.bienvenida.*') ? 'bg-white text-teal-700' : 'text-slate-600 hover:bg-white' }}">
                Bienvenida
              </a>
            @else
              <button type="button" data-open-personalizacion class="flex min-h-[40px] items-center rounded-lg px-3 py-2 text-left text-sm font-semibold text-slate-600 hover:bg-white">
                Bienvenida
              </button>
            @endif
            @if($canPersonalizacion)
              <a href="{{ route('admin.personalizacion.servicios.edit') }}" class="flex min-h-[40px] items-center rounded-lg px-3 py-2 text-sm font-semibold {{ request()->routeIs('admin.personalizacion.servicios.*') ? 'bg-white text-teal-700' : 'text-slate-600 hover:bg-white' }}">
                Servicios
              </a>
            @else
              <button type="button" data-open-personalizacion class="flex min-h-[40px] items-center rounded-lg px-3 py-2 text-left text-sm font-semibold text-slate-600 hover:bg-white">
                Servicios
              </button>
            @endif
            <div class="flex min-h-[40px] items-center justify-between rounded-lg px-3 py-2 text-sm font-semibold text-slate-400 opacity-70">
              <span>Contacto</span>
              <span class="badge">Próximamente</span>
            </div>
          </div>
        </details>

        <a href="{{ route('admin.horarios.index') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('admin.horarios.*') ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-slate-50' }}">
            <i class="ri-time-line text-lg"></i> Horarios
        </a>

        <a href="{{ route('admin.perfil.edit') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('admin.perfil.*') ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-slate-50' }}">
            <i class="ri-account-circle-line text-lg"></i> Perfil
        </a>

        <a href="{{ route('admin.cambios-citas.index') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('admin.cambios-citas.*') ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-slate-50' }}">
            <i class="ri-refresh-line text-lg"></i> Cambios de citas
        </a>

        <a href="{{ route('admin.historial.index') }}" class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ request()->routeIs('admin.historial.*') ? 'bg-teal-50 text-teal-700' : 'text-slate-600 hover:bg-slate-50' }}">
            <i class="ri-file-list-2-line text-lg"></i> Historial clínico
        </a>

        <form id="logout-form" action="{{ route('salir') }}" method="POST" class="hidden">@csrf</form>
        <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" class="mt-5 flex min-h-[44px] items-center gap-3 rounded-xl px-3 py-2.5 text-rose-600 transition-colors hover:bg-rose-50">
            <i class="ri-logout-circle-r-line text-lg"></i> Cerrar sesión
        </a>
    </nav>
</aside>
