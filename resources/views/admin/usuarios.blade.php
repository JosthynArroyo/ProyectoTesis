@extends('layouts.admin')
@section('title','Usuarios | Administración')
@section('header-title','Usuarios')
@section('header-subtitle','Gestión y control de usuarios')

@section('main')
@php
  $collection   = $users->getCollection();
  $resumenRoles = $collection->groupBy(fn($i) => optional($i->roles->first())->name ?? 'Sin rol')->map->count();
  $filtersOpen  = request()->has('cols');
@endphp

<div class="space-y-6">
  <div class="card p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Usuarios</p>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Gestión de usuarios</h1>
        <p class="text-slate-600">Administra datos, roles y estados. Exporta filtros actuales a Excel o PDF.</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <a class="btn btn-outline" href="{{ route('admin.usuarios.export.excel', request()->query()) }}">
          <i class="ri-file-excel-2-line"></i> Excel
        </a>
        <a class="btn btn-outline" href="{{ route('admin.usuarios.export.pdf', request()->query()) }}">
          <i class="ri-file-pdf-line"></i> PDF
        </a>
        <a class="btn btn-primary" href="{{ route('admin.usuarios.create', array_filter(['preset_role'=>request('role')])) }}">
          <i class="ri-user-add-line"></i> Crear usuario
        </a>
      </div>
    </div>
  </div>

  <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
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
      <div class="flex flex-1 items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
        <i class="ri-search-line text-slate-400"></i>
        <input type="search" name="buscar" value="{{ old('buscar', $buscar) }}" placeholder="Buscar por nombre, correo, cédula, teléfono" class="w-full bg-transparent text-sm text-slate-700" required>
      </div>
      @error('buscar')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror

      @php $roleSel = request('role'); @endphp
      <select class="form-select" name="role" onchange="this.form.submit()" required>
        <option value="all" @selected($roleSel==='' || $roleSel==='all')>Todos los roles</option>
        <option value="doctor" @selected($roleSel==='doctor')>Doctores</option>
        <option value="paciente" @selected($roleSel==='paciente')>Pacientes</option>
        <option value="laboratorio" @selected($roleSel==='laboratorio')>Laboratorio</option>
      </select>
      @error('role')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror

      <div class="flex flex-wrap items-center gap-2">
        <button type="button" class="btn btn-outline" id="btn-more-filters" aria-expanded="{{ $filtersOpen ? 'true' : 'false' }}">
          <i class="ri-equalizer-line"></i> Más filtros
        </button>
        <select class="form-select" name="per_page" onchange="this.form.submit()" required>
          @foreach([12,24,48,96] as $pp)
            <option value="{{ $pp }}" @selected(($perPage ?? 12) == $pp)>{{ $pp }}/pag</option>
          @endforeach
        </select>
        @error('per_page')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
        <button class="btn btn-primary" type="submit"><i class="ri-check-line"></i> Aplicar</button>
      </div>
    </div>

    <div id="filters-wrap" class="mt-4" @unless($filtersOpen) hidden @endunless>
      <div class="flex flex-wrap gap-2">
        @foreach($allColumns as $c)
          @php $checked = in_array($c,$cols); @endphp
          <label class="filter-chip flex items-center gap-2 rounded-full border border-slate-200 px-3 py-2 text-xs font-semibold {{ $checked ? 'is-active bg-teal-50 text-teal-700' : 'text-slate-500' }}" tabindex="0" aria-pressed="{{ $checked ? 'true' : 'false' }}">
            <i class="icon ri-checkbox-blank-circle-line"></i>
            <input type="checkbox" name="cols[]" value="{{ $c }}" {{ $checked ? 'checked' : '' }}>
            {{ ucfirst($c) }}
          </label>
        @endforeach
      </div>
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
        @foreach($users as $u)
          @php
            $roleIdActual = optional($u->roles->first())->id;
            $esAdmin      = $u->roles->contains(fn($rr)=>in_array($rr->name, ['administrador','superadmin'], true));
            $espNombres   = ($u->especialidades ?? collect())->pluck('nombre')->all();
            $estado       = $u->status ?? 'active';
            $isSusp       = $u->suspended_until && now()->lt($u->suspended_until);
            $rowError     = (string)old('user_id') === (string)$u->id;
          @endphp

          <form id="update-{{ $u->id }}" action="{{ route('admin.usuarios.update', $u) }}" method="POST">
            @csrf @method('PUT')
            <input type="hidden" name="user_id" value="{{ $u->id }}">
          </form>
          @unless($esAdmin)
            <form id="delete-{{ $u->id }}" action="{{ route('admin.usuarios.destroy', $u) }}" method="POST">@csrf @method('DELETE')</form>
            <form id="block-{{ $u->id }}" action="{{ route('admin.usuarios.block', $u) }}" method="POST">@csrf @method('PATCH')</form>
            <form id="suspend-{{ $u->id }}" action="{{ route('admin.usuarios.suspend', $u) }}" method="POST">@csrf @method('PATCH')</form>
            <form id="activate-{{ $u->id }}" action="{{ route('admin.usuarios.activate', $u) }}" method="POST">@csrf @method('PATCH')</form>
            <form id="deactivate-{{ $u->id }}" action="{{ route('admin.usuarios.deactivate', $u) }}" method="POST">@csrf @method('PATCH')</form>
          @endunless

          <tr>
            @if(in_array('usuario',$cols))
            <td data-label="Usuario">
              <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-100 text-sm font-semibold text-slate-600">{{ \Illuminate\Support\Str::substr($u->name,0,1) }}</div>
                <div>
                  <input class="form-input" form="update-{{ $u->id }}" type="text" name="name" value="{{ $rowError ? old('name', $u->name) : $u->name }}" required>
                  @if($rowError)
                    @error('name')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
                  @endif
                  <p class="user-name text-xs text-slate-500">ID #{{ $u->id }} | <a href="{{ route('admin.usuarios.show',$u) }}" class="text-teal-600">ver</a> | <a href="{{ route('admin.usuarios.edit',$u) }}" class="text-teal-600">editar</a></p>
                </div>
              </div>
            </td>
            @endif

            @if(in_array('contacto',$cols))
            <td data-label="Contacto">
              <div class="space-y-1">
                <input class="form-input" form="update-{{ $u->id }}" type="email" name="email" value="{{ $rowError ? old('email', $u->email) : $u->email }}" required>
                @if($rowError)
                  @error('email')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
                @endif
                @if(!empty($u->telefono))
                  <span class="text-xs text-slate-500">Tel: {{ $u->telefono }}</span>
                @endif
              </div>
            </td>
            @endif

            @if(in_array('rol',$cols))
            <td data-label="Rol">
              @if($esAdmin)
                <span class="badge info" title="No editable para administradores">Administrador</span>
                <input type="hidden" form="update-{{ $u->id }}" name="role_id" value="{{ $roleIdActual }}">
              @else
                <select class="form-select" form="update-{{ $u->id }}" name="role_id" required>
                  @foreach($roles as $r)
                    <option value="{{ $r->id }}" @selected($roleIdActual===$r->id)>{{ ucfirst($r->name) }}</option>
                  @endforeach
                </select>
                @if($rowError)
                  @error('role_id')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
                @endif
              @endif
            </td>
            @endif

            @if(in_array('estado',$cols))
            <td data-label="Estado">
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
                @unless($esAdmin)
                  <span class="text-xs text-slate-500">Último acceso: {{ optional($u->last_login_at)->diffForHumans() ?? 'N/D' }}</span>
                @endunless
              </div>
            </td>
            @endif

            @if(in_array('especialidades',$cols))
            <td data-label="Especialidad">
              @if(count($espNombres))
                <span class="text-xs text-slate-500">{{ implode(', ', $espNombres) }}</span>
              @else
                <span class="text-xs text-slate-500">Sin especialidad</span>
              @endif
            </td>
            @endif

            @if(in_array('acciones',$cols))
            <td data-label="Acciones">
              <div class="table-actions">
                <button form="update-{{ $u->id }}" type="submit" class="btn btn-outline" title="Guardar cambios">
                  <i class="ri-save-line"></i>
                </button>
                @unless($esAdmin)
                  <button form="delete-{{ $u->id }}" type="submit" class="btn btn-outline"
                          onclick="return confirm('Eliminar usuario {{ $u->name }}');" title="Eliminar">
                    <i class="ri-delete-bin-line"></i>
                  </button>
                  @if($estado !== 'blocked')
                    <button form="block-{{ $u->id }}" type="submit" class="btn btn-outline"
                            onclick="return confirm('Bloquear a {{ $u->name }}');" title="Bloquear">
                      <i class="ri-forbid-line"></i>
                    </button>
                  @endif
                  @if($estado !== 'inactive')
                    <button form="deactivate-{{ $u->id }}" type="submit" class="btn btn-outline"
                            onclick="return confirm('Marcar inactivo a {{ $u->name }}');" title="Inactivar">
                      <i class="ri-user-unfollow-line"></i>
                    </button>
                  @endif
                  <button type="button" class="btn btn-outline" onclick="openSuspend('{{ $u->id }}')" title="Suspender">
                    <i class="ri-timer-line"></i>
                  </button>
                  @if($estado!=='active' || $isSusp)
                    <button form="activate-{{ $u->id }}" type="submit" class="btn btn-outline"
                            onclick="return confirm('Reactivar acceso de {{ $u->name }}');" title="Reactivar">
                      <i class="ri-user-follow-line"></i>
                    </button>
                  @endif
                @endunless
              </div>
              @unless($esAdmin)
                <input type="hidden" form="block-{{ $u->id }}" name="reason" value="Bloqueo manual">
            <input type="hidden" form="deactivate-{{ $u->id }}" name="reason" value="Inactivación manual">
                <input type="hidden" form="activate-{{ $u->id }}" name="reason" value="">
              @endunless
            </td>
            @endif
          </tr>

          <tr id="susp-row-{{ $u->id }}" style="display:none;">
            <td colspan="{{ count($cols) }}" class="bg-slate-50/60">
              <div class="m-4 rounded-2xl border border-dashed border-slate-200 bg-white p-4">
                <div class="action-group">
                  <div class="font-semibold text-slate-700">Suspender hasta:</div>
                  <input class="form-input w-full sm:w-auto sm:max-w-[240px]" form="suspend-{{ $u->id }}" type="datetime-local" name="until" required>
                  <input class="form-input w-full sm:flex-1 sm:min-w-[220px]" form="suspend-{{ $u->id }}" type="text" name="reason" placeholder="Motivo (opcional)">
                  <button class="btn btn-primary" form="suspend-{{ $u->id }}" type="submit">
                    <i class="ri-time-line"></i> Confirmar
                  </button>
                  <button class="btn btn-outline" type="button" onclick="closeSuspend('{{ $u->id }}')">Cancelar</button>
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

@push('scripts')
  @vite('resources/js/admin/usuarios.js')
@endpush
@endsection
