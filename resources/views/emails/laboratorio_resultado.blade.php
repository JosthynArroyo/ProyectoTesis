@php
    $paciente = $paciente ?? null;
    $nombre = $paciente?->name ?? 'Paciente';
    $citaObj = $cita ?? null;
    $ordenObj = $orden ?? null;
    $fechaResultado = $citaObj?->fecha
        ?? $ordenObj?->scheduled_at
        ?? $ordenObj?->resultado_publicado_at
        ?? $ordenObj?->created_at;
    $fechaTxt = $fechaResultado ? \Carbon\Carbon::parse($fechaResultado)->format('d/m/Y') : '';
    $horaTxt = $citaObj?->hora
        ? \Carbon\Carbon::parse($citaObj->hora)->format('H:i')
        : ($fechaResultado ? \Carbon\Carbon::parse($fechaResultado)->format('H:i') : '');
    $prioridad = $ordenObj?->prioridad ?? $ordenObj?->priority ?? 'Normal';
@endphp

<x-email.layout
    eyebrow="Laboratorio clínico"
    title="Tus resultados ya están disponibles"
    intro="Adjuntamos el informe en PDF para que puedas revisarlo y también consultarlo desde tu panel cuando lo necesites."
    badge="Resultado listo para descargar"
    preheader="Tus resultados de laboratorio ya están disponibles en Clínica Don Bosco."
    footer-note="Si tienes dudas sobre tus resultados, agenda una cita con tu profesional tratante para su interpretación."
>
    <p style="margin:0 0 22px; font-size:15px; line-height:1.7; color:#334155;">
        Hola {{ $nombre }}, estos son los datos principales asociados a tu resultado de laboratorio.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; border-collapse:separate; border-spacing:0; border:1px solid #dbe4ee; border-radius:22px; overflow:hidden; background:#ffffff;">
        <tr>
            <td colspan="2" style="padding:18px 20px; border-bottom:1px solid #e2e8f0; background:#f8fafc; font-size:13px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#0f766e;">
                Detalles del resultado
            </td>
        </tr>
        <tr>
            <td style="width:34%; padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:13px; font-weight:700; color:#64748b;">Examen</td>
            <td style="padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:14px; font-weight:600; color:#0f172a;">{{ $ordenObj?->tipo_examen ?? 'No disponible' }}</td>
        </tr>
        <tr>
            <td style="padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:13px; font-weight:700; color:#64748b;">Prioridad</td>
            <td style="padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:14px; font-weight:600; color:#0f172a; text-transform:capitalize;">{{ $prioridad }}</td>
        </tr>
        <tr>
            <td style="padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:13px; font-weight:700; color:#64748b;">Fecha de la cita</td>
            <td style="padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:14px; font-weight:600; color:#0f172a;">{{ trim($fechaTxt.' '.$horaTxt) ?: 'No disponible' }}</td>
        </tr>
        <tr>
            <td style="padding:14px 20px; font-size:13px; font-weight:700; color:#64748b;">Especialidad</td>
            <td style="padding:14px 20px; font-size:14px; font-weight:600; color:#0f172a;">{{ $citaObj?->especialidad?->nombre ?? 'Laboratorio clínico' }}</td>
        </tr>
    </table>

    @if (!empty($ordenObj?->resultado_resumen))
        <div style="margin-top:20px; padding:20px; border-radius:22px; background:#f8fafc; border:1px solid #dbe4ee;">
            <div style="font-size:13px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#0f766e;">
                Resumen reportado
            </div>
            <div style="margin-top:12px; font-size:14px; line-height:1.75; color:#475569;">
                {{ $ordenObj->resultado_resumen }}
            </div>
        </div>
    @endif

    <x-slot:actions>
        <a href="{{ route('paciente.laboratorio.index') }}" style="display:inline-block; padding:14px 24px; border-radius:14px; background:#0f766e; color:#ffffff; text-decoration:none; font-size:14px; font-weight:700;">
            Ver resultados en mi panel
        </a>
        <a href="{{ route('paciente.crear-cita') }}" style="display:inline-block; margin-left:12px; padding:14px 24px; border-radius:14px; background:#ffffff; color:#0f766e; text-decoration:none; font-size:14px; font-weight:700; border:1px solid #99f6e4;">
            Agendar nueva cita
        </a>
    </x-slot:actions>
</x-email.layout>
