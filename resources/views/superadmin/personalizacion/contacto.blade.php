@extends('layouts.superadmin')
@section('title','Personalización')
@section('header-title','Personalización')
@section('header-subtitle','Contacto (sitio público)')

@section('main')
<div class="space-y-6">
  @include('shared.personalizacion-tabs', ['scope' => 'superadmin'])

  @if (session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  <form class="form space-y-6" method="POST" action="{{ route('superadmin.personalizacion.contacto.update') }}" data-draft-key="superadmin.personalizacion.contacto">
    @csrf
    @method('PUT')

    @include('shared.personalizacion-contacto-form', ['settings' => $settings])

    <div class="flex flex-wrap items-center justify-between gap-3">
      <p class="text-xs text-gray-500" data-draft-status></p>
      <div class="flex flex-wrap items-center gap-3">
        <button class="btn btn-outline" type="button" data-save-draft>
          <i class="ri-draft-line"></i> Guardar borrador
        </button>
        <button class="btn btn-outline" type="button" data-restore-draft>
          <i class="ri-history-line"></i> Restaurar borrador
        </button>
        <button class="btn btn-ghost" type="button" data-clear-draft>
          <i class="ri-delete-bin-7-line"></i> Limpiar borrador
        </button>
      </div>
      <div class="flex items-center gap-3">
      <button class="btn btn-primary" type="submit">
        <i class="ri-save-line"></i> Guardar cambios
      </button>
      </div>
    </div>
  </form>
</div>
@endsection

@push('scripts')
  @vite('resources/js/admin/personalizacion-contacto.js')
  @vite('resources/js/admin/personalizacion-drafts.js')
@endpush

