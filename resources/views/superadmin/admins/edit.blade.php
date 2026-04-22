@extends('layouts.superadmin')
@section('title','Editar administrador')
@section('header-title', $admin->name)
@section('header-subtitle','Administrador #'.$admin->id.' | Actualiza datos y credenciales')

@section('main')
<div class="space-y-6">
  @if (session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
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
