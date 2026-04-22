@php
    $nombre = $datos['nombre'] ?? '-';
    $email = $datos['email'] ?? null;
    $telefono = $datos['telefono'] ?? 'No indicado';
    $asunto = $datos['asunto'] ?? 'Sin asunto';
    $mensaje = $datos['mensaje'] ?? 'Sin contenido';
@endphp

<x-email.layout
    eyebrow="Contacto web"
    title="Nuevo mensaje recibido"
    intro="Se registró una nueva solicitud desde el formulario de contacto del sitio web."
    badge="Revisar y responder"
    preheader="Nuevo mensaje enviado desde el formulario público de la clínica."
    footer-note="Este mensaje fue generado automáticamente desde la sección de contacto del sitio."
>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; border-collapse:separate; border-spacing:0; border:1px solid #dbe4ee; border-radius:22px; overflow:hidden; background:#ffffff;">
        <tr>
            <td style="width:34%; padding:14px 18px; border-bottom:1px solid #eef2f7; background:#f8fafc; font-size:13px; font-weight:700; color:#64748b;">Nombre</td>
            <td style="padding:14px 18px; border-bottom:1px solid #eef2f7; font-size:14px; font-weight:600; color:#0f172a;">{{ $nombre }}</td>
        </tr>
        <tr>
            <td style="padding:14px 18px; border-bottom:1px solid #eef2f7; background:#f8fafc; font-size:13px; font-weight:700; color:#64748b;">Correo electrónico</td>
            <td style="padding:14px 18px; border-bottom:1px solid #eef2f7; font-size:14px; font-weight:600; color:#0f172a;">
                @if ($email)
                    <a href="mailto:{{ $email }}" style="color:#0f766e; text-decoration:none;">{{ $email }}</a>
                @else
                    -
                @endif
            </td>
        </tr>
        <tr>
            <td style="padding:14px 18px; border-bottom:1px solid #eef2f7; background:#f8fafc; font-size:13px; font-weight:700; color:#64748b;">Teléfono</td>
            <td style="padding:14px 18px; border-bottom:1px solid #eef2f7; font-size:14px; font-weight:600; color:#0f172a;">{{ $telefono }}</td>
        </tr>
        <tr>
            <td style="padding:14px 18px; background:#f8fafc; font-size:13px; font-weight:700; color:#64748b;">Asunto</td>
            <td style="padding:14px 18px; font-size:14px; font-weight:600; color:#0f172a;">{{ $asunto }}</td>
        </tr>
    </table>

    <div style="margin-top:20px; padding:20px; border-radius:22px; background:#f8fafc; border:1px solid #dbe4ee;">
        <div style="font-size:13px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#0f766e;">
            Mensaje
        </div>
        <div style="margin-top:12px; font-size:14px; line-height:1.75; color:#334155; white-space:pre-wrap;">
            {{ $mensaje }}
        </div>
    </div>

    @if ($email)
        <x-slot:actions>
            <a href="mailto:{{ $email }}" style="display:inline-block; padding:14px 24px; border-radius:14px; background:#0f766e; color:#ffffff; text-decoration:none; font-size:14px; font-weight:700;">
                Responder al remitente
            </a>
        </x-slot:actions>
    @endif
</x-email.layout>
