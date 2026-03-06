@extends('layouts.superadmin')
@section('title','Panel superadmin')
@section('header-title','Panel global')
@section('header-subtitle','Métricas y control centralizado')

@section('main')
<div class="space-y-6">
  <section class="card p-6">
    <div class="page-header">
      <div class="page-header__info">
        <p class="text-xs uppercase tracking-widest text-slate-500">Superadmin</p>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Resumen general</h1>
        <p class="text-slate-600">Visibilidad completa de usuarios, citas y permisos.</p>
      </div>
      <div class="page-header__actions">
        <a class="btn btn-outline btn-full-mobile" href="{{ route('superadmin.admins.index') }}">
          <i class="ri-shield-user-line"></i> Administradores
        </a>
        <a class="btn btn-primary btn-full-mobile" href="{{ route('superadmin.personalizacion.bienvenida.edit') }}">
          <i class="ri-palette-line"></i> Personalización
        </a>
      </div>
    </div>
  </section>

  @if($maintenanceEnabled)
    <x-ui.alert tone="warning">
      <div>
        <strong>Modo mantenimiento activo.</strong> Solo el superadmin tiene acceso al sistema.
      </div>
    </x-ui.alert>
  @endif

  <section class="stat-grid">
    <a class="block" href="{{ route('superadmin.users.index') }}">
      <x-ui.stat label="Usuarios totales" :value="$totalUsuarios" tone="slate">
        <x-slot:icon><i class="ri-group-line"></i></x-slot:icon>
      </x-ui.stat>
    </a>
    <a class="block" href="{{ route('superadmin.users.index', ['role' => 'paciente']) }}">
      <x-ui.stat label="Pacientes" :value="$totalPacientes" tone="teal">
        <x-slot:icon><i class="ri-heart-pulse-line"></i></x-slot:icon>
      </x-ui.stat>
    </a>
    <a class="block" href="{{ route('superadmin.users.index', ['role' => 'doctor']) }}">
      <x-ui.stat label="Doctores" :value="$totalDoctores" tone="sky">
        <x-slot:icon><i class="ri-stethoscope-line"></i></x-slot:icon>
      </x-ui.stat>
    </a>
    <a class="block" href="{{ route('superadmin.admins.index') }}">
      <x-ui.stat label="Administradores" :value="$totalAdmins" tone="amber">
        <x-slot:icon><i class="ri-shield-user-line"></i></x-slot:icon>
      </x-ui.stat>
    </a>
  </section>

  <section class="stat-grid">
    <a class="block" href="{{ route('superadmin.users.index', ['role' => 'laboratorio']) }}">
      <x-ui.stat label="Laboratorio" :value="$totalLabs" tone="rose">
        <x-slot:icon><i class="ri-test-tube-line"></i></x-slot:icon>
      </x-ui.stat>
    </a>
    <a class="block" href="{{ route('superadmin.dashboard') }}">
      <x-ui.stat label="Citas totales" :value="$totalCitas" tone="slate">
        <x-slot:icon><i class="ri-calendar-check-line"></i></x-slot:icon>
      </x-ui.stat>
    </a>
    <a class="block" href="{{ route('superadmin.dashboard') }}">
      <x-ui.stat label="Citas hoy" :value="$citasHoy" tone="teal">
        <x-slot:icon><i class="ri-calendar-todo-line"></i></x-slot:icon>
        <div class="mt-2 text-sm text-slate-500">Pendientes: {{ $pendientes }}</div>
      </x-ui.stat>
    </a>
  </section>

  <section class="card p-6">
    <div class="page-header">
      <div class="page-header__info">
        <p class="text-xs uppercase tracking-widest text-slate-500">Actividad</p>
        <h2>Citas y usuarios por dia</h2>
        <p>Ultimos 7 dias de actividad del sistema.</p>
      </div>
    </div>
    @php
      $maxCitas = max($activity->max('citas') ?: 1, 1);
    @endphp
    <div class="mt-4 grid gap-3 sm:grid-cols-7">
      @foreach($activity as $item)
        @php
          $height = max(10, (int) round(($item['citas'] / $maxCitas) * 100));
        @endphp
        <div class="rounded-2xl border border-slate-200 bg-white/90 p-3">
          <p class="text-[11px] font-semibold text-slate-500">{{ $item['label'] }}</p>
          <div class="mt-3 h-24 rounded-xl bg-slate-100/80 p-2">
            <div class="flex h-full items-end">
              <div class="w-full rounded-md bg-teal-500/85" style="height: {{ $height }}%"></div>
            </div>
          </div>
          <p class="mt-2 text-xs text-slate-600">Citas: <strong>{{ $item['citas'] }}</strong></p>
          <p class="text-xs text-slate-500">Usuarios: {{ $item['usuarios'] }}</p>
        </div>
      @endforeach
    </div>
  </section>

  <section class="card p-6">
    <div class="page-header">
      <div class="page-header__info">
        <p class="text-xs uppercase tracking-widest text-slate-500">Solicitudes</p>
        <h2>Personalización</h2>
        <p>Revisa permisos pendientes de administradores.</p>
      </div>
      <div class="page-header__actions">
        <div class="text-right">
          <p class="text-3xl font-semibold text-slate-900">{{ $pendientesPersonalizacion }}</p>
          <p class="text-xs text-slate-500">Pendientes</p>
        </div>
      </div>
    </div>
    <div class="mt-4 flex flex-wrap gap-2">
      <a class="btn btn-outline btn-full-mobile" href="{{ route('superadmin.solicitudes.personalizacion.index') }}">
        <i class="ri-notification-4-line"></i> Ver solicitudes
      </a>
    </div>
  </section>
</div>
@endsection
