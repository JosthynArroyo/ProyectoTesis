@extends('layouts.app')

@section('title', 'Selector de Perfiles - Demostración Interactiva')

@section('content')
<div class="relative min-h-screen bg-slate-50/80 bg-gradient-to-br from-slate-50 via-blue-50/20 to-indigo-50/30 py-8 px-4 sm:px-6 lg:px-8 flex flex-col justify-between overflow-x-hidden">
    {{-- Decoración atmosférica de fondo --}}
    <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
        <div class="absolute -top-40 -left-40 w-96 h-96 rounded-full bg-blue-100/50 blur-3xl"></div>
        <div class="absolute top-1/3 -right-40 w-96 h-96 rounded-full bg-indigo-100/40 blur-3xl"></div>
        <div class="absolute -bottom-40 left-1/3 w-96 h-96 rounded-full bg-sky-100/40 blur-3xl"></div>
    </div>

    <div class="relative max-w-7xl w-full mx-auto space-y-8 my-auto">
        {{-- Fila superior: Botón de retorno --}}
        <div class="flex items-center justify-start">
            <a href="{{ route('demo.clinic') }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white border border-slate-200/90 text-sm font-semibold text-slate-700 hover:text-slate-900 hover:bg-slate-50 hover:border-slate-300 shadow-sm transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                <i class="ri-arrow-left-line text-base text-slate-500" aria-hidden="true"></i>
                <span>Volver a la página principal</span>
            </a>
        </div>

        {{-- Encabezado Principal --}}
        <div class="text-center space-y-3 max-w-3xl mx-auto">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-blue-50 border border-blue-100 text-blue-600 text-xs font-semibold tracking-wide">
                <i class="ri-sparkling-line text-sm" aria-hidden="true"></i>
                <span>Vista previa interactiva</span>
            </div>

            <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-slate-900 tracking-tight leading-tight">
                Selecciona un perfil para explorar el sistema
            </h1>

            <p class="text-sm sm:text-base text-slate-600 leading-relaxed">
                Accede a paneles reales y flujos de trabajo con datos de demostración en un entorno seguro y preparado para evaluación.
            </p>
        </div>

        {{-- Franja Informativa de 4 Conceptos --}}
        <div class="max-w-5xl mx-auto bg-white/90 backdrop-blur-md rounded-2xl border border-slate-200/80 shadow-sm p-4 sm:p-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">
                {{-- Concepto 1: Acceso guiado --}}
                <div class="flex items-center gap-3.5 px-3 py-1">
                    <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 text-lg border border-blue-100/80" aria-hidden="true">
                        <i class="ri-shield-check-line"></i>
                    </div>
                    <div>
                        <h2 class="text-xs font-bold text-slate-900">Acceso guiado</h2>
                        <p class="text-[11px] text-slate-500 leading-snug">Recorre los principales paneles de cada rol.</p>
                    </div>
                </div>

                {{-- Concepto 2: Datos de demostración --}}
                <div class="flex items-center gap-3.5 px-3 py-1 pt-3 sm:pt-1">
                    <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 text-lg border border-blue-100/80" aria-hidden="true">
                        <i class="ri-database-2-line"></i>
                    </div>
                    <div>
                        <h2 class="text-xs font-bold text-slate-900">Datos de demostración</h2>
                        <p class="text-[11px] text-slate-500 leading-snug">Información preparada para explorar funcionalidades.</p>
                    </div>
                </div>

                {{-- Concepto 3: Prueba controlada --}}
                <div class="flex items-center gap-3.5 px-3 py-1 pt-3 sm:pt-1">
                    <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 text-lg border border-blue-100/80" aria-hidden="true">
                        <i class="ri-lock-line"></i>
                    </div>
                    <div>
                        <h2 class="text-xs font-bold text-slate-900">Prueba controlada</h2>
                        <p class="text-[11px] text-slate-500 leading-snug">Las operaciones demo no alteran el flujo productivo.</p>
                    </div>
                </div>

                {{-- Concepto 4: Entorno de evaluación --}}
                <div class="flex items-center gap-3.5 px-3 py-1 pt-3 sm:pt-1">
                    <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 text-lg border border-blue-100/80" aria-hidden="true">
                        <i class="ri-shield-user-line"></i>
                    </div>
                    <div>
                        <h2 class="text-xs font-bold text-slate-900">Entorno de evaluación</h2>
                        <p class="text-[11px] text-slate-500 leading-snug">Diseñado para conocer el sistema antes de implementarlo.</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Grid de 5 Tarjetas de Perfiles --}}
        @php
            $roleThemes = [
                'superadmin' => [
                    'border' => 'border-blue-200/80 hover:border-blue-400 hover:shadow-blue-500/10',
                    'icon_bg' => 'bg-blue-50 text-blue-600 border-blue-100 group-hover:bg-blue-600 group-hover:text-white',
                    'badge_bg' => 'bg-blue-50 text-blue-700 border-blue-200/70',
                    'button' => 'bg-blue-50 text-blue-600 group-hover:bg-blue-600 group-hover:text-white hover:bg-blue-600 hover:text-white',
                    'featured' => true,
                ],
                'administrador' => [
                    'border' => 'border-emerald-200/80 hover:border-emerald-400 hover:shadow-emerald-500/10',
                    'icon_bg' => 'bg-emerald-50 text-emerald-600 border-emerald-100 group-hover:bg-emerald-600 group-hover:text-white',
                    'badge_bg' => 'bg-emerald-50 text-emerald-700 border-emerald-200/70',
                    'button' => 'bg-emerald-50 text-emerald-700 group-hover:bg-emerald-600 group-hover:text-white hover:bg-emerald-600 hover:text-white',
                    'featured' => false,
                ],
                'doctor' => [
                    'border' => 'border-purple-200/80 hover:border-purple-400 hover:shadow-purple-500/10',
                    'icon_bg' => 'bg-purple-50 text-purple-600 border-purple-100 group-hover:bg-purple-600 group-hover:text-white',
                    'badge_bg' => 'bg-purple-50 text-purple-700 border-purple-200/70',
                    'button' => 'bg-purple-50 text-purple-700 group-hover:bg-purple-600 group-hover:text-white hover:bg-purple-600 hover:text-white',
                    'featured' => false,
                ],
                'paciente' => [
                    'border' => 'border-amber-200/80 hover:border-amber-400 hover:shadow-amber-500/10',
                    'icon_bg' => 'bg-amber-50 text-amber-600 border-amber-100 group-hover:bg-amber-600 group-hover:text-white',
                    'badge_bg' => 'bg-amber-50 text-amber-700 border-amber-200/70',
                    'button' => 'bg-amber-50 text-amber-700 group-hover:bg-amber-600 group-hover:text-white hover:bg-amber-600 hover:text-white',
                    'featured' => false,
                ],
                'laboratorio' => [
                    'border' => 'border-cyan-200/80 hover:border-cyan-400 hover:shadow-cyan-500/10',
                    'icon_bg' => 'bg-cyan-50 text-cyan-600 border-cyan-100 group-hover:bg-cyan-600 group-hover:text-white',
                    'badge_bg' => 'bg-cyan-50 text-cyan-700 border-cyan-200/70',
                    'button' => 'bg-cyan-50 text-cyan-700 group-hover:bg-cyan-600 group-hover:text-white hover:bg-cyan-600 hover:text-white',
                    'featured' => false,
                ],
            ];
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-5 pt-2">
            @foreach($roles as $key => $role)
                @php
                    $theme = $roleThemes[$key] ?? $roleThemes['superadmin'];
                @endphp

                <a href="{{ route('demo.access.role', ['role' => $key]) }}"
                   class="group relative bg-white rounded-3xl p-6 shadow-sm border {{ $theme['border'] }} hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between text-center overflow-hidden focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                   data-role="{{ $key }}"
                   aria-label="Acceder a la demostración como {{ $role['title'] }}">

                    {{-- Distintivo de esquina para Superadministrador --}}
                    @if($theme['featured'])
                        <div class="absolute top-0 left-0 bg-blue-600 text-white rounded-tl-2xl rounded-br-2xl w-8 h-8 flex items-center justify-center text-xs shadow-sm" aria-hidden="true" title="Perfil destacado">
                            <i class="ri-star-fill"></i>
                        </div>
                    @endif

                    <div>
                        {{-- Icono representativo --}}
                        <div class="mx-auto w-16 h-16 rounded-2xl flex items-center justify-center text-3xl border transition-all duration-300 {{ $theme['icon_bg'] }}" aria-hidden="true">
                            <i class="{{ $role['icon'] }}"></i>
                        </div>

                        {{-- Badge de categoría --}}
                        <div class="mt-4">
                            <span class="inline-block px-3 py-0.5 rounded-full text-xs font-semibold border {{ $theme['badge_bg'] }}">
                                {{ $role['badge'] }}
                            </span>
                        </div>

                        {{-- Nombre del rol --}}
                        <h3 class="mt-3 text-lg font-bold text-slate-900 group-hover:text-slate-950 transition-colors">
                            {{ $role['title'] }}
                        </h3>

                        {{-- Descripción --}}
                        <p class="mt-2 text-xs text-slate-500 leading-relaxed min-h-[48px]">
                            {{ $role['description'] }}
                        </p>
                    </div>

                    {{-- Botón de Acción --}}
                    <div class="mt-6">
                        <div class="w-full py-2.5 px-4 rounded-xl font-semibold text-sm flex items-center justify-center gap-2 transition-all duration-200 {{ $theme['button'] }}">
                            <span>Ingresar al panel</span>
                            <i class="ri-arrow-right-line group-hover:translate-x-1 transition-transform" aria-hidden="true"></i>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        {{-- Bloque Informativo Inferior --}}
        <div class="max-w-4xl mx-auto bg-white/70 backdrop-blur-sm rounded-2xl border border-slate-200/70 p-4 sm:p-5 shadow-sm">
            <div class="flex flex-col sm:flex-row items-center justify-center sm:justify-start gap-3.5 text-center sm:text-left">
                <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 text-lg border border-blue-100/80" aria-hidden="true">
                    <i class="ri-shield-check-line"></i>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-800">Esta vista previa permite conocer los principales módulos del sistema antes de implementarlo.</p>
                    <p class="text-[11px] text-slate-500">Elige el perfil que mejor se adapte a lo que deseas explorar.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
