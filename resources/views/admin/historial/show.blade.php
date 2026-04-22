@extends('layouts.admin')
@section('title', 'Nota clínica')
@section('header-title','Nota clínica firmada')
@section('header-subtitle','Detalle de una atención médica individual')

@section('main')
  <div class="space-y-6">
    @include('soap.nota', ['nota' => $nota])

    <section class="card p-6">
      <x-ui.form-actions>
        <x-slot:left>
          <a href="{{ $backUrl ?? route('admin.historial.index') }}" class="btn btn-ghost">
            <i class="ri-arrow-left-line"></i> {{ $backLabel ?? 'Volver' }}
          </a>
        </x-slot>
      </x-ui.form-actions>
    </section>
  </div>
@endsection
