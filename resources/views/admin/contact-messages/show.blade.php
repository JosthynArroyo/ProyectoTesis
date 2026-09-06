@extends('layouts.admin')
@section('title', 'Detalle de mensaje')
@section('header-title', 'Mensaje de contacto')
@section('header-subtitle', $contactMessage->asunto)

@section('main')
<div class="space-y-6">
  <div class="panel-action-bar panel-action-bar--between">
    <div class="panel-action-bar__meta">
      Recibido el {{ $contactMessage->created_at?->format('d/m/Y H:i') ?? '-' }}.
    </div>
    <div class="panel-action-bar__actions">
      <a href="{{ route('admin.contacto.mensajes') }}" class="btn btn-outline">
        <i class="ri-arrow-left-line"></i> Volver
      </a>
    </div>
  </div>

  <section class="card p-6">
    @php
      $estadoLabel = match ($contactMessage->estado) {
        'nuevo' => 'Nuevo',
        'leido' => 'Leido',
        default => ucfirst((string) $contactMessage->estado),
      };
      $estadoTone = match ($contactMessage->estado) {
        'nuevo' => 'warning',
        'leido' => 'success',
        default => 'neutral',
      };
    @endphp

    <div class="grid gap-4 md:grid-cols-2">
      <div>
        <p class="text-xs uppercase tracking-wide text-gray-400">Nombre</p>
        <p class="mt-1 font-semibold text-gray-900">{{ $contactMessage->nombre }}</p>
      </div>
      <div>
        <p class="text-xs uppercase tracking-wide text-gray-400">Correo</p>
        @php
          $emailDestino = trim((string) $contactMessage->correo);
          $asuntoOriginal = trim((string) ($contactMessage->asunto ?: 'Contacto'));
          $asuntoLimpio = preg_replace('/[\r\n]+/', ' ', $asuntoOriginal);
          $subjectFormatted = str_starts_with(strtolower($asuntoLimpio), 're:') ? $asuntoLimpio : 'Re: ' . $asuntoLimpio;
          $mailtoQuery = http_build_query(['subject' => $subjectFormatted], '', '&', PHP_QUERY_RFC3986);
          $mailtoHref = 'mailto:' . $emailDestino . ($mailtoQuery !== '' ? '?' . $mailtoQuery : '');
        @endphp
        <a href="{{ $mailtoHref }}" class="mt-1 inline-flex font-semibold text-teal-700 hover:underline" data-action-lock-ignore="1" data-no-loader="1">{{ $contactMessage->correo }}</a>
      </div>
      <div>
        <p class="text-xs uppercase tracking-wide text-gray-400">Telefono</p>
        <p class="mt-1 font-semibold text-gray-900">{{ $contactMessage->telefono ?: '-' }}</p>
      </div>
      <div>
        <p class="text-xs uppercase tracking-wide text-gray-400">Estado</p>
        <span class="mt-2 badge {{ $estadoTone }}">{{ $estadoLabel }}</span>
      </div>
    </div>

    <div class="mt-6 rounded-xl border border-gray-200 bg-gray-50 p-5">
      <p class="text-xs uppercase tracking-wide text-gray-400">Mensaje</p>
      <p class="mt-3 whitespace-pre-wrap text-gray-700">{{ $contactMessage->mensaje }}</p>
    </div>

    <div class="mt-6 flex flex-wrap gap-3">
      <a href="{{ $mailtoHref }}" class="btn btn-primary" data-action-lock-ignore="1" data-no-loader="1">
        <i class="ri-mail-send-line"></i> Responder por correo
      </a>
      <a href="{{ route('admin.contacto.mensajes') }}" class="btn btn-ghost">
        Volver al listado
      </a>
    </div>
  </section>
</div>
@endsection
