@extends('layouts.app')

@section('title', 'Verificar documento')

@section('content')
<div class="min-h-screen bg-[radial-gradient(circle_at_top,_#d1fae5_0,_#f8fafc_35%,_#ffffff_100%)]">
  <div class="mx-auto flex min-h-screen max-w-5xl items-center px-4 py-16 sm:px-6 lg:px-8">
    <div class="grid w-full gap-8 rounded-[2rem] border border-emerald-100 bg-white/90 p-6 shadow-[0_25px_70px_rgba(15,118,110,0.12)] backdrop-blur md:grid-cols-[1.05fr_0.95fr] md:p-10">
      <div class="space-y-6">
        <div class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-4 py-2 text-xs font-semibold uppercase tracking-[0.25em] text-emerald-700">
          Verificacion de documentos
        </div>
        <div class="space-y-3">
          <h1 class="text-4xl font-bold tracking-tight text-slate-900 sm:text-5xl">
            Comprueba un documento con su CSV.
          </h1>
          <p class="max-w-xl text-base leading-7 text-slate-600">
            Ingresa el codigo que aparece en el pie del documento o escanea el QR para abrir el PDF original almacenado en el servidor.
          </p>
        </div>

        <div class="grid gap-3 text-sm text-slate-600 sm:grid-cols-2">
          <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <div class="font-semibold text-slate-900">CSV seguro</div>
            <div class="mt-1">Codigo alfanumerico unico asociado a la receta, certificado o pedido.</div>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <div class="font-semibold text-slate-900">PDF original</div>
            <div class="mt-1">El sistema abre exactamente el archivo almacenado, sin regenerarlo ni alterarlo.</div>
          </div>
        </div>
      </div>

      <div class="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-6 shadow-inner">
        <form method="POST" action="{{ route('documentos.verificar.search') }}" class="space-y-4">
          @csrf
          <div>
            <label for="csv" class="mb-2 block text-sm font-semibold text-slate-700">Codigo CSV</label>
            <input
              id="csv"
              name="csv"
              type="text"
              value="{{ old('csv') }}"
              placeholder="ABC-12345-XYZ"
              class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-base text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
              autocomplete="off"
              spellcheck="false"
              required
            >
            @error('csv')
              <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
            @enderror
          </div>

          <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-4 py-3 text-base font-semibold text-white transition hover:bg-emerald-700">
            <i class="ri-search-line"></i>
            Verificar documento
          </button>
        </form>

        <div class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-white p-4 text-sm text-slate-600">
          Si escaneas el QR del documento, debes llegar directamente a la ruta de verificacion publica.
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
