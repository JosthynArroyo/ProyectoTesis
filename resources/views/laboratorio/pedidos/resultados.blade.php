@extends('layouts.laboratorio')
@section('title', 'Registrar resultados de laboratorio')
@section('activeSidebar', 'pedidos')
@section('header-title', 'Registrar resultados')
@section('header-subtitle', 'Formulario estructurado para exámenes solicitados')

@section('main')
<section class="space-y-6">
  @if(session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  @if ($errors->any())
    <x-ui.alert tone="error">
      <div class="space-y-1">
        <p class="font-semibold">No se pudo guardar o publicar el resultado.</p>
        <ul class="list-disc pl-5 text-sm">
          @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    </x-ui.alert>
  @endif

  <article class="card p-6">
    <div class="grid gap-4 md:grid-cols-3">
      <div>
        <p class="text-xs uppercase tracking-widest text-gray-500">Paciente</p>
        <p class="mt-1 font-semibold text-gray-900">{{ $pedido->nombrePacienteReal() }}</p>
        @if($pedido->representanteNombre())
          <p class="text-sm text-gray-500">Representante: {{ $pedido->representanteNombre() }}</p>
        @endif
      </div>
      <div>
        <p class="text-xs uppercase tracking-widest text-gray-500">Orden</p>
        <p class="mt-1 font-semibold text-gray-900">#{{ $pedido->id }}</p>
        <p class="text-sm text-gray-500">Exámenes: {{ implode(', ', array_map(fn ($item) => $item['nombre'], $items)) }}</p>
      </div>
      <div>
        <p class="text-xs uppercase tracking-widest text-gray-500">Estado</p>
        <p class="mt-1 font-semibold text-gray-900">{{ $resultado->estado === 'publicado' ? 'Resultados publicados' : 'Borrador interno' }}</p>
        <p class="text-sm text-gray-500">La versión publicada se bloqueará para evitar cambios silenciosos.</p>
      </div>
    </div>
  </article>

  <form id="lab-result-form" method="POST" action="{{ route('laboratorio.pedidos.resultados.draft', $pedido) }}" class="card p-6 space-y-6">
    @csrf
    <input type="hidden" name="resultado_id" value="{{ $resultado->id }}">

    <div class="rounded-2xl border border-emerald-100 bg-emerald-50/60 p-4 text-sm text-emerald-900">
      Completa únicamente los exámenes solicitados por el doctor. El sistema generará el PDF y el QR automáticamente.
    </div>

    <div class="grid gap-5">
      @foreach($items as $item)
        <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h3 class="text-base font-semibold text-gray-900">{{ $item['nombre'] }}</h3>
              <p class="text-xs uppercase tracking-widest text-gray-500">No editable</p>
            </div>
            <span class="badge info">Examen solicitado</span>
          </div>

          <input type="hidden" name="items[{{ $item['key'] }}][nombre]" value="{{ $item['nombre'] }}">

          <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div>
              <label class="form-label" for="resultado_{{ $item['key'] }}">Resultado</label>
              <input
                id="resultado_{{ $item['key'] }}"
                name="items[{{ $item['key'] }}][resultado]"
                value="{{ old('items.'.$item['key'].'.resultado', $item['resultado']) }}"
                list="lab-result-values"
                class="form-input"
                required
              >
            </div>
            <div>
              <label class="form-label" for="unidad_{{ $item['key'] }}">Unidad de medida</label>
              <input
                id="unidad_{{ $item['key'] }}"
                name="items[{{ $item['key'] }}][unidad]"
                value="{{ old('items.'.$item['key'].'.unidad', $item['unidad']) }}"
                class="form-input"
              >
            </div>
            <div>
              <label class="form-label" for="referencia_{{ $item['key'] }}">Valor / rango de referencia</label>
              <input
                id="referencia_{{ $item['key'] }}"
                name="items[{{ $item['key'] }}][referencia]"
                value="{{ old('items.'.$item['key'].'.referencia', $item['referencia']) }}"
                class="form-input"
              >
            </div>
            <div>
              <label class="form-label" for="clasificacion_{{ $item['key'] }}">Clasificación</label>
              <select id="clasificacion_{{ $item['key'] }}" name="items[{{ $item['key'] }}][clasificacion]" class="form-select" required>
                @foreach(['normal' => 'Normal', 'alto' => 'Alto', 'bajo' => 'Bajo', 'critico' => 'Crítico'] as $value => $label)
                  <option value="{{ $value }}" @selected(old('items.'.$item['key'].'.clasificacion', $item['clasificacion']) === $value)>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label class="form-label" for="metodo_{{ $item['key'] }}">Método utilizado</label>
              <input
                id="metodo_{{ $item['key'] }}"
                name="items[{{ $item['key'] }}][metodo]"
                value="{{ old('items.'.$item['key'].'.metodo', $item['metodo']) }}"
                class="form-input"
              >
            </div>
            <div class="md:col-span-2 xl:col-span-3">
              <label class="form-label" for="observaciones_{{ $item['key'] }}">Observaciones particulares</label>
              <textarea id="observaciones_{{ $item['key'] }}" name="items[{{ $item['key'] }}][observaciones]" rows="3" class="form-textarea">{{ old('items.'.$item['key'].'.observaciones', $item['observaciones']) }}</textarea>
            </div>
          </div>
        </section>
      @endforeach
    </div>

    <div>
      <label class="form-label" for="observaciones_generales">Observaciones generales del informe</label>
      <textarea id="observaciones_generales" name="observaciones_generales" rows="4" class="form-textarea">{{ old('observaciones_generales', $resultado->observaciones_generales) }}</textarea>
    </div>

    <datalist id="lab-result-values">
      <option value="Positivo"></option>
      <option value="Negativo"></option>
      <option value="Reactivo"></option>
      <option value="No reactivo"></option>
      <option value="Detectado"></option>
      <option value="No detectado"></option>
    </datalist>

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
