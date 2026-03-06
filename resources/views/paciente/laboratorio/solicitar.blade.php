@extends('layouts.paciente')
@section('title', 'Solicitar examen de laboratorio')
@section('body-class', 'paciente-body--lab-order')
@section('header-title','Solicitar examen')
@section('header-subtitle','Confirma tu solicitud de laboratorio')

@push('scripts')
  @vite('resources/js/paciente/lab-order.js')
@endpush

@php($selectedSource = old('source', $defaultSource ?? \App\Models\LabOrder::SOURCE_ROUTINE))
@php($selectedMedicalOrder = old('medical_order_id'))
@php($selectedTestId = old('lab_test_id'))
@php($selectedPriority = old('priority', 'normal'))

@section('main')
  <div class="space-y-6">
    <section class="card p-6">
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Laboratorio</p>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Agendar / generar orden</h1>
        <p class="text-slate-600">Selecciona el origen y confirma tu solicitud de examen.</p>
      </div>
    </section>

    <x-ui.alert tone="warning">Esta solicitud genera la orden. La toma de muestra y resultados se gestionan desde el laboratorio.</x-ui.alert>

    @if (session('success'))
      <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
    @endif

    <section class="card p-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 class="text-lg font-semibold text-slate-900">Solicitud de examen</h2>
          <p class="text-sm text-slate-500">El paciente solo confirma. El sistema gestiona preparaciÃ³n e indicaciones.</p>
        </div>
        <span class="badge info" data-origin-badge>Con orden mÃ©dica</span>
      </div>

      <form method="POST" action="{{ route('paciente.laboratorio.solicitar.store') }}" class="mt-6 space-y-6">
        @csrf

        <div class="flex flex-wrap gap-3">
          <label class="flex items-center gap-2 rounded-full border border-slate-200 px-3 py-2 text-sm">
            <input type="radio" name="source" value="{{ \App\Models\LabOrder::SOURCE_MEDICAL_ORDER }}"
              {{ $selectedSource === \App\Models\LabOrder::SOURCE_MEDICAL_ORDER ? 'checked' : '' }}
              {{ $medicalOrders->isEmpty() ? 'disabled' : '' }} required>
            <span>Con orden mÃ©dica</span>
          </label>
          <label class="flex items-center gap-2 rounded-full border border-slate-200 px-3 py-2 text-sm">
            <input type="radio" name="source" value="{{ \App\Models\LabOrder::SOURCE_ROUTINE }}"
              {{ $selectedSource === \App\Models\LabOrder::SOURCE_ROUTINE ? 'checked' : '' }} required>
            <span>Examen de rutina</span>
          </label>
        </div>
        @error('source')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror

        <div class="text-xs text-slate-500">
          @if($medicalOrders->isEmpty())
            <span>Sin Ã³rdenes mÃ©dicas disponibles. Puedes solicitar exÃ¡menes de rutina.</span>
          @endif
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <div class="md:col-span-2" data-origin-section="MEDICAL_ORDER">
            <label for="medical_order_id" class="form-label">Doctor / Orden</label>
            <select id="medical_order_id" name="medical_order_id" data-has-orders="{{ $medicalOrders->isNotEmpty() ? '1' : '0' }}" {{ $medicalOrders->isEmpty() ? 'disabled' : '' }} class="form-select">
              <option value="">{{ $medicalOrders->isEmpty() ? 'Sin Ã³rdenes disponibles' : 'Seleccionar orden' }}</option>
              @foreach($medicalOrders as $order)
                <option value="{{ $order->id }}"
                  data-test-id="{{ $order->labTest->id }}"
                  data-notes="{{ e($order->doctor_notes ?? '') }}"
                  {{ (string) $selectedMedicalOrder === (string) $order->id ? 'selected' : '' }}>
                  Orden #{{ $order->id }} - {{ optional($order->doctor)->name ?? 'Doctor' }} - {{ optional($order->labTest)->nombre ?? 'Examen' }}
                </option>
              @endforeach
            </select>
            @error('medical_order_id')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
          </div>

          <div class="md:col-span-2">
            <label for="lab_test_id" class="form-label">Tipo de examen</label>
            <select id="lab_test_id" name="lab_test_id" required
              data-prep-empty="Selecciona un examen para ver la preparaciÃ³n."
              data-prep-missing="Este examen no tiene preparaciÃ³n registrada. Contacte a la clÃ­nica."
              data-indicaciones-empty="Selecciona un examen para ver las indicaciones del examen."
              data-indicaciones-missing="Sin indicaciones adicionales para este examen."
              data-exam-wrapper
              class="form-select">
              <option value="">Seleccionar examen</option>
              @foreach($labTests as $test)
                <option value="{{ $test->id }}"
                  data-prep="{{ e($test->preparacion_default) }}"
                  data-indicaciones="{{ e($test->indicaciones_default) }}"
                  data-rutina="{{ $test->es_rutina ? '1' : '0' }}"
                  data-requiere-orden="{{ $test->requiere_orden ? '1' : '0' }}"
                  data-tipo="{{ e($test->tipo) }}"
                  data-categoria="{{ e($test->categoria) }}"
                  {{ (string) $selectedTestId === (string) $test->id ? 'selected' : '' }}>
                  {{ $test->nombre }}
                </option>
              @endforeach
            </select>
            @error('lab_test_id')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
          </div>

          <div>
            <label for="priority" class="form-label">Prioridad (si aplica)</label>
            <select id="priority" name="priority" required class="form-select">
              <option value="normal" {{ $selectedPriority === 'normal' ? 'selected' : '' }}>Normal</option>
              <option value="urgente" {{ $selectedPriority === 'urgente' ? 'selected' : '' }}>Urgente</option>
            </select>
            @error('priority')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
          </div>

          <div class="rounded-2xl border border-slate-200 bg-white/90 p-4">
            <div class="info-head">
              <h4 class="text-sm font-semibold text-slate-900">PreparaciÃ³n del examen</h4>
              <p class="text-xs text-slate-500">Generada automÃ¡ticamente segÃºn el examen.</p>
            </div>
            <p class="mt-2 text-xs text-slate-600" data-prep-text>Selecciona un examen para ver la preparaciÃ³n.</p>
          </div>

          <div class="rounded-2xl border border-slate-200 bg-white/90 p-4">
            <div class="info-head">
              <h4 class="text-sm font-semibold text-slate-900">Indicaciones del examen</h4>
              <p class="text-xs text-slate-500">Indicaciones estÃ¡ndar del laboratorio.</p>
            </div>
            <p class="mt-2 text-xs text-slate-600" data-exam-indications>Selecciona un examen para ver las indicaciones.</p>
          </div>

          <div class="rounded-2xl border border-slate-200 bg-white/90 p-4" data-origin-section="MEDICAL_ORDER">
            <div class="info-head">
              <h4 class="text-sm font-semibold text-slate-900">Indicaciones mÃ©dicas</h4>
              <p class="text-xs text-slate-500">Definidas por tu doctor.</p>
            </div>
            <p class="mt-2 text-xs text-slate-600" data-doctor-notes>Sin indicaciones adicionales.</p>
          </div>

          <div class="rounded-2xl border border-slate-200 bg-white/90 p-4" data-origin-section="ROUTINE">
            <div class="info-head">
              <h4 class="text-sm font-semibold text-slate-900">Indicaciones mÃ©dicas</h4>
              <p class="text-xs text-slate-500">Examen de rutina (sin orden mÃ©dica).</p>
            </div>
            <p class="mt-2 text-xs text-slate-600">No hay indicaciones mÃ©dicas personalizadas para esta solicitud.</p>
          </div>
        </div>

        <div class="flex justify-end">
          <button type="submit" class="btn btn-primary">Confirmar solicitud</button>
        </div>
      </form>
    </section>
  </div>
@endsection

