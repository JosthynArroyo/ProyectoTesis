<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Informe de resultados de laboratorio</title>
    <style>{{ $pdfCss }}</style>
</head>
<body>
    @php
        $paciente = $pedido->cita?->dependiente_id && $pedido->cita?->dependiente
            ? $pedido->cita->dependiente
            : $pedido->cita?->paciente;
        $representante = $pedido->cita?->dependiente_id && $pedido->cita?->dependiente
            ? $pedido->cita?->paciente
            : null;
        $dateOrder = $pedido->created_at?->format('d/m/Y H:i') ?? '-';
        $datePublished = $resultado->publicado_at?->format('d/m/Y H:i') ?? '-';
    @endphp

    <div class="page">
        <div class="sheet">
            <div class="header avoid-break">
                <div class="brand-block">
                    @if(!empty($logoBase64))
                        <img class="logo" src="{{ $logoBase64 }}" alt="{{ $clinica }}">
                    @else
                        <div class="logo" aria-hidden="true"></div>
                    @endif
                    <div>
                        <h1 class="clinic-title">{{ $clinica }}</h1>
                        <div class="clinic-sub">Informe de resultados de laboratorio</div>
                    </div>
                </div>
                <div class="small soft" style="text-align:right">
                    <div><b>Orden:</b> #{{ $pedido->id }}</div>
                    <div><b>Informe:</b> #INF-{{ $pedido->id }}-V{{ $resultado->version }}</div>
                    <div><b>CSV:</b> {{ $csv ?? 'N/D' }}</div>
                    <div><b>Publicado:</b> {{ $datePublished }}</div>
                </div>
            </div>

            <div class="report-summary avoid-break">
                <div class="report-summary-grid">
                    <div class="report-summary-item"><b>Paciente:</b> {{ $pedido->nombrePacienteReal() }}</div>
                    <div class="report-summary-item"><b>Documento:</b> {{ $pedido->dniPacienteReal() ?? 'N/D' }}</div>
                    @if($representante)
                        <div class="report-summary-item"><b>Representante:</b> {{ $representante->name }}</div>
                    @endif
                    <div class="report-summary-item"><b>Doctor solicitante:</b> {{ $pedido->doctor?->name ?? '-' }}</div>
                    <div class="report-summary-item"><b>Fecha de orden:</b> {{ $dateOrder }}</div>
                    <div class="report-summary-item"><b>Procesamiento / toma de muestra:</b> {{ $pedido->resultado_publicado_at?->format('d/m/Y H:i') ?? $datePublished }}</div>
                    <div class="report-summary-item"><b>Responsable laboratorio:</b> {{ $resultado->laboratorio?->name ?? '-' }}</div>
                    <div class="report-summary-item"><b>Versión:</b> {{ $resultado->version }}</div>
                </div>
            </div>

            <div class="divider"></div>

            <div class="section avoid-break">
                <h3>Resultados</h3>
                <table class="result-table">
                    <thead>
                        <tr>
                            <th style="width:17%">Examen</th>
                            <th style="width:16%">Resultado</th>
                            <th style="width:10%">Unidad</th>
                            <th style="width:16%">Referencia</th>
                            <th style="width:10%">Clasificación</th>
                            <th style="width:13%">Método</th>
                            <th style="width:18%">Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(($resultado->resultado_items ?? []) as $item)
                            <tr>
                                <td><strong>{{ $item['nombre'] ?? $item['key'] ?? 'Examen' }}</strong></td>
                                <td>{{ $item['resultado'] ?? '-' }}</td>
                                <td>{{ $item['unidad'] ?? '-' }}</td>
                                <td>{{ $item['referencia'] ?? '-' }}</td>
                                <td><span class="result-badge {{ $item['clasificacion'] ?? 'normal' }}">{{ $item['clasificacion'] ?? 'normal' }}</span></td>
                                <td>{{ $item['metodo'] ?? '-' }}</td>
                                <td class="result-note">{{ $item['observaciones'] ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if(!empty($resultado->observaciones_generales))
                <div class="section avoid-break">
                    <h3>Observaciones generales</h3>
                    <div class="preserve">{{ $resultado->observaciones_generales }}</div>
                </div>
            @endif

            <div class="verification-panel avoid-break">
                <div>
                    <div class="verification-label">Verificación pública</div>
                    <div class="verification-code">CSV: {{ $csv ?? 'N/D' }}</div>
                    <div class="verification-url">{{ $verificationUrl ?? '' }}</div>
                </div>
                @if(!empty($qrDataUri))
                    <img class="verification-qr" src="{{ $qrDataUri }}" alt="Código QR de verificación">
                @endif
            </div>

            <div class="lab-footer">
                <div>
                    <strong>Emitido por {{ $clinica }}</strong><br>
                    Documento válido únicamente con su versión y CSV de verificación.
                </div>
                <div class="text-right">
                    <strong>{{ $resultado->laboratorio?->name ?? 'Laboratorio' }}</strong><br>
                </div>
            </div>
        </div>
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->get_font('DejaVu Sans', 'normal');
            $pdf->page_text(500, 812, 'Página {PAGE_NUM} de {PAGE_COUNT}', $font, 8, array(100, 116, 139));
        }
    </script>
</body>
</html>
