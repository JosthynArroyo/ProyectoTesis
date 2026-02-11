@extends('layouts.superadmin')
@section('title','Administradores')
@section('header-title','Administradores')
@section('header-subtitle','Gestión de cuentas de administrador')

@section('main')
<div class="space-y-6">
  <section class="card p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Superadmin</p>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Cuentas de administrador</h1>
        <p class="text-slate-600">Solo el superadmin puede crear y administrar cuentas de administrador.</p>
      </div>
      <a class="btn btn-primary" href="{{ route('superadmin.admins.create') }}">
        <i class="ri-user-add-line"></i> Crear administrador
      </a>
    </div>
  </section>

  <form class="card p-5" method="GET" action="{{ route('superadmin.admins.index') }}">
    <div class="flex flex-wrap gap-4">
      <div class="flex flex-1 items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
        <i class="ri-search-line text-slate-400"></i>
        <input type="search" name="buscar" value="{{ $buscar ?? '' }}" placeholder="Buscar por nombre, correo o cédula" class="w-full bg-transparent text-sm text-slate-700" required>
      </div>
      @error('buscar')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
      <select class="form-select" name="per_page" onchange="this.form.submit()" required>
        @foreach([12,24,48] as $pp)
          <option value="{{ $pp }}" @selected(($perPage ?? 12) == $pp)>{{ $pp }}/pag</option>
        @endforeach
      </select>
      @error('per_page')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
      <button class="btn btn-primary" type="submit"><i class="ri-check-line"></i> Aplicar</button>
    </div>
  </form>

  @if ($errors->any())
    <x-ui.alert tone="error">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</x-ui.alert>
  @endif
  @if (session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  <div class="card p-0">
    <div class="table-shell users">
      <table class="table users" role="region" aria-label="Listado de administradores">
        <thead>
          <tr>
            <th>Administrador</th>
            <th>Contacto</th>
            <th>Estado</th>
            <th class="text-right">Acciones</th>
          </tr>
        </thead>
        <tbody>
        @foreach($admins as $admin)
          @php
            $estado = $admin->status ?? 'active';
            $isSusp = $admin->suspended_until && now()->lt($admin->suspended_until);
            $rowError = (string)old('admin_id') === (string)$admin->id;
          @endphp
          <form id="delete-{{ $admin->id }}" action="{{ route('superadmin.admins.destroy', $admin) }}" method="POST">@csrf @method('DELETE')</form>
          <form id="block-{{ $admin->id }}" action="{{ route('superadmin.admins.block', $admin) }}" method="POST">@csrf @method('PATCH')</form>
          <form id="suspend-{{ $admin->id }}" action="{{ route('superadmin.admins.suspend', $admin) }}" method="POST">
            @csrf @method('PATCH')
            <input type="hidden" name="admin_id" value="{{ $admin->id }}">
          </form>
          <form id="activate-{{ $admin->id }}" action="{{ route('superadmin.admins.activate', $admin) }}" method="POST">@csrf @method('PATCH')</form>
          <form id="deactivate-{{ $admin->id }}" action="{{ route('superadmin.admins.deactivate', $admin) }}" method="POST">@csrf @method('PATCH')</form>

          <tr>
            <td>
              <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-100 text-sm font-semibold text-slate-600">{{ \Illuminate\Support\Str::substr($admin->name,0,1) }}</div>
                <div>
                  <p class="font-semibold text-slate-900">{{ $admin->name }}</p>
                  <p class="text-xs text-slate-500">ID #{{ $admin->id }}</p>
                </div>
              </div>
            </td>
            <td>
              <div class="space-y-1">
                <div class="text-sm text-slate-700">{{ $admin->email }}</div>
                @if(!empty($admin->telefono))
                  <span class="text-xs text-slate-500">Tel: {{ $admin->telefono }}</span>
                @endif
              </div>
            </td>
            <td>
              <div class="space-y-1">
                @if($estado==='blocked')
                  <span class="badge danger">Bloqueado</span>
                @elseif($estado==='inactive')
                  <span class="badge danger">Inactivo</span>
                @elseif($isSusp)
                  <span class="badge warning">Suspendido</span>
                @else
                  <span class="badge success">Activo</span>
                @endif
                <span class="text-xs text-slate-500">Último acceso: {{ $admin->last_login_at?->diffForHumans() ?? 'N/D' }}</span>
              </div>
            </td>
            <td>
              <div class="table-actions">
                <a class="btn btn-outline" href="{{ route('superadmin.admins.edit', $admin) }}" title="Editar">
                  <i class="ri-edit-line"></i>
                </a>
                <button form="delete-{{ $admin->id }}" type="submit" class="btn btn-outline" onclick="return confirm('Eliminar administrador {{ $admin->name }}');" title="Eliminar">
                  <i class="ri-delete-bin-line"></i>
                </button>
                @if($estado !== 'blocked')
                  <button form="block-{{ $admin->id }}" type="submit" class="btn btn-outline" onclick="return confirm('Bloquear a {{ $admin->name }}');" title="Bloquear">
                    <i class="ri-forbid-line"></i>
                  </button>
                @endif
                @if($estado !== 'inactive')
                  <button form="deactivate-{{ $admin->id }}" type="submit" class="btn btn-outline" onclick="return confirm('Marcar inactivo a {{ $admin->name }}');" title="Inactivar">
                    <i class="ri-user-unfollow-line"></i>
                  </button>
                @endif
                <button type="button" class="btn btn-outline" onclick="openSuspend('{{ $admin->id }}')" title="Suspender">
                  <i class="ri-timer-line"></i>
                </button>
                @if($estado!=='active' || $isSusp)
                  <button form="activate-{{ $admin->id }}" type="submit" class="btn btn-outline" onclick="return confirm('Reactivar acceso de {{ $admin->name }}');" title="Reactivar">
                    <i class="ri-user-follow-line"></i>
                  </button>
                @endif
              </div>
              <input type="hidden" form="block-{{ $admin->id }}" name="reason" value="Bloqueo manual">
              <input type="hidden" form="deactivate-{{ $admin->id }}" name="reason" value="Inactivación manual">
              <input type="hidden" form="activate-{{ $admin->id }}" name="reason" value="">
            </td>
          </tr>

          <tr id="susp-row-{{ $admin->id }}" style="display:none;">
            <td colspan="4" class="bg-slate-50/60">
              <div class="m-4 rounded-2xl border border-dashed border-slate-200 bg-white p-4">
                  <div class="action-group">
                    <div class="font-semibold text-slate-700">Suspender hasta:</div>
                  <input class="form-input w-full sm:w-auto sm:max-w-[240px]" form="suspend-{{ $admin->id }}" type="datetime-local" name="until" required>
                  @if($rowError)
                    @error('until')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
                  @endif
                  <input class="form-input w-full sm:flex-1 sm:min-w-[220px]" form="suspend-{{ $admin->id }}" type="text" name="reason" placeholder="Motivo de suspensión" required>
                  @if($rowError)
                    @error('reason')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
                  @endif
                  <button class="btn btn-primary" form="suspend-{{ $admin->id }}" type="submit">
                    <i class="ri-time-line"></i> Confirmar
                  </button>
                  <button class="btn btn-outline" type="button" onclick="closeSuspend('{{ $admin->id }}')">Cancelar</button>
                </div>
              </div>
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-4 text-sm text-slate-500">
      <div>
        @if ($admins->hasPages())
          Página {{ $admins->currentPage() }} de {{ $admins->lastPage() }}
        @else
          Mostrando {{ $admins->count() }} registros
        @endif
      </div>
      {!! $admins->withQueryString()->links() !!}
    </div>
  </div>
</div>

@push('scripts')
  @vite('resources/js/admin/usuarios.js')
@endpush
@endsection
