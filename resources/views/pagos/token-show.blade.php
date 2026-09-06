<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Verificación de Orden de Cobro - {{ $pago->folio_unico ?: 'Orden' }}</title>
    @vite('resources/css/pagos/token-show.css')
</head>
<body>
    <div class="card">
        @php
            $badgeClass = match($estadoActual) {
                'pagado' => 'status-pagado',
                'en_verificacion' => 'status-en_verificacion',
                'rechazado' => 'status-rechazado',
                'anulado' => 'status-anulado',
                default => 'status-pendiente',
            };
        @endphp

        <div class="status-badge {{ $badgeClass }}">
            Orden de cobro {{ str_replace('_', ' ', $estadoActual) }}
        </div>

        <h1 class="title">Verificación de Orden de Cobro</h1>
        <p class="subtitle">Estado de autenticidad registrado en la Clínica</p>

        @php
            $clinicName = (isset($clinicIdentity) && $clinicIdentity instanceof \App\Services\ClinicIdentityService)
                ? $clinicIdentity->name()
                : app(\App\Services\ClinicIdentityService::class)->name();
        @endphp

        <div class="detail-group">
            <div class="detail-row">
                <span class="detail-label">Folio Orden:</span>
                <span class="detail-value">{{ $pago->folio_unico ?: 'Sin Folio' }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Token:</span>
                <span class="detail-value detail-value--token">{{ $pago->token_publico }}</span>
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
                <span class="detail-value">{{ $pago->created_at?->format('Y-m-d H:i') ?? 'N/D' }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Monto:</span>
                <span class="detail-value">${{ number_format((float) $pago->monto, 2) }} {{ $pago->moneda ?: 'USD' }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Estado Actual:</span>
                <span class="detail-value">{{ strtoupper(str_replace('_', ' ', $estadoActual)) }}</span>
            </div>
        </div>

        <div class="footer">
            {{ $clinicName }} — Plataforma de Verificación Financiera
        </div>
    </div>
</body>
</html>
