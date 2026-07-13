@extends('layouts.doctor')
@section('title', 'Historial de Pedidos de Laboratorio')
@section('activeSidebar', 'pedidos-laboratorio')
@section('header-title', 'Historial de pedidos de laboratorio')
@section('header-subtitle', 'Consulta los pedidos de laboratorio generados')

@section('main')
<section class="space-y-6">
  @if (session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  <div class="card p-6">
    <div class="table-shell table-responsive-cards">
      <table class="table">
        <thead>
          <tr>
            <th>Paciente</th>
            <th>Nro. Pedido</th>
            <th>Fecha de emisión</th>
            <th>Cantidad Exámenes</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
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
              $examList = collect($pedido->examenes ?? [])
                ->map(fn($k) => $examNames[$k] ?? $k)
                ->join(', ');
            @endphp
            <tr>
              <td data-label="Paciente">{{ optional($pedido->paciente)->name ?? '-' }}</td>
              <td data-label="Nro. Pedido">#{{ sprintf('%06d', $pedido->id) }}</td>
              <td data-label="Fecha de emisión">{{ $pedido->created_at->format('d/m/Y H:i') }}</td>
              <td data-label="Exámenes" title="{{ $examList }}">
                <span class="badge neutral text-xs">{{ count($pedido->examenes ?? []) }} examen(es)</span>
              </td>
              <td data-label="Estado">
                <x-ui.badge :tone="$badgeTone">{{ $statusLabel }}</x-ui.badge>
              </td>
              <td data-label="Acciones">
                @if($pedido->pdf_path)
                  <a class="btn btn-outline btn-sm" href="{{ route('doctor.pedidos-laboratorio.download', $pedido->id) }}" target="_blank">
                    <i class="ri-file-pdf-line"></i> Ver PDF
                  </a>
                @else
                  <span class="text-xs text-gray-500">Sin PDF</span>
                @endif
                <a class="btn btn-outline btn-sm ml-1" href="{{ route('doctor.pedidos-laboratorio.edit', $pedido->cita_id) }}">
                  <i class="ri-edit-line"></i> Editar
                </a>
                @if(in_array($pedido->envio_estado, ['failed', 'queued', 'sending'], true))
                  <form class="inline-block ml-1" method="POST" action="{{ route('doctor.pedidos-laboratorio.resend', $pedido) }}">
                    @csrf
                    <button type="submit" class="btn btn-outline btn-sm">
                      <i class="ri-mail-send-line"></i> Reenviar
                    </button>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-center">Aún no hay pedidos de laboratorio generados.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="mt-4">
    {{ $pedidos->links() }}
  </div>
</section>
@endsection
