@extends('layouts.demo')
@section('title', 'Historial de recetas | Demo')
@section('header-title', 'Historial de recetas')
@section('header-subtitle', 'Descarga y consulta de recetas emitidas')

@section('sidebar')
    @include('demo.partials.sidebar-doctor-demo')
@endsection

@section('main')
<div class="space-y-6">
    <section class="card p-0">
        <div class="table-shell table-responsive-cards">
            <table class="table">
                <thead>
                    <tr>
                        <th>Paciente</th>
                        <th>Especialidad</th>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>PDF</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($prescriptions as $prescription)
                        <tr>
                            <td data-label="Paciente">{{ $prescription['patient'] }}</td>
                            <td data-label="Especialidad">{{ $prescription['specialty'] }}</td>
                            <td data-label="Fecha">{{ $prescription['date'] }}</td>
                            <td data-label="Hora">{{ $prescription['time'] }}</td>
                            <td data-label="PDF">
                                <button class="btn btn-outline btn-sm demo-action-blocked"><i class="ri-download-2-line"></i> {{ $prescription['pdf'] }}</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
