@extends('layouts.demo')
@section('title', 'Usuarios | Demo')
@section('header-title', 'Usuarios')
@section('header-subtitle', 'Gestion de usuarios del sistema')

@section('sidebar')
    @include('demo.partials.sidebar-superadmin-demo')
@endsection

@section('main')
<div class="space-y-6">
    <div class="panel-action-bar flex flex-wrap items-center justify-between gap-3">
        <div class="inline-control-shell max-w-sm flex-1">
            <i class="ri-search-line text-gray-400"></i>
            <input type="text" value="" placeholder="Buscar por nombre, correo o documento..." readonly>
        </div>
        <div class="flex flex-wrap gap-2">
            <button class="btn btn-outline demo-action-blocked"><i class="ri-filter-3-line"></i> Filtros</button>
            <button class="btn btn-primary demo-action-blocked"><i class="ri-user-add-line"></i> Nuevo usuario</button>
        </div>
    </div>

    <section class="card p-0">
        <div class="table-shell table-responsive-cards">
            <table class="table">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Registro</th>
                        <th class="text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($usuarios as $usuario)
                        <tr>
                            <td data-label="Usuario">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-100 text-sm font-semibold text-blue-700">
                                        {{ strtoupper(substr($usuario['name'], 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">{{ $usuario['name'] }}</p>
                                        <p class="text-xs text-gray-500">{{ $usuario['email'] }}</p>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Rol"><span class="badge {{ $usuario['role_tone'] }}">{{ $usuario['role'] }}</span></td>
                            <td data-label="Estado"><span class="badge {{ $usuario['status_tone'] }}">{{ $usuario['status'] }}</span></td>
                            <td data-label="Registro">{{ $usuario['created_at'] }}</td>
                            <td data-label="Acciones">
                                <div class="table-actions">
                                    <button class="btn btn-outline btn-sm demo-action-blocked"><i class="ri-eye-line"></i> Ver</button>
                                    <button class="btn btn-ghost btn-sm demo-action-blocked"><i class="ri-edit-line"></i> Editar</button>
                                    <button class="btn btn-ghost btn-sm demo-action-blocked text-rose-600"><i class="ri-forbid-line"></i> Suspender</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 text-sm text-gray-500">
            <span>Mostrando 1 a {{ count($usuarios) }} de {{ $kpis['total_usuarios'] }} resultados simulados</span>
            <div class="flex gap-2">
                <button class="btn btn-ghost btn-sm demo-action-blocked">Anterior</button>
                <button class="btn btn-primary btn-sm demo-action-blocked">1</button>
                <button class="btn btn-outline btn-sm demo-action-blocked">2</button>
            </div>
        </div>
    </section>
</div>
@endsection
