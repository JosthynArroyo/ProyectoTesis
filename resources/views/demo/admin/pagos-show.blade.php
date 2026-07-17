@extends('layouts.demo')
@section('title', 'Detalle de cobro - Demo')
@section('header-title', 'Cobro de cita #'.($pago->cita_id ?? '100'))
@section('header-subtitle', 'Pago #'.$pago->id.' | Validación, orden y recibo (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-admin-demo')
@endsection

@section('main')
<div class="space-y-6">
  <div class="panel-action-bar">
    <a href="{{ route('demo.admin.pagos.index') }}" class="btn btn-outline">Volver</a>
  </div>

  @if(session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  <div class="grid gap-6 lg:grid-cols-2">
    <section class="card p-6 space-y-4">
      <h2 class="text-lg font-semibold text-gray-900">Datos del cobro</h2>
      <div class="space-y-2 break-words text-sm text-gray-700">
        <p><strong>Folio orden:</strong> {{ $pago->folio_unico ?? 'FOL-2026-1001' }}</p>
        <p><strong>Token público:</strong> {{ $pago->token_publico ?? 'N/D' }}</p>
        <p><strong>Paciente:</strong> {{ $pago->patient ?? 'Maria Fernanda Vega' }}</p>
        <p><strong>Correo:</strong> maria.fernanda@example.com</p>
        <p><strong>Monto:</strong> {{ $pago->amount ?? $pago->monto }}</p>
        <p><strong>Método:</strong> {{ $pago->method ?? $pago->metodo_pago }}</p>
        <p><strong>Estado:</strong> {{ strtoupper($pago->status ?? $pago->estado) }}</p>
      </div>

      <div class="grid gap-2 md:grid-cols-3">
        <button class="btn btn-outline w-full demo-action-blocked">Orden PDF</button>
        <button class="btn btn-outline w-full demo-action-blocked">Abrir token</button>
      </div>

      <form method="POST" action="{{ route('demo.admin.pagos.monto.update', $pago->id) }}" class="grid gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4 md:grid-cols-3">
        @csrf
        <div>
          <label class="form-label" for="monto">Monto</label>
          <input id="monto" name="monto" type="number" step="0.01" min="0" value="35.00" class="form-input" required>
        </div>
        <div>
          <label class="form-label" for="moneda">Moneda</label>
          <input id="moneda" name="moneda" type="text" maxlength="3" value="USD" class="form-input">
        </div>
        <div class="flex items-end">
          <button type="submit" class="btn btn-outline w-full">Actualizar monto</button>
        </div>
      </form>

      <form method="POST" action="{{ route('demo.admin.pagos.metodo.update', $pago->id) }}" class="grid gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4 md:grid-cols-2">
        @csrf
        <div>
          <label class="form-label" for="metodo_pago">Método de pago</label>
          <select id="metodo_pago" name="metodo_pago" class="form-select" required>
            <option value="transferencia" selected>Transferencia</option>
            <option value="efectivo">Efectivo</option>
          </select>
        </div>
        <div>
          <label class="form-label" for="metodo_obs">Observación</label>
          <textarea id="metodo_obs" name="observacion_admin" class="form-input" rows="2"></textarea>
        </div>
        <div class="md:col-span-2">
          <button type="submit" class="btn btn-outline w-full">Guardar método de pago</button>
        </div>
      </form>
    </section>

    <section class="card p-6 space-y-4">
      <h2 class="text-lg font-semibold text-gray-900">Comprobante</h2>
      <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-center text-sm text-gray-500">
        <i class="ri-image-line text-4xl block mb-2 text-gray-400"></i>
        Vista simulada de comprobante de transferencia bancaria.
      </div>

      <hr class="border-gray-200">

      <h3 class="text-base font-semibold text-gray-900">Recibo de pago</h3>
      <p class="text-sm text-gray-500">Aún no se ha emitido recibo.</p>
    </section>
  </div>

  <section class="card p-6">
    <h2 class="text-lg font-semibold text-gray-900">Acciones administrativas</h2>
    <p class="mt-1 text-sm text-gray-500">Rechazar y anular requieren observación obligatoria.</p>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
      <form method="POST" action="{{ route('demo.admin.pagos.aprobar', $pago->id) }}" class="space-y-2 rounded-xl border border-gray-200 bg-gray-100 p-4">
        @csrf
        <label class="form-label" for="approve_obs">Observación (opcional)</label>
        <textarea id="approve_obs" name="observacion_admin" class="form-input" rows="3"></textarea>
        <button type="submit" class="btn btn-primary w-full">Aprobar pago</button>
      </form>

      <form method="POST" action="{{ route('demo.admin.pagos.rechazar', $pago->id) }}" class="space-y-2 rounded-xl border border-rose-200 bg-rose-50 p-4">
        @csrf
        <label class="form-label" for="reject_obs">Observación (obligatoria)</label>
        <textarea id="reject_obs" name="observacion_admin" class="form-input" rows="3" required></textarea>
        <button type="submit" class="btn btn-danger w-full">Rechazar pago</button>
      </form>

      <form method="POST" action="{{ route('demo.admin.pagos.anular', $pago->id) }}" class="space-y-2 rounded-xl border border-gray-300 bg-gray-100 p-4">
        @csrf
        <label class="form-label" for="void_obs">Motivo de anulación</label>
        <textarea id="void_obs" name="observacion_admin" class="form-input" rows="3" required></textarea>
        <button type="submit" class="btn btn-ghost w-full">Anular pago</button>
      </form>
    </div>
  </section>
</div>
@endsection
