@extends('layouts.navbar')

@section('title','Sitio en mantenimiento')

@section('main')
<div style="min-height:calc(100vh - 4.5rem);display:flex;flex-direction:column;">
  <section class="section-pad section-pad--first">
    <div class="page-shell">
      <div class="card p-8 text-center space-y-4">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-amber-50 text-amber-600">
          <i class="ri-tools-line text-3xl"></i>
        </div>
        <h1 class="text-3xl font-semibold text-slate-900">Estamos en mantenimiento</h1>
        <p class="text-slate-600 max-w-2xl mx-auto">{{ $message ?? 'Estamos realizando ajustes para mejorar tu experiencia.' }}</p>
        @if(!empty($until))
          <p class="text-sm text-slate-500">Tiempo estimado: {{ \Illuminate\Support\Carbon::parse($until)->format('d/m/Y H:i') }}</p>
        @endif
        <p class="text-xs text-slate-400">Gracias por tu paciencia.</p>
      </div>
    </div>
  </section>
  @include('partials.footer')
</div>
@endsection
