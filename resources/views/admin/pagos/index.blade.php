@extends('layouts.admin')
@section('title', 'Gestión de pagos')
@section('header-title', 'Gestión de pagos')
@section('header-subtitle', 'Control de cobros por cita')

@section('main')
<div class="space-y-6">
  <section class="card p-6">
    <div class="page-header">
      <div class="page-header__info">
        <p class="text-xs uppercase tracking-widest text-slate-500">Administración</p>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Gestión de cobros</h1>
        <p class="text-slate-600">Filtre por estado, fecha, folio o datos del paciente para revisar órdenes de cobro.</p>
      </div>
      <div class="page-header__actions">
        <a href="{{ route('admin.citas.override.create') }}" class="btn btn-outline">Agendar con override</a>
      </div>
    </div>
  </section>

  @if(session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif
  @if($errors->any())
    <x-ui.alert tone="error">{{ $errors->first() }}</x-ui.alert>
  @endif

  <section class="card p-6">
    <form method="GET" action="{{ url()->current() }}" class="grid gap-3 md:grid-cols-5">
      <div class="md:col-span-2">
        <label class="form-label" for="q">Paciente / cédula / correo / folio</label>
        <input id="q" type="search" name="q" value="{{ $buscar }}" class="form-input" placeholder="Buscar por paciente o folio">
      </div>

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

      <div>
        <label class="form-label" for="desde">Desde</label>
        <input id="desde" type="date" name="desde" value="{{ $desde }}" class="form-input">
      </div>

      <div>
        <label class="form-label" for="hasta">Hasta</label>
        <input id="hasta" type="date" name="hasta" value="{{ $hasta }}" class="form-input">
      </div>

      <div class="md:col-span-5 flex items-center gap-2">
        <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
        <a href="{{ route('admin.pagos.index') }}" class="btn btn-ghost btn-sm">Limpiar</a>
      </div>
    </form>
  </section>

  <section class="card p-6">
    <div class="mb-4 flex flex-wrap gap-2 text-xs">
      <span class="badge warning">Pendiente: {{ $totales['pendiente'] ?? 0 }}</span>
      <span class="badge info">En verificación: {{ $totales['en_verificacion'] ?? 0 }}</span>
      <span class="badge danger">Rechazado: {{ $totales['rechazado'] ?? 0 }}</span>
      <span class="badge success">Pagado: {{ $totales['pagado'] ?? 0 }}</span>
      <span class="badge neutral">Anulado: {{ $totales['anulado'] ?? 0 }}</span>
    </div>

    <div class="table-shell table-responsive-cards">
      <table class="table">
        <thead>
          <tr>
            <th>Pago</th>
            <th>Folio</th>
            <th>Paciente</th>
            <th>Cita</th>
            <th>Monto</th>
            <th>Método</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
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
              $metodo = $pago->metodo_pago ? strtoupper((string) $pago->metodo_pago) : 'SIN DEFINIR';
            @endphp
            <tr>
              <td data-label="Pago">#{{ $pago->id }}</td>
              <td data-label="Folio">
                <div class="text-xs font-semibold text-slate-900">{{ $pago->folio_unico ?: 'SIN FOLIO' }}</div>
                @if($pago->token_publico)
                  <a href="{{ route('pagos.token.show', $pago->token_publico) }}" class="text-xs text-sky-700 hover:underline">Abrir por token</a>
                @endif
              </td>
              <td data-label="Paciente">
                <div class="text-sm font-semibold text-slate-900">{{ $pago->paciente?->name ?? 'N/D' }}</div>
                <div class="text-xs text-slate-500">{{ $pago->paciente?->dni }} · {{ $pago->paciente?->email }}</div>
              </td>
              <td data-label="Cita">
                #{{ $pago->cita_id }}<br>
                <span class="text-xs text-slate-500">
                  {{ optional($pago->cita?->fecha)->format('Y-m-d') }} {{ $pago->cita?->hora ? substr((string)$pago->cita->hora, 0, 5) : '' }}
                </span>
              </td>
              <td data-label="Monto">{{ number_format((float)$pago->monto, 2) }} {{ $pago->moneda }}</td>
              <td data-label="Método">{{ $metodo }}</td>
              <td data-label="Estado"><span class="badge {{ $estadoTone }}">{{ $estadoLabel }}</span></td>
              <td data-label="Acciones">
                <a href="{{ route('admin.pagos.show', $pago) }}" class="btn btn-outline btn-sm">Ver detalle</a>
              </td>
            </tr>
          @empty
            <tr><td colspan="8">No hay pagos para mostrar con el filtro aplicado.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="mt-5">
      {{ $pagos->links() }}
    </div>
  </section>
</div>
@endsection
