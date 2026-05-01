@extends('layouts.demo')
@section('title', 'Perfil del paciente | Demo')
@section('header-title', 'Perfil')
@section('header-subtitle', 'Datos personales del paciente demo')

@section('sidebar')
    @include('demo.partials.sidebar-paciente-demo')
@endsection

@section('main')
<div class="grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
    <section class="card p-6">
        <div class="flex flex-col items-center text-center">
            <div class="flex h-24 w-24 items-center justify-center rounded-full bg-blue-100 text-2xl font-bold text-blue-700">PD</div>
            <h2 class="mt-4 text-xl font-semibold text-gray-900">{{ $patientProfile['name'] }}</h2>
            <p class="text-sm text-gray-500">{{ $patientProfile['email'] }}</p>
        </div>
    </section>

    <section class="card p-6">
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="form-label">Telefono</label>
                <input class="form-input" type="text" value="{{ $patientProfile['phone'] }}" readonly>
            </div>
            <div>
                <label class="form-label">Documento</label>
                <input class="form-input" type="text" value="{{ $patientProfile['document'] }}" readonly>
            </div>
            <div>
                <label class="form-label">Tipo de sangre</label>
                <input class="form-input" type="text" value="{{ $patientProfile['blood_type'] }}" readonly>
            </div>
            <div>
                <label class="form-label">Alergias</label>
                <input class="form-input" type="text" value="{{ $patientProfile['allergies'] }}" readonly>
            </div>
            <div class="md:col-span-2">
                <label class="form-label">Contacto de emergencia</label>
                <input class="form-input" type="text" value="{{ $patientProfile['emergency_contact'] }}" readonly>
            </div>
        </div>
        <div class="mt-6">
            <button class="btn btn-primary demo-action-blocked">Guardar cambios</button>
        </div>
    </section>
</div>
@endsection
