@extends('layouts.superadmin')
@section('title','Administradores')
@section('header-title','Administradores')
@section('header-subtitle','Gestión de cuentas de administrador')

@php
  $suspendTarget = old('until')
    ? $admins->getCollection()->firstWhere('id', (int) old('admin_id'))
    : null;
@endphp

@section('main')
<div class="space-y-6">
  <p class="sr-only">Cuentas de administrador</p>
  <div class="panel-action-bar">
    <a class="btn btn-primary" href="{{ route('superadmin.admins.create') }}">
      <i class="ri-user-add-line"></i> Crear administrador
    </a>
  </div>

  <form class="card p-5" method="GET" action="{{ route('superadmin.admins.index') }}">
    <div class="flex flex-wrap gap-4">
      <div class="inline-control-shell flex-1">
        <i class="ri-search-line text-gray-400"></i>
        <input type="search" name="buscar" value="{{ $buscar ?? '' }}" placeholder="Buscar por nombre, correo o cédula">
      </div>
      @error('buscar')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
      <select class="form-select" name="per_page" onchange="this.form.submit()">
        @foreach([12,24,48] as $pp)
          <option value="{{ $pp }}" @selected(($perPage ?? 12) == $pp)>{{ $pp }}/pag</option>
        @endforeach
      </select>
      @error('per_page')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
      <button class="btn btn-primary" type="submit"><i class="ri-check-line"></i> Aplicar</button>
    </div>
  </form>

  @if (session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  <div class="card p-0">
    <div class="table-shell table-shell--overflow-visible users table-responsive-cards">
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
        @forelse($admins as $admin)
          @php
            $estado = $admin->status ?? 'active';
            $isSusp = $admin->suspended_until && now()->lt($admin->suspended_until);
          @endphp
          <form id="delete-{{ $admin->id }}" action="{{ route('superadmin.admins.destroy', $admin) }}" method="POST">@csrf @method('DELETE')</form>
          <form id="block-{{ $admin->id }}" action="{{ route('superadmin.admins.block', $admin) }}" method="POST">
            @csrf
            @method('PATCH')
            <input type="hidden" name="reason" value="Bloqueo manual">
          </form>
          <form id="activate-{{ $admin->id }}" action="{{ route('superadmin.admins.activate', $admin) }}" method="POST">
            @csrf
            @method('PATCH')
            <input type="hidden" name="reason" value="">
          </form>
          <form id="deactivate-{{ $admin->id }}" action="{{ route('superadmin.admins.deactivate', $admin) }}" method="POST">
            @csrf
            @method('PATCH')
            <input type="hidden" name="reason" value="Inactivación manual">
          </form>

          <tr data-user-row>
            <td data-label="Administrador">
              <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gray-100 text-sm font-semibold text-gray-600">{{ \Illuminate\Support\Str::substr($admin->name,0,1) }}</div>
                <div>
                  <p class="font-semibold text-gray-900">{{ $admin->name }}</p>
                  <p class="text-xs text-gray-500">ID #{{ $admin->id }}</p>
                </div>
              </div>
            </td>
            <td data-label="Contacto">
              <div class="space-y-1">
                <div class="text-sm text-gray-700">{{ $admin->email }}</div>
                @if(!empty($admin->telefono))
                  <span class="text-xs text-gray-500">Tel: {{ $admin->telefono }}</span>
                @endif
              </div>
            </td>
            <td data-label="Estado">
              <div class="space-y-1">
                @if($estado === 'blocked')
                  <span class="badge danger">Bloqueado</span>
                @elseif($estado === 'inactive')
                  <span class="badge danger">Inactivo</span>
                @elseif($isSusp)
                  <span class="badge warning">Suspendido</span>
                @else
                  <span class="badge success">Activo</span>
                @endif
                <span class="text-xs text-gray-500">Último acceso: {{ $admin->last_login_at?->diffForHumans() ?? 'N/D' }}</span>
              </div>
            </td>
            <td data-label="Acciones">
              <div class="table-actions">
                <a class="btn btn-outline btn-sm" href="{{ route('superadmin.admins.edit', $admin) }}">
                  <i class="ri-edit-line"></i> Editar
                </a>
                <div class="relative">
                  <button type="button" class="btn btn-outline btn-sm" data-kebab="admin-actions-{{ $admin->id }}" data-kebab-placement="top" aria-label="Más acciones para {{ $admin->name }}">
                    <i class="ri-more-2-fill"></i>
                  </button>
                  <div id="admin-actions-{{ $admin->id }}" class="kebab-menu" role="menu">
                    <button
                      type="button"
                      class="btn btn-ghost btn-sm justify-start"
                      data-suspend-open
                      data-suspend-id="{{ $admin->id }}"
                      data-suspend-name="{{ $admin->name }}"
                      data-suspend-title="Suspender administrador"
                      data-suspend-action="{{ route('superadmin.admins.suspend', $admin) }}"
                    >
                      <i class="ri-timer-line"></i> Suspender
                    </button>
                    @if($estado !== 'blocked')
                      <button
                        type="button"
                        class="btn btn-ghost btn-sm justify-start"
                        data-confirm-form="block-{{ $admin->id }}"
                        data-confirm-title="Bloquear administrador"
                        data-confirm-message="Se bloqueara el acceso de {{ $admin->name }}."
                        data-confirm-button="Bloquear"
                      >
                        <i class="ri-forbid-line"></i> Bloquear
                      </button>
                    @endif
                    @if($estado !== 'inactive')
                      <button
                        type="button"
                        class="btn btn-ghost btn-sm justify-start"
                        data-confirm-form="deactivate-{{ $admin->id }}"
                        data-confirm-title="Inactivar administrador"
                        data-confirm-message="La cuenta de {{ $admin->name }} quedara inactiva."
                        data-confirm-button="Inactivar"
                      >
                        <i class="ri-user-unfollow-line"></i> Inactivar
                      </button>
                    @endif
                    @if($estado !== 'active' || $isSusp)
                      <button
                        type="button"
                        class="btn btn-ghost btn-sm justify-start"
                        data-confirm-form="activate-{{ $admin->id }}"
                        data-confirm-title="Reactivar acceso"
                        data-confirm-message="Se restaurara el acceso de {{ $admin->name }}."
                        data-confirm-button="Reactivar"
                      >
                        <i class="ri-user-follow-line"></i> Reactivar
                      </button>
                    @endif
                    <button
                      type="button"
                      class="btn btn-ghost btn-sm justify-start text-rose-600"
                      data-confirm-form="delete-{{ $admin->id }}"
                      data-confirm-title="Eliminar administrador"
                      data-confirm-message="Se eliminara la cuenta de {{ $admin->name }}."
                      data-confirm-button="Eliminar"
                    >
                      <i class="ri-delete-bin-line"></i> Eliminar
                    </button>
                  </div>
                </div>
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="4">
              <x-ui.empty-state title="No hay administradores para mostrar." message="Ajusta la búsqueda o crea una nueva cuenta de administrador desde esta misma pantalla." />
            </td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-4 text-sm text-gray-500">
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

<div class="modal modal--sheet" data-confirm-sheet aria-hidden="true">
  <div class="modal-backdrop" data-sheet-close></div>
  <div class="modal-dialog modal-dialog--sheet" role="document" tabindex="-1">
    <div class="card modal-sheet p-6">
      <div class="flex items-center justify-between gap-3 border-b border-gray-100 pb-4">
        <div>
          <p class="text-xs uppercase tracking-widest text-gray-500">Confirmación</p>
          <h3 class="mt-2 text-lg font-semibold text-gray-900" data-confirm-title>Confirmar acción</h3>
        </div>
        <button type="button" class="btn btn-ghost px-2" data-sheet-close aria-label="Cerrar">
          <i class="ri-close-line"></i>
        </button>
      </div>
      <p class="mt-4 text-sm text-gray-600" data-confirm-message>Confirma para continuar.</p>
      <div class="mt-6 flex flex-wrap justify-end gap-3">
        <button type="button" class="btn btn-outline" data-sheet-close>Cancelar</button>
        <button type="button" class="btn btn-primary" data-confirm-submit>Confirmar</button>
      </div>
    </div>
  </div>
</div>

<div class="modal modal--sheet" data-suspend-sheet aria-hidden="true" @if($suspendTarget) data-open-on-load="1" @endif>
  <div class="modal-backdrop" data-sheet-close></div>
  <div class="modal-dialog modal-dialog--sheet" role="document" tabindex="-1">
    <div class="card modal-sheet p-6">
      <div class="flex items-center justify-between gap-3 border-b border-gray-100 pb-4">
        <div>
          <p class="text-xs uppercase tracking-widest text-gray-500">Suspensión</p>
          <h3 class="mt-2 text-lg font-semibold text-gray-900" data-suspend-title>Suspender administrador</h3>
          <p class="text-sm text-gray-500">Cuenta: <span data-suspend-name>{{ $suspendTarget?->name ?? 'Administrador' }}</span></p>
        </div>
        <button type="button" class="btn btn-ghost px-2" data-sheet-close aria-label="Cerrar">
          <i class="ri-close-line"></i>
        </button>
      </div>

      <form
        method="POST"
        class="mt-4 space-y-4"
        data-suspend-sheet-form
        action="{{ $suspendTarget ? route('superadmin.admins.suspend', $suspendTarget) : '' }}"
      >
        @csrf
        @method('PATCH')
        <input type="hidden" name="admin_id" value="{{ old('admin_id') }}">

        <div>
          <label class="form-label" for="sheet-admin-until">Suspender hasta</label>
          <input id="sheet-admin-until" class="form-input" type="datetime-local" name="until" value="{{ old('until') }}" required>
          @error('until')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <div>
          <label class="form-label" for="sheet-admin-reason">Motivo</label>
          <input id="sheet-admin-reason" class="form-input" type="text" name="reason" value="{{ old('reason') }}" placeholder="Motivo de suspensión" required>
          @error('reason')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <div class="flex flex-wrap justify-end gap-3">
          <button type="button" class="btn btn-outline" data-sheet-close>Cancelar</button>
          <button class="btn btn-primary" type="submit">Guardar suspensión</button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('scripts')
  @vite('resources/js/admin/usuarios.js')
@endpush
@endsection

