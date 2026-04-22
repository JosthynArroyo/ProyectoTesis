@extends('layouts.admin')
@section('title', 'Mensajes de contacto')
@section('header-title', 'Mensajes de contacto')
@section('header-subtitle', 'Solicitudes enviadas desde el formulario publico')

@section('main')
<div class="space-y-6">
  <div class="panel-action-bar">
    <span class="badge info">{{ $messages->total() }} mensajes</span>
  </div>

  <section class="card p-6">
    <div class="table-shell table-responsive-cards">
      <table class="table">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Correo</th>
            <th>Telefono</th>
            <th>Asunto</th>
            <th>Estado</th>
            <th>Recibido</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          @forelse($messages as $message)
            @php
              $estadoLabel = match ($message->estado) {
                'nuevo' => 'Nuevo',
                'leido' => 'Leido',
                default => ucfirst((string) $message->estado),
              };
              $estadoTone = match ($message->estado) {
                'nuevo' => 'warning',
                'leido' => 'success',
                default => 'neutral',
              };
            @endphp
            <tr>
              <td data-label="Nombre">
                <div class="text-sm font-semibold text-slate-900">{{ $message->nombre }}</div>
              </td>
              <td data-label="Correo">
                <a href="mailto:{{ $message->correo }}" class="text-sm text-teal-700 hover:underline">{{ $message->correo }}</a>
              </td>
              <td data-label="Telefono">{{ $message->telefono ?: '-' }}</td>
              <td data-label="Asunto">{{ \Illuminate\Support\Str::limit($message->asunto, 48) }}</td>
              <td data-label="Estado"><span class="badge {{ $estadoTone }}">{{ $estadoLabel }}</span></td>
              <td data-label="Recibido">{{ $message->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
              <td data-label="Acciones">
                <a href="{{ route('admin.contacto.mensajes.show', $message) }}" class="btn btn-outline btn-sm">
                  <i class="ri-eye-line"></i> Ver detalle
                </a>
              </td>
            </tr>
          @empty
            <tr><td colspan="7">No hay mensajes de contacto registrados.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="mt-5">
      {{ $messages->links() }}
    </div>
  </section>
</div>
@endsection
