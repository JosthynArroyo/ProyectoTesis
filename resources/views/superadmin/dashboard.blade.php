@extends('layouts.superadmin')
@section('title','Panel superadmin')
@section('header-title','Panel global')
@section('header-subtitle','Métricas y control centralizado')

@section('main')
<div class="space-y-6">
  <section class="card p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Superadmin</p>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Resumen general</h1>
        <p class="text-slate-600">Visibilidad completa de usuarios, citas y permisos.</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <a class="btn btn-outline" href="{{ route('superadmin.admins.index') }}">
          <i class="ri-shield-user-line"></i> Administradores
        </a>
        <a class="btn btn-primary" href="{{ route('superadmin.personalizacion.bienvenida.edit') }}">
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

  <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
    <x-ui.stat label="Usuarios totales" :value="$totalUsuarios" tone="slate">
      <x-slot:icon><i class="ri-group-line"></i></x-slot:icon>
    </x-ui.stat>
    <x-ui.stat label="Pacientes" :value="$totalPacientes" tone="teal">
      <x-slot:icon><i class="ri-heart-pulse-line"></i></x-slot:icon>
    </x-ui.stat>
    <x-ui.stat label="Doctores" :value="$totalDoctores" tone="sky">
      <x-slot:icon><i class="ri-stethoscope-line"></i></x-slot:icon>
    </x-ui.stat>
    <x-ui.stat label="Administradores" :value="$totalAdmins" tone="amber">
      <x-slot:icon><i class="ri-shield-user-line"></i></x-slot:icon>
    </x-ui.stat>
  </section>

  <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    <x-ui.stat label="Laboratorio" :value="$totalLabs" tone="rose">
      <x-slot:icon><i class="ri-test-tube-line"></i></x-slot:icon>
    </x-ui.stat>
    <x-ui.stat label="Citas totales" :value="$totalCitas" tone="slate">
      <x-slot:icon><i class="ri-calendar-check-line"></i></x-slot:icon>
    </x-ui.stat>
    <x-ui.stat label="Citas hoy" :value="$citasHoy" tone="teal">
      <x-slot:icon><i class="ri-calendar-todo-line"></i></x-slot:icon>
      <div class="mt-2 text-sm text-slate-500">Pendientes: {{ $pendientes }}</div>
    </x-ui.stat>
  </section>

  <section class="card p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Solicitudes</p>
        <h2 class="mt-2 text-xl font-semibold text-slate-900">Personalización</h2>
        <p class="text-slate-600">Revisa permisos pendientes de administradores.</p>
      </div>
      <div class="text-right">
        <p class="text-3xl font-semibold text-slate-900">{{ $pendientesPersonalizacion }}</p>
        <p class="text-xs text-slate-500">Pendientes</p>
      </div>
    </div>
    <div class="mt-4 flex flex-wrap gap-2">
      <a class="btn btn-outline" href="{{ route('superadmin.solicitudes.personalizacion.index') }}">
        <i class="ri-notification-4-line"></i> Ver solicitudes
      </a>
    </div>
  </section>
</div>
@endsection
