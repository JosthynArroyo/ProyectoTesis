@extends('layouts.demo')
@section('title', 'Horarios laboratorio | Demo')
@section('header-title', 'Horarios')
@section('header-subtitle', 'Bloques del laboratorio y capacidad de recepcion')

@section('sidebar')
    @include('demo.partials.sidebar-laboratorio-demo')
@endsection

@section('main')
<div class="space-y-6">
    <section class="card p-0">
        <div class="table-shell table-responsive-cards">
            <table class="table">
                <thead>
                    <tr>
                        <th>Dia</th>
                        <th>Horario</th>
                        <th>Operacion</th>
                        <th>Capacidad</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($scheduleBlocks as $block)
                        <tr>
                            <td data-label="Dia">{{ $block['day'] }}</td>
                            <td data-label="Horario">{{ $block['range'] }}</td>
                            <td data-label="Operacion">{{ $block['state'] }}</td>
                            <td data-label="Capacidad">{{ $block['slots'] }}</td>
                            <td data-label="Acciones">
                                <button class="btn btn-outline btn-sm demo-action-blocked">Editar</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
