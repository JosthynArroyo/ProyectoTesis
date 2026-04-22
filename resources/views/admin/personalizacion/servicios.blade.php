@extends('layouts.admin')
@section('title','Personalización')
@section('header-title','Personalización')
@section('header-subtitle','Servicios (landing)')

@section('main')
<div class="space-y-6">
  @include('shared.personalizacion-tabs', ['scope' => 'admin'])

  @if (session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  <form class="form space-y-6" method="POST" action="{{ route('admin.personalizacion.servicios.update') }}">
    @csrf
    @method('PUT')
    @include('shared.personalizacion-servicios-form', ['especialidades' => $especialidades])

    <div class="flex flex-wrap items-center justify-end gap-3">
      <button class="btn btn-primary" type="submit">
        <i class="ri-save-line"></i> Guardar cambios
      </button>
    </div>
  </form>
</div>
@endsection

@push('scripts')
  @vite('resources/js/admin/personalizacion-servicios.js')
@endpush

