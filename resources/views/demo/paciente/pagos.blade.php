@extends('layouts.demo')
@section('title', 'Órdenes de cobro - Demo')
@section('header-title', 'Órdenes de cobro')
@section('header-subtitle', 'Control y estado de cobros por cita (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-paciente-demo')
@endsection

@php
  $pagos = collect($payments)->map(function($p) {
      $pago = new \App\Models\Pago();
      $pago->id = $p['id'];
      $pago->cita_id = $p['id'];
      $pago->estado = match (strtolower($p['status'])) {
          'pagada' => 'pagado',
          'pendiente' => 'pendiente',
          'verificacion' => 'en_verificacion',
          'rechazada' => 'rechazado',
          default => 'pendiente',
      };
      $pago->metodo_pago = strtolower($p['method']) === 'n/a' ? '' : strtolower($p['method']);
      $pago->monto = (float) str_replace(['$', ' USD', ' '], '', $p['amount']);
      $pago->moneda = 'USD';
      $pago->folio_unico = $p['folio'];
      $pago->observacion_admin = $pago->estado === 'rechazado' ? 'El comprobante cargado es borroso o inválido.' : null;
      
      $cita = new \App\Models\Cita();
      $cita->id = $p['id'];
      $cita->fecha = \Carbon\Carbon::parse(str_replace('/', '-', $p['date']));
      $cita->hora = '09:00';
      $cita->setRelation('doctor', new \App\Models\User(['name' => 'Dra. Sofía Cárdenas']));
      $cita->setRelation('especialidad', new \App\Models\Especialidad(['nombre' => 'Pediatría']));
      
      $pago->setRelation('cita', $cita);
      return $pago;
  });
@endphp

@section('main')
<div class="space-y-6">
  <div class="panel-action-bar">
    <a href="{{ route('demo.paciente.citas') }}" class="btn btn-outline">Ver citas</a>
  </div>

  @if(session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  <section class="card p-6 bg-white">
    <form method="GET" action="{{ route('demo.paciente.pagos.index') }}" class="flex flex-wrap items-end gap-3">
      <div>
        <label class="form-label" for="estado">Estado</label>
        <select id="estado" name="estado" class="form-select">
          <option value="all" selected>Todos</option>
          <option value="pendiente">Pendiente</option>
          <option value="en_verificacion">En verificación</option>
          <option value="rechazado">Rechazado</option>
          <option value="pagado">Pagado</option>
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
          default => ucfirst((string) $pago->estado),
        };
        $estadoTone = match($pago->estado) {
          'pagado' => 'success',
          'rechazado' => 'danger',
          'en_verificacion' => 'info',
          default => 'warning',
        };

        $metodoActual = old('metodo_pago', $pago->metodo_pago);
        $esTransferencia = $metodoActual === 'transferencia';
        $esEditable = !in_array($pago->estado, ['pagado', 'anulado']);
      @endphp
      <article id="pago-{{ $pago->id }}" class="card p-5 bg-white">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h3 class="text-base font-semibold text-gray-900">Cita #{{ $pago->cita_id }}</h3>
            <p class="text-sm text-gray-500">
              {{ optional($pago->cita?->doctor)->name }} · {{ optional($pago->cita?->especialidad)->nombre }}
            </p>
            <p class="text-xs text-gray-500">
              {{ optional($pago->cita?->fecha)->format('Y-m-d') }} {{ $pago->cita?->hora }}
            </p>
            <p class="mt-1 text-xs text-gray-500">
              Folio orden: {{ $pago->folio_unico }}
            </p>
          </div>
          <div class="text-right">
            <p class="text-sm font-semibold text-gray-900">{{ number_format((float)$pago->monto, 2) }} {{ $pago->moneda }}</p>
            <span class="badge {{ $estadoTone }}">{{ $estadoLabel }}</span>
          </div>
        </div>

        <div class="mt-3 flex flex-wrap gap-2">
          <button class="btn btn-outline btn-sm demo-action-blocked">Descargar orden</button>
          @if($pago->estado === 'pagado')
            <button class="btn btn-outline btn-sm demo-action-blocked">Descargar recibo</button>
          @endif
        </div>

        @if($pago->observacion_admin)
          <p class="mt-3 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700 font-medium">
            <strong>Observación administrativa:</strong> {{ $pago->observacion_admin }}
          </p>
        @endif

        @if($esEditable)
          <form method="POST" action="{{ route('demo.paciente.pagos.submit', $pago->id) }}" enctype="multipart/form-data" class="mt-4 grid gap-3 md:grid-cols-2">
            @csrf
            <div>
              <label class="form-label" for="metodo_pago_{{ $pago->id }}">Método de pago</label>
              <select id="metodo_pago_{{ $pago->id }}" name="metodo_pago" class="form-select" required onchange="const wrap = this.form.querySelector('.comprobante-wrapper'); if (this.value === 'transferencia') { wrap.classList.remove('hidden'); } else { wrap.classList.add('hidden'); }">
                <option value="">Seleccione</option>
                <option value="efectivo" @selected($metodoActual === 'efectivo')>Efectivo (Pagar en clínica)</option>
                <option value="transferencia" @selected($metodoActual === 'transferencia')>Transferencia bancaria</option>
              </select>
            </div>

            <div>
              <label class="form-label" for="referencia_transaccion_{{ $pago->id }}">Referencia (opcional)</label>
              <input id="referencia_transaccion_{{ $pago->id }}" type="text" name="referencia_transaccion" value="" class="form-input">
            </div>

            <div class="md:col-span-2 comprobante-wrapper {{ $esTransferencia ? '' : 'hidden' }}">
              <label class="form-label" for="comprobante_{{ $pago->id }}">Comprobante (JPG, PNG, PDF)</label>
              <input id="comprobante_{{ $pago->id }}" type="file" name="comprobante" class="form-input" accept=".jpg,.jpeg,.png,.pdf">
            </div>

            <div class="md:col-span-2 mt-2">
              <button type="submit" class="btn btn-primary">
                Confirmar registro de pago
              </button>
            </div>
          </form>
        @else
          <p class="mt-4 text-sm text-gray-500">Este pago ya ha sido procesado o anulado.</p>
        @endif
      </article>
    @empty
      <x-ui.empty-state title="No hay órdenes de cobro para mostrar.">
        <div class="mt-4 flex flex-wrap justify-center gap-3">
          <a class="btn btn-primary" href="{{ route('demo.paciente.citas') }}">Ver mis citas</a>
        </div>
      </x-ui.empty-state>
    @endforelse
  </section>
</div>
@endsection
