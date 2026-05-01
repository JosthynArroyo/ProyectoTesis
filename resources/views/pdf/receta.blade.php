<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Receta médica</title>
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
            <div class="clinic-sub small muted">Receta médica</div>
          </div>
        </div>
        <div class="small soft" style="text-align:right">
          <div><b>Fecha:</b> {{ $fechaPdf->format('d/m/Y H:i') }}</div>
          <div><b>Doctor(a):</b> {{ $cita->doctor->name ?? '-' }}</div>
          <div><b>Paciente:</b> {{ $cita->paciente->name ?? '-' }}</div>
        </div>
      </div>

      <div class="meta avoid-break">
        <div class="xs"><b>No. Historia:</b> {{ $cita->paciente->id ?? '-' }}</div>
        <div class="xs"><b>ID Cita:</b> {{ $cita->id ?? '-' }}</div>
        <div class="xs"><b>Estado:</b> {{ $cita->estado ?? '-' }}</div>
        <div class="xs"><b>Emitida por:</b> Sistema de recetas</div>
      </div>

      <div class="divider"></div>

      <div class="section avoid-break">
        <h3>Diagnóstico / Motivo</h3>
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

      <div class="sign-row avoid-break">
        <div class="sign">
          <div class="line"></div>
          <div class="xs muted">Firma y sello del médico</div>
        </div>
        <div class="sign">
          <div class="line"></div>
          <div class="xs muted">Firma del paciente o responsable</div>
        </div>
      </div>

      <div class="footer small">
        <div class="muted xs">
          <em>Documento generado automáticamente. Ante dudas, consulte con su médico.</em>
        </div>
        <div>
          <span class="badge">Uso interno</span>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
