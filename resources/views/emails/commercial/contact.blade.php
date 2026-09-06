@php
    $nombre = $datos['nombre'] ?? '-';
    $email = $datos['email'] ?? null;
    $telefono = $datos['telefono'] ?? 'No indicado';
    $asunto = $datos['asunto'] ?? 'Sin indicar';
    $mensaje = $datos['mensaje'] ?? 'Sin contenido';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nueva solicitud comercial — JA MedSys</title>
</head>
<body style="margin:0; padding:0; background-color:#f1f5f9; font-family:'Segoe UI', Arial, sans-serif; color:#0f172a; -webkit-font-smoothing:antialiased;">
    {{-- Preheader oculto para clientes de correo --}}
    <div style="display:none; overflow:hidden; line-height:1px; opacity:0; max-height:0; max-width:0;">
        Nueva solicitud comercial recibida desde la landing de JA MedSys — {{ $nombre }} ({{ $asunto }})
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; border-collapse:collapse; background-color:#f1f5f9;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                {{-- Contenedor principal --}}
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; max-width:640px; border-collapse:separate; background:#ffffff; border:1px solid #cbd5e1; border-radius:24px; overflow:hidden; box-shadow:0 12px 36px rgba(15, 23, 42, 0.08);">
                    {{-- Header con branding JA MedSys --}}
                    <tr>
                        <td style="padding:32px 32px 28px; background-color:#0f172a; background-image:linear-gradient(135deg, #0f172a 0%, #0e7490 100%);">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                                <tr>
                                    <td>
                                        <div style="display:inline-block; padding:6px 14px; border-radius:10px; background:rgba(255,255,255,0.12); color:#ffffff; font-size:18px; font-weight:800; letter-spacing:0.02em;">
                                            JA <span style="color:#38bdf8;">MedSys</span>
                                        </div>
                                        <div style="margin-top:14px;">
                                            <span style="display:inline-block; padding:4px 12px; border-radius:999px; background:rgba(56,189,248,0.22); color:#7dd3fc; font-size:11px; font-weight:700; letter-spacing:0.06em; text-transform:uppercase;">
                                                Contacto Comercial
                                            </span>
                                        </div>
                                        <h1 style="margin:12px 0 0; font-size:24px; line-height:1.25; font-weight:800; color:#ffffff;">
                                            Nueva solicitud comercial
                                        </h1>
                                        <p style="margin:8px 0 0; font-size:14px; line-height:1.6; color:rgba(255,255,255,0.85);">
                                            Se recibió una nueva solicitud desde la landing comercial de JA MedSys.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Cuerpo del correo --}}
                    <tr>
                        <td style="padding:32px;">
                            {{-- Tabla de datos del interesado --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; border-collapse:separate; border-spacing:0; border:1px solid #e2e8f0; border-radius:16px; overflow:hidden; background:#ffffff;">
                                <tr>
                                    <td style="width:36%; padding:12px 16px; border-bottom:1px solid #f1f5f9; background:#f8fafc; font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.04em;">
                                        Nombre
                                    </td>
                                    <td style="padding:12px 16px; border-bottom:1px solid #f1f5f9; font-size:14px; font-weight:600; color:#0f172a;">
                                        {{ $nombre }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px; border-bottom:1px solid #f1f5f9; background:#f8fafc; font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.04em;">
                                        Correo electrónico
                                    </td>
                                    <td style="padding:12px 16px; border-bottom:1px solid #f1f5f9; font-size:14px; font-weight:600; color:#0f172a;">
                                        @if ($email)
                                            <a href="mailto:{{ $email }}" style="color:#0891b2; text-decoration:none;">{{ $email }}</a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px; border-bottom:1px solid #f1f5f9; background:#f8fafc; font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.04em;">
                                        Teléfono
                                    </td>
                                    <td style="padding:12px 16px; border-bottom:1px solid #f1f5f9; font-size:14px; font-weight:600; color:#0f172a;">
                                        {{ $telefono }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px; background:#f8fafc; font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.04em;">
                                        Clínica / empresa
                                    </td>
                                    <td style="padding:12px 16px; font-size:14px; font-weight:600; color:#0f172a;">
                                        {{ $asunto }}
                                    </td>
                                </tr>
                            </table>

                            {{-- Bloque de Mensaje --}}
                            <div style="margin-top:24px; padding:20px; border-radius:16px; background:#f8fafc; border:1px solid #e2e8f0;">
                                <div style="font-size:12px; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; color:#0891b2;">
                                    Mensaje del interesado
                                </div>
                                <div style="margin-top:10px; font-size:14px; line-height:1.75; color:#334155; white-space:pre-wrap;">{{ $mensaje }}</div>
                            </div>

                            {{-- Botón CTA Responder --}}
                            @if ($email)
                                <div style="margin-top:28px; text-align:center;">
                                    <a href="mailto:{{ $email }}?subject={{ rawurlencode('Re: Información sobre JA MedSys — ' . $asunto) }}" style="display:inline-block; padding:14px 28px; border-radius:12px; background:#0e7490; color:#ffffff; text-decoration:none; font-size:14px; font-weight:700; box-shadow:0 4px 12px rgba(14, 116, 144, 0.25);">
                                        Responder al interesado
                                    </a>
                                </div>
                            @endif
                        </td>
                    </tr>

                    {{-- Footer con branding JA MedSys --}}
                    <tr>
                        <td style="padding:24px 32px; background-color:#0f172a; border-top:1px solid #1e293b; color:#94a3b8; font-size:12px; line-height:1.6; text-align:center;">
                            <div style="font-weight:700; color:#ffffff; font-size:14px; margin-bottom:4px;">
                                JA MedSys
                            </div>
                            <div style="color:#cbd5e1; margin-bottom:12px;">
                                Software clínico para clínicas y consultorios
                            </div>
                            <div style="margin-bottom:8px;">
                                Correo: <a href="mailto:alejandroucenriquez@gmail.com" style="color:#38bdf8; text-decoration:none;">alejandroucenriquez@gmail.com</a>
                                &nbsp;|&nbsp;
                                WhatsApp: <a href="https://wa.me/593998740927" style="color:#38bdf8; text-decoration:none;">+593 998 740 927</a>
                            </div>
                            <div style="color:#64748b; font-size:11px; margin-top:8px;">
                                © 2026 JA MedSys. Todos los derechos reservados.
                            </div>
                            <div style="color:#475569; font-size:10px; margin-top:6px;">
                                Este mensaje fue generado automáticamente desde la landing comercial pública de JA MedSys.
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
