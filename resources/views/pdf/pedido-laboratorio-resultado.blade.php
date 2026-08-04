<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Informe de resultados de laboratorio</title>
    <style>{{ $pdfCss }}</style>
</head>
<body>
    @php
        $paciente = !empty($pedido->cita?->dependiente)
            ? $pedido->cita->dependiente
            : ($pedido->cita?->paciente ?? $pedido->paciente);
        $representante = !empty($pedido->cita?->dependiente)
            ? ($pedido->cita?->paciente ?? $pedido->paciente)
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

            <div class="section">
                <h3 style="margin-bottom: 12px;">Resultados de Laboratorio</h3>

                @foreach(($resultado->resultado_items ?? []) as $item)
                    @php
                        $nameClean = ($item['nombre'] ?? $item['key'] ?? 'Examen');
                        if ($nameClean === 'Brusella Abortus') {
                            $nameClean = 'Brucella abortus';
                        }
                    @endphp
                    <div class="result-block avoid-break" style="margin-bottom: 14px; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; background-color: #ffffff;">
                        <table style="width: 100%; border-collapse: collapse; margin-bottom: 6px;">
                            <thead>
                                <tr style="border-bottom: 1px solid #cbd5e1; font-size: 9pt; color: #475569; text-transform: uppercase;">
                                    <th style="text-align: left; width: 32%; padding-bottom: 4px;">Componente / Analito</th>
                                    <th style="text-align: left; width: 18%; padding-bottom: 4px;">Resultado</th>
                                    <th style="text-align: left; width: 15%; padding-bottom: 4px;">Unidad</th>
                                    <th style="text-align: left; width: 20%; padding-bottom: 4px;">Intervalo/valor de referencia</th>
                                    <th style="text-align: right; width: 15%; padding-bottom: 4px;">Clasificación</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr style="font-size: 10pt;">
                                    <td style="padding-top: 6px; font-weight: bold; color: #0f172a;">{{ $nameClean }}</td>
                                    <td style="padding-top: 6px; font-weight: bold; color: #0f172a;">{{ $item['resultado'] ?? '-' }}</td>
                                    <td style="padding-top: 6px; color: #334155;">{{ $item['unidad'] ?? 'No aplica' }}</td>
                                    <td style="padding-top: 6px; color: #334155;">{{ $item['referencia'] ?? 'No aplica' }}</td>
                                    <td style="padding-top: 6px; text-align: right;">
                                        @php
                                            $rawClass = $item['clasificacion'] ?? 'normal';
                                            $badgeClass = match($rawClass) {
                                                'critico', 'critical_low', 'critical_high' => 'critico',
                                                'alto', 'high', 'abnormal' => 'alto',
                                                'bajo', 'low' => 'bajo',
                                                'normal' => 'normal',
                                                default => 'normal'
                                            };
                                            $badgeLabel = match($rawClass) {
                                                'critical_low' => 'Crítico Bajo',
                                                'critical_high' => 'Crítico Alto',
                                                'high', 'alto' => 'Alto',
                                                'low', 'bajo' => 'Bajo',
                                                'abnormal' => 'Anormal',
                                                'not_applicable' => 'No aplica',
                                                default => 'Normal'
                                            };
                                        @endphp
                                        <span class="result-badge {{ $badgeClass }}">{{ $badgeLabel }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <div style="font-size: 8.5pt; color: #64748b; line-height: 1.45; border-top: 1px dashed #e2e8f0; padding-top: 6px; margin-top: 4px;">
                            <span><strong>Método utilizado:</strong> {{ $item['metodo'] ?? 'Método institucional' }}</span>
                            @if(!empty($item['observaciones']))
                                <span style="margin-left: 14px;"><strong>Notas:</strong> {{ $item['observaciones'] }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @if(!empty($resultado->observaciones_generales))
                <div class="section avoid-break" style="margin-top: 16px;">
                    <h3 style="margin-bottom: 8px;">Observaciones generales del informe</h3>
                    <div class="preserve" style="font-size: 9pt; line-height: 1.45; color: #1e293b; background: #f8fafc; padding: 10px; border-radius: 6px; border: 1px solid #e2e8f0;">
                        {{ $resultado->observaciones_generales }}
                    </div>
                </div>
            @endif

            <div class="verification-panel avoid-break" style="margin-top: 20px;">
                <div>
                    <div class="verification-label">Verificación pública de autenticidad</div>
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
                    Documento firmado y verificado electrónicamente.
                </div>
                <div class="text-right">
                    <strong>{{ $resultado->laboratorio?->name ?? 'Laboratorio Clínico' }}</strong><br>
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
