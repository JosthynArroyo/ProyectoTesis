@extends($esAdmin ? 'layouts.admin' : 'layouts.paciente')
@section('title', 'Consulta de cobro')
@section('header-title', 'Consulta de cobro')
@section('header-subtitle', 'Orden localizada por token QR')

@section('main')
<div class="space-y-6">
  <section class="card p-6">
    <p class="text-xs uppercase tracking-widest text-slate-500">Orden de cobro</p>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">Folio {{ $pago->folio_unico ?: 'SIN FOLIO' }}</h1>
    <p class="mt-1 text-sm text-slate-600">Token: {{ $pago->token_publico }}</p>
  </section>

  <section class="card p-6">
    <div class="grid gap-4 md:grid-cols-2">
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Paciente</p>
        <p class="text-sm font-semibold text-slate-900">{{ $pago->paciente?->name ?? 'N/D' }}</p>
        <p class="text-xs text-slate-500">{{ $pago->paciente?->dni ?? 'N/D' }}</p>
      </div>
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Estado</p>
        <p class="text-sm font-semibold text-slate-900">{{ strtoupper((string) $pago->estado) }}</p>
      </div>
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Monto</p>
        <p class="text-sm font-semibold text-slate-900">{{ number_format((float) $pago->monto, 2) }} {{ $pago->moneda }}</p>
      </div>
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Metodo</p>
        <p class="text-sm font-semibold text-slate-900">{{ $pago->metodo_pago ? strtoupper((string) $pago->metodo_pago) : 'SIN DEFINIR' }}</p>
      </div>
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Cita</p>
        <p class="text-sm font-semibold text-slate-900">#{{ $pago->cita_id }}</p>
        <p class="text-xs text-slate-500">
          {{ $pago->cita?->fecha?->format('Y-m-d') ?? 'N/D' }}
          {{ $pago->cita?->hora ? substr((string) $pago->cita->hora, 0, 5) : '' }}
        </p>
      </div>
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Recibo</p>
        <p class="text-sm font-semibold text-slate-900">{{ $pago->receipt?->folio_recibo ?: 'No emitido' }}</p>
      </div>
    </div>

    <div class="mt-6 flex flex-wrap gap-2">
      @if($esAdmin)
        <a href="{{ route('admin.pagos.show', $pago) }}" class="btn btn-primary">Gestionar cobro</a>
        <a href="{{ route('admin.pagos.orden.pdf', $pago) }}" class="btn btn-outline" target="_blank" rel="noopener">Descargar orden</a>
        @if($pago->estado === 'pagado')
          <a href="{{ route('admin.pagos.recibo.pdf', $pago) }}" class="btn btn-outline" target="_blank" rel="noopener">Descargar recibo</a>
        @endif
      @elseif($esPacientePropietario)
        <a href="{{ route('paciente.pagos.index') }}" class="btn btn-primary">Volver a mis pagos</a>
        @if($pago->tieneOrdenCobro())
          <a href="{{ route('paciente.pagos.orden.pdf', $pago) }}" class="btn btn-outline" target="_blank" rel="noopener">Descargar orden</a>
        @endif
        @if($pago->estado === 'pagado' && $pago->receipt)
          <a href="{{ route('paciente.pagos.recibo.pdf', $pago) }}" class="btn btn-outline" target="_blank" rel="noopener">Descargar recibo</a>
        @endif
      @endif
    </div>
  </section>
</div>
@endsection

