@extends('layouts.superadmin')
@section('title','Personalizacion')
@section('header-title','Personalizacion')
@section('header-subtitle','Contacto (sitio pÃºblico)')

@section('main')
<div class="space-y-6">
  <section class="card p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Personalizacion</p>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Configurar secciÃ³n de contacto</h1>
        <p class="text-slate-600">Edita el contenido del bloque informativo y del formulario pÃºblico.</p>
      </div>
    </div>
  </section>

  @include('shared.personalizacion-tabs', ['scope' => 'superadmin'])

  @if (session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  <form class="form space-y-6" method="POST" action="{{ route('superadmin.personalizacion.contacto.update') }}" data-draft-key="superadmin.personalizacion.contacto">
    @csrf
    @method('PUT')

    @include('shared.personalizacion-contacto-form', ['settings' => $settings])

    <div class="flex flex-wrap items-center justify-between gap-3">
      <p class="text-xs text-slate-500" data-draft-status></p>
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
  @vite('resources/js/admin/personalizacion-drafts.js')
@endpush

