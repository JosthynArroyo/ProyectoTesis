@extends('layouts.superadmin')
@section('title','Usuarios')
@section('header-title','Usuarios del sistema')
@section('header-subtitle','Listado global por rol')

@section('main')
<div class="space-y-6">
  <form class="card p-5" method="GET" action="{{ route('superadmin.users.index') }}">
    <div class="flex flex-wrap gap-3">
      <div class="inline-control-shell flex-1">
        <i class="ri-search-line text-gray-400"></i>
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
            @php($status = $user->status ?? 'active')
            @php($statusLabel = ['active' => 'Activo', 'inactive' => 'Inactivo', 'blocked' => 'Bloqueado'][$status] ?? ucfirst($status))
            <tr>
              <td data-label="Usuario">{{ $user->name }}</td>
              <td data-label="Correo">{{ $user->email }}</td>
              <td data-label="Cédula">{{ $user->dni ?: 'N/D' }}</td>
              <td data-label="Rol">{{ ucfirst($roleName) }}</td>
              <td data-label="Estado">
                @if($status === 'active')
                  <span class="badge success">{{ $statusLabel }}</span>
                @elseif($status === 'inactive')
                  <span class="badge warning">{{ $statusLabel }}</span>
                @else
                  <span class="badge danger">{{ $statusLabel }}</span>
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
