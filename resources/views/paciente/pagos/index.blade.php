@extends('layouts.paciente')
@section('title', 'Órdenes de cobro')
@section('header-title', 'Órdenes de cobro')
@section('header-subtitle', 'Control y estado de cobros por cita')

@section('main')
<div class="space-y-6">
  <div class="panel-action-bar">
    <a href="{{ route('paciente.citas') }}" class="btn btn-outline">Ver citas</a>
  </div>

  @if($bloqueoActivo)
    <x-ui.alert tone="warning">
      Tienes órdenes de cobro vencidas de citas concluidas. Regulariza tu cuenta para agendar una nueva cita.
    </x-ui.alert>
  @endif

  @if(session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  <section class="card p-6">
    <form method="GET" action="{{ url()->current() }}" class="flex flex-wrap items-end gap-3">
      <div>
        <label class="form-label" for="estado">Estado</label>
        <select id="estado" name="estado" class="form-select">
          <option value="all" @selected($estado === '')>Todos</option>
          <option value="pendiente" @selected($estado === 'pendiente')>Pendiente</option>
          <option value="en_verificacion" @selected($estado === 'en_verificacion')>En verificación</option>
          <option value="rechazado" @selected($estado === 'rechazado')>Rechazado</option>
          <option value="pagado" @selected($estado === 'pagado')>Pagado</option>
          <option value="anulado" @selected($estado === 'anulado')>Anulado</option>
        </select>
      </div>
      <button type="submit" class="btn btn-outline btn-sm">Filtrar</button>
    </form>
  </section>

  <section class="grid gap-4">
    @forelse($pagos as $pago)
      @php
        $estadoLabel = match($pago->estado) {
          'pendiente' => 'Pendiente',
          'en_verificacion' => 'En verificación',
          'rechazado' => 'Rechazado',
          'pagado' => 'Pagado',
          'anulado' => 'Anulado',
          default => ucfirst((string) $pago->estado),
        };
        $estadoTone = match($pago->estado) {
          'pagado' => 'success',
          'rechazado' => 'danger',
          'en_verificacion' => 'info',
          'anulado' => 'neutral',
          default => 'warning',
        };

        $metodoActual = old('metodo_pago', $pago->metodo_pago);
        $esTransferencia = $metodoActual === 'transferencia';
        $esEfectivo = $metodoActual === 'efectivo';
        $ordenDisponible = $pago->tieneOrdenCobro();
      @endphp
      <article id="pago-{{ $pago->id }}" class="card p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h3 class="text-base font-semibold text-gray-900">Cita #{{ $pago->cita_id }}</h3>
            <p class="text-sm text-gray-500">
              {{ optional($pago->cita?->doctor)->name ?? 'Doctor no disponible' }}
              · {{ optional($pago->cita?->especialidad)->nombre ?? 'Especialidad' }}
            </p>
            <p class="text-xs text-gray-500">
              {{ optional($pago->cita?->fecha)->format('Y-m-d') }} {{ $pago->cita?->hora ? substr((string)$pago->cita->hora, 0, 5) : '' }}
            </p>
            <p class="mt-1 text-xs text-gray-500">
              Folio orden: {{ $pago->folio_unico ?: 'Sin generar' }}
            </p>
          </div>
          <div class="text-right">
            <p class="text-sm font-semibold text-gray-900">{{ number_format((float)$pago->monto, 2) }} {{ $pago->moneda }}</p>
            <span class="badge {{ $estadoTone }}">{{ $estadoLabel }}</span>
          </div>
        </div>

        <div class="mt-3 flex flex-wrap gap-2">
          @if($pago->estado !== 'pagado' && $ordenDisponible)
            <a href="{{ route('paciente.pagos.orden.pdf', $pago) }}" class="btn btn-outline btn-sm" target="_blank" rel="noopener" data-action-lock-ignore data-skip-page-loader>Descargar orden</a>
            @if($pago->token_publico)
              <a href="{{ route('pagos.token.show', $pago->token_publico) }}" class="btn btn-outline btn-sm" target="_blank" rel="noopener" data-action-lock-ignore data-skip-page-loader>Abrir token</a>
            @endif
          @endif

          @if($pago->estado === 'pagado' && $pago->receipt)
            <a href="{{ route('paciente.pagos.recibo.pdf', $pago) }}" class="btn btn-outline btn-sm" target="_blank" rel="noopener" data-action-lock-ignore data-skip-page-loader>Descargar recibo</a>
          @endif

          @if($pago->comprobante_path)
            <a href="{{ route('paciente.pagos.comprobante', $pago) }}" class="btn btn-outline btn-sm" target="_blank" rel="noopener" data-action-lock-ignore data-skip-page-loader>Ver comprobante</a>
          @endif
        </div>

        @if($pago->observacion_admin)
          <p class="mt-3 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
            <strong>Observación administrativa:</strong> {{ $pago->observacion_admin }}
          </p>
        @endif

        @if(!$ordenDisponible)
          <p class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-3 text-sm text-gray-600">
            La orden de cobro aún no está disponible. Solo se genera cuando la cita queda en estado realizada.
          </p>
        @elseif($pago->esEditablePorPaciente())
          <form method="POST" action="{{ route('paciente.pagos.submit', $pago) }}" enctype="multipart/form-data" class="mt-4 grid gap-3 md:grid-cols-2" data-pago-form data-saved-metodo="{{ $pago->metodo_pago }}">
            @csrf
            <div>
              <label class="form-label" for="metodo_pago_{{ $pago->id }}">Método de pago</label>
              <select id="metodo_pago_{{ $pago->id }}" name="metodo_pago" class="form-select" required data-metodo-select>
                <option value="">Seleccione</option>
                <option value="efectivo" @selected($metodoActual === 'efectivo')>Efectivo</option>
                <option value="transferencia" @selected($metodoActual === 'transferencia')>Transferencia</option>
              </select>
              @error('metodo_pago')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
            </div>

            <div>
              <label class="form-label" for="referencia_transaccion_{{ $pago->id }}">Referencia (opcional)</label>
              <input id="referencia_transaccion_{{ $pago->id }}" type="text" name="referencia_transaccion" value="{{ old('referencia_transaccion', $pago->referencia_transaccion) }}" class="form-input">
              @error('referencia_transaccion')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
            </div>

            <div class="md:col-span-2 {{ $esTransferencia ? '' : 'hidden' }}" data-comprobante-wrapper>
              <label class="form-label" for="comprobante_{{ $pago->id }}">Comprobante (JPG, JPEG, PNG · máx. 5MB)</label>
              <input id="comprobante_{{ $pago->id }}" type="file" name="comprobante" class="form-input" accept="image/jpeg,image/png" data-comprobante-input @if(!$esTransferencia) disabled @endif>
              <span data-comprobante-error class="text-xs text-rose-600 hidden mt-1"></span>
              @error('comprobante')<span class="text-xs text-rose-600 block mt-1">{{ $message }}</span>@enderror
              <p class="mt-1 text-xs text-gray-500">Formatos permitidos: JPG, JPEG y PNG. Tamaño máximo: 5 MB</p>
              <div data-comprobante-preview class="mt-2 hidden">
                <img src="" alt="Vista previa de comprobante" class="max-h-48 rounded-lg border border-gray-200 object-contain">
              </div>
            </div>

            <div class="md:col-span-2 rounded-xl border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700 hidden" data-efectivo-msg-unconfirmed>
              Pago en clínica: este pago será confirmado por recepción al momento de su atención.
            </div>

            <div class="md:col-span-2 rounded-xl border border-green-200 bg-green-50 p-3 text-sm text-green-700 hidden" data-efectivo-msg-confirmed>
              Pago en efectivo confirmado. El cobro será registrado por recepción al momento de su atención.
            </div>

            <div class="md:col-span-2">
              <button type="submit" class="btn btn-primary" data-submit-label>
                {{ $esTransferencia ? 'Guardar y enviar' : 'Confirmar que pagaré en clínica' }}
              </button>
            </div>
          </form>
        @else
          <p class="mt-4 text-sm text-gray-500">Este pago no admite cambios en su estado actual.</p>
        @endif
      </article>
    @empty
      <x-ui.empty-state title="No hay órdenes de cobro para mostrar." message="Las citas futuras y sus comprobantes de agendamiento no aparecen aquí. Solo se listan órdenes de cobro reales.">
        <div class="mt-4 flex flex-wrap justify-center gap-3">
          <a class="btn btn-primary" href="{{ route('paciente.citas') }}">Ver mis citas</a>
          <a class="btn btn-outline" href="{{ route('paciente.crear-cita') }}">Agendar cita</a>
        </div>
      </x-ui.empty-state>
    @endforelse
  </section>

  <div class="flex justify-center">
    {{ $pagos->links() }}
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-pago-form]').forEach((form) => {
    const metodoSelect = form.querySelector('[data-metodo-select]');
    const comprobanteWrapper = form.querySelector('[data-comprobante-wrapper]');
    const comprobanteInput = form.querySelector('[data-comprobante-input]');
    const comprobanteError = form.querySelector('[data-comprobante-error]');
    const comprobantePreview = form.querySelector('[data-comprobante-preview]');
    const unconfirmedMsg = form.querySelector('[data-efectivo-msg-unconfirmed]');
    const confirmedMsg = form.querySelector('[data-efectivo-msg-confirmed]');
    const submitButton = form.querySelector('[data-submit-label]');

    if (!metodoSelect || !submitButton) {
      return;
    }

    if (comprobanteInput) {
      comprobanteInput.addEventListener('change', () => {
        if (comprobanteError) comprobanteError.classList.add('hidden');
        if (comprobantePreview) comprobantePreview.classList.add('hidden');

        const file = comprobanteInput.files ? comprobanteInput.files[0] : null;
        if (!file) return;

        const validTypes = ['image/jpeg', 'image/png'];
        const ext = file.name.split('.').pop().toLowerCase();
        const validExts = ['jpg', 'jpeg', 'png'];

        if (!validTypes.includes(file.type) || !validExts.includes(ext) || file.size > 5 * 1024 * 1024) {
          if (comprobanteError) {
            comprobanteError.textContent = 'Formatos permitidos: JPG, JPEG y PNG. Tamaño máximo: 5 MB';
            comprobanteError.classList.remove('hidden');
          }
          comprobanteInput.value = '';
          return;
        }

        if (comprobantePreview) {
          const img = comprobantePreview.querySelector('img');
          if (img) {
            const reader = new FileReader();
            reader.onload = (e) => {
              img.src = e.target.result;
              comprobantePreview.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
          }
        }
      });
    }

    form.addEventListener('submit', (e) => {
      if (submitButton.disabled) {
        e.preventDefault();
        return;
      }
      submitButton.disabled = true;
      setTimeout(() => { submitButton.disabled = false; }, 4000);
    });

    const applyMode = () => {
      const metodo = metodoSelect.value;
      const savedMetodo = form.getAttribute('data-saved-metodo');
      const isTransferencia = metodo === 'transferencia';
      const isEfectivo = metodo === 'efectivo';

      if (comprobanteWrapper) {
        comprobanteWrapper.classList.toggle('hidden', !isTransferencia);
      }

      if (comprobanteInput) {
        comprobanteInput.disabled = !isTransferencia;
        comprobanteInput.required = isTransferencia;
        if (!isTransferencia) {
          comprobanteInput.value = '';
          if (comprobantePreview) comprobantePreview.classList.add('hidden');
          if (comprobanteError) comprobanteError.classList.add('hidden');
        }
      }

      if (unconfirmedMsg) unconfirmedMsg.classList.add('hidden');
      if (confirmedMsg) confirmedMsg.classList.add('hidden');

      if (isEfectivo) {
        if (savedMetodo === 'efectivo') {
          if (confirmedMsg) confirmedMsg.classList.remove('hidden');
          submitButton.classList.add('hidden');
        } else {
          if (unconfirmedMsg) unconfirmedMsg.classList.remove('hidden');
          submitButton.classList.remove('hidden');
        }
      } else {
        submitButton.classList.remove('hidden');
      }

      submitButton.textContent = isTransferencia
        ? 'Guardar y enviar'
        : 'Confirmar que pagaré en clínica';
    };

    metodoSelect.addEventListener('change', applyMode);
    applyMode();
  });
});
</script>
@endpush
