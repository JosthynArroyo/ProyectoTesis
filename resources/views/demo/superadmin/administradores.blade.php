@extends('layouts.demo')
@section('title', 'Administradores | Demo')
@section('header-title', 'Administradores')
@section('header-subtitle', 'Gestion de personal administrativo')

@section('sidebar')
    @include('demo.partials.sidebar-superadmin-demo')
@endsection

@section('main')
<div class="space-y-6">
    <div class="panel-action-bar flex flex-wrap items-center justify-between gap-3">
        <div class="inline-control-shell max-w-sm flex-1">
            <i class="ri-search-line text-gray-400"></i>
            <input type="text" value="" placeholder="Buscar administrador..." readonly>
        </div>
        <button class="btn btn-primary demo-action-blocked">
            <i class="ri-user-add-line"></i> Nuevo admin
        </button>
    </div>

    <section class="card p-0">
        <div class="table-shell table-responsive-cards">
            <table class="table">
                <thead>
                    <tr>
                        <th>Administrador</th>
                        <th>Estado</th>
                        <th>Registro</th>
                        <th class="text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($administradores as $admin)
                        <tr>
                            <td data-label="Administrador">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-amber-100 text-sm font-semibold text-amber-700">
                                        {{ strtoupper(substr($admin['name'], 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">{{ $admin['name'] }}</p>
                                        <p class="text-xs text-gray-500">{{ $admin['email'] }}</p>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Estado"><span class="badge success">{{ $admin['status'] }}</span></td>
                            <td data-label="Registro">{{ $admin['created_at'] }}</td>
                            <td data-label="Acciones">
                                <div class="table-actions">
                                    <button class="btn btn-outline btn-sm demo-action-blocked"><i class="ri-edit-line"></i> Editar</button>
                                    <button class="btn btn-ghost btn-sm demo-action-blocked text-rose-600"><i class="ri-forbid-line"></i> Suspender</button>
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
