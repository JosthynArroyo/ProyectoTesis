@extends('layouts.laboratorio')
@section('title', 'Pedidos de laboratorio firmados - '.$clinicIdentity->name())
@section('activeSidebar', 'pedidos')
@section('header-title', 'Pedidos Médicos (.p12)')
@section('header-subtitle', 'Gestión de órdenes firmadas digitalmente y carga de resultados')

@section('main')
<section class="space-y-6">
  <header class="card p-6">
    <form class="flex flex-wrap items-end gap-3" method="GET" action="{{ route('laboratorio.pedidos.index') }}">
      <div>
        <label class="form-label" for="estado">Filtrar por estado</label>
        <select id="estado" name="estado" class="form-select">
          <option value="all" @selected(($estado ?? 'all') === 'all')>Todos</option>
          <option value="pendiente_toma" @selected(($estado ?? '') === 'pendiente_toma')>Pendiente de muestra</option>
          <option value="muestra_tomada" @selected(($estado ?? '') === 'muestra_tomada')>Muestra tomada</option>
          <option value="resultado_listo" @selected(($estado ?? '') === 'resultado_listo')>Resultado listo</option>
        </select>
      </div>
      <button class="btn btn-outline btn-sm" type="submit">
        <i class="ri-filter-3-line"></i> Aplicar
      </button>
      @if(($estado ?? 'all') !== 'all')
        <a class="btn btn-ghost btn-sm" href="{{ route('laboratorio.pedidos.index') }}">
          <i class="ri-refresh-line"></i> Limpiar
        </a>
      @endif
    </form>
  </header>

  @if (session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  @if ($errors->any())
    <x-ui.alert tone="error">{{ $errors->first() }}</x-ui.alert>
  @endif

  @php
    $examNames = [
        'biometria_hematica' => 'Biometría Hemática',
        'plaquetas' => 'Plaquetas',
        'eritrosedimentacion' => 'Eritrosedimentación',
        'inv_hematozoario' => 'Inv. de Hematozoario',
        'grupo_sanguineo' => 'Grupo Sanguíneo',
        'reticulocitos' => 'Reticulocitos',
        'glucosa' => 'Glucosa',
        'glucosa_2pp' => 'Glucosa 2PP',
        'urea' => 'Urea',
        'creatinina' => 'Creatinina',
        'acido_urico' => 'Ácido Úrico',
        'colesterol_total' => 'Colesterol Total',
        'colesterol_hdl' => 'Colesterol HDL',
        'colesterol_ldl' => 'Colesterol LDL',
        'trigliceridos' => 'Triglicéridos',
        'bilirrubinas' => 'Bilirrubinas',
        'tgo_tgp' => 'T.G.O. / T.G.P.',
        'fosfatasa_alcalina' => 'Fosfatasa Alcalina',
        'amilasa' => 'Amilasa',
        'lipasa' => 'Lipasa',
        'cpk' => 'C.P.K.',
        'ck_mb' => 'C.K. Mb',
        't3_ft3_t4_ft4_tsh' => 'T3, FT3, T4, FT4, TSH',
        'anti_tpo' => 'Anti-TPO',
        'lh_fsh' => 'LH / FSH',
        'prolactina' => 'Prolactina',
        'insulina' => 'Insulina',
        'estradiol' => 'Estradiol',
        'progesterona' => 'Progesterona',
        'testosterona' => 'Testosterona',
        'hcg_beta' => 'H.C.G. Beta',
        'asto_pcr_fr' => 'A.S.T.O. / P.C.R. / F.R.',
        'vdrl' => 'V.D.R.L.',
        'widal_weil' => 'Widal-Weil',
        'brusella' => 'Brusella',
        'toxoplasma' => 'Toxoplasma',
        'rubeola' => 'Rubeola',
        'citomegalovirus' => 'Citomegalovirus',
        'herpes' => 'Herpes',
        'hepatitis' => 'Hepatitis',
        'helicobacter' => 'Helicobacter',
        'dengue' => 'Dengue',
        'psa_total_libre' => 'P.S.A. Total / Libre',
        'cea_afp' => 'C.E.A. / A.F.P.',
        'ca_125_15_3_19_9' => 'CA-125 / CA-15-3 / CA-19-9',
        'fisico_quimico' => 'Físico Químico Orina',
        'gram_gota' => 'Gram Gota Fresca',
        'cultivo_orina' => 'Cultivo Orina',
        'microalbuminuria' => 'Microalbuminuria',
        'coproparasitario' => 'Coproparasitario',
        'sangre_oculta' => 'Sangre Oculta Heces',
        'coprocultivo' => 'Coprocultivo',
        'rotavirus' => 'Rotavirus',
        'cultivo_secrecion' => 'Cultivo Secreción',
        'tincion_gram_baar' => 'Tinción Gram / BAAR',
        'sodio_potasio_cloro' => 'Sodio / Potasio / Cloro',
        'calcio_ionico' => 'Calcio / Calcio Iónico',
        'hierro_fosforo_litio' => 'Hierro / Fósforo / Litio',
        'magnesio' => 'Magnesio',
        'gasometria_arterial' => 'Gasometría Arterial',
        'mioglobina_stat' => 'Mioglobina STAT',
        'troponina_stat' => 'Troponina I STAT',
        'procalcitonina' => 'Procalcitonina',
        'liquido_cefalorraquideo' => 'Líquido Cefalorraquídeo',
        'liquido_pleural' => 'Líquido Pleural',
        'liquido_sinovial' => 'Líquido Sinovial',
    ];
  @endphp

  <div class="grid gap-6">
    @forelse($pedidos as $pedido)
      @php
        $badgeTone = match ($pedido->estado) {
            'pendiente_toma' => 'warning',
            'muestra_tomada' => 'info',
            'resultado_listo' => 'success',
            default => 'neutral'
        };
        $statusLabel = match ($pedido->estado) {
            'pendiente_toma' => 'Pendiente de muestra',
            'muestra_tomada' => 'Muestra tomada',
            'resultado_listo' => 'Resultado listo',
            default => $pedido->estado
        };
      @endphp
      <article class="card p-6" id="pedido-{{ $pedido->id }}">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <h2 class="text-lg font-semibold text-gray-900">Pedido de Laboratorio MVP #{{ sprintf('%06d', $pedido->id) }}</h2>
              <x-ui.badge :tone="$badgeTone">{{ $statusLabel }}</x-ui.badge>
            </div>
            <p class="mt-1 text-sm text-gray-500">Paciente: <strong>{{ $pedido->paciente->name }}</strong> (Ced: {{ $pedido->paciente->dni ?? 'N/D' }})</p>
            <p class="text-xs uppercase tracking-widest text-gray-400">Solicitado por: Dr. {{ $pedido->doctor->name }}</p>
          </div>
        </div>

        <div class="mt-4 grid gap-3 text-sm text-gray-600 sm:grid-cols-2">
          <div>
            <span class="text-gray-500">Fecha de emisión:</span>
            {{ $pedido->created_at->format('d/m/Y H:i') }}
          </div>
        </div>

        <!-- Lista de exámenes solicitados -->
        <div class="mt-4">
          <strong class="text-sm text-gray-700 block mb-1.5">Exámenes solicitados en el pedido:</strong>
          <div class="flex flex-wrap gap-1.5">
            @foreach($pedido->examenes ?? [] as $examKey)
              <span class="badge neutral text-xs">{{ $examNames[$examKey] ?? $examKey }}</span>
            @endforeach
          </div>
        </div>

        @if($pedido->resultado_resumen)
          <div class="mt-3 rounded-xl border border-gray-200 bg-gray-100/80 px-3 py-2 text-sm text-gray-800">
            <strong>Resumen del resultado:</strong> {{ $pedido->resultado_resumen }}
          </div>
        @endif

        <div class="mt-5 flex flex-wrap gap-3">
          @if($pedido->pdf_path)
            <a class="btn btn-outline" href="{{ route('laboratorio.pedidos.download-orden', $pedido->id) }}" target="_blank">
              <i class="ri-file-shield-line"></i> Ver orden firmada
            </a>
          @endif

          @if($pedido->resultado_path)
            <a class="btn btn-outline" href="{{ route('laboratorio.pedidos.download-resultado', $pedido->id) }}">
              <i class="ri-download-line"></i> Descargar informe resultados
            </a>
          @endif

          @if($pedido->estado === 'pendiente_toma')
            <form method="POST" action="{{ route('laboratorio.pedidos.muestra', $pedido->id) }}">
              @csrf
              <button class="btn btn-outline" type="submit">
                <i class="ri-test-tube-line"></i> Marcar muestra tomada
              </button>
            </form>
          @endif
        </div>

        @if($pedido->estado !== 'resultado_listo')
          <form class="mt-5 grid gap-4 md:grid-cols-2" method="POST" action="{{ route('laboratorio.pedidos.resultado', $pedido->id) }}" enctype="multipart/form-data">
            @csrf
            <div>
              <label for="resultado_pdf_{{ $pedido->id }}" class="form-label font-semibold">Cargar PDF de Resultados</label>
              <label class="mt-1 block rounded-xl border-2 border-dashed border-gray-300 bg-gray-50/70 px-3 py-4 text-center text-sm text-gray-500 hover:border-gray-300 hover:text-gray-700 cursor-pointer" data-dropzone>
                <span data-dropzone-text>Arrastra el PDF aquí o haz clic para seleccionar.</span>
                <input id="resultado_pdf_{{ $pedido->id }}" type="file" name="resultado_pdf" accept="application/pdf" required class="sr-only" data-dropzone-input>
              </label>
            </div>
            <div>
              <label for="resultado_resumen_{{ $pedido->id }}" class="form-label font-semibold">Resumen o Observaciones Clínicas</label>
              <textarea id="resultado_resumen_{{ $pedido->id }}" name="resultado_resumen" rows="3" required class="form-textarea" placeholder="Escriba un breve resumen de los valores encontrados o conclusiones del análisis..."></textarea>
            </div>
            <div class="md:col-span-2">
              <button class="btn btn-primary" type="submit">
                <i class="ri-upload-2-line"></i> Publicar y Enviar Resultados
              </button>
            </div>
          </form>
        @endif
      </article>
    @empty
      <x-ui.empty-state
        title="No hay pedidos médicos registrados"
        message="Cuando los doctores emitan pedidos de laboratorio firmados digitalmente aparecerán aquí."
      />
    @endforelse
  </div>

  <x-ui.pagination :paginator="$pedidos" />
</section>

@push('scripts')
  @vite('resources/js/laboratorio/ordenes.js')
@endpush
@endsection
