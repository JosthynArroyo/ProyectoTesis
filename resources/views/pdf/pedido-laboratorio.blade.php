<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de Laboratorio #{{ $pedido->id }}</title>
    <style>
        {!! $pdfCss !!}
        @page { margin: 20px 30px 60px 30px; }
        .footer {
            position: fixed;
            bottom: -45px;
            left: 0;
            right: 0;
            height: 30px;
            border-top: 1px solid #cbd5e1;
            padding-top: 5px;
        }
        .footer table {
            width: 100%;
            border-collapse: collapse;
            border: none;
        }
        .footer td {
            font-size: 8px;
            color: #64748b;
            font-family: 'DejaVu Sans', sans-serif;
            border: none;
            padding: 0;
        }
        .summary-grid {
            margin: 14px 0 16px;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
        .summary-card {
            border: 1px solid #dbe4ee;
            border-radius: 10px;
            padding: 10px 12px;
            background: #f8fafc;
        }
        .summary-label {
            display: block;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #0f766e;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .summary-value {
            font-size: 10px;
            color: #0f172a;
            line-height: 1.45;
        }
        .exam-section {
            margin-top: 12px;
        }
        .exam-group {
            margin-top: 10px;
            border: 1px solid #dbe4ee;
            border-radius: 10px;
            overflow: hidden;
            page-break-inside: avoid;
        }
        .exam-group-header {
            background: #f1f5f9;
            color: #0f766e;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            padding: 8px 10px;
            border-bottom: 1px solid #dbe4ee;
        }
        .exam-group table {
            width: 100%;
            border-collapse: collapse;
        }
        .exam-group td {
            border: none;
            border-bottom: 1px solid #eef2f7;
            padding: 7px 10px;
            font-size: 9.5px;
            vertical-align: top;
        }
        .exam-group tr:last-child td {
            border-bottom: none;
        }
        .check-box {
            display: inline-block;
            width: 14px;
            height: 14px;
            margin-right: 6px;
            border: 1px solid #94a3b8;
            border-radius: 3px;
            text-align: center;
            line-height: 12px;
            font-size: 11px;
            font-weight: 700;
            color: #0f766e;
            vertical-align: middle;
        }
        .check-box.is-checked {
            background: #d1fae5;
            border-color: #0f766e;
        }
        .exam-name {
            color: #0f172a;
        }
        .exam-note {
            color: #64748b;
            font-size: 9px;
            margin-left: 20px;
            margin-top: 2px;
        }
        .section-block {
            margin-top: 12px;
            padding: 12px;
            border: 1px solid #dbe4ee;
            border-radius: 10px;
            background: #fff;
        }
        .section-title {
            margin: 0 0 6px;
            font-size: 11px;
            color: #0f172a;
        }
        .exam-grid-table {
            width: 100%;
            border-collapse: collapse;
            border: none;
            table-layout: fixed;
            margin-top: 10px;
        }
        .exam-grid-table tr {
            page-break-inside: avoid;
        }
        .exam-grid-table td {
            border: none;
            padding: 5px;
            vertical-align: top;
            width: 33.33%;
        }
        .category-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px;
            box-sizing: border-box;
        }
        .category-header {
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 5px;
            margin-bottom: 8px;
            line-height: 12px;
        }
        .beaker-icon {
            fill: #059669;
            width: 11px;
            height: 11px;
            vertical-align: middle;
            margin-right: 4px;
            display: inline-block;
        }
        .category-title {
            font-size: 9px;
            font-weight: bold;
            color: #1e293b;
            text-transform: uppercase;
            vertical-align: middle;
            font-family: 'DejaVu Sans', sans-serif;
        }
        .exam-list-table {
            width: 100%;
            border-collapse: collapse;
            border: none;
        }
        .exam-list-table td {
            padding: 3px 0;
            border: none;
            font-size: 8.5px;
            color: #475569;
            vertical-align: middle;
            line-height: 1.15;
        }
        .exam-checkbox {
            display: inline-block;
            width: 11px;
            height: 11px;
            border: 1px solid #cbd5e1;
            border-radius: 2px;
            background-color: #ffffff;
            text-align: center;
            line-height: 9px;
            vertical-align: middle;
            margin-right: 6px;
        }
        .exam-checkbox.checked {
            background-color: #059669;
            border-color: #059669;
        }
        .exam-checkbox .checkmark {
            color: #ffffff;
            font-size: 7.5px;
            font-weight: bold;
            font-family: 'DejaVu Sans', sans-serif;
            vertical-align: middle;
        }
        .exam-label-text {
            vertical-align: middle;
            font-family: 'DejaVu Sans', sans-serif;
        }
    </style>
</head>
<body>
    @php
        $categorias = [
            'HEMATOLOGIA' => [
                'biometria_hematica' => 'Biometria hematica completa',
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
        $examenesSeleccionados = array_fill_keys($pedido->examenes ?? [], true);
        $cita = $pedido->cita;
        $nota = $cita?->notaSoap;
        $diagnosticoMotivo = trim((string) (
            $nota?->assessment
            ?: $nota?->subjetivo_motivo
            ?: $cita?->motivo_consulta
            ?: ''
        ));
        $observaciones = trim((string) (
            $nota?->plan_notas
            ?: $nota?->plan_general
            ?: $nota?->follow_up_notes
            ?: ''
        ));
    @endphp

    <div class="footer">
        <table>
            <tr>
                <td style="text-align: left;">
                    Código Seguro de Verificación (CSV): <strong>{{ $csv }}</strong>
                </td>
                <td style="text-align: right;">
                    Verificación en: {{ $verificationUrl }}
                </td>
            </tr>
        </table>
    </div>

    <div class="header">
        <table width="100%">
            <tr>
                <td width="60%">
                    @if($logoBase64)
                        <img src="{{ $logoBase64 }}" class="logo" alt="Logo">
                    @else
                        <div class="logo-placeholder">LOGO</div>
                    @endif
                    <h1 class="clinic-name">{{ $clinica }}</h1>
                    <p class="clinic-slogan">{{ $slogan }}</p>
                </td>
                <td width="40%" class="text-right">
                    <div class="order-badge">ORDEN DE LABORATORIO</div>
                    <p class="order-number">Nro: <strong>{{ sprintf('%06d', $pedido->id) }}</strong></p>
                    <p class="order-date">Fecha: {{ $pedido->created_at?->format('d/m/Y H:i') ?? 'No disponible' }}</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="summary-grid">
        <div class="summary-card">
            <span class="summary-label">Paciente</span>
            @if(isset($cita) && $cita && $cita->dependiente_id)
                <div class="summary-value">{{ $cita->nombrePacienteReal() }}</div>
                <div class="summary-value">Documento: {{ $cita->dniPacienteReal() }}</div>
                <div class="summary-value">Representante: {{ $pedido->paciente->name ?? '-' }} (C.I. {{ $pedido->paciente->dni ?? '-' }})</div>
            @else
                <div class="summary-value">{{ $pedido->paciente->name ?? '-' }}</div>
                <div class="summary-value">Documento: {{ $pedido->paciente->dni ?? 'N/D' }}</div>
                <div class="summary-value">Telefono: {{ $pedido->paciente->telefono ?? 'N/D' }}</div>
            @endif
        </div>
        <div class="summary-card">
            <span class="summary-label">Medico solicitante</span>
            <div class="summary-value">{{ $pedido->doctor->name ?? '-' }}</div>
            <div class="summary-value">Documento: {{ $pedido->doctor->dni ?? 'N/D' }}</div>
            <div class="summary-value">Especialidad: {{ $cita?->especialidad?->nombre ?? 'No registrada' }}</div>
        </div>
        </div>

    <div class="section-block avoid-break">
        <p class="section-title"><strong>Fecha de emision:</strong> {{ $pedido->created_at?->format('d/m/Y H:i') ?? 'No disponible' }}</p>
        <p class="section-title"><strong>Fecha de la cita:</strong> {{ $cita?->fecha?->format('d/m/Y') ?? '-' }} {{ $cita?->hora ? substr((string) $cita->hora, 0, 5) : '' }}</p>
        <p class="section-title"><strong>Motivo / diagnostico:</strong></p>
        <div class="summary-value">{{ $diagnosticoMotivo !== '' ? $diagnosticoMotivo : 'No especificado' }}</div>
    </div>

    @if($observaciones !== '')
        <div class="section-block avoid-break">
            <p class="section-title"><strong>Observaciones e indicaciones</strong></p>
            <div class="summary-value">{{ $observaciones }}</div>
        </div>
    @endif

    <div class="exam-section">
        <div class="section-title" style="font-size: 11px; font-weight: 700; color: #0f766e; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 8px;">Exámenes solicitados</div>
        
        <table class="exam-grid-table">
            @foreach(array_chunk(array_keys($categorias), 3) as $chunk)
                <tr>
                    @foreach($chunk as $catName)
                        @php
                            $items = $categorias[$catName];
                        @endphp
                        <td>
                            <div class="category-card">
                                <div class="category-header">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="beaker-icon">
                                        <path d="M19 19.5L14 10.5V5H16V3H8V5H10V10.5L5 19.5C4.4 20.6 5.2 22 6.5 22H17.5C18.8 22 19.6 20.6 19 19.5ZM7.6 18L10.9 12H13.1L16.4 18H7.6Z"/>
                                    </svg>
                                    <span class="category-title">{{ $catName }}</span>
                                </div>
                                <table class="exam-list-table">
                                    @foreach($items as $key => $label)
                                        @php $selected = isset($examenesSeleccionados[$key]); @endphp
                                        <tr>
                                            <td>
                                                <span class="exam-checkbox {{ $selected ? 'checked' : '' }}">
                                                    @if($selected)
                                                        <span class="checkmark">✓</span>
                                                    @else
                                                        &nbsp;
                                                    @endif
                                                </span>
                                                <span class="exam-label-text">{{ $label }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                            </div>
                        </td>
                    @endforeach
                    @if(count($chunk) < 3)
                        @for($i = 0; $i < 3 - count($chunk); $i++)
                            <td>&nbsp;</td>
                        @endfor
                    @endif
                </tr>
            @endforeach
        </table>
    </div>

    <div class="verification-panel avoid-break">
        <div>
            <div class="verification-label">Verificacion publica</div>
            <div class="verification-code">CSV: {{ $csv ?? 'N/D' }}</div>
            <div class="verification-url">{{ $verificationUrl ?? '' }}</div>
        </div>
        @if(!empty($qrDataUri))
            <img class="verification-qr" src="{{ $qrDataUri }}" alt="Codigo QR de verificacion">
        @endif
    </div>
</body>
</html>
