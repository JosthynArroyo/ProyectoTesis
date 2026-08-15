@php
    $pacienteNombre = $certificado->nombrePacienteReal();
    $doctorName = $doctor->name ?? 'tu profesional de salud';
    $fecha = $certificado->fecha_emision ? $certificado->fecha_emision->format('d/m/Y') : now()->format('d/m/Y');
@endphp

<x-email.layout
    eyebrow="Certificado medico"
    title="Tu certificado medico fue emitido"
    intro="Adjuntamos el certificado en PDF para tu resguardo y seguimiento."
    badge="Documento adjunto"
    :preheader="'Tu certificado medico ya esta disponible en '.$clinicIdentity->name().'.'"
    footer-note="Conserva este documento y presenta solo la version emitida desde el sistema de la clinica."
>
    <p style="margin:0 0 22px; font-size:15px; line-height:1.7; color:#334155;">
        Hola {{ $pacienteNombre }}, tu certificado medico fue emitido por {{ $doctorName }} y se adjunta en este correo.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; border-collapse:separate; border-spacing:0; border:1px solid #dbe4ee; border-radius:22px; overflow:hidden; background:#ffffff;">
        <tr>
            <td colspan="2" style="padding:18px 20px; border-bottom:1px solid #e2e8f0; background:#f8fafc; font-size:13px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#0f766e;">
                Datos del certificado
            </td>
        </tr>
        <tr>
            <td style="width:34%; padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:13px; font-weight:700; color:#64748b;">Codigo</td>
            <td style="padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:14px; font-weight:600; color:#0f172a;">{{ $certificado->codigo }}</td>
        </tr>
        <tr>
            <td style="padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:13px; font-weight:700; color:#64748b;">Fecha de emision</td>
            <td style="padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:14px; font-weight:600; color:#0f172a;">{{ $fecha }}</td>
        </tr>
        <tr>
            <td style="padding:14px 20px; font-size:13px; font-weight:700; color:#64748b;">Profesional</td>
            <td style="padding:14px 20px; font-size:14px; font-weight:600; color:#0f172a;">{{ $doctorName }}</td>
        </tr>
    </table>

    <div style="margin-top:20px; padding:20px; border-radius:22px; background:#f8fafc; border:1px solid #dbe4ee;">
        <div style="font-size:13px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#0f766e;">
            Recomendacion
        </div>
        <p style="margin:10px 0 0; font-size:14px; line-height:1.7; color:#475569;">
            Si necesitas una copia adicional, puedes descargarla desde el sistema o solicitar un nuevo envio desde el documento emitido.
        </p>
    </div>

    <x-slot:actions>
        <a href="{{ route('paciente.dashboard') }}" style="display:inline-block; padding:14px 24px; border-radius:14px; background:#0f766e; color:#ffffff; text-decoration:none; font-size:14px; font-weight:700;">
            Ir a mi panel
        </a>
    </x-slot:actions>
</x-email.layout>
