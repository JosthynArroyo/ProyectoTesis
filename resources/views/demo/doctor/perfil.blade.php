@extends('layouts.demo')
@section('title', 'Perfil doctor | Demo')
@section('header-title', 'Perfil')
@section('header-subtitle', 'Informacion profesional del doctor demo')

@section('sidebar')
    @include('demo.partials.sidebar-doctor-demo')
@endsection

@section('main')
<div class="grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
    <section class="card p-6">
        <div class="flex flex-col items-center text-center">
            <div class="flex h-24 w-24 items-center justify-center rounded-full bg-emerald-100 text-2xl font-bold text-emerald-700">DD</div>
            <h2 class="mt-4 text-xl font-semibold text-gray-900">{{ $doctorProfile['name'] }}</h2>
            <p class="text-sm text-gray-500">{{ $doctorProfile['specialty'] }}</p>
            <p class="mt-2 text-sm text-gray-600">{{ $doctorProfile['summary'] }}</p>
        </div>
    </section>

    <section class="card p-6">
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="form-label">Correo</label>
                <input class="form-input" type="text" value="{{ $doctorProfile['email'] }}" readonly>
            </div>
            <div>
                <label class="form-label">Telefono</label>
                <input class="form-input" type="text" value="{{ $doctorProfile['phone'] }}" readonly>
            </div>
            <div>
                <label class="form-label">Especialidad</label>
                <input class="form-input" type="text" value="{{ $doctorProfile['specialty'] }}" readonly>
            </div>
            <div>
                <label class="form-label">Licencia</label>
                <input class="form-input" type="text" value="{{ $doctorProfile['license'] }}" readonly>
            </div>
        </div>
        <div class="mt-6">
            <button class="btn btn-primary demo-action-blocked">Guardar cambios</button>
        </div>
    </section>
</div>
@endsection
