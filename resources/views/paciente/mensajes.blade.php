@extends('layouts.paciente')
@section('title', 'Mensajes')
@section('header-title','Mensajes')
@section('header-subtitle','Notificaciones y comunicaciones')

@section('main')
  <div class="space-y-6">
    <section class="card p-6">
      <x-ui.empty-state
        title="Sin mensajes por ahora"
        message="Mantente atento a este espacio para recibir notificaciones importantes."
      />
    </section>
  </div>
@endsection
