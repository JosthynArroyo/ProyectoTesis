@extends('layouts.superadmin')
@section('title','PersonalizaciÃ³n')
@section('header-title','PersonalizaciÃ³n')
@section('header-subtitle','Contenido pÃºblico y branding')

@section('main')
<div class="space-y-6">
  <section class="card p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">PersonalizaciÃ³n</p>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Configurar sitio pÃºblico</h1>
        <p class="text-slate-600">Supervisa textos, botones y branding del sitio.</p>
      </div>
    </div>
  </section>

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

