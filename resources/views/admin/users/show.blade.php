@extends('layouts.admin')
@section('title','Usuario')
@section('header-title','Detalle de usuario #'.$user->id)
@section('header-subtitle','Información completa del perfil')

@section('main')
@php
  $statusLabel = ['active' => 'Activo', 'inactive' => 'Inactivo', 'blocked' => 'Bloqueado'][$user->status ?? 'active'] ?? 'Activo';
  $statusTone = ['active' => 'success', 'inactive' => 'warning', 'blocked' => 'danger'][$user->status ?? 'active'] ?? 'success';
@endphp
<div class="space-y-6">
  <div class="panel-action-bar">
    <span class="badge neutral">{{ optional($user->roles->first())->name ?? '-' }}</span>
  </div>

  <section class="card p-6">
    @php
      $avatarFolder = 'users';
      $avatarEntity = 'user';
      $imageUrlService = $imageUrl ?? app(\App\Support\ImageUrl::class);
      if ($user->hasRole('doctor') || $user->hasRole('laboratorio')) {
        $avatarFolder = 'doctors';
        $avatarEntity = 'doctor';
      } elseif ($user->hasRole('paciente')) {
        $avatarFolder = 'patients';
        $avatarEntity = 'patient';
      }
      $avatarImage = $imageUrlService->variants($user->avatar, $avatarFolder, $avatarEntity);
    @endphp
    <div class="flex flex-wrap items-center gap-4">
      <div class="h-20 w-20 overflow-hidden rounded-lg border border-gray-200 bg-gray-50">
        <img
          src="{{ $avatarImage['thumb'] }}"
          @if($avatarImage['srcset']) srcset="{{ $avatarImage['srcset'] }}" sizes="80px" @endif
          alt="Avatar"
          class="h-full w-full {{ $avatarEntity === 'doctor' ? 'doctor-avatar-photo' : 'object-cover' }}"
          loading="lazy"
          decoding="async"
        >
      </div>
      <div>
        <div class="flex flex-wrap items-center gap-2">
          <h3 class="text-lg font-semibold text-gray-900">{{ $user->name }}</h3>
          <span class="badge {{ $statusTone }}">{{ $statusLabel }}</span>
        </div>
        @if($user->suspended_until)
          <div class="text-sm text-gray-500">Suspendido hasta {{ $user->suspended_until->format('Y-m-d H:i') }}</div>
        @endif
      </div>
    </div>

    <dl class="mt-6 grid gap-4 md:grid-cols-2">
      <div>
        <dt class="text-xs uppercase tracking-widest text-gray-400">Correo electrónico</dt>
        <dd class="flex items-center gap-2 text-sm text-gray-700">
          <span id="emailText">{{ $user->email }}</span>
          <button class="btn btn-ghost px-2" data-copy="#emailText"><i class="ri-file-copy-line"></i></button>
        </dd>
      </div>

      <div>
        <dt class="text-xs uppercase tracking-widest text-gray-400">Teléfono</dt>
        <dd class="flex items-center gap-2 text-sm text-gray-700">
          <span id="telText">{{ $user->telefono ?? '-' }}</span>
          @if($user->telefono)
            <a class="btn btn-ghost px-2" href="tel:{{ $user->telefono }}"><i class="ri-phone-line"></i></a>
            <button class="btn btn-ghost px-2" data-copy="#telText"><i class="ri-file-copy-line"></i></button>
          @endif
        </dd>
      </div>

      <div>
        <dt class="text-xs uppercase tracking-widest text-gray-400">Cédula</dt>
        <dd class="text-sm text-gray-700">{{ $user->dni ?? '-' }}</dd>
      </div>
      <div>
        <dt class="text-xs uppercase tracking-widest text-gray-400">Dirección</dt>
        <dd class="text-sm text-gray-700">{{ $user->direccion ?? '-' }}</dd>
      </div>

      <div>
        <dt class="text-xs uppercase tracking-widest text-gray-400">Fecha de nacimiento</dt>
        <dd class="text-sm text-gray-700">{{ optional($user->fecha_nacimiento)->format('Y-m-d') ?? '-' }}</dd>
      </div>
      <div>
        <dt class="text-xs uppercase tracking-widest text-gray-400">Sexo</dt>
        <dd class="text-sm text-gray-700">{{ $user->sexo ?? '-' }}</dd>
      </div>

      <div class="md:col-span-2">
        <dt class="text-xs uppercase tracking-widest text-gray-400">Especialidades</dt>
        <dd class="text-sm text-gray-700">{{ ($user->especialidades->pluck('nombre')->implode(', ')) ?: '—' }}</dd>
      </div>

      <div>
        <dt class="text-xs uppercase tracking-widest text-gray-400">Último acceso</dt>
        <dd class="text-sm text-gray-700">{{ optional($user->last_login_at)->format('Y-m-d H:i') ?? '-' }}</dd>
      </div>
      <div>
        <dt class="text-xs uppercase tracking-widest text-gray-400">Creado</dt>
        <dd class="text-sm text-gray-700">{{ $user->created_at->format('Y-m-d H:i') }}</dd>
      </div>
    </dl>

    <div class="mt-6">
      <x-ui.form-actions>
        <x-slot:left>
          <a class="btn btn-ghost" href="{{ route('admin.usuarios.index') }}">
            <i class="ri-arrow-left-line"></i> Volver
          </a>
        </x-slot>
      </x-ui.form-actions>
    </div>
  </section>
</div>
@endsection

@push('scripts')
  @vite('resources/js/admin/users/show.js')
@endpush
