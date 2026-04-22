@extends('layouts.superadmin')
@section('title','Crear administrador')
@section('header-title','Nuevo administrador')
@section('header-subtitle','Alta de cuentas de administrador')

@section('main')
<div class="space-y-6">
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
