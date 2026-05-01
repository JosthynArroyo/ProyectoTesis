@extends('layouts.demo')
@section('title', 'Gestion de pagos | Demo')
@section('header-title', 'Gestion de pagos')
@section('header-subtitle', 'Control de cobros por cita')

@section('sidebar')
    @include('demo.partials.sidebar-admin-demo')
@endsection

@section('main')
<div class="space-y-6">
    <section class="card p-6">
        <div class="page-header">
            <div class="page-header__info">
                <h2>Filtros de cobro</h2>
                <p>Replica visual del modulo financiero con busqueda y estados.</p>
            </div>
        </div>
        <div class="mt-4 grid gap-3 md:grid-cols-5">
            <input type="text" class="form-input md:col-span-2" placeholder="Paciente, correo o folio" readonly>
            <input type="text" class="form-input" placeholder="Estado" readonly>
            <input type="text" class="form-input" placeholder="Desde" readonly>
            <input type="text" class="form-input" placeholder="Hasta" readonly>
        </div>
    </section>

    <section class="card p-6">
        <div class="mb-4 flex flex-wrap gap-2 text-xs">
            <span class="badge warning">Pendiente: {{ $paymentTotals['pendiente'] }}</span>
            <span class="badge info">En verificacion: {{ $paymentTotals['en_verificacion'] }}</span>
            <span class="badge danger">Rechazado: {{ $paymentTotals['rechazado'] }}</span>
            <span class="badge success">Pagado: {{ $paymentTotals['pagado'] }}</span>
            <span class="badge neutral">Anulado: {{ $paymentTotals['anulado'] }}</span>
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
                        <th>Metodo</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payments as $payment)
                        <tr>
                            <td data-label="Pago">{{ $payment['id'] }}</td>
                            <td data-label="Folio">{{ $payment['folio'] }}</td>
                            <td data-label="Paciente">{{ $payment['patient'] }}</td>
                            <td data-label="Cita">{{ $payment['appointment'] }}</td>
                            <td data-label="Monto">{{ $payment['amount'] }}</td>
                            <td data-label="Metodo">{{ $payment['method'] }}</td>
                            <td data-label="Estado"><span class="badge {{ $payment['status_tone'] }}">{{ $payment['status'] }}</span></td>
                            <td data-label="Acciones">
                                <div class="table-actions">
                                    <button class="btn btn-outline btn-sm demo-action-blocked">Ver detalle</button>
                                    <button class="btn btn-ghost btn-sm demo-action-blocked">Aprobar</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
