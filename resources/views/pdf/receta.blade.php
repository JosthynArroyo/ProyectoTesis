<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Receta Médica</title>
  <style>
    /* ===== Reset mínimo y página ===== */
    *{ box-sizing:border-box; }
    html,body{ margin:0; padding:0; }
    body{
      font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
      color:#0f172a;
      font-size:13px;
      line-height:1.45;
      background:#fff;
    }
    .page{ width:100%; max-width:720px; margin:0 auto; padding:24px 28px; }

    /* ===== Tokens ===== */
    .brand{ color:#0ea5e9; font-weight:800; letter-spacing:.2px; }
    .muted{ color:#6b7280; }
    .soft{ color:#475569; }
    .small{ font-size:12px; }
    .xs{ font-size:11px; }
    .divider{ height:1px; background:#e5e7eb; margin:14px 0; }

    /* ===== Contenedor principal ===== */
    .sheet{ border:1px solid #e5e7eb; border-radius:12px; padding:20px; }

    /* ===== Encabezado ===== */
    .header{ display:flex; align-items:center; justify-content:space-between; gap:16px; }
    .brand-block{ display:flex; align-items:center; gap:12px; }
    .logo{ width:54px; height:54px; border-radius:8px; object-fit:contain; border:1px solid #e5e7eb; padding:6px; }
    .clinic-title{ font-size:20px; font-weight:800; margin:0; }
    .clinic-sub{ margin:2px 0 0; }

    /* ===== Meta de documento ===== */
    .meta{
      border:1px solid #e5e7eb; border-radius:10px; padding:10px 12px;
      display:grid; grid-template-columns:repeat(2,minmax(0,1fr));
      gap:6px 14px; margin-top:12px; background:#fafafa;
    }
    .meta b{ font-weight:700; }

    /* ===== Secciones ===== */
    h3{ margin:14px 0 8px; font-size:14px; color:#0f172a; }
    .section{ margin-top:12px; padding:12px; border:1px solid #eef2f7; border-radius:10px; background:#fff; }
    .preserve{ white-space:pre-wrap; word-wrap:break-word; }

    /* ===== Firma ===== */
    .sign-row{ display:flex; gap:24px; margin-top:24px; }
    .sign{ flex:1; text-align:center; padding-top:40px; }
    .sign .line{ border-top:1px solid #94a3b8; height:0; margin:8px 0 4px; }

    /* ===== Pie ===== */
    .footer{ margin-top:14px; padding-top:10px; border-top:1px dashed #e5e7eb; display:flex; justify-content:space-between; gap:12px; }
    .badge{ display:inline-block; border:1px solid #bae6fd; background:#ecfeff; color:#0369a1; padding:2px 8px; border-radius:999px; font-weight:700; font-size:11px; }

    /* ===== Impresión ===== */
    .avoid-break{ page-break-inside:avoid; }
    @page{ margin:24px 28px; }
  </style>
</head>
<body>
  <div class="page">
    <div class="sheet">
      <!-- ===== Header ===== -->
      <div class="header avoid-break">
        <div class="brand-block">
          @if(!empty($logoBase64))
            <img class="logo" src="{{ $logoBase64 }}" alt="Logo Clínica">
          @else
            <div class="logo" aria-hidden="true"></div>
          @endif
          <div>
            <h1 class="clinic-title"><span class="brand">Clínica Don Bosco</span></h1>
            <div class="clinic-sub small muted">Receta médica</div>
          </div>
        </div>
        <div class="small soft" style="text-align:right">
          <div><b>Fecha:</b> {{ $fechaPdf->format('d/m/Y H:i') }}</div>
          <div><b>Doctor(a):</b> {{ $cita->doctor->name ?? '—' }}</div>
          <div><b>Paciente:</b> {{ $cita->paciente->name ?? '—' }}</div>
        </div>
      </div>

      <!-- ===== Meta ===== -->
      <div class="meta avoid-break">
        <div class="xs"><b>N.º Historia:</b> {{ $cita->paciente->id ?? '—' }}</div>
        <div class="xs"><b>ID Cita:</b> {{ $cita->id ?? '—' }}</div>
        <div class="xs"><b>Estado:</b> {{ $cita->estado ?? '—' }}</div>
        <div class="xs"><b>Emitida por:</b> Sistema de Recetas</div>
      </div>

      <div class="divider"></div>

      <!-- ===== Diagnóstico ===== -->
      <div class="section avoid-break">
        <h3>Diagnóstico / Motivo</h3>
        <div class="preserve">{{ $diagnostico }}</div>
      </div>

      <!-- ===== Medicamentos ===== -->
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

      <!-- ===== Firmas ===== -->
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

      <!-- ===== Pie ===== -->
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
