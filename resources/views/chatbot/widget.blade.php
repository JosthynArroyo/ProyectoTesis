{{-- resources/views/chatbot/widget.blade.php --}}
@once
  @push('scripts')
    @vite('resources/js/chatbot/widget.js')
  @endpush
@endonce

<div id="chatbot-widget"
     class="fixed bottom-6 right-6 z-50"
     data-base-url="{{ url('/') }}"
     data-csrf="{{ csrf_token() }}">
  <button id="chatbot-toggle"
          type="button"
          class="flex h-14 w-14 cursor-pointer items-center justify-center rounded-full bg-teal-600 text-white shadow-lg shadow-teal-500/40"
          aria-controls="chatbot-panel"
          aria-expanded="false"
          aria-label="Abrir asistente de la clínica">
    <i class="ri-customer-service-2-line text-xl"></i>
  </button>

  <div id="chatbot-panel" class="chatbot-panel mt-4 w-80 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl" aria-live="polite" aria-hidden="true">
    <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
      <div>
        <p class="text-sm font-semibold text-slate-800">Asistente clínico</p>
        <p class="text-xs text-slate-500">Disponible 24/7</p>
      </div>
      <span class="badge success">En línea</span>
    </div>

    <div id="chat-log" class="h-64 space-y-3 overflow-y-auto bg-slate-50/60 px-4 py-3"></div>

    <div id="chatbot-input-area" class="border-t border-slate-100 bg-white px-3 py-3">
      <form id="chatbot-form" autocomplete="off" class="flex items-center gap-2">
        <input id="chatbot-input" type="text" placeholder="Escribe aquí..." class="flex-1 rounded-full border border-slate-200 px-3 py-2 text-sm" required>
        <button id="chatbot-send-btn" type="submit" class="btn btn-primary px-3 py-2" aria-label="Enviar mensaje">
          <i class="ri-send-plane-2-line"></i>
        </button>
      </form>
    </div>
  </div>
</div>