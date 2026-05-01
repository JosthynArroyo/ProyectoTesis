@extends('layouts.demo')
@section('title', 'Ordenes de cobro | Demo')
@section('header-title', 'Ordenes de cobro')
@section('header-subtitle', 'Estado de pagos y comprobantes simulados')

@section('sidebar')
    @include('demo.partials.sidebar-paciente-demo')
@endsection

@section('main')
<div class="space-y-6">
    <section class="stat-grid">
        <x-ui.stat label="Total" :value="$totalPagos" tone="slate">
            <x-slot:icon><i class="ri-wallet-3-line"></i></x-slot:icon>
        </x-ui.stat>
        <x-ui.stat label="Pendientes" :value="$pagosPendientes" tone="amber">
            <x-slot:icon><i class="ri-time-line"></i></x-slot:icon>
        </x-ui.stat>
        <x-ui.stat label="Pagadas" :value="$pagosPagados" tone="teal">
            <x-slot:icon><i class="ri-checkbox-circle-line"></i></x-slot:icon>
        </x-ui.stat>
    </section>

    <section class="card p-0">
        <div class="table-shell table-responsive-cards">
            <table class="table">
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Descripcion</th>
                        <th>Monto</th>
                        <th>Metodo</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payments as $payment)
                        <tr>
                            <td data-label="Folio">{{ $payment['folio'] }}</td>
                            <td data-label="Descripcion">{{ $payment['description'] }}</td>
                            <td data-label="Monto">{{ $payment['amount'] }}</td>
                            <td data-label="Metodo">{{ $payment['method'] }}</td>
                            <td data-label="Estado"><span class="badge {{ $payment['status_tone'] }}">{{ $payment['status'] }}</span></td>
                            <td data-label="Fecha">{{ $payment['date'] }}</td>
                            <td data-label="Acciones">
                                <div class="table-actions">
                                    <button class="btn btn-outline btn-sm demo-action-blocked">Ver detalle</button>
                                    <button class="btn btn-primary btn-sm demo-action-blocked">Subir comprobante</button>
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
