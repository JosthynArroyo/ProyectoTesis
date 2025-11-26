@extends('layouts.admin')
@section('title','Usuarios | Admin')

@push('head')
  @vite('resources/css/admin/usuarios.css')
@endpush

@section('main')
@php
  $collection   = $users->getCollection();
  $resumenRoles = $collection->groupBy(fn($i)=>optional($i->roles->first())->name ?? 'Sin rol')->map->count();
@endphp

<div class="users-page">
  <section class="intro">
    <div>
      <h2>Gestión de usuarios</h2>
      <p>Administra datos, roles y estados. Exporta filtros actuales a Excel o PDF.</p>
    </div>
    <div class="intro-badges">
      <span class="badge"><span class="material-symbols-outlined">group</span>Total: {{ $users->total() }}</span>
      <span class="badge" style="background:rgba(29,78,216,.12);border-color:rgba(29,78,216,.3);color:var(--usuarios-primary);">
        <span class="material-symbols-outlined">verified</span>Datos verificados
      </span>
    </div>
  </section>

  <section class="stats">
    @foreach($resumenRoles as $rolNombre => $cantidad)
      <article class="stat-card">
        <h3>{{ $cantidad }}</h3>
        <span><span class="material-symbols-outlined" style="color:var(--usuarios-primary);font-size:1.1rem;">shield_person</span> {{ ucfirst($rolNombre) }}</span>
      </article>
    @endforeach
  </section>

  <div class="users-card">
    <div class="users-card__header">
      <div class="toolbar">
        <form method="GET" action="{{ route('admin.usuarios.index') }}"
              style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:center;width:100%;">
          {{-- Buscador --}}
          <div class="search-group">
            <input class="input" type="search" name="buscar" value="{{ $buscar ?? '' }}"
                   placeholder="Buscar por nombre, correo, cédula, teléfono" />
            <button class="search-btn" type="submit" aria-label="Buscar">
              <span class="material-symbols-outlined">search</span>
            </button>
          </div>

          {{-- Filtro por rol --}}
          @php $roleSel = request('role'); @endphp
          <select class="input" name="role" onchange="this.form.submit()">
            <option value="">Todos los roles</option>
            <option value="doctor"        {{ $roleSel==='doctor'?'selected':'' }}>Doctores</option>
            <option value="paciente"      {{ $roleSel==='paciente'?'selected':'' }}>Pacientes</option>
            <option value="administrador" {{ $roleSel==='administrador'?'selected':'' }}>Administradores</option>
          </select>

          {{-- Tamaño de página --}}
          <select class="input" name="per_page" onchange="this.form.submit()">
            @foreach([12,24,48,96] as $pp)
              <option value="{{ $pp }}" @selected(($perPage ?? 12)==$pp)>{{ $pp }}/pág</option>
            @endforeach
          </select>

          {{-- Selector de columnas --}}
          @foreach($allColumns as $c)
            @php $checked = in_array($c,$cols); @endphp
            <label class="filter-chip {{ $checked?'is-active':'' }}" tabindex="0" aria-pressed="{{ $checked?'true':'false' }}">
              <span class="material-symbols-outlined icon">{{ $checked ? 'check_circle' : 'radio_button_unchecked' }}</span>
              <input type="checkbox" name="cols[]" value="{{ $c }}" {{ $checked?'checked':'' }} onchange="this.form.submit()">
              {{ ucfirst($c) }}
            </label>
          @endforeach
        </form>
      </div>

      <div class="toolbar">
        {{-- Export mantienen filtros actuales (buscar, role, per_page, cols...) --}}
        <a class="btn" href="{{ route('admin.usuarios.export.excel', request()->query()) }}">
          <span class="material-symbols-outlined">grid_on</span> Exportar Usuarios en Excel
        </a>
        <a class="btn" href="{{ route('admin.usuarios.export.pdf', request()->query()) }}">
          <span class="material-symbols-outlined">picture_as_pdf</span> Exportar Usuarios en PDF
        </a>
        <a class="btn" href="{{ route('admin.usuarios.create', array_filter(['preset_role'=>request('role')])) }}">
          <span class="material-symbols-outlined">person_add</span> Crear Nuevo Usuario
        </a>
      </div>
    </div>

    @if ($errors->any())
      <div class="alert alert-error">
        @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
      </div>
    @endif
    @if (session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="users-table" role="region" aria-label="Listado de usuarios" tabindex="0">
    <table class="users">
      <thead>
        <tr>
          @if(in_array('usuario',$cols))        <th>Usuario</th>@endif
          @if(in_array('contacto',$cols))       <th>Contacto</th>@endif
          @if(in_array('rol',$cols))            <th>Rol</th>@endif
          @if(in_array('estado',$cols))         <th>Estado</th>@endif
          @if(in_array('especialidades',$cols)) <th>Especialidades</th>@endif
          @if(in_array('acciones',$cols))       <th>Acciones</th>@endif
        </tr>
      </thead>
      <tbody>
      @foreach($users as $u)
        @php
          $roleIdActual = optional($u->roles->first())->id;
          $esAdmin      = $u->roles->contains(fn($rr)=>$rr->name==='administrador');
          $espNombres   = ($u->especialidades ?? collect())->pluck('nombre')->all();
        @endphp

        <form id="update-{{ $u->id }}" action="{{ route('admin.usuarios.update', $u) }}" method="POST">@csrf @method('PUT')</form>
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
            <div class="user-name">
              <input class="input" form="update-{{ $u->id }}" type="text" name="name" value="{{ old('name_'.$u->id, $u->name) }}" required>
            </div>
            <div class="user-id">
              ID: {{ $u->id }} ·
              <a href="{{ route('admin.usuarios.show',$u) }}">ver</a> ·
              <a href="{{ route('admin.usuarios.edit',$u) }}">editar</a>
            </div>
          </td>
          @endif

          @if(in_array('contacto',$cols))
          <td data-label="Contacto">
            <input class="input" form="update-{{ $u->id }}" type="email" name="email" value="{{ old('email_'.$u->id, $u->email) }}" required>
          </td>
          @endif

          @if(in_array('rol',$cols))
          <td data-label="Rol">
            @if($esAdmin)
              <span class="chip" title="No editable para administradores">Administrador</span>
              <input type="hidden" form="update-{{ $u->id }}" name="role_id" value="{{ $roleIdActual }}">
            @else
              <select class="input" form="update-{{ $u->id }}" name="role_id" required>
                @foreach($roles->whereNotIn('name',['administrador']) as $r)
                  <option value="{{ $r->id }}" @selected($roleIdActual===$r->id)>{{ ucfirst($r->name) }}</option>
                @endforeach
              </select>
            @endif
          </td>
          @endif

          @if(in_array('estado',$cols))
          <td data-label="Estado">
            @php
              $estado = $u->status ?? 'active';
              $isSusp = $u->suspended_until && now()->lt($u->suspended_until);
            @endphp
            <div class="chips">
              @if($estado==='blocked')
                <span class="chip bad">Bloqueado</span>
              @elseif($estado==='inactive')
                <span class="chip warn">Inactivo</span>
              @elseif($isSusp)
                <span class="chip warn">Suspendido</span>
              @else
                <span class="chip ok">Activo</span>
              @endif
            </div>
            @unless($esAdmin)
              <div class="user-id">Último acceso: {{ $u->last_login_at?->diffForHumans() ?? '—' }}</div>
            @endunless
          </td>
          @endif

          @if(in_array('especialidades',$cols))
          <td data-label="Especialidades">
            @if(count($espNombres))
              <div class="chips">
                @foreach($espNombres as $n)
                  <span class="chip">{{ $n }}</span>
                @endforeach
              </div>
            @else
              <div class="user-id">—</div>
            @endif
          </td>
          @endif

          @if(in_array('acciones',$cols))
          <td data-label="Acciones">
            <div class="actions">
              <button form="update-{{ $u->id }}" type="submit" class="btn">
                <span class="material-symbols-outlined">save</span> Guardar
              </button>
              @unless($esAdmin)
                <button form="delete-{{ $u->id }}" type="submit" class="btn btn-outline"
                        onclick="return confirm('¿Eliminar usuario {{ $u->name }}?');">
                  <span class="material-symbols-outlined">delete</span>
                </button>
                @if(($u->status ?? 'active') !== 'blocked')
                  <button form="block-{{ $u->id }}" type="submit" class="btn btn-danger"
                          onclick="return confirm('Bloquear a {{ $u->name }} inmediatamente?');">
                    <span class="material-symbols-outlined">block</span>
                  </button>
                @endif
                @if(($u->status ?? 'active') !== 'inactive')
                  <button form="deactivate-{{ $u->id }}" type="submit" class="btn btn-outline"
                          onclick="return confirm('Marcar inactivo a {{ $u->name }}?');">
                    <span class="material-symbols-outlined">person_off</span>
                  </button>
                @endif
                <button type="button" class="btn btn-warn" onclick="openSuspend('{{ $u->id }}')">
                  <span class="material-symbols-outlined">hourglass</span>
                </button>
                @if(($u->status ?? 'active')!=='active' || ($u->suspended_until && now()->lt($u->suspended_until)))
                  <button form="activate-{{ $u->id }}" type="submit" class="btn btn-ok"
                          onclick="return confirm('Reactivar acceso de {{ $u->name }}?');">
                    <span class="material-symbols-outlined">verified</span>
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
          <td colspan="{{ count($cols) }}" style="padding-top:0;">
            <div style="margin:0 16px 16px;border:1px dashed var(--usuarios-border);border-radius:16px;padding:16px;background:#fff;">
              <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
                <div style="font-weight:700;">Suspender hasta:</div>
                <input class="input" form="suspend-{{ $u->id }}" type="datetime-local" name="until" required>
                <input class="input" form="suspend-{{ $u->id }}" type="text" name="reason" placeholder="Motivo (opcional)" style="flex:1;min-width:220px;">
                <button class="btn btn-warn" form="suspend-{{ $u->id }}" type="submit">
                  <span class="material-symbols-outlined">schedule</span> Confirmar
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

    <div class="users-card__footer">
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
