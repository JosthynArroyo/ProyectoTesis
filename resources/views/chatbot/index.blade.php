{{-- resources/views/chatbot/index.blade.php --}}
@extends('layouts.app')

@section('title','Asistente virtual')

@section('content')
  <main class="section-pad">
    <div class="page-shell">
      <div class="card p-8">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div>
            <p class="text-xs uppercase tracking-widest text-slate-500">Asistente virtual</p>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">Tu guía clínica en línea</h1>
            <p class="text-slate-600">Te ayudamos a gestionar tus citas médicas. Elige una opción o responde a las preguntas.</p>
          </div>
          <span class="badge success">Disponible</span>
        </div>

        <div
          id="chatbot"
          class="mt-6 rounded-2xl border border-slate-200 bg-slate-50/60 p-4"
          data-base-url="{{ url('/') }}"
          data-csrf="{{ csrf_token() }}">
          <div id="chat-log" class="h-80 space-y-3 overflow-y-auto" aria-live="polite"></div>

          <div class="mt-4 border-t border-slate-200 pt-3">
            <form id="chat-form" autocomplete="off" class="flex items-center gap-2">
              <input id="chat-input" type="text" placeholder="Escribe tu respuesta..." class="flex-1 rounded-full border border-slate-200 bg-white px-3 py-2 text-sm" required>
              <button type="submit" class="btn btn-primary px-4"><i class="ri-send-plane-2-line"></i></button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </main>
@endsection

@push('scripts')
  @vite('resources/js/chatbot/index.js')
@endpush