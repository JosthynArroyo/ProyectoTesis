@php
    $doctorName = $pedido->doctor->name ?? 'tu profesional de salud';
    $pacienteName = $pedido->paciente->name ?? 'Paciente';
    $fecha = $pedido->created_at ? $pedido->created_at->format('d/m/Y') : now()->format('d/m/Y');
@endphp

<x-email.layout
    eyebrow="Orden Médica de Laboratorio"
    title="Tu pedido de laboratorio está firmado y listo"
    intro="Adjuntamos el documento PDF firmado digitalmente por tu médico con los exámenes requeridos."
    badge="Documento firmado"
    :preheader="'Tu pedido de laboratorio ya está disponible en '.$clinicIdentity->name().'.'"
    footer-note="Lleva esta orden (impresa o digital) al laboratorio clínico de tu elección para la toma de muestras."
>
    <p style="margin:0 0 22px; font-size:15px; line-height:1.7; color:#334155;">
        Hola {{ $pacienteName }}, tu médico {{ $doctorName }} ha generado y firmado digitalmente una nueva orden de exámenes de laboratorio.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; border-collapse:separate; border-spacing:0; border:1px solid #dbe4ee; border-radius:22px; overflow:hidden; background:#ffffff;">
        <tr>
            <td colspan="2" style="padding:18px 20px; border-bottom:1px solid #e2e8f0; background:#f8fafc; font-size:13px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#0f766e;">
                Detalles del Pedido
            </td>
        </tr>
        <tr>
            <td style="width:34%; padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:13px; font-weight:700; color:#64748b;">Médico</td>
            <td style="padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:14px; font-weight:600; color:#0f172a;">{{ $doctorName }}</td>
        </tr>
        <tr>
            <td style="padding:14px 20px; font-size:13px; font-weight:700; color:#64748b;">Fecha de Emisión</td>
            <td style="padding:14px 20px; font-size:14px; font-weight:600; color:#0f172a;">{{ $fecha }}</td>
        </tr>
    </table>

    <div style="margin-top:20px; padding:20px; border-radius:22px; background:#f8fafc; border:1px solid #dbe4ee;">
        <div style="font-size:13px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#0f766e;">
            Firma Criptográfica (.p12)
        </div>
        <p style="margin:10px 0 0; font-size:14px; line-height:1.7; color:#475569;">
            Este documento PDF contiene una firma electrónica criptográfica legalmente válida que garantiza la integridad y autoría de tu médico.
        </p>
    </div>

    <x-slot:actions>
        <a href="{{ route('paciente.dashboard') }}" style="display:inline-block; padding:14px 24px; border-radius:14px; background:#0f766e; color:#ffffff; text-decoration:none; font-size:14px; font-weight:700;">
            Ir a mi panel
        </a>
    </x-slot:actions>
</x-email.layout>
