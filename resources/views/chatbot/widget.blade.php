{{-- resources/views/chatbot/widget.blade.php --}}
@once
  @push('styles')
    @vite('resources/css/chatbot/widget.css')
  @endpush
  @push('scripts')
    @vite('resources/js/chatbot/widget.js')
  @endpush
@endonce

<div id="chatbot-widget"
     class="fixed bottom-4 right-4 z-30 md:bottom-6 md:right-6"
     data-base-url="{{ url('/') }}"
     data-slot-hold-url="{{ route('api.slot-holds.store') }}"
     data-csrf="{{ csrf_token() }}">
  <button id="chatbot-toggle"
          type="button"
          class="flex h-14 w-14 cursor-pointer items-center justify-center rounded-full text-white shadow-lg"
          aria-controls="chatbot-panel"
          aria-expanded="false"
          aria-label="Abrir asistente de la clínica">
    <i class="ri-customer-service-2-line text-xl"></i>
  </button>

  <div id="chatbot-panel" class="chatbot-panel mt-4 w-80 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl" aria-live="polite" aria-hidden="true">
    <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
      <div>
        <p class="text-sm font-semibold text-gray-800">Asistente clínico</p>
        <p class="text-xs text-gray-500">Para iniciar agendamientos</p>
      </div>
      <span class="badge success">En línea</span>
    </div>

    <div id="chat-log" class="h-64 space-y-3 overflow-y-auto bg-gray-50/60 px-4 py-3"></div>

    <div id="chatbot-input-area" class="border-t border-gray-100 bg-white px-3 py-3">
      <form id="chatbot-form" autocomplete="off" class="flex items-center gap-2">
        <input id="chatbot-input" type="text" placeholder="Escribe aquí..." class="flex-1 rounded-full border border-gray-200 px-3 py-2 text-sm" required>
        <button id="chatbot-send-btn" type="submit" class="btn btn-primary px-3 py-2" aria-label="Enviar mensaje">
          <i class="ri-send-plane-2-line"></i>
        </button>
      </form>
    </div>
  </div>
</div>
