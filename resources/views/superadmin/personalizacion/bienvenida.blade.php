@extends('layouts.superadmin')
@section('title','Personalización')
@section('header-title','Personalización')
@section('header-subtitle','Bienvenida (inicio)')

@section('main')
<div class="space-y-6">
  @include('shared.personalizacion-tabs', ['scope' => 'superadmin'])

  @if (session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  @if (session('error'))
    <x-ui.alert tone="error">{{ session('error') }}</x-ui.alert>
  @endif

  <div
    class="alert error mt-4"
    role="alert"
    aria-live="polite"
    tabindex="-1"
    id="bienvenida-upload-alert"
    data-bienvenida-upload-alert
    hidden
  ></div>

  <form class="form space-y-6" method="POST" action="{{ route('superadmin.personalizacion.bienvenida.update') }}" enctype="multipart/form-data" data-draft-key="superadmin.personalizacion.bienvenida" id="bienvenida-form">
    @csrf
    @method('PUT')
    @include('shared.personalizacion-bienvenida-form', [
      'settings'             => $settings,
      'welcomeSiteSettings'  => $welcomeSiteSettings ?? [],
      'slides'               => $slides ?? [],
      'doctors'              => $doctors ?? [],
      'prices'               => $prices ?? [],
      'featuredIds'          => $featuredIds ?? [],
      'especialidadesActivas' => collect($especialidadesActivas ?? []),
      'batchStatusRouteName' => 'superadmin.personalizacion.bienvenida.batch',
    ])

    <div class="flex flex-wrap items-center justify-between gap-3">
      <p class="text-xs text-gray-500" data-draft-status></p>
      <div class="flex flex-wrap items-center gap-3">
        <button class="btn btn-outline cursor-pointer" type="button" data-save-draft>
          <i class="ri-draft-line"></i> Guardar borrador
        </button>
        <button class="btn btn-outline cursor-pointer" type="button" data-restore-draft>
          <i class="ri-history-line"></i> Restaurar borrador
        </button>
        <button class="btn btn-ghost cursor-pointer" type="button" data-clear-draft>
          <i class="ri-delete-bin-7-line"></i> Limpiar borrador
        </button>
      </div>
      <div class="flex items-center gap-3">
      <button class="btn btn-primary cursor-pointer" type="submit" id="bienvenida-submit-btn">
        <i class="ri-save-line"></i> Guardar cambios
      </button>
      </div>
    </div>
  </form>

  {{-- Media Processing Overlay (same pattern as Servicios) --}}
  <div
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm transition-opacity"
    id="bienvenida-media-processing-overlay"
    data-media-processing-overlay
    hidden
    aria-hidden="true"
  >
    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl dark:bg-gray-900 border border-gray-100 dark:border-gray-800 text-center space-y-4">
      <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full text-[var(--accent)]" data-media-processing-icon-wrap>
        <i class="ri-loader-4-line animate-spin text-3xl" data-media-processing-spinner></i>
        <i class="ri-checkbox-circle-line text-3xl text-emerald-500 hidden" data-media-processing-success-icon></i>
        <i class="ri-error-warning-line text-3xl text-rose-500 hidden" data-media-processing-error-icon></i>
      </div>
      <div>
        <h3 class="text-lg font-bold text-gray-900 dark:text-white" data-media-processing-title>
          Preparando imágenes...
        </h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400" data-media-processing-status-text>
          Preparando imágenes...
        </p>
      </div>

      <div class="w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800 h-3">
        <div
          class="h-full transition-all duration-300 rounded-full"
          data-media-processing-progress-bar
        ></div>
      </div>

      <div class="text-xs text-gray-400 dark:text-gray-500" data-media-processing-percentage-text>
        0% completado
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
  @vite('resources/js/admin/personalizacion-bienvenida.js')
  @vite('resources/js/admin/personalizacion-drafts.js')
@endpush
