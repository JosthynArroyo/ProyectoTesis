<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Certificado medico</title>
  <style>{{ $pdfCss }}</style>
</head>
<body>
  @php
    $cita = $certificado->cita;
    $paciente = $certificado->paciente;
    $doctor = $certificado->doctor;
    $especialidad = $cita?->especialidad?->nombre
        ?? $doctor?->especialidades?->first()?->nombre
        ?? 'No registrada';
  @endphp

  <div class="page">
    <div class="sheet">
      <div class="header avoid-break">
        <div class="brand-block">
          @if(!empty($logoBase64))
            <img class="logo" src="{{ $logoBase64 }}" alt="Logo de la clinica">
          @else
            <div class="logo" aria-hidden="true"></div>
          @endif
          <div>
            <h1 class="clinic-title">{{ $clinica }}</h1>
            <div class="clinic-sub">Certificado medico</div>
          </div>
        </div>
        <div class="small soft" style="text-align:right">
          <div><b>Codigo:</b> {{ $certificado->codigo }}</div>
          <div><b>Fecha de emision:</b> {{ $certificado->fecha_emision?->format('d/m/Y H:i') }}</div>
          <div><b>Cita:</b> #{{ $cita?->id ?? '-' }}</div>
        </div>
      </div>

      <div class="divider"></div>

      <div class="meta avoid-break">
        <div><b>Paciente:</b> {{ $certificado->nombrePacienteReal() }}</div>
        <div><b>Documento:</b> {{ $certificado->dniPacienteReal() !== 'N/D' ? $certificado->dniPacienteReal() : 'Sin registro' }}</div>
        @if(($certificado->dependiente_id && $certificado->dependiente) || ($cita?->dependiente_id && $cita?->dependiente))
          <div><b>Representante:</b> {{ $paciente?->name ?? '-' }}</div>
        @endif
        <div><b>Doctor:</b> {{ $doctor?->name ?? '-' }}</div>
        <div><b>Especialidad:</b> {{ $especialidad }}</div>
        <div><b>Fecha cita:</b> {{ $cita?->fecha?->format('d/m/Y') ?? '-' }} {{ $cita?->hora ? substr((string) $cita->hora, 0, 5) : '' }}</div>
        <div><b>Estado cita:</b> {{ $cita?->estado ?? '-' }}</div>
      </div>

      <div class="section certificate-text avoid-break">
        <h2>Certificado medico</h2>
        <div class="preserve">{{ $certificado->texto_constancia }}</div>
      </div>

      <div class="rest-grid avoid-break">
        <div>
          <span>Dias de reposo</span>
          <strong>{{ $certificado->dias_reposo }}</strong>
        </div>
        <div>
          <span>Desde</span>
          <strong>{{ $certificado->reposo_desde?->format('d/m/Y') ?? 'No aplica' }}</strong>
        </div>
        <div>
          <span>Hasta</span>
          <strong>{{ $certificado->reposo_hasta?->format('d/m/Y') ?? 'No aplica' }}</strong>
        </div>
      </div>

      @if($certificado->observaciones)
        <div class="section avoid-break">
          <h3>Observaciones o recomendaciones</h3>
          <div class="preserve">{{ $certificado->observaciones }}</div>
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
        <span>Documento generado por {{ $clinica }}.</span>
        <span class="badge">Codigo {{ $certificado->codigo }}</span>
      </div>
    </div>
  </div>
</body>
</html>
