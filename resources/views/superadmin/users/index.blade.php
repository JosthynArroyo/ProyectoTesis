@extends('layouts.superadmin')
@section('title','Usuarios')
@section('header-title','Usuarios del sistema')
@section('header-subtitle','Listado global por rol')

@section('main')
<div class="space-y-6">
  <section class="card p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Superadmin</p>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Usuarios</h1>
        <p class="text-slate-600">Filtra por rol y busca por nombre, correo o cédula.</p>
      </div>
    </div>
  </section>

  <form class="card p-5" method="GET" action="{{ route('superadmin.users.index') }}">
    <div class="flex flex-wrap gap-3">
      <div class="inline-control-shell flex-1">
        <i class="ri-search-line text-slate-400"></i>
        <input type="search" name="buscar" value="{{ $search }}" placeholder="Buscar usuario">
      </div>
      <select class="form-select" name="role">
        <option value="all" @selected($role === 'all')>Todos</option>
        <option value="paciente" @selected($role === 'paciente')>Pacientes</option>
        <option value="doctor" @selected($role === 'doctor')>Doctores</option>
        <option value="laboratorio" @selected($role === 'laboratorio')>Laboratorio</option>
        <option value="administrador" @selected($role === 'administrador')>Administradores</option>
      </select>
      <button class="btn btn-primary" type="submit">Aplicar</button>
    </div>
  </form>

  <div class="card p-0">
    <div class="table-shell table-responsive-cards">
      <table class="table">
        <thead>
          <tr>
            <th>Usuario</th>
            <th>Correo</th>
            <th>Cédula</th>
            <th>Rol</th>
            <th>Estado</th>
          </tr>
        </thead>
        <tbody>
          @forelse($users as $user)
            @php($roleName = optional($user->roles->first())->name ?? 'sin_rol')
            <tr>
              <td data-label="Usuario">{{ $user->name }}</td>
              <td data-label="Correo">{{ $user->email }}</td>
              <td data-label="Cédula">{{ $user->dni ?: 'N/D' }}</td>
              <td data-label="Rol">{{ ucfirst($roleName) }}</td>
              <td data-label="Estado">
                @if(($user->status ?? 'active') === 'active')
                  <span class="badge success">Activo</span>
                @else
                  <span class="badge danger">{{ ucfirst($user->status) }}</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="5">No se encontraron usuarios.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="px-6 py-4">
      {{ $users->links() }}
    </div>
  </div>
</div>
@endsection
