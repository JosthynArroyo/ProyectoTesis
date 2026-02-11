@extends('layouts.superadmin')
@section('title','Personalización')
@section('header-title','Personalización')
@section('header-subtitle','Bienvenida (inicio)')

@section('main')
<div class="space-y-6">
  <section class="card p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Personalización</p>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Configurar bienvenida</h1>
        <p class="text-slate-600">Controla la visibilidad del bloque de especialidades destacadas.</p>
      </div>
    </div>
  </section>

  @if ($errors->any())
    <x-ui.alert tone="error">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</x-ui.alert>
  @endif
  @if (session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  <form class="form space-y-6" method="POST" action="{{ route('superadmin.personalizacion.bienvenida.update') }}" enctype="multipart/form-data">
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

    <div class="flex flex-wrap items-center justify-end gap-3">
      <button class="btn btn-primary" type="submit">
        <i class="ri-save-line"></i> Guardar cambios
      </button>
    </div>
  </form>
</div>
@endsection

@push('scripts')
  @vite('resources/js/admin/personalizacion-bienvenida.js')
@endpush
