@extends('layouts.demo')
@section('title', 'Detalle de mensaje | Demo')
@section('header-title', 'Mensaje de contacto #' . $mensaje->id)
@section('header-subtitle', 'Solicitud recibida desde el formulario público (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-admin-demo')
@endsection

@section('main')
<div class="space-y-6">
  <div class="panel-action-bar panel-action-bar--between">
    <div class="panel-action-bar__meta">
      Recibido el {{ $mensaje->received_at ?? '-' }}
    </div>
    <div class="panel-action-bar__actions">
      <a href="{{ route('demo.admin.notificaciones-contacto') }}" class="btn btn-outline">
        <i class="ri-arrow-left-line"></i> Volver
      </a>
    </div>
  </div>

  <section class="card p-6">
    <div class="grid gap-4 md:grid-cols-2">
      <div>
        <p class="text-xs uppercase tracking-wide text-gray-400">Nombre</p>
        <p class="mt-1 font-semibold text-gray-900">{{ $mensaje->name ?? '-' }}</p>
      </div>
      <div>
        <p class="text-xs uppercase tracking-wide text-gray-400">Correo</p>
        <p class="mt-1 font-semibold text-teal-700">{{ $mensaje->email ?? '-' }}</p>
      </div>
      <div>
        <p class="text-xs uppercase tracking-wide text-gray-400">Teléfono</p>
        <p class="mt-1 font-semibold text-gray-900">{{ $mensaje->phone ?? '-' }}</p>
      </div>
      <div>
        <p class="text-xs uppercase tracking-wide text-gray-400">Estado</p>
        <span class="mt-2 badge {{ $mensaje->status_tone ?? 'neutral' }}">{{ $mensaje->status ?? 'Nuevo' }}</span>
      </div>
      <div class="md:col-span-2">
        <p class="text-xs uppercase tracking-wide text-gray-400">Asunto</p>
        <p class="mt-1 font-semibold text-gray-900">{{ $mensaje->subject ?? '-' }}</p>
      </div>
    </div>

    <div class="mt-6 rounded-xl border border-gray-200 bg-gray-50 p-5">
      <p class="text-xs uppercase tracking-wide text-gray-400">Mensaje</p>
      <p class="mt-3 whitespace-pre-wrap text-gray-700">{{ $mensaje->message ?? 'Estimados, solicito información sobre los servicios disponibles en la clínica.' }}</p>
    </div>

    <div class="mt-6 flex flex-wrap gap-3">
      <button class="btn btn-primary demo-action-blocked">
        <i class="ri-mail-send-line"></i> Responder por correo
      </button>
      <a href="{{ route('demo.admin.notificaciones-contacto') }}" class="btn btn-ghost">
        Volver al listado
      </a>
    </div>
  </section>
</div>
@endsection
