@extends('layouts.doctor')
@section('title', isset($pedido) ? 'Editar pedido de laboratorio' : 'Generar pedido de laboratorio')
@section('activeSidebar', 'citas')
@section('header-title', isset($pedido) ? 'Editar pedido de laboratorio' : 'Generar pedido de laboratorio')
@section('header-subtitle', 'Documento clínico asociado a una cita realizada')

@section('main')
<div class="space-y-6">
  @if ($errors->any())
    <x-ui.alert tone="error">
      <div class="space-y-1">
        <p class="font-semibold">{{ isset($pedido) ? 'No se pudo guardar los cambios del pedido de laboratorio.' : 'No se pudo generar el pedido de laboratorio.' }}</p>
        <ul class="list-disc pl-5 text-sm">
          @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    </x-ui.alert>
  @endif

  <section class="grid gap-4 lg:grid-cols-3">
    <article class="card p-5">
      <p class="text-xs uppercase tracking-widest text-gray-500 dark:text-gray-400">Paciente</p>
      <p class="mt-2 font-semibold text-gray-900 dark:text-slate-50">{{ $cita->paciente?->name ?? '-' }}</p>
      <p class="text-sm text-gray-600 dark:text-slate-300">{{ $cita->paciente?->dni ?: 'Documento no registrado' }}</p>
    </article>
    <article class="card p-5">
      <p class="text-xs uppercase tracking-widest text-gray-500 dark:text-gray-400">Cita</p>
      <p class="mt-2 font-semibold text-gray-900 dark:text-slate-50">#{{ $cita->id }}</p>
      <p class="text-sm text-gray-600 dark:text-slate-300">Fecha: {{ \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') }}</p>
    </article>
    <article class="card p-5">
      <p class="text-xs uppercase tracking-widest text-gray-500 dark:text-gray-400">Doctor</p>
      <p class="mt-2 font-semibold text-gray-900 dark:text-slate-50">{{ $cita->doctor?->name ?? '-' }}</p>
      <p class="text-sm text-gray-600 dark:text-slate-300">La orden incluirá CSV y QR de verificación.</p>
    </article>
  </section>

  <form method="POST" action="{{ isset($pedido) ? route('doctor.pedidos-laboratorio.update', $cita) : route('doctor.pedidos-laboratorio.store', $cita) }}" class="card p-6 space-y-6">
    @csrf

    <div>
      <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-50">Seleccionar Exámenes</h3>
      <p class="text-sm text-gray-500 dark:text-slate-400">Marca los exámenes que deseas solicitar para el paciente.</p>
    </div>

    @php
      $categorias = [
          'HEMATOLOGIA' => [
              'biometria_hematica' => 'Biometría Hemática completa',
              'plaquetas' => 'Plaquetas',
              'eritrosedimentacion' => 'Eritrosedimentacion',
              'inv_hematozoario' => 'Inv. de hematozoario',
              'grupo_sanguineo' => 'Grupo sanguineo',
              'reticulocitos' => 'Reticulocitos',
          ],
          'QUIMICA CINETICA' => [
              'glucosa' => 'Glucosa',
              'glucosa_2pp' => 'Glucosa 2PP',
              'urea' => 'Urea',
              'creatinina' => 'Creatinina',
              'acido_urico' => 'Acido urico',
              'colesterol_total' => 'Colesterol total',
              'colesterol_hdl' => 'Colesterol HDL',
              'colesterol_ldl' => 'Colesterol LDL',
              'trigliceridos' => 'Trigliceridos',
              'bilirrubinas' => 'Bilirrubinas total, dir. e indir.',
          ],
          'ENZIMAS CINETICA' => [
              'tgo_tgp' => 'T.G.O. / T.G.P.',
              'fosfatasa_alcalina' => 'Fosfatasa alcalina',
              'amilasa' => 'Amilasa',
              'lipasa' => 'Lipasa',
              'cpk' => 'C.P.K.',
              'ck_mb' => 'C.K. Mb',
          ],
          'HORMONAS' => [
              't3_ft3_t4_ft4_tsh' => 'T3, FT3, T4, FT4, TSH',
              'anti_tpo' => 'Anti - TPO',
              'lh_fsh' => 'LH / FSH',
              'prolactina' => 'Prolactina',
              'insulina' => 'Insulina',
              'estradiol' => 'Estradiol',
              'progesterona' => 'Progesterona',
              'testosterona' => 'Testosterona',
              'hcg_beta' => 'H.C.G. Beta (Embarazo)',
          ],
          'SERO INMUNOLOGIA' => [
              'asto_pcr_fr' => 'A.S.T.O. / P.C.R. / F.R.',
              'vdrl' => 'V.D.R.L.',
              'widal_weil' => 'Widal - Weil Felix',
              'brusella' => 'Brusella Abortus',
              'toxoplasma' => 'Toxoplasma IgG / IgM',
              'rubeola' => 'Rubeola IgG / IgM',
              'citomegalovirus' => 'Citomegalovirus IgG / IgM',
              'herpes' => 'Herpes I / II IgG / IgM',
              'hepatitis' => 'Hepatitis A / B / C',
              'helicobacter' => 'Helicobacter pylori',
              'dengue' => 'Dengue IgG / IgM',
          ],
          'MARCADORES TUMORALES' => [
              'psa_total_libre' => 'P.S.A. Total / Libre',
              'cea_afp' => 'C.E.A. / A.F.P.',
              'ca_125_15_3_19_9' => 'CA-125 / CA-15-3 / CA-19-9',
          ],
          'ORINA' => [
              'fisico_quimico' => 'Fisico quimico y sedimento',
              'gram_gota' => 'Gram de gota fresca',
              'cultivo_orina' => 'Cultivo y antibiograma',
              'microalbuminuria' => 'Microalbuminuria',
          ],
          'HECES' => [
              'coproparasitario' => 'Coproparasitario',
              'sangre_oculta' => 'Sangre oculta',
              'coprocultivo' => 'Coprocultivo',
              'rotavirus' => 'Rotavirus',
          ],
          'MICROBIOLOGIA' => [
              'cultivo_secrecion' => 'Cultivo y antibiograma',
              'tincion_gram_baar' => 'Tincion Gram / BAAR',
          ],
          'ELECTROLITOS' => [
              'sodio_potasio_cloro' => 'Sodio / Potasio / Cloro',
              'calcio_ionico' => 'Calcio / Calcio ionico',
              'hierro_fosforo_litio' => 'Hierro / Fosforo / Litio',
              'magnesio' => 'Magnesio',
          ],
          'CUADRO CRITICO' => [
              'gasometria_arterial' => 'Gasometria arterial',
              'mioglobina_stat' => 'Mioglobina STAT',
              'troponina_stat' => 'Troponina I STAT',
              'procalcitonina' => 'Procalcitonina',
          ],
          'VARIOS' => [
              'liquido_cefalorraquideo' => 'Liquido cefalorraquideo',
              'liquido_pleural' => 'Liquido pleural',
              'liquido_sinovial' => 'Liquido sinovial',
          ],
      ];
    @endphp

    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
      @foreach($categorias as $categoria => $items)
        <div class="rounded-2xl border border-gray-200 bg-gray-50/70 dark:bg-slate-900/90 dark:border-slate-700 p-4">
          <h4 class="mb-3 flex items-center gap-2 border-b border-gray-200 dark:border-slate-700 pb-2 font-bold text-gray-800 dark:text-slate-100">
            <i class="ri-flask-line text-emerald-600 dark:text-emerald-400"></i> {{ $categoria }}
          </h4>
          <div class="space-y-2">
            @foreach($items as $key => $label)
              <label class="flex cursor-pointer items-start gap-2 text-sm">
                <input type="checkbox" name="examenes[]" value="{{ $key }}" class="mt-0.5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-900 dark:text-emerald-400 dark:focus:ring-emerald-500/30" @checked(isset($pedido) && is_array($pedido->examenes) && in_array($key, $pedido->examenes))>
                <span class="text-gray-700 dark:text-slate-300">{{ $label }}</span>
              </label>
            @endforeach
          </div>
        </div>
      @endforeach
    </div>

    <div class="rounded-2xl border border-emerald-100 bg-emerald-50/60 dark:border-emerald-900/40 dark:bg-emerald-950/30 p-4 text-sm text-emerald-900 dark:text-emerald-100">
      El PDF final incluirá un CSV único y un código QR para verificación pública.
    </div>

    <x-ui.form-actions>
      <x-slot:left>
        <a href="{{ route('doctor.citas') }}" class="btn btn-ghost">Cancelar</a>
      </x-slot>
      <button type="submit" class="btn btn-primary">
        <i class="ri-file-list-3-line"></i> {{ isset($pedido) ? 'Guardar cambios' : 'Generar pedido' }}
      </button>
    </x-ui.form-actions>
  </form>
</div>
@endsection
