{{-- resources/views/admin/usuarios/create.blade.php --}}
@extends('layouts.admin')
@section('title','Crear usuario')
@section('header-title','Nuevo usuario')
@section('header-subtitle','Alta rápida de usuarios')

@section('main')
@php $preset = in_array(request('role'), ['administrador','superadmin'], true) ? null : request('role'); @endphp
<div class="space-y-6">
  @if (session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  <form class="form space-y-6" method="POST" action="{{ route('admin.usuarios.store') }}" enctype="multipart/form-data" novalidate data-email-check-url="{{ route('admin.usuarios.email.check') }}">
    @csrf
    @if($preset)
      <input type="hidden" name="role_id" value="{{ optional($roles->firstWhere('name',$preset))->id }}">
    @endif

    @include('admin.users.form', ['user'=>null,'roles'=>$roles,'especialidades'=>$especialidades])

    <div class="flex flex-wrap items-center justify-end gap-3">
      <button class="btn btn-primary" type="submit" id="btn-registrar-usuario" aria-label="Registrar usuario">
        <i class="ri-save-line"></i> Registrar
      </button>
      <button class="btn btn-ghost" type="reset" id="btn-limpiar-usuario">
        <i class="ri-refresh-line"></i> Limpiar
      </button>
    </div>
  </form>
</div>
@endsection

@push('scripts')
  @vite('resources/js/admin/create-user.js')
@endpush
