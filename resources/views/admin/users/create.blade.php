{{-- resources/views/admin/usuarios/create.blade.php --}}
@extends('layouts.admin')
@section('title','Crear usuario')

@push('head')
  @vite(['resources/css/admin/create-user.css','resources/js/admin/create-user.js'])
@endpush

@section('main')
@php $preset = request('role'); @endphp
<div class="wrap">
  <div class="page-head">
    <h1 class="page-title">Nuevo usuario</h1>
  </div>

  @if ($errors->any())
    <div class="alert alert-error">
      @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
  @endif

  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <form class="card form" method="POST" action="{{ route('admin.usuarios.store') }}" enctype="multipart/form-data" novalidate>
    @csrf
    @if($preset)
      <input type="hidden" name="role_id" value="{{ optional($roles->firstWhere('name',$preset))->id }}">
    @endif

    <div class="form-section">
      {{-- Deja que el parcial maneje su propia grid para ocupar 100% --}}
      @include('admin.users.form', ['user'=>null,'roles'=>$roles,'especialidades'=>$especialidades])
    </div>

    <div class="form-actions">
      <button class="btn" type="submit" aria-label="Crear usuario">
        <span class="material-symbols-outlined" aria-hidden="true">save</span> Crear
      </button>
      <button class="btn btn-ghost" type="reset">
        <span class="material-symbols-outlined" aria-hidden="true">restart_alt</span> Limpiar
      </button>
    </div>
  </form>
</div>
@endsection
