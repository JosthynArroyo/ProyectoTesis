@php
    $isDemo = $applicationMode?->isDemo() ?? app(\App\Services\ApplicationModeService::class)->isDemo();
    $currentUser = Auth::user();
    $currentRoleName = null;
    if ($currentUser) {
        if ($currentUser->hasRole('superadmin')) {
            $currentRoleName = 'Superadmin';
        } elseif ($currentUser->hasRole('administrador')) {
            $currentRoleName = 'Admin';
        } elseif ($currentUser->hasRole('doctor')) {
            $currentRoleName = 'Médico';
        } elseif ($currentUser->hasRole('paciente')) {
            $currentRoleName = 'Paciente';
        } elseif ($currentUser->hasRole('laboratorio')) {
            $currentRoleName = 'Laboratorio';
        }
    }
@endphp

@if($isDemo)
<div class="bg-gradient-to-r from-slate-900 via-sky-950 to-slate-900 text-white px-3 py-1.5 text-xs font-medium border-b border-sky-800/40 shadow-inner select-none transition-all" data-demo-preview-bar role="status" aria-label="Indicador de vista previa">
    <div class="page-shell flex flex-wrap items-center justify-between gap-2">
        <div class="flex items-center gap-2 min-w-0">
            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-sky-500/20 text-sky-300 font-semibold text-[11px] border border-sky-400/30 shrink-0">
                <i class="ri-sparkling-fill text-sky-400"></i>
                Vista previa
            </span>
            @if($currentRoleName)
                <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 font-medium text-[10px] border border-slate-700 shrink-0">
                    Rol: {{ $currentRoleName }}
                </span>
            @endif
            <span class="text-slate-300 text-[11px] truncate hidden sm:inline">· Los cambios no se almacenan</span>
        </div>

        <div class="flex items-center gap-2 sm:gap-3 shrink-0">
            @if(\Illuminate\Support\Facades\Route::has('demo.access.selector'))
                <a href="{{ route('demo.access.selector') }}" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-sky-600 hover:bg-sky-500 text-white text-[11px] font-semibold transition-all shadow-sm active:scale-95" id="demo-bar-switch-role">
                    <i class="ri-user-shared-line"></i>
                    <span>Cambiar perfil</span>
                </a>
            @endif
            @if(\Illuminate\Support\Facades\Route::has('home.index'))
                <a href="{{ route('home.index') }}" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-[11px] font-semibold transition-all border border-slate-700 active:scale-95" id="demo-bar-back-product">
                    <i class="ri-home-4-line"></i>
                    <span>Volver al producto</span>
                </a>
            @endif
        </div>
    </div>
</div>
@endif
