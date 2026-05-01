@extends('layouts.admin')
@section('title','Personalización')
@section('header-title','Personalización')
@section('header-subtitle','Bienvenida (inicio)')

@section('main')
<div class="space-y-6">
  @include('shared.personalizacion-tabs', ['scope' => 'admin'])

  @if (session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  <form class="form space-y-6" method="POST" action="{{ route('admin.personalizacion.bienvenida.update') }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    @include('shared.personalizacion-bienvenida-form', [
      'settings' => $settings,
      'welcomeSiteSettings' => $welcomeSiteSettings ?? [],
      'slides' => $slides ?? [],
      'doctors' => $doctors ?? [],
      'prices' => $prices ?? [],
      'featuredIds' => $featuredIds ?? [],
      'especialidadesActivas' => collect($especialidadesActivas ?? []),
    ])

    <div class="flex flex-wrap items-center justify-end gap-3">
      <button class="btn btn-primary cursor-pointer" type="submit">
        <i class="ri-save-line"></i> Guardar cambios
      </button>
    </div>
  </form>
</div>
@endsection

@push('scripts')
  @vite('resources/js/admin/personalizacion-bienvenida.js')
@endpush

