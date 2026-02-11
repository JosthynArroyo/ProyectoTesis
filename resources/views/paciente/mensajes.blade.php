@extends('layouts.paciente')
@section('title', 'Mensajes')
@section('header-title','Mensajes')
@section('header-subtitle','Notificaciones y comunicaciones')

@section('main')
  <div class="space-y-6">
    <section class="card p-6">
      <p class="text-xs uppercase tracking-widest text-slate-500">Mensajes</p>
      <h1 class="mt-2 text-2xl font-semibold text-slate-900">Centro de mensajes</h1>
      <p class="text-slate-600">Aquí veras recordatorios, respuestas y avisos del equipo médico.</p>
    </section>

    <section class="card p-6">
      <x-ui.empty-state
        title="Sin mensajes por ahora"
        message="Mantente atento a este espacio para recibir notificaciones importantes."
      />
    </section>
  </div>
@endsection