@extends('layouts.admin')
@section('title','Editar usuario')
@section('header-title','Editar usuario')
@section('header-subtitle','Actualiza información y permisos')

@section('main')
@php($statusLabel = ['active' => 'Activo', 'inactive' => 'Inactivo', 'blocked' => 'Bloqueado'][$user->status ?? 'active'] ?? 'Activo')
<div class="space-y-6">
  <section class="card p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <x-ui.button :href="route('admin.usuarios.index')" variant="outline">
          <i class="ri-arrow-left-line"></i>
          Volver
        </x-ui.button>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Editar usuario #{{ $user->id }}</h1>
        <p class="text-slate-600">Modifica datos personales, contacto y rol.</p>
      </div>
      <div class="flex items-center gap-2">
        <span class="badge neutral">{{ optional($user->roles->first())->name ?? '-' }}</span>
      </div>
    </div>
  </section>

  @if (session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  <div class="grid gap-6 lg:grid-cols-[1.4fr_0.6fr]">
    <form class="card p-6" method="POST" action="{{ route('admin.usuarios.update',$user) }}">
      @csrf
      @method('PUT')

      <div class="md:hidden space-y-4">
        <div class="card p-4">
          <div class="flex items-center gap-3">
            <i class="ri-user-line text-slate-400"></i>
            <div>
              <p class="text-xs uppercase tracking-widest text-slate-500">Resumen</p>
              <h3 class="text-base font-semibold text-slate-900">{{ $user->name }}</h3>
            </div>
          </div>
          <ul class="mt-3 space-y-1 text-sm text-slate-600">
            <li>ID: <strong>#{{ $user->id }}</strong></li>
            <li>Creado: <strong>{{ optional($user->created_at)->format('Y-m-d H:i') ?? '-' }}</strong></li>
            <li>Último acceso: <strong>{{ optional($user->last_login_at)->format('Y-m-d H:i') ?? '-' }}</strong></li>
            <li>Estado: <strong>{{ $statusLabel }}</strong></li>
          </ul>
        </div>
        <div class="card p-4">
          <div class="flex items-center gap-3">
            <i class="ri-cake-2-line text-slate-400"></i>
            <div>
              <p class="text-xs uppercase tracking-widest text-slate-500">Edad</p>
              <h3 class="js-age-badge text-base font-semibold text-slate-900">--</h3>
            </div>
          </div>
          <p class="mt-2 text-xs text-slate-500">Calculada según la fecha de nacimiento.</p>
        </div>
      </div>

      @include('admin.users.form', ['user'=>$user, 'roles'=>$roles, 'especialidades'=>collect($especialidades ?? [])])

      <div class="mt-6">
        <x-ui.form-actions>
          <button class="btn btn-primary" type="submit">
            <i class="ri-save-line"></i>
            Guardar
          </button>
        </x-ui.form-actions>
      </div>
    </form>

    <aside class="hidden space-y-4 lg:block">
      <div class="card p-4">
        <div class="flex items-center gap-3">
          <i class="ri-user-line text-slate-400"></i>
          <div>
            <p class="text-xs uppercase tracking-widest text-slate-500">Resumen</p>
            <h3 class="text-base font-semibold text-slate-900">{{ $user->name }}</h3>
          </div>
        </div>
        <ul class="mt-3 space-y-1 text-sm text-slate-600">
          <li>ID: <strong>#{{ $user->id }}</strong></li>
          <li>Creado: <strong>{{ optional($user->created_at)->format('Y-m-d H:i') ?? '-' }}</strong></li>
          <li>Último acceso: <strong>{{ optional($user->last_login_at)->format('Y-m-d H:i') ?? '-' }}</strong></li>
          <li>Estado: <strong>{{ $statusLabel }}</strong></li>
        </ul>
      </div>

      <div class="card p-4">
        <div class="flex items-center gap-3">
          <i class="ri-cake-2-line text-slate-400"></i>
          <div>
            <p class="text-xs uppercase tracking-widest text-slate-500">Edad</p>
            <h3 class="js-age-badge text-base font-semibold text-slate-900">--</h3>
          </div>
        </div>
        <p class="mt-2 text-xs text-slate-500">Calculada según la fecha de nacimiento.</p>
      </div>
    </aside>
  </div>
</div>
@endsection

@push('scripts')
  @vite('resources/js/admin/users/edit.js')
@endpush
