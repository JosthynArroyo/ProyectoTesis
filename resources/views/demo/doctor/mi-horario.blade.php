@extends('layouts.demo')
@section('title', 'Mi horario | Demo')
@section('header-title', 'Mi horario')
@section('header-subtitle', 'Bloques de atencion del doctor demo')

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
                        <th>Dia</th>
                        <th>Horario</th>
                        <th>Cupos</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($scheduleBlocks as $block)
                        <tr>
                            <td data-label="Dia">{{ $block['day'] }}</td>
                            <td data-label="Horario">{{ $block['range'] }}</td>
                            <td data-label="Cupos">{{ $block['slots'] }}</td>
                            <td data-label="Estado">{{ $block['state'] }}</td>
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
