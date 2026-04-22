<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Cita {{ $cita->folio_cita }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #14323f; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; }
        .logo { height: 52px; }
        .title { font-size: 18px; font-weight: 700; margin: 0; color: #0f3d56; }
        .muted { color: #5b6b78; }
        .status { display: inline-block; margin-top: 8px; padding: 6px 10px; border-radius: 999px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .status.ok { background: #d1fae5; color: #065f46; }
        .status.off { background: #fee2e2; color: #991b1b; }
        .grid { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .grid td { border: 1px solid #cfe4ea; padding: 8px; vertical-align: top; }
        .label { font-size: 10px; text-transform: uppercase; color: #5b6b78; }
        .value { font-size: 12px; font-weight: 600; margin-top: 3px; }
        .note { margin-top: 14px; padding: 12px; border: 1px solid #cfe4ea; background: #f1fbfb; color: #155e75; }
        .qr-box { margin-top: 16px; border: 1px solid #cfe4ea; padding: 12px; text-align: center; }
        .qr-box img { width: 170px; height: 170px; }
        .small { font-size: 10px; color: #5b6b78; word-break: break-all; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <p class="title">Comprobante de Cita</p>
            <p class="muted">Folio: {{ $cita->folio_cita }}</p>
            <p class="muted">Código de validación: {{ $cita->token_validacion }}</p>
            <p class="muted">Emitido: {{ $fechaPdf->format('Y-m-d H:i') }}</p>
            <span class="status {{ $cita->comprobanteEstaVigente() ? 'ok' : 'off' }}">
                {{ $cita->comprobanteEstaVigente() ? 'Vigente' : 'Sin vigencia' }}
            </span>
        </div>
        @if(!empty($logoBase64))
            <img class="logo" src="{{ $logoBase64 }}" alt="Logo">
        @endif
    </div>

    <table class="grid">
        <tr>
            <td>
                <div class="label">Paciente</div>
                <div class="value">{{ $cita->paciente?->name ?? 'N/D' }}</div>
            </td>
            <td>
                <div class="label">Cédula</div>
                <div class="value">{{ $cita->paciente?->dni ?? 'N/D' }}</div>
            </td>
            <td>
                <div class="label">Teléfono</div>
                <div class="value">{{ $cita->paciente?->telefono ?? 'N/D' }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Fecha</div>
                <div class="value">{{ $cita->fecha?->format('Y-m-d') ?? 'N/D' }}</div>
            </td>
            <td>
                <div class="label">Hora</div>
                <div class="value">{{ $cita->hora ? substr((string) $cita->hora, 0, 5) : 'N/D' }}</div>
            </td>
            <td>
                <div class="label">Estado actual</div>
                <div class="value">{{ $cita->estadoComprobante() }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Médico</div>
                <div class="value">{{ $cita->doctor?->name ?? 'N/D' }}</div>
            </td>
            <td>
                <div class="label">Especialidad</div>
                <div class="value">{{ $cita->especialidad?->nombre ?? 'N/D' }}</div>
            </td>
            <td>
                <div class="label">Clínica</div>
                <div class="value">{{ $clinica }}</div>
            </td>
        </tr>
    </table>

    <div class="note">
        Este comprobante identifica la cita agendada y sirve para validación en recepción. No representa una deuda ni una orden de pago.
    </div>

    <div class="qr-box">
        <p><strong>Validación rápida</strong></p>
        <img src="{{ $qrDataUri }}" alt="QR comprobante de cita">
        <p class="small">{{ $qrUrl }}</p>
    </div>
</body>
</html>
