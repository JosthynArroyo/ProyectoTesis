@extends('layouts.admin')
@section('title','Personalizacion')
@section('header-title','Personalizacion')
@section('header-subtitle','Contacto (sitio pÃºblico)')

@section('main')
<div class="space-y-6">
  <section class="card p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Personalizacion</p>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Editar secciÃ³n de contacto</h1>
        <p class="text-slate-600">Configura todos los textos visibles y el mapa del formulario pÃºblico.</p>
      </div>
    </div>
  </section>

  @include('shared.personalizacion-tabs', ['scope' => 'admin'])

  @if (session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  <form class="form space-y-6" method="POST" action="{{ route('admin.personalizacion.contacto.update') }}">
    @csrf
    @method('PUT')

    @include('shared.personalizacion-contacto-form', ['settings' => $settings])

    <div class="flex flex-wrap items-center justify-end gap-3">
      <button class="btn btn-primary" type="submit">
        <i class="ri-save-line"></i> Guardar cambios
      </button>
    </div>
  </form>
</div>
@endsection

