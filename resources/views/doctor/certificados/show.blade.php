@extends('layouts.doctor')
@section('title', 'Certificado medico')
@section('activeSidebar', 'citas')
@section('header-title','Certificado medico')
@section('header-subtitle','Documento emitido asociado a la cita')

@section('main')
<div class="space-y-6">
  @if (session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif
  @if (session('info'))
    <x-ui.alert tone="warning">{{ session('info') }}</x-ui.alert>
  @endif

  @include('certificados._detalle', ['certificado' => $certificado])

  <section class="card p-6">
    <x-ui.form-actions>
      <x-slot:left>
        <a href="{{ route('doctor.citas') }}" class="btn btn-ghost">Volver a citas</a>
      </x-slot>
      @if(in_array($certificado->envio_estado, ['failed', 'queued', 'sending'], true))
        <form method="POST" action="{{ route('doctor.certificados.resend', $certificado) }}">
          @csrf
          <button type="submit" class="btn btn-outline">
            <i class="ri-mail-send-line"></i> Reenviar por correo
          </button>
        </form>
      @endif
      <a href="{{ route('doctor.certificados.download', $certificado) }}" class="btn btn-primary" download data-action-lock-ignore data-skip-page-loader>
        <i class="ri-download-2-line"></i> Descargar certificado
      </a>
    </x-ui.form-actions>
  </section>
</div>
@endsection
