<aside>
    <div class="top">
        <div class="logo">
            <h2>Doctor <span class="primary">Los Ángeles</span></h2>
        </div>
        <div class="close">
            <span class="material-symbols-outlined">close</span>
        </div>
    </div>

    <div class="sidebar">
        @php($current = $active ?? '')
        <a href="{{ route('doctor.dashboard') }}" class="{{ $current === 'dashboard' ? 'active' : '' }}">
            <span class="material-symbols-outlined">dashboard</span>
            <h3>Inicio</h3>
        </a>

        <a href="{{ route('doctor.citas') }}" class="{{ $current === 'citas' ? 'active' : '' }}">
            <span class="material-symbols-outlined">calendar_month</span>
            <h3>Mis Citas</h3>
        </a>

        {{-- NUEVO: solo agregado, sin cambiar nada más --}}
        <a href="{{ route('doctor.recetas.index') }}" class="{{ $current === 'recetas' ? 'active' : '' }}">
            <span class="material-symbols-outlined">prescriptions</span>
            <h3>Historial de recetas</h3>
        </a>

        <a href="{{ route('doctor.perfil.edit') }}" class="{{ $current === 'perfil' ? 'active' : '' }}">
            <span class="material-symbols-outlined">account_circle</span>
            <h3>Perfil</h3>
        </a>

        <a href="{{ route('doctor.agenda') }}" class="{{ $current === 'agenda' ? 'active' : '' }}">
            <span class="material-symbols-outlined">event_note</span>
            <h3>Agenda Semanal</h3>
        </a>

        <a href="{{ route('doctor.horario.index') }}" class="{{ $current==='horario' ? 'active' : '' }}">
            <span class="material-symbols-outlined">schedule</span>
            <h3>Mi horario</h3>
        </a>

        <form id="doctor-logout-form" action="{{ route('salir') }}" method="POST" style="display: none;">
            @csrf
        </form>
        <a href="#" onclick="event.preventDefault(); document.getElementById('doctor-logout-form').submit();">
            <span class="material-symbols-outlined">logout</span>
            <h3>Cerrar Sesión</h3>
        </a>
    </div>
</aside>
