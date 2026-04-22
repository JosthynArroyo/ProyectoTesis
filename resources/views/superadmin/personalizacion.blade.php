@extends('layouts.superadmin')
@section('title','Personalización')
@section('header-title','Personalización')
@section('header-subtitle','Contenido público y branding')

@section('main')
<div class="space-y-6">
  @if (session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  <form class="form space-y-6" method="POST" action="{{ route('superadmin.personalizacion.update') }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    @include('shared.personalizacion-form', ['settings' => $settings])

    <div class="flex flex-wrap items-center justify-end gap-3">
      <button class="btn btn-primary" type="submit">
        <i class="ri-save-line"></i> Guardar cambios
      </button>
    </div>
  </form>
</div>
@endsection

