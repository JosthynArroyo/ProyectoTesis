@php
    $doctorName = $pedido->doctor->name ?? 'tu profesional de salud';
    $pacienteName = $pedido->paciente->name ?? 'Paciente';
    $fecha = $pedido->resultado_publicado_at ? $pedido->resultado_publicado_at->format('d/m/Y') : now()->format('d/m/Y');
@endphp

<x-email.layout
    eyebrow="Resultados de Laboratorio"
    title="Tus resultados de laboratorio están listos"
    intro="Adjuntamos el informe de resultados en PDF emitido por el laboratorio clínico."
    badge="Resultados adjuntos"
    :preheader="'Tus resultados de exámenes de laboratorio ya están disponibles en '.$clinicIdentity->name().'.'"
    footer-note="Recuerda agendar una consulta con tu médico para la lectura y explicación de estos resultados."
>
    <p style="margin:0 0 22px; font-size:15px; line-height:1.7; color:#334155;">
        Hola {{ $pacienteName }}, te informamos que los resultados del pedido de laboratorio solicitado por el Dr. {{ $doctorName }} ya han sido cargados.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; border-collapse:separate; border-spacing:0; border:1px solid #dbe4ee; border-radius:22px; overflow:hidden; background:#ffffff;">
        <tr>
            <td colspan="2" style="padding:18px 20px; border-bottom:1px solid #e2e8f0; background:#f8fafc; font-size:13px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#0f766e;">
                Detalles del Informe
            </td>
        </tr>
        <tr>
            <td style="width:34%; padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:13px; font-weight:700; color:#64748b;">Solicitado por</td>
            <td style="padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:14px; font-weight:600; color:#0f172a;">Dr. {{ $doctorName }}</td>
        </tr>
        <tr>
            <td style="padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:13px; font-weight:700; color:#64748b;">Fecha de Publicación</td>
            <td style="padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:14px; font-weight:600; color:#0f172a;">{{ $fecha }}</td>
        </tr>
        @if($pedido->resultado_resumen)
        <tr>
            <td style="padding:14px 20px; font-size:13px; font-weight:700; color:#64748b;">Resumen Clínico</td>
            <td style="padding:14px 20px; font-size:14px; color:#334155;">{{ $pedido->resultado_resumen }}</td>
        </tr>
        @endif
    </table>

    <x-slot:actions>
        <a href="{{ route('paciente.dashboard') }}" style="display:inline-block; padding:14px 24px; border-radius:14px; background:#0f766e; color:#ffffff; text-decoration:none; font-size:14px; font-weight:700;">
            Ir a mi panel
        </a>
    </x-slot:actions>
</x-email.layout>
