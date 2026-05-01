@extends('layouts.demo')
@section('title', 'Usuarios | Demo')
@section('header-title', 'Usuarios')
@section('header-subtitle', 'Gestion y control de usuarios')

@section('sidebar')
    @include('demo.partials.sidebar-admin-demo')
@endsection

@section('main')
<div class="space-y-6">
    <div class="panel-action-bar">
        <button class="btn btn-outline btn-sm btn-full-mobile demo-action-blocked"><i class="ri-file-excel-2-line"></i> Excel</button>
        <button class="btn btn-outline btn-sm btn-full-mobile demo-action-blocked"><i class="ri-file-pdf-line"></i> PDF</button>
        <a class="btn btn-primary btn-full-mobile" href="{{ route('demo.admin.registrar-usuario') }}"><i class="ri-user-add-line"></i> Crear usuario</a>
    </div>

    <section class="stat-grid">
        <x-ui.stat label="Pacientes" :value="2" tone="teal">
            <x-slot:icon><i class="ri-heart-pulse-line"></i></x-slot:icon>
        </x-ui.stat>
        <x-ui.stat label="Doctores" :value="1" tone="sky">
            <x-slot:icon><i class="ri-stethoscope-line"></i></x-slot:icon>
        </x-ui.stat>
        <x-ui.stat label="Laboratorio" :value="1" tone="amber">
            <x-slot:icon><i class="ri-test-tube-line"></i></x-slot:icon>
        </x-ui.stat>
    </section>

    <section class="card p-5">
        <div class="flex flex-wrap gap-4">
            <div class="inline-control-shell flex-1">
                <i class="ri-search-line text-gray-400"></i>
                <input type="search" value="" placeholder="Buscar por nombre, correo, cedula o telefono" readonly>
            </div>
            <input class="form-input max-w-[180px]" type="text" value="Todos los roles" readonly>
            <button class="btn btn-outline btn-sm demo-action-blocked"><i class="ri-equalizer-line"></i> Filtros avanzados</button>
        </div>
    </section>

    <section class="card p-0">
        <div class="table-shell table-responsive-cards">
            <table class="table">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Contacto</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Especialidad</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($usuarios as $user)
                        <tr>
                            <td data-label="Usuario">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gray-100 text-sm font-semibold text-gray-700">
                                        {{ strtoupper(substr($user['name'], 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">{{ $user['name'] }}</p>
                                        <p class="text-xs text-gray-500">Registro: {{ $user['registered_at'] }}</p>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Contacto">
                                <p class="text-sm text-gray-700">{{ $user['email'] }}</p>
                                <p class="text-xs text-gray-500">Ultimo acceso: {{ $user['last_login'] }}</p>
                            </td>
                            <td data-label="Rol"><span class="badge {{ $user['role_tone'] }}">{{ $user['role'] }}</span></td>
                            <td data-label="Estado"><span class="badge {{ $user['status_tone'] }}">{{ $user['status'] }}</span></td>
                            <td data-label="Especialidad">{{ $user['specialty'] }}</td>
                            <td data-label="Acciones">
                                <div class="table-actions">
                                    <button class="btn btn-outline btn-sm demo-action-blocked"><i class="ri-eye-line"></i> Ver</button>
                                    <button class="btn btn-ghost btn-sm demo-action-blocked"><i class="ri-edit-line"></i> Editar</button>
                                    <button class="btn btn-ghost btn-sm demo-action-blocked text-rose-600"><i class="ri-forbid-line"></i> Bloquear</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
