<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recibo de Pago {{ $receipt->folio_recibo }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .logo { height: 52px; }
        .title { font-size: 18px; font-weight: 700; margin: 0; }
        .muted { color: #6b7280; }
        .grid { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .grid td { border: 1px solid #e5e7eb; padding: 8px; vertical-align: top; }
        .label { font-size: 10px; text-transform: uppercase; color: #6b7280; }
        .value { font-size: 12px; font-weight: 600; margin-top: 3px; }
        .paid { margin-top: 16px; padding: 10px; text-align: center; border: 2px solid #059669; color: #047857; font-size: 18px; font-weight: 700; }
        .footer { margin-top: 12px; font-size: 11px; color: #4b5563; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <p class="title">Recibo de Pago</p>
            <p class="muted">Folio recibo: {{ $receipt->folio_recibo }}</p>
            <p class="muted">Folio orden: {{ $pago->folio_unico ?? 'N/D' }}</p>
        </div>
        @if(!empty($logoBase64))
            <img class="logo" src="{{ $logoBase64 }}" alt="Logo">
        @endif
    </div>

    <table class="grid">
        <tr>
            <td>
                <div class="label">Paciente</div>
                <div class="value">{{ $pago->paciente?->name ?? 'N/D' }}</div>
            </td>
            <td>
                <div class="label">Cédula</div>
                <div class="value">{{ $pago->paciente?->dni ?? 'N/D' }}</div>
            </td>
            <td>
                <div class="label">Cita</div>
                <div class="value">#{{ $pago->cita_id }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Método de pago</div>
                <div class="value">{{ strtoupper((string) $receipt->metodo_pago) }}</div>
            </td>
            <td>
                <div class="label">Monto</div>
                <div class="value">{{ number_format((float) $receipt->monto, 2) }} {{ $pago->moneda }}</div>
            </td>
            <td>
                <div class="label">Fecha pago</div>
                <div class="value">{{ $receipt->emitido_en?->format('Y-m-d H:i') ?? 'N/D' }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Referencia</div>
                <div class="value">{{ $receipt->referencia_transaccion ?: 'N/A' }}</div>
            </td>
            <td>
                <div class="label">Registrado por</div>
                <div class="value">{{ $receipt->emisor?->name ?? $pago->aprobador?->name ?? 'Sistema' }}</div>
            </td>
            <td>
                <div class="label">Estado</div>
                <div class="value">PAGADO</div>
            </td>
        </tr>
    </table>

    <div class="paid">PAGADO</div>

    @if(!empty($qrDataUri))
        <div style="margin-top: 14px; text-align: center;">
            <img src="{{ $qrDataUri }}" alt="QR Verificación" style="width: 90px; height: 90px;">
            <p style="font-size: 9px; color: #6b7280; margin-top: 2px;">Escanee para verificar la autenticidad financiera de este recibo</p>
        </div>
    @endif

    <div class="footer">
        Documento emitido por el sistema interno de cobros.
        No incluye información clínica.
    </div>
</body>
</html>
