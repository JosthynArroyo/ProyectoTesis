@extends('layouts.admin')
@section('title','Personalización')
@section('header-title','Personalización')
@section('header-subtitle','Servicios (landing)')

@section('main')
<div class="space-y-6">
  <section class="card p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Personalización</p>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Editar servicios</h1>
        <p class="text-slate-600">Gestiona las especialidades destacadas de la página de inicio.</p>
      </div>
    </div>
  </section>

  @if ($errors->any())
    <x-ui.alert tone="error">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</x-ui.alert>
  @endif
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
