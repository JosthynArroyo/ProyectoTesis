@extends('layouts.demo')
@section('title', 'Registrar usuario | Demo')
@section('header-title', 'Registrar usuario')
@section('header-subtitle', 'Alta visual de usuarios con formularios bloqueados')

@section('sidebar')
    @include('demo.partials.sidebar-admin-demo')
@endsection

@section('main')
<div class="space-y-6">
    <section class="card p-6">
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="form-label">Nombre completo</label>
                <input class="form-input" type="text" value="Paciente Demo Nuevo" readonly>
            </div>
            <div>
                <label class="form-label">Correo</label>
                <input class="form-input" type="email" value="nuevo.usuario@demo.clinica" readonly>
            </div>
            <div>
                <label class="form-label">Rol</label>
                <input class="form-input" type="text" value="Paciente" readonly>
            </div>
            <div>
                <label class="form-label">Telefono</label>
                <input class="form-input" type="text" value="+593 99 123 4567" readonly>
            </div>
            <div>
                <label class="form-label">Documento</label>
                <input class="form-input" type="text" value="0911122233" readonly>
            </div>
            <div>
                <label class="form-label">Clave temporal</label>
                <input class="form-input" type="password" value="temporal-demo" readonly>
            </div>
        </div>
        <div class="mt-6 flex flex-wrap gap-3">
            <button class="btn btn-primary demo-action-blocked">Registrar usuario</button>
            <button class="btn btn-outline demo-action-blocked">Enviar credenciales</button>
        </div>
    </section>
</div>
@endsection
