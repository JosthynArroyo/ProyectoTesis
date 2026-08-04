@extends('layouts.admin')
@section('title', 'Detalle de cobro')
@section('header-title', 'Cobro de cita #'.$pago->cita_id)
@section('header-subtitle', 'Pago #'.$pago->id.' | Validacion, orden y recibo')

@php
  $administrativeActions = $pago->availableAdministrativeActions();
  $canReviewPayment = ! empty($administrativeActions);
  $administrativeMessage = $pago->administrativeStateMessage();
@endphp

@section('main')
<div class="space-y-6">
  <div class="panel-action-bar">
    <a href="{{ route('admin.pagos.index') }}" class="btn btn-outline">Volver</a>
  </div>

  @if(session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif
  @if($errors->any())
    <x-ui.alert tone="error">{{ $errors->first() }}</x-ui.alert>
  @endif
  @if(! $canReviewPayment)
    <x-ui.alert tone="warning">{{ $administrativeMessage }}</x-ui.alert>
  @endif

  <div class="grid gap-6 lg:grid-cols-2">
    <section class="card p-6 space-y-4">
      <h2 class="text-lg font-semibold text-gray-900">Datos del cobro</h2>
      <div class="space-y-2 break-words text-sm text-gray-700">
        <p><strong>Folio orden:</strong> {{ $pago->folio_unico ?: 'SIN FOLIO' }}</p>
        <p><strong>Token público:</strong> {{ $pago->token_publico ?: 'N/D' }}</p>
        <p><strong>Paciente:</strong> {{ $pago->cita ? $pago->cita->nombrePacienteReal() : ($pago->paciente?->name ?? '-') }} ({{ $pago->cita ? $pago->cita->dniPacienteReal() : ($pago->paciente?->dni ?? 'N/D') }})</p>
        @if($pago->cita && $pago->cita->dependiente_id)
          <p><strong>Representante (Responsable de pago):</strong> {{ $pago->paciente?->name }} ({{ $pago->paciente?->dni }})</p>
        @endif
        <p><strong>Correo:</strong> {{ $pago->paciente?->email }}</p>
        <p><strong>Monto:</strong> {{ number_format((float)$pago->monto, 2) }} {{ $pago->moneda }}</p>
        <p><strong>Metodo:</strong> {{ $pago->metodo_pago ? strtoupper((string)$pago->metodo_pago) : 'SIN DEFINIR' }}</p>
        <p><strong>Estado:</strong> {{ strtoupper((string)$pago->estado) }}</p>
        <p><strong>Referencia:</strong> {{ $pago->referencia_transaccion ?: 'N/A' }}</p>
        <p><strong>Aprobado/Revisado por:</strong> {{ $pago->aprobador?->name ?: 'N/A' }}</p>
        <p><strong>Fecha revision:</strong> {{ $pago->aprobado_en?->format('Y-m-d H:i') ?: 'N/A' }}</p>
      </div>

      <div class="grid gap-2 md:grid-cols-3">
        @if($pago->tieneOrdenCobro())
          <a href="{{ route('admin.pagos.orden.pdf', $pago) }}" class="btn btn-outline w-full" target="_blank" rel="noopener" data-action-lock-ignore data-skip-page-loader>Orden PDF</a>
          <a href="{{ route('pagos.token.show', $pago->token_publico) }}" class="btn btn-outline w-full" target="_blank" rel="noopener" data-action-lock-ignore data-skip-page-loader>Abrir token</a>
        @endif
        @if($pago->estado === 'pagado')
          <a href="{{ route('admin.pagos.recibo.pdf', $pago) }}" class="btn btn-outline w-full" target="_blank" rel="noopener" data-action-lock-ignore data-skip-page-loader>Recibo PDF</a>
        @endif
      </div>

      <form method="POST" action="{{ route('admin.pagos.monto.update', $pago) }}" class="grid gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4 md:grid-cols-3">
        @csrf
        <div>
          <label class="form-label" for="monto">Monto</label>
          <input id="monto" name="monto" type="number" step="0.01" min="0" value="{{ old('monto', $pago->monto) }}" class="form-input" required>
        </div>
        <div>
          <label class="form-label" for="moneda">Moneda</label>
          <input id="moneda" name="moneda" type="text" maxlength="3" value="{{ old('moneda', $pago->moneda) }}" class="form-input">
        </div>
        <div class="flex items-end">
          <button type="submit" class="btn btn-outline w-full">Actualizar monto</button>
        </div>
      </form>

      <form method="POST" action="{{ route('admin.pagos.metodo.update', $pago) }}" class="grid gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4 md:grid-cols-2">
        @csrf
        <div>
          <label class="form-label" for="metodo_pago">Metodo de pago</label>
          <select id="metodo_pago" name="metodo_pago" class="form-select" required>
            <option value="">Seleccione</option>
            <option value="efectivo" @selected(old('metodo_pago', $pago->metodo_pago) === 'efectivo')>Efectivo</option>
            <option value="transferencia" @selected(old('metodo_pago', $pago->metodo_pago) === 'transferencia')>Transferencia</option>
          </select>
        </div>
        <div>
          <label class="form-label" for="metodo_obs">Observacion</label>
          <textarea id="metodo_obs" name="observacion_admin" class="form-input" rows="2">{{ old('observacion_admin') }}</textarea>
          <p class="mt-1 text-xs text-gray-500">Si cambia un metodo ya definido, la observacion es obligatoria y queda en auditoria.</p>
        </div>
        <div class="md:col-span-2">
          <button type="submit" class="btn btn-outline w-full">Guardar metodo de pago</button>
        </div>
      </form>

      @if($pago->observacion_admin)
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700">
          <strong>Observacion administrativa:</strong> {{ $pago->observacion_admin }}
        </div>
      @endif
    </section>

    <section class="card p-6 space-y-4">
      <h2 class="text-lg font-semibold text-gray-900">Comprobante</h2>

      @if($pago->comprobante_path)
        @if($pago->comprobanteEsPdf())
          <a href="{{ route('admin.pagos.comprobante', $pago) }}" target="_blank" rel="noopener" class="btn btn-outline">
            Abrir PDF
          </a>
        @else
          <img src="{{ route('admin.pagos.comprobante', $pago) }}" alt="Comprobante del pago {{ $pago->id }}" class="w-full rounded-xl border border-gray-200 object-contain max-h-[420px]" loading="lazy" decoding="async">
        @endif
      @else
        <p class="text-sm text-gray-500">No se ha adjuntado comprobante.</p>
      @endif

      <hr class="border-gray-200">

      <h3 class="text-base font-semibold text-gray-900">Recibo de pago</h3>
      @if($pago->receipt)
        <p class="text-sm text-gray-700"><strong>Folio:</strong> {{ $pago->receipt->folio_recibo }}</p>
        <p class="text-sm text-gray-700"><strong>Emitido:</strong> {{ $pago->receipt->emitido_en?->format('Y-m-d H:i') }}</p>
        <p class="text-sm text-gray-700"><strong>Emisor:</strong> {{ $pago->receipt->emisor?->name ?: 'N/D' }}</p>
        <a href="{{ route('admin.pagos.recibo.pdf', $pago) }}" class="btn btn-outline" target="_blank" rel="noopener" data-action-lock-ignore data-skip-page-loader>Abrir recibo PDF</a>
      @else
        <p class="text-sm text-gray-500">Aún no se ha emitido recibo.</p>
      @endif
    </section>
  </div>

  <section class="card p-6">
    <h2 class="text-lg font-semibold text-gray-900">Acciones administrativas</h2>
    <p class="mt-1 text-sm text-gray-500">Rechazar y anular requieren observacion obligatoria.</p>

    @if($canReviewPayment)
      <div class="mt-4 grid gap-4 lg:grid-cols-3">
        @if(in_array('aprobar', $administrativeActions, true))
          <form method="POST" action="{{ route('admin.pagos.aprobar', $pago) }}" class="space-y-2 rounded-xl border border-gray-200 bg-gray-100 p-4" data-action-lock>
            @csrf
            <label class="form-label" for="approve_obs">Observacion (opcional)</label>
            <textarea id="approve_obs" name="observacion_admin" class="form-input" rows="3">{{ old('observacion_admin') }}</textarea>
            <button type="submit" class="btn btn-primary w-full">Aprobar pago</button>
          </form>
        @endif

        @if(in_array('rechazar', $administrativeActions, true))
          <form method="POST" action="{{ route('admin.pagos.rechazar', $pago) }}" class="space-y-2 rounded-xl border border-rose-200 bg-rose-50 p-4" data-action-lock>
            @csrf
            <label class="form-label" for="reject_obs">Observacion (obligatoria)</label>
            <textarea id="reject_obs" name="observacion_admin" class="form-input" rows="3" required>{{ old('observacion_admin') }}</textarea>
            <button type="submit" class="btn btn-danger w-full">Rechazar pago</button>
          </form>
        @endif

        @if(in_array('anular', $administrativeActions, true))
          <form method="POST" action="{{ route('admin.pagos.anular', $pago) }}" class="space-y-2 rounded-xl border border-gray-300 bg-gray-100 p-4" data-action-lock>
            @csrf
            <label class="form-label" for="void_obs">Motivo de anulacion</label>
            <textarea id="void_obs" name="observacion_admin" class="form-input" rows="3" required>{{ old('observacion_admin') }}</textarea>
            <button type="submit" class="btn btn-ghost w-full">Anular pago</button>
          </form>
        @endif
      </div>
    @else
      <div class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
        {{ $administrativeMessage }}
      </div>
    @endif
  </section>

  <section class="card p-6">
    <h2 class="text-lg font-semibold text-gray-900">Historial de estado de cobro</h2>
    <div class="mt-4 table-shell table-responsive-cards">
      <table class="table">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>De</th>
            <th>A</th>
            <th>Actor</th>
            <th>Rol</th>
            <th>Motivo</th>
          </tr>
        </thead>
        <tbody>
          @forelse($pago->statusLogs as $log)
            <tr>
              <td data-label="Fecha">{{ $log->created_at?->format('Y-m-d H:i') }}</td>
              <td data-label="De">{{ strtoupper((string)($log->estado_anterior ?: '-')) }}</td>
              <td data-label="A">{{ strtoupper((string)$log->estado_nuevo) }}</td>
              <td data-label="Actor">{{ $log->actor?->name ?: 'Sistema' }}</td>
              <td data-label="Rol">{{ $log->actor_rol ?: 'sistema' }}</td>
              <td data-label="Motivo">{{ $log->motivo ?: '-' }}</td>
            </tr>
          @empty
            <tr><td colspan="6">Sin historial registrado.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  <section class="card p-6">
    <h2 class="text-lg font-semibold text-gray-900">Historial de recibo</h2>
    <div class="mt-4 table-shell table-responsive-cards">
      <table class="table">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>De</th>
            <th>A</th>
            <th>Actor</th>
            <th>Rol</th>
            <th>Motivo</th>
          </tr>
        </thead>
        <tbody>
          @forelse(($pago->receipt?->logs ?? collect()) as $log)
            <tr>
              <td data-label="Fecha">{{ $log->created_at?->format('Y-m-d H:i') }}</td>
              <td data-label="De">{{ strtoupper((string)($log->estado_anterior ?: '-')) }}</td>
              <td data-label="A">{{ strtoupper((string)$log->estado_nuevo) }}</td>
              <td data-label="Actor">{{ $log->actor?->name ?: 'Sistema' }}</td>
              <td data-label="Rol">{{ $log->actor_rol ?: 'sistema' }}</td>
              <td data-label="Motivo">{{ $log->motivo ?: '-' }}</td>
            </tr>
          @empty
            <tr><td colspan="6">Sin historial de recibo.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
</div>
@endsection
