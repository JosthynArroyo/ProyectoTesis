<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Receta medica</title>
  <style>{{ $pdfCss }}</style>
</head>
<body>
  <div class="page">
    <div class="sheet">
      <div class="header avoid-break">
        <div class="brand-block">
          @if(!empty($logoBase64))
            <img class="logo" src="{{ $logoBase64 }}" alt="{{ $clinicIdentity->institutionalName() }}">
          @else
            <div class="logo" aria-hidden="true"></div>
          @endif
          <div>
            <h1 class="clinic-title"><span class="brand">{{ $clinicIdentity->institutionalName() }}</span></h1>
            <div class="clinic-sub small muted">Receta medica</div>
          </div>
        </div>
        <div class="small soft" style="text-align:right">
          <div><b>Fecha:</b> {{ $fechaPdf->format('d/m/Y H:i') }}</div>
          <div><b>Doctor(a):</b> {{ $cita->doctor->name ?? '-' }}</div>
          <div><b>Paciente:</b> {{ $cita->nombrePacienteReal() }}</div>
          @if($cita->dependiente_id && $cita->dependiente)
            <div><b>Representante:</b> {{ $cita->paciente->name ?? '-' }}</div>
          @endif
        </div>
      </div>

      <div class="meta avoid-break">
        <div class="xs"><b>No. Historia:</b> {{ $cita->dependiente_id && $cita->dependiente ? 'D-' . $cita->dependiente->id : ($cita->paciente->id ?? '-') }}</div>
        <div class="xs"><b>ID Cita:</b> {{ $cita->id ?? '-' }}</div>
        <div class="xs"><b>Estado:</b> {{ $cita->estado ?? '-' }}</div>
        <div class="xs"><b>Emitida por:</b> Sistema de recetas</div>
      </div>

      <div class="divider"></div>

      <div class="section avoid-break">
        <h3>Diagnostico / Motivo</h3>
        <div class="preserve">{{ $diagnostico }}</div>
      </div>

      <div class="section avoid-break">
        <h3>Medicamentos</h3>
        <div class="preserve">{{ $medicamentos }}</div>
      </div>

      @if(!empty($indicaciones))
        <div class="section avoid-break">
          <h3>Indicaciones</h3>
          <div class="preserve">{{ $indicaciones }}</div>
        </div>
      @endif

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

      <div class="footer small">
        <div class="muted xs">
          <em>Documento generado automaticamente. Ante dudas, consulte con su medico.</em>
        </div>
        <div>
          <span class="badge">Uso interno</span>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
