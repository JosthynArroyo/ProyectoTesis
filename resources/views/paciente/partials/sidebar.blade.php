<aside>
    <div class="top">
        <div class="logo">
            <h2>Paciente <span class="danger"> Los Ángeles</span></h2>
        </div>
        <div class="close">
            <span class="material-symbols-outlined">close</span>
        </div>
    </div>

    <div class="sidebar">
        <a href="{{ route('paciente.dashboard') }}" @class(['active' => request()->routeIs('paciente.dashboard')])>
            <span class="material-symbols-outlined">dashboard</span>
            <h3>Inicio</h3>
        </a>

        <a href="{{ route('paciente.citas') }}" @class(['active' => request()->routeIs('paciente.citas', 'paciente.citas.*', 'paciente.editar-cita')])>
            <span class="material-symbols-outlined">calendar_month</span>
            <h3>Mis Citas</h3>
        </a>

        <a href="{{ route('paciente.crear-cita') }}" @class(['active' => request()->routeIs('paciente.crear-cita', 'paciente.crear-cita.*')])>
            <span class="material-symbols-outlined">add_circle</span>
            <h3>Agendar Cita</h3>
        </a>

        <a href="{{ route('paciente.perfil.edit') }}" @class(['active' => request()->routeIs('paciente.perfil.*')])>
            <span class="material-symbols-outlined">account_circle</span>
            <h3>Perfil</h3>
        </a>

        <form id="logout-form" action="{{ route('salir') }}" method="POST" style="display:none;">@csrf</form>
        <a href="#" class="logout-link" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
            <span class="material-symbols-outlined">logout</span>
            <h3>Cerrar Sesión</h3>
        </a>
    </div>
</aside>
