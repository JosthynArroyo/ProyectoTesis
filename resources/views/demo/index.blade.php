@extends('layouts.demo')

@section('title', 'Demo del sistema')
@section('header-title', 'Demo del sistema')
@section('header-subtitle', 'Selecciona un rol y explora una replica segura del sistema real.')
@section('header-actions')
    <a href="{{ route('home.index') }}" class="btn btn-outline" aria-label="Volver al inicio del sistema" data-demo-allow="true">
        <i class="ri-arrow-left-line"></i>
        Volver al inicio
    </a>
@endsection

@section('main')
<div class="w-full space-y-8 py-8">
    <section class="card overflow-hidden p-8">
        <div class="grid gap-8 lg:grid-cols-[1.2fr_0.8fr] lg:items-center">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-teal-600">Seccion publica</p>
                <h2 class="mt-3 text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">Explora la demo sin iniciar sesion</h2>
                <p class="mt-4 max-w-2xl text-base leading-7 text-gray-600">
                    Esta demostracion replica la interfaz operativa del sistema con datos simulados, navegacion segura y acciones bloqueadas. No se consultan datos reales ni se escribe nada en la base de datos.
                </p>
                <div class="mt-6 flex flex-wrap gap-3 text-sm">
                    <span class="badge success">Solo lectura</span>
                    <span class="badge info">Sin Auth</span>
                    <span class="badge neutral">Sin Eloquent</span>
                    <span class="badge warning">Sin rutas POST</span>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-3xl border border-gray-200 bg-gray-50 p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Cobertura</p>
                    <p class="mt-3 text-2xl font-semibold text-gray-900">5 roles demo</p>
                    <p class="mt-2 text-sm text-gray-600">Superadmin, Admin, Paciente, Doctor y Laboratorio.</p>
                </div>
                <div class="rounded-3xl border border-gray-200 bg-gray-50 p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Seguridad</p>
                    <p class="mt-3 text-2xl font-semibold text-gray-900">100% aislada</p>
                    <p class="mt-2 text-sm text-gray-600">Todas las acciones mutables muestran feedback y quedan deshabilitadas.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="demo-role-grid">
        @foreach($roleCards as $roleCard)
            <a href="{{ $roleCard['route'] }}" class="demo-role-card group card block h-full p-6 transition-all hover:-translate-y-1 hover:shadow-lg" data-demo-allow="true">
                <div class="flex h-full flex-col gap-6">
                    <div class="demo-role-card__header">
                        <span class="demo-role-card__icon inline-flex h-14 w-14 items-center justify-center rounded-2xl text-2xl {{ $roleCard['icon_classes'] }}">
                            <i class="{{ $roleCard['icon'] }}"></i>
                        </span>
                        <div class="demo-role-card__copy">
                            <h3 class="demo-role-card__title text-lg font-semibold text-gray-900 {{ $roleCard['hover_classes'] }}">{{ $roleCard['name'] }}</h3>
                            <p class="demo-role-card__description text-sm text-gray-600">{{ $roleCard['description'] }}</p>
                        </div>
                    </div>
                    <div class="demo-role-card__footer mt-auto flex items-center gap-2 text-sm font-semibold text-gray-700 {{ $roleCard['hover_classes'] }}">
                        Ingresar a la demo
                        <i class="ri-arrow-right-line transition-transform group-hover:translate-x-1"></i>
                    </div>
                </div>
            </a>
        @endforeach
    </section>
</div>
@endsection
