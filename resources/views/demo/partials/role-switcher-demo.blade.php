<div class="demo-role-switcher fixed bottom-4 right-4 z-50">
    <div class="rounded-2xl border border-gray-200 bg-white p-3 shadow-xl">
        <p class="mb-2 text-center text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500">Cambiar rol</p>
        <div class="flex flex-wrap justify-center gap-2">
            <a href="{{ route('demo.superadmin.dashboard') }}" data-role-switcher-link class="role-switcher-btn rounded-full px-3 py-1.5 text-sm {{ request()->routeIs('demo.superadmin.*') ? 'bg-teal-100 text-teal-800 font-semibold' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">Superadmin</a>
            <a href="{{ route('demo.admin.dashboard') }}" data-role-switcher-link class="role-switcher-btn rounded-full px-3 py-1.5 text-sm {{ request()->routeIs('demo.admin.*') ? 'bg-sky-100 text-sky-800 font-semibold' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">Admin</a>
            <a href="{{ route('demo.paciente.dashboard') }}" data-role-switcher-link class="role-switcher-btn rounded-full px-3 py-1.5 text-sm {{ request()->routeIs('demo.paciente.*') ? 'bg-blue-100 text-blue-800 font-semibold' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">Paciente</a>
            <a href="{{ route('demo.doctor.dashboard') }}" data-role-switcher-link class="role-switcher-btn rounded-full px-3 py-1.5 text-sm {{ request()->routeIs('demo.doctor.*') ? 'bg-emerald-100 text-emerald-800 font-semibold' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">Doctor</a>
            <a href="{{ route('demo.laboratorio.dashboard') }}" data-role-switcher-link class="role-switcher-btn rounded-full px-3 py-1.5 text-sm {{ request()->routeIs('demo.laboratorio.*') ? 'bg-amber-100 text-amber-800 font-semibold' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">Laboratorio</a>
        </div>
        <div class="mt-2.5 pt-2.5 border-t border-gray-100 flex justify-center">
            <a href="{{ route('demo.index') }}" data-role-switcher-link class="flex items-center gap-1.5 text-xs text-teal-600 hover:text-teal-700 font-semibold">
                <i class="ri-home-4-line"></i> Volver al inicio
            </a>
        </div>
    </div>
</div>
