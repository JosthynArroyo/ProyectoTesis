@php
    $nombre = trim((string) ($user->name ?? ''));
@endphp

<x-email.layout
    eyebrow="Seguridad de acceso"
    title="Restablece tu contraseña"
    intro="Recibimos una solicitud para cambiar la contraseña de tu cuenta. Usa el siguiente enlace para definir una nueva clave de acceso."
    badge="Enlace válido por {{ $expireMinutes }} minutos"
    preheader="Restablece tu contraseña de forma segura desde el portal de la clínica."
    footer-note="Si no solicitaste este cambio, puedes ignorar este mensaje. Tu contraseña actual seguirá vigente hasta que completes el proceso."
>
    <p style="margin:0 0 18px; font-size:15px; line-height:1.7; color:#334155;">
        Hola{{ $nombre !== '' ? ' '.$nombre : '' }}, protege tu cuenta creando una contraseña nueva y segura para seguir gestionando tus citas y resultados.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; border-collapse:separate; border-spacing:0; border:1px solid #dbe4ee; border-radius:20px; overflow:hidden; background:#f8fafc;">
        <tr>
            <td style="padding:18px 20px; border-bottom:1px solid #e2e8f0; font-size:13px; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; color:#0f766e;">
                Recomendación
            </td>
        </tr>
        <tr>
            <td style="padding:18px 20px; font-size:14px; line-height:1.7; color:#334155;">
                Elige una contraseña con al menos 8 caracteres, combinando letras, números y un carácter especial. Evita reutilizar claves antiguas.
            </td>
        </tr>
    </table>

    <x-slot:actions>
        <a href="{{ $resetUrl }}" style="display:inline-block; padding:14px 24px; border-radius:14px; background:#0f766e; color:#ffffff; text-decoration:none; font-size:14px; font-weight:700;">
            Restablecer contraseña
        </a>
    </x-slot:actions>
</x-email.layout>
