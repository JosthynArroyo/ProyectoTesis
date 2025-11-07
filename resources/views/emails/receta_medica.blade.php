<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Receta médica</title>
</head>
<body style="font-family:Arial,Helvetica,sans-serif;color:#111827;line-height:1.5;margin:0;background:#f5f7fb;">
  <div style="max-width:640px;margin:22px auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
    
    <!-- Cabecera con imagen -->
    <div style="padding:0;background:#111827;text-align:center;">
      <img src="{{ asset('img/img.jpg') }}" alt="Clínica Don Bosco" style="display:block;width:100%;max-width:640px;height:auto;">
    </div>

    <!-- Cuerpo -->
    <div style="padding:24px;">
      <h2 style="margin-bottom:4px;">
        {{ $motivo === 'actualizacion' ? 'Actualización de receta médica' : 'Nueva receta médica' }}
      </h2>

      <p>Estimado/a {{ $cita->paciente->name ?? 'paciente' }},</p>

      <p>
        Adjuntamos su 
        {{ $motivo === 'actualizacion' ? 'receta actualizada' : 'nueva receta' }} 
        correspondiente a la cita con el Dr(a). 
        <strong>{{ $cita->doctor->name ?? '—' }}</strong> 
        del día {{ \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') }} 
        a las {{ \Carbon\Carbon::parse($cita->hora)->format('H:i') }}.
      </p>

      <p>Por favor, siga las indicaciones médicas y conserve este documento para futuras consultas.</p>

      <p style="margin-top:24px;">— Clínica Don Bosco</p>
    </div>

    <!-- Footer -->
    <div style="padding:12px;text-align:center;background:#f3f4f6;font-size:12px;color:#6b7280;">
      © {{ date('Y') }} Clínica Don Bosco · Este es un correo automático, no responder.
    </div>
  </div>
</body>
</html>
