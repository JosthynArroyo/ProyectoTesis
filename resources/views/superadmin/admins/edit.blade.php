@extends('layouts.superadmin')
@section('title','Editar administrador')
@section('header-title','Editar administrador')
@section('header-subtitle','Actualiza datos y credenciales')

@section('main')
<div class="space-y-6">
  <section class="card p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Administrador #{{ $admin->id }}</p>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $admin->name }}</h1>
        <p class="text-slate-600">Actualiza datos personales y acceso.</p>
      </div>
    </div>
  </section>

  @if (session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif
  @if ($errors->any())
    <x-ui.alert tone="error">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</x-ui.alert>
  @endif

  <form class="form space-y-6" method="POST" action="{{ route('superadmin.admins.update', $admin) }}" novalidate>
    @csrf
    @method('PUT')
    @include('superadmin.admins.form', ['admin' => $admin])

    <x-ui.form-actions>
      <x-slot:left>
        <a class="btn btn-ghost" href="{{ route('superadmin.admins.index') }}">
          <i class="ri-arrow-left-line"></i> Volver
        </a>
      </x-slot>
      <button class="btn btn-primary" type="submit">
        <i class="ri-save-line"></i> Guardar cambios
      </button>
    </x-ui.form-actions>
  </form>
</div>
@endsection