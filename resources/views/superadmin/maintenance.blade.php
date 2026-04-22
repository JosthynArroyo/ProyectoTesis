@extends('layouts.superadmin')
@section('title','Mantenimiento')
@section('header-title','Mantenimiento')
@section('header-subtitle','Control de disponibilidad global')

@section('main')
<div class="space-y-6">
  @if (session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  <form class="form space-y-6" method="POST" action="{{ route('superadmin.maintenance.update') }}">
    @csrf
    @method('PUT')
    <section class="card p-6 space-y-4">
      <label class="flex items-center gap-3 text-sm font-semibold text-slate-700">
        <input type="hidden" name="maintenance_enabled" value="0">
        <input type="checkbox" name="maintenance_enabled" value="1" @checked(($settings['maintenance.enabled'] ?? '0') === '1')>
        Activar modo mantenimiento
      </label>
      @error('maintenance_enabled')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror

      <div>
        <label class="form-label" for="maintenance_message">Mensaje público</label>
        <textarea class="form-textarea" id="maintenance_message" name="maintenance_message" rows="3" required>{{ old('maintenance_message', $settings['maintenance.message'] ?? '') }}</textarea>
        @error('maintenance_message')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>

      <div class="grid gap-4 md:grid-cols-2">
        <div>
          <label class="form-label" for="maintenance_until">Hasta</label>
          <input class="form-input" id="maintenance_until" name="maintenance_until" type="datetime-local"
                 value="{{ old('maintenance_until', !empty($settings['maintenance.until']) ? \Illuminate\Support\Carbon::parse($settings['maintenance.until'])->format('Y-m-d\\TH:i') : '') }}" required>
          @error('maintenance_until')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label" for="maintenance_allow_ips">Lista blanca de IPs</label>
          <input class="form-input" id="maintenance_allow_ips" name="maintenance_allow_ips"
                 value="{{ old('maintenance_allow_ips', $settings['maintenance.allow_ips'] ?? '') }}"
                 placeholder="Ej: 127.0.0.1, 190.0.0.10" required>
          <p class="mt-1 text-xs text-slate-500">Solo estas IPs podrán acceder sin ser superadmin. Puedes separarlas con coma, espacio o punto y coma.</p>
          <p class="mt-1 text-xs text-slate-500">IPs detectadas en esta solicitud: <span class="font-semibold text-slate-700">{{ !empty($detectedIps) ? implode(', ', $detectedIps) : 'No disponible' }}</span></p>
          @error('maintenance_allow_ips')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
      </div>
    </section>

    <div class="flex flex-wrap items-center justify-end gap-3">
      <button class="btn btn-primary" type="submit">
        <i class="ri-save-line"></i> Guardar configuración
      </button>
    </div>
  </form>
</div>
@endsection

