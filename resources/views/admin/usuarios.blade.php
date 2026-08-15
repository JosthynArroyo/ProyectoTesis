@extends('layouts.admin')
@section('title','Usuarios | Administración')
@section('header-title','Usuarios')
@section('header-subtitle','Gestión y control de usuarios')

@section('main')
@php
  $collection = $users->getCollection();
  $resumenRoles = $collection->groupBy(fn($item) => optional($item->roles->first())->name ?? 'Sin rol')->map->count();
  $filtersOpen = request()->has('cols');
  $suspendTarget = old('until') ? $collection->firstWhere('id', (int) old('user_id')) : null;
@endphp

<div class="space-y-6">
  <div class="panel-action-bar">
    <a class="btn btn-outline btn-sm btn-full-mobile" href="{{ route('admin.usuarios.export.excel', request()->query()) }}">
      <i class="ri-file-excel-2-line"></i> Excel
    </a>
    <a class="btn btn-outline btn-sm btn-full-mobile" href="{{ route('admin.usuarios.export.pdf', request()->query()) }}">
      <i class="ri-file-pdf-line"></i> PDF
    </a>
    <a class="btn btn-primary btn-full-mobile" href="{{ route('admin.usuarios.create', array_filter(['preset_role'=>request('role')])) }}">
      <i class="ri-user-add-line"></i> Crear usuario
    </a>
  </div>

  <section class="stat-grid">
    <x-ui.stat label="Pacientes" :value="$resumenRoles['paciente'] ?? 0" tone="teal">
      <x-slot:icon><i class="ri-heart-pulse-line"></i></x-slot:icon>
    </x-ui.stat>
    <x-ui.stat label="Doctores" :value="$resumenRoles['doctor'] ?? 0" tone="sky">
      <x-slot:icon><i class="ri-stethoscope-line"></i></x-slot:icon>
    </x-ui.stat>
    <x-ui.stat label="Laboratorio" :value="$resumenRoles['laboratorio'] ?? 0" tone="amber">
      <x-slot:icon><i class="ri-test-tube-line"></i></x-slot:icon>
    </x-ui.stat>
  </section>

  <form class="card p-5" method="GET" action="{{ route('admin.usuarios.index') }}">
    <div class="flex flex-wrap gap-4">
      <div class="inline-control-shell flex-1">
        <i class="ri-search-line text-gray-400"></i>
        <input type="search" name="buscar" value="{{ old('buscar', $buscar) }}" placeholder="Buscar por nombre, correo, cédula o teléfono">
      </div>
      @error('buscar')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror

      @php $roleSel = request('role'); @endphp
      <select class="form-select" name="role" onchange="this.form.submit()">
        <option value="all" @selected($roleSel === '' || $roleSel === 'all')>Todos los roles</option>
        <option value="doctor" @selected($roleSel === 'doctor')>Doctores</option>
        <option value="paciente" @selected($roleSel === 'paciente')>Pacientes</option>
        <option value="laboratorio" @selected($roleSel === 'laboratorio')>Laboratorio</option>
      </select>
      @error('role')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror

      <div class="flex flex-wrap items-center gap-2">
        <button type="button" class="btn btn-outline btn-sm" id="btn-more-filters" aria-expanded="{{ $filtersOpen ? 'true' : 'false' }}">
          <i class="ri-equalizer-line"></i> Filtros avanzados
        </button>
        <select class="form-select" name="per_page" onchange="this.form.submit()">
          @foreach([12,24,48,96] as $pp)
            <option value="{{ $pp }}" @selected(($perPage ?? 12) == $pp)>{{ $pp }}/pag</option>
          @endforeach
        </select>
        @error('per_page')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
        <button class="btn btn-primary btn-sm" type="submit"><i class="ri-check-line"></i> Aplicar</button>
      </div>
    </div>

    <div id="filters-wrap" class="mt-4" @unless($filtersOpen) hidden @endunless>
      <div class="rounded-2xl border border-gray-200 bg-gray-50/70 p-4">
        <p class="text-xs uppercase tracking-widest text-gray-500">Columnas visibles</p>
        <div class="mt-3 flex flex-wrap gap-2">
          @foreach($allColumns as $column)
            @php $checked = in_array($column, $cols); @endphp
            <label class="filter-chip flex items-center gap-2 rounded-full border border-gray-200 px-3 py-2 text-xs font-semibold {{ $checked ? 'is-active bg-gray-100 text-gray-900 font-medium' : 'text-gray-500' }}" tabindex="0" aria-pressed="{{ $checked ? 'true' : 'false' }}">
              <i class="icon ri-checkbox-blank-circle-line"></i>
              <input type="checkbox" name="cols[]" value="{{ $column }}" {{ $checked ? 'checked' : '' }}>
              {{ ucfirst($column) }}
            </label>
          @endforeach
        </div>
      </div>
    </div>
  </form>

  @if (session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  <div class="card p-0 !overflow-visible">
    <div class="table-shell table-shell--overflow-visible users table-responsive-cards !overflow-visible">
      <table class="table users" role="region" aria-label="Listado de usuarios">
        <thead>
          <tr>
            @if(in_array('usuario',$cols))        <th>Usuario</th>@endif
            @if(in_array('contacto',$cols))       <th>Contacto</th>@endif
            @if(in_array('rol',$cols))            <th>Rol</th>@endif
            @if(in_array('estado',$cols))         <th>Estado</th>@endif
            @if(in_array('especialidades',$cols)) <th>Especialidad</th>@endif
            @if(in_array('acciones',$cols))       <th class="text-right">Acciones</th>@endif
          </tr>
        </thead>
        <tbody>
        @forelse($users as $u)
          @php
            $roleNombre = optional($u->roles->first())->name;
            $roleLabel = $roleNombre ? \Illuminate\Support\Str::headline($roleNombre) : 'Sin rol';
            $roleTone = match ($roleNombre) {
                'paciente' => 'success',
                'doctor' => 'info',
                'laboratorio' => 'warning',
                'administrador', 'superadmin' => 'danger',
                default => 'info',
            };
            $esAdmin = in_array($roleNombre, ['administrador', 'superadmin'], true);
            $isClinicalProfessional = in_array($roleNombre, ['doctor', 'laboratorio'], true);
            $espNombres = ($u->especialidades ?? collect())->pluck('nombre')->all();
            $dependientes = $roleNombre === 'paciente' ? ($u->dependientes ?? collect()) : collect();
            $dependientesCount = (int) ($u->dependientes_count ?? $dependientes->count());
            $dependientesPanelId = 'dependientes-panel-'.$u->id;

            $rawStatus = (string) ($u->status ?? 'active');
            $isSuspended = $u->suspended_until && now()->lt($u->suspended_until);

            if ($rawStatus === 'blocked') {
                $displayStatus = 'blocked';
            } elseif ($rawStatus === 'inactive') {
                $displayStatus = 'inactive';
            } elseif ($isSuspended) {
                $displayStatus = 'suspended';
            } else {
                $displayStatus = 'active';
            }
          @endphp

          <tr data-user-row>
            <td class="hidden" aria-hidden="true">
              @unless($esAdmin || $u->id === auth()->id())
                <form id="delete-{{ $u->id }}" action="{{ route('admin.usuarios.destroy', $u) }}" method="POST" data-action-lock-title="Eliminando usuario..." data-action-lock-description="Por favor, espera.">
                  @csrf
                  @method('DELETE')
                </form>
              @endunless
              @unless($esAdmin)
                <form id="block-{{ $u->id }}" action="{{ route('admin.usuarios.block', $u) }}" method="POST" data-action-lock-title="Bloqueando usuario..." data-action-lock-description="Por favor, espera.">
                  @csrf
                  @method('PATCH')
                  <input type="hidden" name="reason" value="Bloqueo manual">
                </form>
                <form id="activate-{{ $u->id }}" action="{{ route('admin.usuarios.activate', $u) }}" method="POST" data-action-lock-title="{{ $displayStatus === 'blocked' ? 'Desbloqueando usuario...' : 'Reactivando usuario...' }}" data-action-lock-description="Por favor, espera.">
                  @csrf
                  @method('PATCH')
                  <input type="hidden" name="reason" value="">
                </form>
                <form id="deactivate-{{ $u->id }}" action="{{ route('admin.usuarios.deactivate', $u) }}" method="POST" data-action-lock-title="Desactivando usuario..." data-action-lock-description="Por favor, espera.">
                  @csrf
                  @method('PATCH')
                  <input type="hidden" name="reason" value="Inactivación manual">
                </form>
                <form id="unsuspend-{{ $u->id }}" action="{{ route('admin.usuarios.activate', $u) }}" method="POST" data-action-lock-title="Levantando suspensión..." data-action-lock-description="Por favor, espera.">
                  @csrf
                  @method('PATCH')
                </form>
              @endunless
            </td>
            @if(in_array('usuario',$cols))
              <td data-label="Usuario">
                <div class="flex items-start gap-3">
                  @if($u->getRawOriginal('avatar'))
                    <img src="{{ $u->avatar_thumb_url }}" alt="{{ $u->name }}" class="h-10 w-10 flex-none rounded-2xl object-cover border border-gray-200" loading="lazy" decoding="async">
                  @else
                    <div class="flex h-10 w-10 flex-none items-center justify-center rounded-2xl bg-gray-100 text-sm font-semibold text-gray-600">{{ \Illuminate\Support\Str::substr($u->name,0,1) }}</div>
                  @endif
                  <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-gray-900">{{ $u->name }}</p>
                    <div class="mt-2 flex flex-wrap items-center gap-3">
                      <p class="text-xs text-gray-500">ID #{{ $u->id }}</p>
                      @if($roleNombre === 'paciente')
                        <button
                          type="button"
                          class="btn btn-ghost btn-sm justify-start"
                          data-dependent-toggle
                          data-dependent-target="{{ $dependientesPanelId }}"
                          aria-controls="{{ $dependientesPanelId }}"
                          aria-expanded="false"
                        >
                          <i class="ri-arrow-down-s-line" data-dependent-chevron></i>
                          <span data-dependent-label>Ver dependientes</span>
                          @if($dependientesCount > 0)
                            <span class="badge">{{ $dependientesCount }}</span>
                          @endif
                        </button>
                      @endif
                      <button
                        type="button"
                        class="btn btn-ghost btn-sm md:hidden"
                        data-row-toggle
                        data-closed-label="Ver detalles"
                        data-open-label="Ocultar detalles"
                        aria-expanded="false"
                      >
                        <i class="ri-arrow-down-s-line"></i> Ver detalles
                      </button>
                    </div>
                  </div>
                </div>
              </td>
            @endif

            @if(in_array('contacto',$cols))
              <td data-label="Contacto" class="user-detail-cell">
                <div class="space-y-1">
                  <p class="break-all text-sm text-gray-700">{{ $u->email }}</p>
                  @if(!empty($u->telefono))
                    <span class="text-xs text-gray-500">Tel: {{ $u->telefono }}</span>
                  @endif
                </div>
              </td>
            @endif

            @if(in_array('rol',$cols))
              <td data-label="Rol" class="user-detail-cell">
                <span class="badge {{ $roleTone }}">{{ $roleLabel }}</span>
              </td>
            @endif

            @if(in_array('estado',$cols))
              <td data-label="Estado">
                <div class="space-y-1">
                  @if($displayStatus === 'blocked')
                    <span class="badge danger">Bloqueado</span>
                  @elseif($displayStatus === 'inactive')
                    <span class="badge warning">Inactivo</span>
                  @elseif($displayStatus === 'suspended')
                    <span class="badge warning">Suspendido</span>
                  @else
                    <span class="badge success">Activo</span>
                  @endif
                  @unless($esAdmin)
                    <span class="text-xs text-gray-500">Último acceso: {{ optional($u->last_login_at)->diffForHumans() ?? 'N/D' }}</span>
                  @endunless
                </div>
              </td>
            @endif

            @if(in_array('especialidades',$cols))
              <td data-label="Especialidad" class="user-detail-cell">
                @if(count($espNombres))
                  <span class="text-xs text-gray-500">{{ implode(', ', $espNombres) }}</span>
                @else
                  <span class="text-xs text-gray-500">Sin especialidad</span>
                @endif
              </td>
            @endif

            @if(in_array('acciones',$cols))
              <td data-label="Acciones">
                <div class="table-actions">
                  <div class="relative">
                    <button type="button" class="btn btn-outline btn-sm" data-kebab="user-actions-{{ $u->id }}" data-kebab-placement="top" aria-label="Más acciones para {{ $u->name }}">
                      <i class="ri-more-2-fill"></i>
                    </button>
                    <div id="user-actions-{{ $u->id }}" class="kebab-menu" role="menu">
                      <a class="btn btn-ghost btn-sm justify-start" href="{{ route('admin.usuarios.show',$u) }}" role="menuitem">
                        <i class="ri-eye-line"></i> Ver detalle
                      </a>
                      <a class="btn btn-ghost btn-sm justify-start" href="{{ route('admin.usuarios.edit',$u) }}" role="menuitem">
                        <i class="ri-edit-line"></i> Editar completo
                      </a>
                      @unless($esAdmin)
                        @if($displayStatus === 'active')
                          <button
                            type="button"
                            class="btn btn-ghost btn-sm justify-start"
                            data-suspend-open
                            data-suspend-id="{{ $u->id }}"
                            data-suspend-name="{{ $u->name }}"
                            data-suspend-title="Suspender usuario"
                            data-suspend-action="{{ route('admin.usuarios.suspend', $u) }}"
                            role="menuitem"
                          >
                            <i class="ri-timer-line"></i> Suspender
                          </button>
                          <button
                            type="button"
                            class="btn btn-ghost btn-sm justify-start"
                            data-confirm-form="block-{{ $u->id }}"
                            data-confirm-title="Bloquear usuario"
                            data-confirm-message="Se bloqueará el acceso de {{ $u->name }}."
                            data-confirm-button="Bloquear"
                            data-action-lock-title="Bloqueando usuario..."
                            role="menuitem"
                          >
                            <i class="ri-forbid-line"></i> Bloquear
                          </button>
                          <button
                            type="button"
                            class="btn btn-ghost btn-sm justify-start"
                            data-confirm-form="deactivate-{{ $u->id }}"
                            data-confirm-title="Desactivar usuario"
                            data-confirm-message="La cuenta de {{ $u->name }} quedará inactiva."
                            data-confirm-button="Desactivar"
                            data-action-lock-title="Desactivando usuario..."
                            role="menuitem"
                          >
                            <i class="ri-user-unfollow-line"></i> Desactivar
                          </button>
                        @elseif($displayStatus === 'blocked')
                          <button
                            type="button"
                            class="btn btn-ghost btn-sm justify-start"
                            data-confirm-form="activate-{{ $u->id }}"
                            data-confirm-title="Desbloquear usuario"
                            data-confirm-message="Se restaurará el acceso de {{ $u->name }}."
                            data-confirm-button="Desbloquear"
                            data-action-lock-title="Desbloqueando usuario..."
                            role="menuitem"
                          >
                            <i class="ri-lock-unlock-line"></i> Desbloquear
                          </button>
                        @elseif($displayStatus === 'inactive')
                          <button
                            type="button"
                            class="btn btn-ghost btn-sm justify-start"
                            data-confirm-form="activate-{{ $u->id }}"
                            data-confirm-title="Reactivar usuario"
                            data-confirm-message="Se reactivará el acceso de {{ $u->name }}."
                            data-confirm-button="Reactivar"
                            data-action-lock-title="Reactivando usuario..."
                            role="menuitem"
                          >
                            <i class="ri-user-follow-line"></i> Reactivar
                          </button>
                        @elseif($displayStatus === 'suspended')
                          <button
                            type="button"
                            class="btn btn-ghost btn-sm justify-start"
                            data-confirm-form="unsuspend-{{ $u->id }}"
                            data-confirm-title="Levantar suspensión"
                            data-confirm-message="Se finalizará la suspensión temporal de {{ $u->name }}."
                            data-confirm-button="Levantar suspensión"
                            data-action-lock-title="Levantando suspensión..."
                            role="menuitem"
                          >
                            <i class="ri-time-line"></i> Levantar suspensión
                          </button>
                        @endif
                        @unless($u->id === auth()->id())
                          <button
                            type="button"
                            class="btn btn-ghost btn-sm justify-start text-rose-600"
                            data-confirm-form="delete-{{ $u->id }}"
                            data-confirm-title="Eliminar usuario"
                            data-confirm-message="Se eliminará permanentemente la cuenta de {{ $u->name }}."
                            data-confirm-button="Eliminar"
                            data-action-lock-title="Eliminando usuario..."
                            role="menuitem"
                          >
                            <i class="ri-delete-bin-line"></i> Eliminar
                          </button>
                        @endunless
                      @endunless
                    </div>
                  </div>
                </div>
              </td>
            @endif
          </tr>
          @if($roleNombre === 'paciente')
            <tr id="{{ $dependientesPanelId }}" data-dependent-panel hidden>
              <td colspan="{{ max(count($cols), 1) }}" class="!px-0 !pt-0">
                <div class="rounded-2xl border border-gray-200 bg-gray-50/80 p-4 sm:p-5">
                  <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                      <p class="text-xs uppercase tracking-widest text-gray-500">Pacientes dependientes</p>
                      <h4 class="mt-1 text-base font-semibold text-gray-900">{{ $u->name }}</h4>
                    </div>
                    <span class="text-xs text-gray-500">Cuenta titular #{{ $u->id }}</span>
                  </div>

                  @if($dependientes->isEmpty())
                    <div class="mt-4">
                      <x-ui.empty-state title="No tiene pacientes dependientes asociados" message="Los registros de esta cuenta no incluyen dependientes activos en este momento." />
                    </div>
                  @else
                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                      @foreach($dependientes as $dep)
                        @php
                          $fechaNacimiento = optional($dep->fecha_nacimiento)->format('d/m/Y') ?: 'Sin registro';
                          $sexo = $dep->sexo ?: 'Sin registro';
                          $dni = $dep->dni ?: 'Sin registro';
                          $parentesco = $dep->parentesco ? \Illuminate\Support\Str::headline($dep->parentesco) : 'Sin registro';
                          $estadoDependiente = $dep->activo ? 'Activo' : 'Inactivo';
                        @endphp
                        <article class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                          <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                              <p class="text-base font-semibold text-gray-900">{{ $dep->nombre ?: 'Sin nombre' }}</p>
                              <p class="mt-1 text-sm text-gray-500">Parentesco: {{ $parentesco }}</p>
                            </div>
                            <span class="badge {{ $dep->activo ? 'success' : 'warning' }}">{{ $estadoDependiente }}</span>
                          </div>

                          <dl class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            <div>
                              <dt class="text-xs uppercase tracking-widest text-gray-500">Documento</dt>
                              <dd class="mt-1 text-sm font-medium text-gray-800">{{ $dni }}</dd>
                            </div>
                            <div>
                              <dt class="text-xs uppercase tracking-widest text-gray-500">Fecha de nacimiento</dt>
                              <dd class="mt-1 text-sm font-medium text-gray-800">{{ $fechaNacimiento }}</dd>
                            </div>
                            <div>
                              <dt class="text-xs uppercase tracking-widest text-gray-500">Sexo</dt>
                              <dd class="mt-1 text-sm font-medium text-gray-800">{{ $sexo }}</dd>
                            </div>
                            <div>
                              <dt class="text-xs uppercase tracking-widest text-gray-500">Estado</dt>
                              <dd class="mt-1 text-sm font-medium text-gray-800">{{ $estadoDependiente }}</dd>
                            </div>
                          </dl>
                        </article>
                      @endforeach
                    </div>
                  @endif
                </div>
              </td>
            </tr>
          @endif
        @empty
          <tr>
            <td colspan="{{ max(count($cols), 1) }}">
              <x-ui.empty-state title="No hay usuarios para mostrar." message="Ajusta la búsqueda, cambia el rol o crea un nuevo usuario desde esta misma pantalla." />
            </td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-4 text-sm text-gray-500">
      <div>
        @if ($users->hasPages())
          Página {{ $users->currentPage() }} de {{ $users->lastPage() }}
        @else
          Mostrando {{ $users->count() }} registros
        @endif
      </div>
      {!! $users->withQueryString()->links() !!}
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
          <h3 class="mt-2 text-lg font-semibold text-gray-900" data-suspend-title>Suspender usuario</h3>
          <p class="text-sm text-gray-500">Cuenta: <span data-suspend-name>{{ $suspendTarget?->name ?? 'Usuario' }}</span></p>
        </div>
        <button type="button" class="btn btn-ghost px-2" data-sheet-close aria-label="Cerrar">
          <i class="ri-close-line"></i>
        </button>
      </div>

      <form
        method="POST"
        class="mt-4 space-y-4"
        data-suspend-sheet-form
        action="{{ $suspendTarget ? route('admin.usuarios.suspend', $suspendTarget) : '' }}"
      >
        @csrf
        @method('PATCH')
        <input type="hidden" name="user_id" value="{{ old('user_id') }}">

        <div>
          <label class="form-label" for="sheet-user-until">Suspender hasta</label>
          <input id="sheet-user-until" class="form-input" type="datetime-local" name="until" value="{{ old('until') }}" required>
        </div>

        <div>
          <label class="form-label" for="sheet-user-reason">Motivo</label>
          <input id="sheet-user-reason" class="form-input" type="text" name="reason" value="{{ old('reason') }}" placeholder="Motivo de suspensión">
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

