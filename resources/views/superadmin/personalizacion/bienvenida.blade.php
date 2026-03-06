@extends('layouts.superadmin')
@section('title','PersonalizaciÃ³n')
@section('header-title','PersonalizaciÃ³n')
@section('header-subtitle','Bienvenida (inicio)')

@section('main')
<div class="space-y-6">
  <section class="card p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">PersonalizaciÃ³n</p>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Configurar bienvenida</h1>
        <p class="text-slate-600">Controla la visibilidad del bloque de especialidades destacadas.</p>
      </div>
    </div>
  </section>

  @include('shared.personalizacion-tabs', ['scope' => 'superadmin'])

  @if (session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  <form class="form space-y-6" method="POST" action="{{ route('superadmin.personalizacion.bienvenida.update') }}" enctype="multipart/form-data" data-draft-key="superadmin.personalizacion.bienvenida">
    @csrf
    @method('PUT')
    @include('shared.personalizacion-bienvenida-form', [
      'settings' => $settings,
      'stats' => $stats ?? [],
      'slides' => $slides ?? [],
      'cards' => $cards ?? [],
      'doctors' => $doctors ?? [],
      'prices' => $prices ?? [],
      'featuredIds' => $featuredIds ?? [],
      'especialidadesActivas' => collect($especialidadesActivas ?? []),
    ])

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
  @vite('resources/js/admin/personalizacion-bienvenida.js')
  @vite('resources/js/admin/personalizacion-drafts.js')
@endpush

