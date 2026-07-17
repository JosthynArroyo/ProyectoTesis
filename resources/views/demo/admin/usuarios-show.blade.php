@extends('layouts.demo')
@section('title','Detalle de usuario - Demo')
@section('header-title','Detalle de usuario #'.$u->id)
@section('header-subtitle','Información completa del perfil (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-admin-demo')
@endsection

@php
  $statusLabel = ['active' => 'Activo', 'inactive' => 'Inactivo', 'blocked' => 'Bloqueado'][$u->status ?? 'active'] ?? 'Activo';
  $statusTone = ['active' => 'success', 'inactive' => 'warning', 'blocked' => 'danger'][$u->status ?? 'active'] ?? 'success';
@endphp

@section('main')
<div class="space-y-6">
  <div class="panel-action-bar">
    <span class="badge neutral">{{ optional($u->roles->first())->name ?? '-' }}</span>
  </div>

  <section class="card p-6">
    <div class="flex flex-wrap items-center gap-4">
      <div class="flex h-20 w-20 items-center justify-center rounded-2xl bg-gray-100 text-2xl font-bold text-gray-600">
        {{ \Illuminate\Support\Str::substr($u->name,0,1) }}
      </div>
      <div>
        <div class="flex flex-wrap items-center gap-2">
          <h3 class="text-lg font-semibold text-gray-900">{{ $u->name }}</h3>
          <span class="badge {{ $statusTone }}">{{ $statusLabel }}</span>
        </div>
        @if($u->suspended_until)
          <div class="text-sm text-gray-500">Suspendido hasta {{ $u->suspended_until->format('Y-m-d H:i') }}</div>
        @endif
      </div>
    </div>

    <dl class="mt-6 grid gap-4 md:grid-cols-2">
      <div>
        <dt class="text-xs uppercase tracking-widest text-gray-400">Correo electrónico</dt>
        <dd class="flex items-center gap-2 text-sm text-gray-700">
          <span id="emailText">{{ $u->email }}</span>
        </dd>
      </div>

      <div>
        <dt class="text-xs uppercase tracking-widest text-gray-400">Teléfono</dt>
        <dd class="flex items-center gap-2 text-sm text-gray-700">
          <span id="telText">{{ $u->telefono ?? '-' }}</span>
        </dd>
      </div>

      <div>
        <dt class="text-xs uppercase tracking-widest text-gray-400">Cédula</dt>
        <dd class="text-sm text-gray-700">{{ $u->dni ?? '-' }}</dd>
      </div>
      <div>
        <dt class="text-xs uppercase tracking-widest text-gray-400">Dirección</dt>
        <dd class="text-sm text-gray-700">{{ $u->direccion ?? 'Quito, Ecuador' }}</dd>
      </div>

      <div>
        <dt class="text-xs uppercase tracking-widest text-gray-400">Fecha de nacimiento</dt>
        <dd class="text-sm text-gray-700">2018-05-10</dd>
      </div>
      <div>
        <dt class="text-xs uppercase tracking-widest text-gray-400">Sexo</dt>
        <dd class="text-sm text-gray-700">Femenino</dd>
      </div>

      <div class="md:col-span-2">
        <dt class="text-xs uppercase tracking-widest text-gray-400">Especialidades</dt>
        <dd class="text-sm text-gray-700">{{ ($u->especialidades->pluck('nombre')->implode(', ')) ?: '—' }}</dd>
      </div>

      <div>
        <dt class="text-xs uppercase tracking-widest text-gray-400">Último acceso</dt>
        <dd class="text-sm text-gray-700">{{ optional($u->last_login_at)->format('Y-m-d H:i') ?? 'N/D' }}</dd>
      </div>
      <div>
        <dt class="text-xs uppercase tracking-widest text-gray-400">Creado</dt>
        <dd class="text-sm text-gray-700">2026-04-01 08:00</dd>
      </div>
    </dl>

    <div class="mt-6 flex justify-between">
      <a class="btn btn-ghost" href="{{ route('demo.admin.usuarios.index') }}">
        <i class="ri-arrow-left-line"></i> Volver
      </a>
    </div>
  </section>
</div>
@endsection
