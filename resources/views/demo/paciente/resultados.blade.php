@extends('layouts.demo')
@section('title', 'Resultados | Demo')
@section('header-title', 'Resultados')
@section('header-subtitle', 'Reportes de laboratorio publicados')

@section('sidebar')
    @include('demo.partials.sidebar-paciente-demo')
@endsection

@section('main')
<div class="space-y-6">
    <section class="card p-0">
        <div class="table-shell table-responsive-cards">
            <table class="table">
                <thead>
                    <tr>
                        <th>Codigo</th>
                        <th>Examen</th>
                        <th>Doctor</th>
                        <th>Entrega</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($results as $result)
                        <tr>
                            <td data-label="Codigo">{{ $result['code'] }}</td>
                            <td data-label="Examen">{{ $result['exam'] }}</td>
                            <td data-label="Doctor">{{ $result['doctor'] }}</td>
                            <td data-label="Entrega">{{ $result['delivered_at'] }}</td>
                            <td data-label="Estado"><span class="badge {{ $result['status_tone'] }}">{{ $result['status'] }}</span></td>
                            <td data-label="Acciones">
                                <button class="btn btn-outline btn-sm demo-action-blocked">Descargar</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
