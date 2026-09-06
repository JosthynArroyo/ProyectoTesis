@extends('layouts.navbar')

@section('title', 'Verificar documento')

@section('main')
<div class="flex min-h-[calc(100vh-4.5rem)] flex-col justify-between bg-[radial-gradient(circle_at_top,_var(--accent-soft,_#f1f5f9)_0,_#f8fafc_35%,_#ffffff_100%)]">
  <div class="mx-auto flex w-full max-w-5xl flex-1 items-center px-4 py-12 sm:px-6 lg:px-8">
    <div class="grid w-full gap-8 rounded-[2rem] border border-slate-200/80 bg-white/90 p-6 shadow-[0_25px_70px_rgba(15,23,42,0.08)] backdrop-blur md:grid-cols-[1.05fr_0.95fr] md:p-10">
      <div class="space-y-6">
        <div class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-xs font-semibold uppercase tracking-[0.25em] text-slate-700">
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
              class="w-full rounded-2xl border @error('csv') border-rose-300 ring-2 ring-rose-100 focus:border-rose-500 focus:ring-rose-200 @else border-slate-300 focus:border-[var(--accent)] focus:ring-[var(--accent-soft)] @enderror bg-white px-4 py-3 text-base text-slate-900 outline-none transition focus:ring-4"
              autocomplete="off"
              spellcheck="false"
              required
              @error('csv') aria-invalid="true" aria-describedby="csv-error" autofocus @enderror
            >
            @error('csv')
              <p id="csv-error" class="mt-2 flex items-center gap-1.5 text-sm font-medium text-rose-600" role="alert">
                <i class="ri-error-warning-line shrink-0 text-base" aria-hidden="true"></i>
                <span>{{ $message }}</span>
              </p>
            @enderror
          </div>

          <button type="submit" class="btn btn-primary flex w-full items-center justify-center gap-2 rounded-2xl px-4 py-3 text-base font-semibold transition">
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
  @include('partials.footer')
</div>
@endsection
