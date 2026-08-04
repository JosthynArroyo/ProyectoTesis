<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Verificación de Recibo - {{ $receipt->folio_recibo }}</title>
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f3f4f6;
            color: #1f2937;
            margin: 0;
            padding: 24px 16px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            box-sizing: border-box;
        }
        .card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
            max-width: 480px;
            width: 100%;
            padding: 32px;
            box-sizing: border-box;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 20px;
        }
        .status-pagado {
            background-color: #d1fae5;
            color: #047857;
            border: 1px solid #6ee7b7;
        }
        .status-anulado {
            background-color: #ffe4e6;
            color: #be123c;
            border: 1px solid #fda4af;
        }
        .title {
            font-size: 22px;
            font-weight: 700;
            color: #111827;
            margin: 0 0 6px 0;
        }
        .subtitle {
            font-size: 13px;
            color: #6b7280;
            margin: 0 0 24px 0;
        }
        .detail-group {
            border-top: 1px solid #f3f4f6;
            padding-top: 16px;
            margin-top: 16px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 14px;
        }
        .detail-label {
            color: #6b7280;
            font-weight: 500;
        }
        .detail-value {
            color: #111827;
            font-weight: 600;
            text-align: right;
        }
        .footer {
            margin-top: 28px;
            padding-top: 16px;
            border-top: 1px solid #f3f4f6;
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="card">
        @if($estadoActual === 'ANULADO')
            <div class="status-badge status-anulado">Recibo Anulado</div>
        @else
            <div class="status-badge status-pagado">Recibo de pago verificado</div>
        @endif

        <h1 class="title">Verificación de Recibo</h1>
        <p class="subtitle">Documento financiero auténtico registrado en la Clínica</p>

        <div class="detail-group">
            <div class="detail-row">
                <span class="detail-label">Folio Recibo:</span>
                <span class="detail-value">{{ $receipt->folio_recibo }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Emisor:</span>
                <span class="detail-value">Clínica Don Bosco</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Paciente:</span>
                <span class="detail-value">{{ $protectedName }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Fecha de Emisión:</span>
                <span class="detail-value">{{ $receipt->emitido_en?->format('Y-m-d H:i') ?? 'N/D' }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Método de Pago:</span>
                <span class="detail-value">{{ strtoupper((string) $receipt->metodo_pago) }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Monto:</span>
                <span class="detail-value">${{ number_format((float) $receipt->monto, 2) }} {{ $receipt->pago?->moneda ?: 'USD' }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Estado Contable:</span>
                <span class="detail-value">{{ $estadoActual }}</span>
            </div>
        </div>

        <div class="footer">
            Clínica Don Bosco — Plataforma de Verificación Financiera
        </div>
    </div>
</body>
</html>
