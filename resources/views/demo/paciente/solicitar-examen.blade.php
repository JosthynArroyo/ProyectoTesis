@extends('layouts.demo')
@section('title', 'Solicitar examen | Demo')
@section('header-title', 'Solicitar examen')
@section('header-subtitle', 'Formulario demo para laboratorio')

@section('sidebar')
    @include('demo.partials.sidebar-paciente-demo')
@endsection

@section('main')
<div class="space-y-6">
    <section class="card p-6">
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="form-label">Tipo de examen</label>
                <input class="form-input" type="text" value="Perfil lipidico" readonly>
            </div>
            <div>
                <label class="form-label">Origen</label>
                <input class="form-input" type="text" value="Solicitud particular" readonly>
            </div>
            <div class="md:col-span-2">
                <label class="form-label">Indicaciones</label>
                <textarea class="form-input min-h-[120px]" readonly>Examen de rutina solicitado por el paciente demo.</textarea>
            </div>
        </div>
        <div class="mt-6 flex gap-3">
            <button class="btn btn-primary demo-action-blocked">Enviar solicitud</button>
            <button class="btn btn-outline demo-action-blocked">Adjuntar orden</button>
        </div>
    </section>
</div>
@endsection
