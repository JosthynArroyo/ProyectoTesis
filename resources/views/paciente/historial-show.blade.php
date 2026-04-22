@extends('layouts.paciente')
@section('title', 'Nota clinica')
@section('header-title','Nota clinica firmada')
@section('header-subtitle','Detalle de una atencion medica individual')

@section('main')
  <div class="space-y-6">
    @include('soap.nota', ['nota' => $nota])

    <section class="card p-6">
      <x-ui.form-actions>
        <x-slot:left>
          <a href="{{ route('paciente.historial') }}" class="btn btn-ghost">
            <i class="ri-arrow-left-line"></i> Volver
          </a>
        </x-slot>
      </x-ui.form-actions>
    </section>
  </div>
@endsection
