<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Verificación de Recibo - {{ $receipt->folio_recibo }}</title>
    @vite('resources/css/recibos/verificar.css')
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

        @php
            $clinicName = (isset($clinicIdentity) && $clinicIdentity instanceof \App\Services\ClinicIdentityService)
                ? $clinicIdentity->name()
                : app(\App\Services\ClinicIdentityService::class)->name();
        @endphp

        <div class="detail-group">
            <div class="detail-row">
                <span class="detail-label">Folio Recibo:</span>
                <span class="detail-value">{{ $receipt->folio_recibo }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Emisor:</span>
                <span class="detail-value">{{ $clinicName }}</span>
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
            {{ $clinicName }} — Plataforma de Verificación Financiera
        </div>
    </div>
</body>
</html>
