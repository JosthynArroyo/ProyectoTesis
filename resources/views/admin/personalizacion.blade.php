@extends('layouts.admin')
@section('title','Personalización')
@section('header-title','Personalización')
@section('header-subtitle','Contenido público y branding')

@section('main')
<div class="space-y-6">
  <section class="card p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Personalización</p>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Editar contenido público</h1>
        <p class="text-slate-600">Los cambios se reflejan automáticamente en el sitio.</p>
      </div>
    </div>
  </section>

  @if ($errors->any())
    <x-ui.alert tone="error">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</x-ui.alert>
  @endif
  @if (session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  <form class="form space-y-6" method="POST" action="{{ route('admin.personalizacion.update') }}" enctype="multipart/form-data">
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
