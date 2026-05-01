@extends('layouts.demo')
@section('title', 'Agendar cita | Demo')
@section('header-title', 'Agendar cita')
@section('header-subtitle', 'Replica del flujo de reserva del paciente')

@section('sidebar')
    @include('demo.partials.sidebar-paciente-demo')
@endsection

@section('main')
<div class="space-y-6">
    <section class="card p-6">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label class="form-label">Especialidad</label>
                <input class="form-input" type="text" value="Cardiologia" readonly>
            </div>
            <div>
                <label class="form-label">Doctor</label>
                <input class="form-input" type="text" value="Dra. Maria Gonzalez" readonly>
            </div>
            <div>
                <label class="form-label">Fecha</label>
                <input class="form-input" type="text" value="30/04/2026" readonly>
            </div>
            <div>
                <label class="form-label">Hora</label>
                <input class="form-input" type="text" value="10:00" readonly>
            </div>
        </div>
        <div class="mt-6 rounded-2xl border border-gray-200 bg-gray-50 p-4">
            <p class="text-xs uppercase tracking-widest text-gray-500">Motivo de consulta</p>
            <p class="mt-2 text-sm text-gray-700">Control preventivo y seguimiento de presion arterial.</p>
        </div>
        <div class="mt-6 flex gap-3">
            <button class="btn btn-primary demo-action-blocked">Confirmar cita</button>
            <button class="btn btn-outline demo-action-blocked">Ver disponibilidad</button>
        </div>
    </section>
</div>
@endsection
