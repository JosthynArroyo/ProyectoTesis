<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de Cobro {{ $pago->folio_unico }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .logo { height: 52px; }
        .title { font-size: 18px; font-weight: 700; margin: 0; }
        .muted { color: #6b7280; }
        .grid { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .grid td { border: 1px solid #e5e7eb; padding: 8px; vertical-align: top; }
        .label { font-size: 10px; text-transform: uppercase; color: #6b7280; }
        .value { font-size: 12px; font-weight: 600; margin-top: 3px; }
        .amount { margin-top: 14px; padding: 12px; border: 1px solid #d1d5db; background: #f9fafb; font-size: 14px; font-weight: 700; }
        .qr-box { margin-top: 16px; border: 1px solid #e5e7eb; padding: 12px; text-align: center; }
        .qr-box img { width: 170px; height: 170px; }
        .small { font-size: 10px; color: #6b7280; word-break: break-all; }
        .footer { margin-top: 14px; font-size: 11px; color: #4b5563; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <p class="title">Orden de Cobro</p>
            <p class="muted">Folio: {{ $pago->folio_unico }}</p>
            <p class="muted">Emitido: {{ $fechaPdf->format('Y-m-d H:i') }}</p>
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
                <div class="label">Teléfono</div>
                <div class="value">{{ $pago->paciente?->telefono ?? 'N/D' }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Cita</div>
                <div class="value">#{{ $pago->cita_id }}</div>
            </td>
            <td>
                <div class="label">Doctor</div>
                <div class="value">{{ $pago->cita?->doctor?->name ?? 'N/D' }}</div>
            </td>
            <td>
                <div class="label">Especialidad</div>
                <div class="value">{{ $pago->cita?->especialidad?->nombre ?? 'N/D' }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Fecha cita</div>
                <div class="value">{{ $pago->cita?->fecha?->format('Y-m-d') ?? 'N/D' }}</div>
            </td>
            <td>
                <div class="label">Hora cita</div>
                <div class="value">{{ $pago->cita?->hora ? substr((string) $pago->cita->hora, 0, 5) : 'N/D' }}</div>
            </td>
            <td>
                <div class="label">Estado cobro</div>
                <div class="value">{{ strtoupper((string) $pago->estado) }}</div>
            </td>
        </tr>
    </table>

    <div class="amount">
        Total a cobrar: {{ number_format((float) $pago->monto, 2) }} {{ $pago->moneda }}
    </div>

    <div class="footer">
        Métodos permitidos: EFECTIVO y TRANSFERENCIA.
        Para TRANSFERENCIA se requiere comprobante.
    </div>

    <div class="qr-box">
        <p><strong>Consulta rápida por QR</strong></p>
        <img src="{{ $qrDataUri }}" alt="QR Orden de Cobro">
        <p class="small">{{ $qrUrl }}</p>
    </div>
</body>
</html>
