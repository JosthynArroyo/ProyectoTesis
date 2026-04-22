@extends('layouts.laboratorio')
@section('title','Flujo continuo | Laboratorio')
@section('activeSidebar','horario')
@section('header-title','Flujo continuo')
@section('header-subtitle','Cola en tiempo real y bloques de 5 min')

@section('main')
  <div class="space-y-6">
    <div class="panel-action-bar">
      <button type="button" class="btn btn-outline">Ingreso prioritario</button>
    </div>

    @php($indicadores = $indicadores ?? [])
    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      <x-ui.stat label="Atendidos" :value="data_get($indicadores, 'atendidos', 0)" tone="teal">
        <p class="text-xs text-slate-500">Atenciones completadas</p>
        <x-slot:icon><i class="ri-checkbox-circle-line"></i></x-slot:icon>
      </x-ui.stat>
      <x-ui.stat label="Pendientes" :value="data_get($indicadores, 'pendientes', 0)" tone="amber">
        <p class="text-xs text-slate-500">En espera</p>
        <x-slot:icon><i class="ri-timer-line"></i></x-slot:icon>
      </x-ui.stat>
      <x-ui.stat label="Retraso acumulado" :value="data_get($indicadores, 'retraso', '0 min')" tone="sky">
        <p class="text-xs text-slate-500">Duración estimada</p>
        <x-slot:icon><i class="ri-time-line"></i></x-slot:icon>
      </x-ui.stat>
    </section>

    @php($siguiente = $siguiente ?? null)
    <section class="card p-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 class="text-lg font-semibold text-slate-900">Acción rápida</h2>
          <p class="text-sm text-slate-500">Bloques de 5 min en cola continua.</p>
        </div>
        <x-ui.badge tone="info">Bloques 5 min</x-ui.badge>
      </div>

      @if($siguiente)
        @php($estadoActual = data_get($siguiente, 'estado', 'pendiente'))
        @php($estadoActualLabel = [
          'pendiente' => 'Pendiente',
          'en_toma' => 'En toma',
          'tomado' => 'Tomado',
          'no_presento' => 'No se presentó',
        ][$estadoActual] ?? 'Pendiente')
        @php($estadoTone = [
          'pendiente' => 'warning',
          'en_toma' => 'info',
          'tomado' => 'success',
          'no_presento' => 'danger',
        ][$estadoActual] ?? 'neutral')

        <div class="mt-4 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
          <div>
            <p class="text-xs uppercase tracking-widest text-slate-500">{{ data_get($siguiente, 'hora', '--:--') }}</p>
            <p class="mt-2 text-lg font-semibold text-slate-900">{{ data_get($siguiente, 'paciente', 'Paciente sin nombre') }}</p>
            <p class="text-sm text-slate-500">{{ data_get($siguiente, 'examen', 'Examen asignado') }}</p>
          </div>
          <x-ui.badge :tone="$estadoTone">{{ $estadoActualLabel }}</x-ui.badge>
        </div>

        @if($estadoActual === 'pendiente')
          <div class="mt-4 flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary">Tomar muestra</button>
            <button type="button" class="btn btn-ghost">No se presentó</button>
          </div>
          <p class="mt-2 text-xs text-slate-500">La hora real se registra automáticamente. "No se presentó" libera el bloque.</p>
        @elseif($estadoActual === 'en_toma')
          <p class="mt-3 text-xs text-slate-500">En toma. El sistema registra el cierre real al finalizar.</p>
        @else
          <p class="mt-3 text-xs text-slate-500">Estado cerrado. El flujo pasa al siguiente paciente.</p>
        @endif
      @else
        <x-ui.empty-state
          title="Sin pacientes en cola"
          message="El sistema mantiene el flujo continuo sin agenda manual."
          class="mt-4"
        />
      @endif
    </section>

    @php($cola = $cola ?? [])
    <section class="card p-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 class="text-lg font-semibold text-slate-900">Cola en tiempo real</h2>
          <p class="text-sm text-slate-500">Ordenada por hora de llegada.</p>
        </div>
      </div>

      @if(count($cola))
        <div class="mt-4 space-y-3">
          @foreach($cola as $item)
            @php($estado = data_get($item, 'estado', 'pendiente'))
            @php($estadoLabel = [
              'pendiente' => 'Pendiente',
              'en_toma' => 'En toma',
              'tomado' => 'Tomado',
              'no_presento' => 'No se presentó',
            ][$estado] ?? 'Pendiente')
            @php($estadoTone = [
              'pendiente' => 'warning',
              'en_toma' => 'info',
              'tomado' => 'success',
              'no_presento' => 'danger',
            ][$estado] ?? 'neutral')
            <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white px-4 py-3">
              <div>
                <p class="text-xs uppercase tracking-widest text-slate-500">{{ data_get($item, 'hora', '--:--') }}</p>
                <p class="mt-1 font-semibold text-slate-900">{{ data_get($item, 'paciente', 'Paciente sin nombre') }}</p>
                <p class="text-sm text-slate-500">{{ data_get($item, 'examen', 'Examen asignado') }}</p>
              </div>
              <x-ui.badge :tone="$estadoTone">{{ $estadoLabel }}</x-ui.badge>
            </div>
          @endforeach
        </div>
      @else
        <x-ui.empty-state
          title="No hay pacientes en espera"
          message="La cola se actualiza automáticamente cuando se registran nuevas órdenes."
          class="mt-4"
        />
      @endif
    </section>
  </div>
@endsection
