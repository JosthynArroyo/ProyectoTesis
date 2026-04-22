@php
    $loginUrl = url('/?login=1');
@endphp

<x-email.layout
    eyebrow="Acceso al portal"
    title="Tu cuenta ya está disponible"
    intro="Creamos tu acceso al sistema de la clínica a partir de la información que registraste en el asistente web."
    badge="Credenciales iniciales"
    preheader="Ya puedes ingresar al portal de la clínica con tu correo y tu cédula."
    footer-note="Por seguridad, cambia esta contraseña inicial apenas completes tu primer ingreso."
>
    <p style="margin:0 0 22px; font-size:15px; line-height:1.7; color:#334155;">
        Hola {{ $user->name }}, desde ahora puedes gestionar citas, revisar resultados y dar seguimiento a tu atención médica desde el portal de Clínica Don Bosco.
        Tu usuario será el correo que registraste y tu contraseña inicial será tu número de cédula.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; border-collapse:separate; border-spacing:0; border:1px solid #dbe4ee; border-radius:22px; overflow:hidden; background:#ffffff;">
        <tr>
            <td colspan="2" style="padding:18px 20px; border-bottom:1px solid #e2e8f0; background:#f8fafc; font-size:13px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#0f766e;">
                Credenciales de acceso
            </td>
        </tr>
        <tr>
            <td style="width:34%; padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:13px; font-weight:700; color:#64748b;">Usuario (correo)</td>
            <td style="padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:14px; font-weight:600; color:#0f172a;">{{ $user->email }}</td>
        </tr>
        <tr>
            <td style="padding:14px 20px; font-size:13px; font-weight:700; color:#64748b;">Contraseña inicial</td>
            <td style="padding:14px 20px; font-size:14px; font-weight:700; color:#0f172a;">{{ $passwordPlano }}</td>
        </tr>
    </table>

    <div style="margin-top:20px; padding:20px; border-radius:22px; background:#f8fafc; border:1px solid #dbe4ee;">
        <div style="font-size:13px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#0f766e;">
            Siguiente paso recomendado
        </div>
        <p style="margin:10px 0 0; font-size:14px; line-height:1.7; color:#475569;">
            Inicia sesión con tu correo y tu cédula, y luego actualiza tu contraseña desde el perfil de usuario para mantener segura tu cuenta.
        </p>
    </div>

    <x-slot:actions>
        <a href="{{ $loginUrl }}" style="display:inline-block; padding:14px 24px; border-radius:14px; background:#0f766e; color:#ffffff; text-decoration:none; font-size:14px; font-weight:700;">
            Ingresar al portal
        </a>
    </x-slot:actions>
</x-email.layout>
