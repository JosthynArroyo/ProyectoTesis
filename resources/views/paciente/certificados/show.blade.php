@extends('layouts.paciente')
@section('title', 'Certificado medico')
@section('header-title','Certificado medico')
@section('header-subtitle','Documento emitido por tu doctor tratante')

@section('main')
<div class="space-y-6">
  @include('certificados._detalle', ['certificado' => $certificado])

  <section class="card p-6">
    <x-ui.form-actions>
      <x-slot:left>
        <a href="{{ route('paciente.historial') }}" class="btn btn-ghost">Volver al historial</a>
      </x-slot>
      <a href="{{ route('paciente.certificados.download', $certificado) }}" class="btn btn-primary" download data-action-lock-ignore data-skip-page-loader>
        <i class="ri-download-2-line"></i> Descargar certificado
      </a>
    </x-ui.form-actions>
  </section>
</div>
@endsection
