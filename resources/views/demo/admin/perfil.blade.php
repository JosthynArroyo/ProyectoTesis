@extends('layouts.demo')
@section('title', 'Perfil admin | Demo')
@section('header-title', 'Perfil')
@section('header-subtitle', 'Informacion del administrador')

@section('sidebar')
    @include('demo.partials.sidebar-admin-demo')
@endsection

@section('main')
<div class="grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
    <section class="card p-6">
        <div class="flex flex-col items-center text-center">
            <div class="flex h-24 w-24 items-center justify-center rounded-full bg-teal-100 text-2xl font-bold text-teal-700">AD</div>
            <h2 class="mt-4 text-xl font-semibold text-gray-900">{{ $adminProfile['name'] }}</h2>
            <p class="text-sm text-gray-500">{{ $adminProfile['position'] }}</p>
            <p class="mt-2 text-sm text-gray-600">{{ $adminProfile['summary'] }}</p>
        </div>
    </section>

    <section class="card p-6">
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="form-label">Correo</label>
                <input class="form-input" type="text" value="{{ $adminProfile['email'] }}" readonly>
            </div>
            <div>
                <label class="form-label">Telefono</label>
                <input class="form-input" type="text" value="{{ $adminProfile['phone'] }}" readonly>
            </div>
            <div class="md:col-span-2">
                <label class="form-label">Direccion de la clinica</label>
                <input class="form-input" type="text" value="{{ $adminProfile['clinic_address'] }}" readonly>
            </div>
        </div>
        <div class="mt-6 flex gap-3">
            <button class="btn btn-primary demo-action-blocked">Guardar cambios</button>
            <button class="btn btn-outline demo-action-blocked">Cambiar avatar</button>
        </div>
    </section>
</div>
@endsection
