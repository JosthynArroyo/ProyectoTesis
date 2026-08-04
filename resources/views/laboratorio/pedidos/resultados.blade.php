@extends('layouts.laboratorio')
@section('title', 'Registrar resultados de laboratorio')
@section('activeSidebar', 'pedidos')
@section('header-title', 'Registrar resultados de laboratorio')
@section('header-subtitle', 'Formulario dinámico adaptado a exámenes solicitados')

@section('main')
<section class="space-y-6">
  @if(session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  @if ($errors->any())
    <x-ui.alert tone="error">
      <div class="space-y-1">
        <p class="font-semibold">Ocurrieron errores al validar el formulario:</p>
        <ul class="list-disc pl-5 text-sm">
          @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    </x-ui.alert>
  @endif

  <article class="card p-6 border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
    <div class="grid gap-4 md:grid-cols-3">
      <div>
        <p class="text-xs uppercase tracking-widest text-gray-500 dark:text-gray-400">Paciente Real</p>
        <p class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $pedido->nombrePacienteReal() }}</p>
        @if($pedido->representanteNombre())
          <p class="text-sm text-gray-500 dark:text-gray-400">Representante: {{ $pedido->representanteNombre() }}</p>
        @endif
      </div>
      <div>
        <p class="text-xs uppercase tracking-widest text-gray-500 dark:text-gray-400">Orden Médica</p>
        <p class="mt-1 font-semibold text-gray-900 dark:text-white">#{{ $pedido->id }}</p>
        <p class="text-sm text-gray-500 dark:text-gray-400">Médico: {{ $pedido->doctor?->name ?? 'N/D' }}</p>
      </div>
      <div>
        <p class="text-xs uppercase tracking-widest text-gray-500 dark:text-gray-400">Estado del Informe</p>
        <p class="mt-1 font-semibold text-gray-900 dark:text-white">
          <span class="badge {{ $resultado->estado === 'publicado' ? 'success' : 'info' }}">
            {{ $resultado->estado === 'publicado' ? 'Resultados Publicados' : 'Borrador Interno (V'.$resultado->version.')' }}
          </span>
        </p>
      </div>
    </div>
  </article>

  <form id="lab-result-form" method="POST" action="{{ route('laboratorio.pedidos.resultados.draft', $pedido) }}" class="card p-6 space-y-6 border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
    @csrf
    <input type="hidden" name="resultado_id" value="{{ $resultado->id }}">

    <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50/70 dark:border-emerald-900/50 dark:bg-emerald-950/30 p-4 text-sm text-emerald-900 dark:text-emerald-300 flex items-center gap-3">
      <i class="ri-checkbox-circle-line text-xl text-emerald-600 dark:text-emerald-400 shrink-0"></i>
      <div>
        <strong>Formulario Profesional Adaptado:</strong> Se muestran únicamente los analitos correspondientes a los exámenes solicitados. La clasificación clínica debe seleccionarse manualmente por el profesional de laboratorio. Los valores de unidad, método e intervalo de referencia son editables para este informe concreto.
      </div>
    </div>

    <div class="space-y-8">
      @foreach($examStructure as $exam)
        <section class="rounded-3xl border border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-850/50 p-6 space-y-4">
          <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 dark:border-gray-800 pb-3">
            <div>
              <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <i class="ri-flask-line text-teal-600 dark:text-teal-400"></i>
                {{ $exam['exam_name'] === 'Brusella Abortus' ? 'Brucella abortus' : $exam['exam_name'] }}
              </h3>
              <span class="text-xs text-gray-500 dark:text-gray-400">Código: {{ $exam['exam_code'] }}</span>
            </div>
            @if($exam['is_panel'])
              <span class="badge info">Panel de Exámenes</span>
            @else
              <span class="badge primary">Examen Individual</span>
            @endif
          </div>

          <div class="space-y-6">
            @foreach($exam['components'] as $comp)
              @php
                $code = $comp['code'];
                $saved = $comp['saved'];
                $type = $comp['result_type'];
                $authMethods = $comp['authorized_methods'] ?? [$comp['default_method']];
                $currentMethod = old("items.{$code}.method", $saved['method'] ?? $comp['default_method']);
                $currentUnit = old("items.{$code}.unit", $saved['unit'] ?? $comp['default_unit']);
                $currentRef = old("items.{$code}.reference", $saved['reference'] ?? $comp['reference_text']);
                $currentClass = old("items.{$code}.classification", $saved['classification'] ?? '');
              @endphp

              <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 space-y-4 transition-all hover:border-teal-300 dark:hover:border-teal-700">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 dark:border-gray-800 pb-2">
                  <label class="font-bold text-base text-gray-900 dark:text-white flex items-center gap-2" for="res_{{ $code }}">
                    {{ $comp['name'] }}
                    @if($type === 'calculated')
                      <span class="badge info text-xs"><i class="ri-calculator-line mr-1"></i> Calculado</span>
                    @endif
                  </label>
                  <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Tipo: {{ strtoupper($type) }}</span>
                </div>

                <div class="grid gap-4 md:grid-cols-12">
                  {{-- 1. RESULTADO --}}
                  <div class="md:col-span-4 space-y-1">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Resultado</label>

                    @if($type === 'numeric')
                      <div class="flex items-center gap-2">
                        <select name="items[{{ $code }}][comparator]" class="form-select text-sm w-20">
                          <option value="=" @selected(in_array(old("items.{$code}.comparator", $saved['comparator'] ?? ''), ['=', '', 'eq']))>=</option>
                          <option value="<" @selected(in_array(old("items.{$code}.comparator", $saved['comparator'] ?? ''), ['<', 'lt']))>&lt;</option>
                          <option value=">" @selected(in_array(old("items.{$code}.comparator", $saved['comparator'] ?? ''), ['>', 'gt']))>&gt;</option>
                          <option value="≤" @selected(in_array(old("items.{$code}.comparator", $saved['comparator'] ?? ''), ['<=', '≤', 'lte']))>&le;</option>
                          <option value="≥" @selected(in_array(old("items.{$code}.comparator", $saved['comparator'] ?? ''), ['>=', '≥', 'gte']))>&ge;</option>
                        </select>
                        <input
                          type="number"
                          step="any"
                          id="res_{{ $code }}"
                          name="items[{{ $code }}][value_numeric]"
                          value="{{ old("items.{$code}.value_numeric", $saved['value_numeric']) }}"
                          placeholder="0.00"
                          class="form-input text-sm font-medium"
                        >
                      </div>

                    @elseif($type === 'coded')
                      <select id="res_{{ $code }}" name="items[{{ $code }}][value_code]" class="form-select text-sm font-medium">
                        <option value="">-- Seleccionar Opción --</option>
                        @foreach($comp['options'] as $opt)
                          <option value="{{ $opt['code'] }}" @selected(old("items.{$code}.value_code", $saved['value_code']) === $opt['code'])>
                            {{ $opt['label'] }}
                          </option>
                        @endforeach
                      </select>

                    @elseif($type === 'titer')
                      <input
                        type="text"
                        id="res_{{ $code }}"
                        name="items[{{ $code }}][value_text]"
                        value="{{ old("items.{$code}.value_text", $saved['value_text']) }}"
                        placeholder="ej. 1:160, <1:80, No reactivo"
                        class="form-input text-sm font-medium"
                      >

                    @elseif($type === 'blood_group')
                      @php
                        $extra = $saved['extra_data'] ?? [];
                      @endphp
                      <div class="grid grid-cols-2 gap-2">
                        <select name="items[{{ $code }}][extra_data][abo]" class="form-select text-sm font-medium">
                          <option value="">-- ABO --</option>
                          @foreach(['O', 'A', 'B', 'AB'] as $abo)
                            <option value="{{ $abo }}" @selected(old("items.{$code}.extra_data.abo", $extra['abo'] ?? '') === $abo)>Grupo {{ $abo }}</option>
                          @endforeach
                        </select>
                        <select name="items[{{ $code }}][extra_data][rh]" class="form-select text-sm font-medium">
                          <option value="">-- Rh --</option>
                          <option value="Positivo" @selected(old("items.{$code}.extra_data.rh", $extra['rh'] ?? '') === 'Positivo')>Factor Rh (+)</option>
                          <option value="Negativo" @selected(old("items.{$code}.extra_data.rh", $extra['rh'] ?? '') === 'Negativo')>Factor Rh (-)</option>
                        </select>
                      </div>

                    @elseif($type === 'calculated')
                      <input
                        type="text"
                        readonly
                        value="{{ $saved['value_numeric'] ?: 'Auto-calculado al guardar' }}"
                        class="form-input text-sm bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-medium"
                      >

                    @elseif($type === 'culture')
                      @php
                        $extra = $saved['extra_data'] ?? [];
                        $currentCultureState = old("items.{$code}.value_code", $saved['value_code']);
                      @endphp
                      <div class="space-y-3" x-data="{ cultureState: '{{ $currentCultureState }}' }">
                        <select name="items[{{ $code }}][value_code]" x-model="cultureState" class="form-select text-sm font-medium">
                          <option value="">-- Estado del Cultivo --</option>
                          <option value="sin_crecimiento">Sin crecimiento bacteriano</option>
                          <option value="crecimiento_significativo">Crecimiento significativo</option>
                          <option value="crecimiento_mixto">Crecimiento mixto / Posible contaminación</option>
                          <option value="muestra_no_apta">Muestra no apta</option>
                        </select>

                        <div x-show="cultureState === 'crecimiento_significativo'" class="p-3 border border-amber-200 bg-amber-50/50 dark:border-amber-900/40 dark:bg-amber-950/20 rounded-xl space-y-3">
                          <div>
                            <label class="text-xs font-semibold text-amber-900 dark:text-amber-300">Microorganismo aislado</label>
                            <input
                              type="text"
                              name="items[{{ $code }}][extra_data][microorganism]"
                              value="{{ old("items.{$code}.extra_data.microorganism", $extra['microorganism'] ?? '') }}"
                              placeholder="ej. Escherichia coli, Staphylococcus aureus"
                              class="form-input text-sm"
                            >
                          </div>
                        </div>
                      </div>

                    @else
                      <input
                        type="text"
                        id="res_{{ $code }}"
                        name="items[{{ $code }}][value_text]"
                        value="{{ old("items.{$code}.value_text", $saved['value_text']) }}"
                        placeholder="Ingresa el resultado u observación"
                        class="form-input text-sm"
                      >
                    @endif
                  </div>

                  {{-- 2. UNIDAD DE MEDIDA --}}
                  <div class="md:col-span-2 space-y-1">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Unidad de medida</label>
                    <input
                      type="text"
                      name="items[{{ $code }}][unit]"
                      value="{{ $currentUnit }}"
                      placeholder="ej. mg/dL, g/dL, No aplica"
                      class="form-input text-sm font-medium"
                    >
                  </div>

                  {{-- 3. MÉTODO UTILIZADO --}}
                  <div class="md:col-span-3 space-y-1">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Método utilizado</label>
                    @if(count($authMethods) > 1)
                      <select name="items[{{ $code }}][method]" class="form-select text-sm font-medium">
                        @foreach($authMethods as $mOpt)
                          <option value="{{ $mOpt }}" @selected($currentMethod === $mOpt)>{{ $mOpt }}</option>
                        @endforeach
                      </select>
                    @else
                      <input
                        type="text"
                        name="items[{{ $code }}][method]"
                        value="{{ $currentMethod }}"
                        placeholder="Método analítico"
                        class="form-input text-sm font-medium"
                      >
                    @endif
                  </div>

                  {{-- 4. CLASIFICACIÓN MANUAL OBLIGATORIA --}}
                  <div class="md:col-span-3 space-y-1">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Clasificación manual</label>
                    <select name="items[{{ $code }}][classification]" class="form-select text-sm font-medium" required>
                      <option value="">-- Seleccionar clasificación --</option>
                      <option value="normal" @selected($currentClass === 'normal')>Normal</option>
                      <option value="bajo" @selected($currentClass === 'bajo' || $currentClass === 'low')>Bajo</option>
                      <option value="alto" @selected($currentClass === 'alto' || $currentClass === 'high')>Alto</option>
                      <option value="critical_low" @selected($currentClass === 'critical_low')>Crítico bajo</option>
                      <option value="critical_high" @selected($currentClass === 'critical_high')>Crítico alto</option>
                      <option value="abnormal" @selected($currentClass === 'abnormal')>Anormal</option>
                      <option value="indeterminate" @selected($currentClass === 'indeterminate')>Indeterminado</option>
                      <option value="not_applicable" @selected($currentClass === 'not_applicable')>No aplica</option>
                    </select>
                  </div>
                </div>

                <div class="grid gap-4 md:grid-cols-12 pt-2 border-t border-gray-100 dark:border-gray-800">
                  {{-- 5. INTERVALO / VALOR DE REFERENCIA --}}
                  <div class="md:col-span-6 space-y-1">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Intervalo/valor de referencia</label>
                    <input
                      type="text"
                      name="items[{{ $code }}][reference]"
                      value="{{ $currentRef }}"
                      placeholder="ej. 0.50 – 0.95 mg/dL, No reactivo, No aplica"
                      class="form-input text-sm font-medium"
                    >
                  </div>

                  {{-- 6. OBSERVACIONES PARTICULARES --}}
                  <div class="md:col-span-6 space-y-1">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Observaciones particulares</label>
                    <textarea
                      name="items[{{ $code }}][observation]"
                      rows="1"
                      placeholder="Observación particular (opcional)"
                      class="form-textarea text-xs"
                    >{{ old("items.{$code}.observation", $saved['observation']) }}</textarea>
                  </div>
                </div>
              </div>
            @endforeach
          </div>
        </section>
      @endforeach
    </div>

    <div class="pt-4 border-t border-gray-200 dark:border-gray-800">
      <label class="form-label font-semibold text-gray-900 dark:text-white" for="observaciones_generales">Observaciones generales del informe completo</label>
      <textarea id="observaciones_generales" name="observaciones_generales" rows="4" class="form-textarea text-sm" placeholder="Conclusiones generales o notas adicionales del laboratorio">{{ old('observaciones_generales', $resultado->observaciones_generales) }}</textarea>
    </div>

    <x-ui.form-actions>
      <x-slot:left>
        <a href="{{ route('laboratorio.pedidos.index') }}" class="btn btn-ghost">Volver</a>
      </x-slot>
      <div class="flex flex-wrap gap-2">
        <button type="submit" formaction="{{ route('laboratorio.pedidos.resultados.draft', $pedido) }}" class="btn btn-outline" data-submit-lock>
          Guardar borrador
        </button>
        <button type="submit" formaction="{{ route('laboratorio.pedidos.resultados.preview', $pedido) }}" class="btn btn-outline" data-submit-lock>
          Vista previa PDF
        </button>
        <button type="submit" formaction="{{ route('laboratorio.pedidos.resultados.publish', $pedido) }}" class="btn btn-primary" data-submit-lock>
          Publicar resultados
        </button>
      </div>
    </x-ui.form-actions>
  </form>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('lab-result-form');
  if (!form) return;
  form.addEventListener('submit', () => {
    form.querySelectorAll('[data-submit-lock]').forEach((button) => {
      button.disabled = true;
    });
  }, { once: false });
});
</script>
@endsection
