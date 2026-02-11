@extends('layouts.superadmin')
@section('title','Crear administrador')
@section('header-title','Nuevo administrador')
@section('header-subtitle','Alta de cuentas de administrador')

@section('main')
<div class="space-y-6">
  <section class="card p-6">
    <div>
      <p class="text-xs uppercase tracking-widest text-slate-500">Administradores</p>
      <h1 class="mt-2 text-2xl font-semibold text-slate-900">Crear administrador</h1>
      <p class="text-slate-600">Solo el superadmin puede crear cuentas de administrador.</p>
    </div>
  </section>

  @if ($errors->any())
    <x-ui.alert tone="error">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</x-ui.alert>
  @endif

  <form class="form space-y-6" method="POST" action="{{ route('superadmin.admins.store') }}" novalidate>
    @csrf
    @include('superadmin.admins.form', ['admin' => null])

    <x-ui.form-actions>
      <x-slot:left>
        <a class="btn btn-ghost" href="{{ route('superadmin.admins.index') }}">
          <i class="ri-arrow-left-line"></i> Volver
        </a>
      </x-slot>
      <button class="btn btn-primary" type="submit">
        <i class="ri-save-line"></i> Crear
      </button>
    </x-ui.form-actions>
  </form>
</div>
@endsection