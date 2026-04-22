@php
    $esActualizacion = ($motivo ?? 'creacion') === 'actualizacion';
    $titulo = $esActualizacion ? 'Tu receta médica fue actualizada' : 'Tu receta médica está lista';
    $doctor = $cita->doctor->name ?? 'tu profesional de salud';
    $paciente = $cita->paciente->name ?? 'Paciente';
    $fecha = $cita->fecha ? \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') : 'No disponible';
    $hora = $cita->hora ? \Carbon\Carbon::parse($cita->hora)->format('H:i') : 'No disponible';
@endphp

<x-email.layout
    eyebrow="Indicaciones médicas"
    :title="$titulo"
    :intro="'Adjuntamos el documento en PDF para que lo conserves y lo consultes cuando lo necesites.'"
    badge="Documento adjunto"
    preheader="Tu receta médica ya está disponible en Clínica Don Bosco."
    footer-note="Sigue únicamente las indicaciones emitidas por tu profesional de salud. Si presentas molestias o dudas sobre la medicación, agenda una revisión médica."
>
    <p style="margin:0 0 22px; font-size:15px; line-height:1.7; color:#334155;">
        Hola {{ $paciente }}, te enviamos la {{ $esActualizacion ? 'versión actualizada de tu receta médica' : 'receta médica generada durante tu atención' }}.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; border-collapse:separate; border-spacing:0; border:1px solid #dbe4ee; border-radius:22px; overflow:hidden; background:#ffffff;">
        <tr>
            <td colspan="2" style="padding:18px 20px; border-bottom:1px solid #e2e8f0; background:#f8fafc; font-size:13px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#0f766e;">
                Datos de la atención
            </td>
        </tr>
        <tr>
            <td style="width:34%; padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:13px; font-weight:700; color:#64748b;">Profesional</td>
            <td style="padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:14px; font-weight:600; color:#0f172a;">{{ $doctor }}</td>
        </tr>
        <tr>
            <td style="padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:13px; font-weight:700; color:#64748b;">Fecha</td>
            <td style="padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:14px; font-weight:600; color:#0f172a;">{{ $fecha }}</td>
        </tr>
        <tr>
            <td style="padding:14px 20px; font-size:13px; font-weight:700; color:#64748b;">Hora</td>
            <td style="padding:14px 20px; font-size:14px; font-weight:600; color:#0f172a;">{{ $hora }}</td>
        </tr>
    </table>

    <div style="margin-top:20px; padding:20px; border-radius:22px; background:#f8fafc; border:1px solid #dbe4ee;">
        <div style="font-size:13px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#0f766e;">
            Importante
        </div>
        <p style="margin:10px 0 0; font-size:14px; line-height:1.7; color:#475569;">
            Guarda este archivo en un lugar seguro y preséntalo cuando sea necesario para tu seguimiento o dispensación de medicamentos.
        </p>
    </div>

    <x-slot:actions>
        <a href="{{ route('paciente.dashboard') }}" style="display:inline-block; padding:14px 24px; border-radius:14px; background:#0f766e; color:#ffffff; text-decoration:none; font-size:14px; font-weight:700;">
            Ir a mi panel
        </a>
    </x-slot:actions>
</x-email.layout>
