@extends('layouts.demo')
@section('title', 'Personalizacion | Demo')
@section('header-title', 'Personalizacion')
@section('header-subtitle', 'Configuracion visual y de marca')

@section('sidebar')
    @include('demo.partials.sidebar-superadmin-demo')
@endsection

@section('main')
<div class="grid gap-6 xl:grid-cols-2">
    <section class="card p-6">
        <div class="page-header">
            <div class="page-header__info">
                <h2>Resumen de marca</h2>
                <p>Replica de la configuracion general del sitio publico.</p>
            </div>
        </div>
        <div class="mt-4 space-y-4">
            @foreach($brandingSections as $section)
                <div class="rounded-2xl border border-gray-200 bg-white/90 p-4">
                    <p class="text-xs uppercase tracking-widest text-gray-500">{{ $section['label'] }}</p>
                    <p class="mt-2 text-sm font-medium text-gray-900">{{ $section['value'] }}</p>
                </div>
            @endforeach
        </div>
        <div class="mt-6">
            <button type="button" class="btn btn-primary demo-action-blocked">Guardar cambios</button>
        </div>
    </section>

    <section class="card p-6">
        <div class="page-header">
            <div class="page-header__info">
                <h2>Vista previa</h2>
                <p>Hero y colores principales del sitio institucional.</p>
            </div>
        </div>
        <div class="mt-4 rounded-3xl bg-gradient-to-br from-teal-700 via-teal-600 to-blue-700 p-6 text-white">
            <p class="text-xs uppercase tracking-[0.2em] text-white/70">Pagina de bienvenida</p>
            <h3 class="mt-3 text-2xl font-semibold">Clinica demo con agenda, laboratorio y pagos</h3>
            <p class="mt-3 max-w-md text-sm leading-6 text-white/80">Explora la experiencia publica con una replica segura del sistema y sin tocar informacion real.</p>
            <div class="mt-5 flex flex-wrap gap-3">
                <button type="button" class="btn bg-white text-teal-700 demo-action-blocked">Actualizar banner</button>
                <button type="button" class="btn btn-outline border-white/40 text-white demo-action-blocked">Cambiar colores</button>
            </div>
        </div>
    </section>
</div>
@endsection
