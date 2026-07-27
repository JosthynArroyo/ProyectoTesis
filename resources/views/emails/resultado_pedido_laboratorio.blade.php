@php
    $pedido = $pedido ?? $resultado->pedido ?? null;
    $paciente = $paciente ?? $pedido?->paciente;
    $doctor = $doctor ?? $pedido?->doctor;
    $version = $resultado instanceof \App\Models\PedidoLaboratorioResultado ? $resultado->version : 1;
    $fecha = $resultado instanceof \App\Models\PedidoLaboratorioResultado
        ? ($resultado->publicado_at ? $resultado->publicado_at->format('d/m/Y H:i') : now()->format('d/m/Y H:i'))
        : ($pedido?->resultado_publicado_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i'));
    $observacion = $resultado instanceof \App\Models\PedidoLaboratorioResultado
        ? $resultado->observaciones_generales
        : $pedido?->resultado_resumen;
@endphp

<x-email.layout
    eyebrow="Resultados de laboratorio"
    title="Tus resultados ya están disponibles"
    intro="Adjuntamos el informe final en PDF para que puedas revisarlo de forma segura desde tu correo y tu panel de paciente."
    badge="Informe publicado"
    :preheader="'Los resultados de laboratorio emitidos por '.$clinicIdentity->name().' ya están disponibles.'"
    footer-note="Si necesitas una interpretación clínica, revisa el informe con el doctor solicitante."
>
    <p style="margin:0 0 22px; font-size:15px; line-height:1.7; color:#334155;">
        Hola {{ $paciente?->name ?? 'Paciente' }}, el informe de resultados solicitado por el Dr. {{ $doctor?->name ?? 'tu médico' }} ya fue publicado.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; border-collapse:separate; border-spacing:0; border:1px solid #dbe4ee; border-radius:22px; overflow:hidden; background:#ffffff;">
        <tr>
            <td colspan="2" style="padding:18px 20px; border-bottom:1px solid #e2e8f0; background:#f8fafc; font-size:13px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#0f766e;">
                Detalles del informe
            </td>
        </tr>
        <tr>
            <td style="width:34%; padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:13px; font-weight:700; color:#64748b;">Orden</td>
            <td style="padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:14px; font-weight:600; color:#0f172a;">#{{ $pedido?->id ?? '-' }}</td>
        </tr>
        <tr>
            <td style="padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:13px; font-weight:700; color:#64748b;">Informe</td>
            <td style="padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:14px; font-weight:600; color:#0f172a;">#INF-{{ $pedido?->id ?? '-' }}-V{{ $version }}</td>
        </tr>
        <tr>
            <td style="padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:13px; font-weight:700; color:#64748b;">Fecha de publicación</td>
            <td style="padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:14px; font-weight:600; color:#0f172a;">{{ $fecha }}</td>
        </tr>
        <tr>
            <td style="padding:14px 20px; font-size:13px; font-weight:700; color:#64748b;">Versión</td>
            <td style="padding:14px 20px; font-size:14px; font-weight:600; color:#0f172a;">V{{ $version }}</td>
        </tr>
    </table>

    @if(!empty($observacion))
        <div style="margin-top:20px; padding:20px; border-radius:22px; background:#f8fafc; border:1px solid #dbe4ee;">
            <div style="font-size:13px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#0f766e;">
                Observación general
            </div>
            <div style="margin-top:12px; font-size:14px; line-height:1.75; color:#475569;">
                {{ $observacion }}
            </div>
        </div>
    @endif

    <x-slot:actions>
        <a href="{{ route('paciente.laboratorio.index') }}" style="display:inline-block; padding:14px 24px; border-radius:14px; background:#0f766e; color:#ffffff; text-decoration:none; font-size:14px; font-weight:700;">
            Ver resultados en mi panel
        </a>
    </x-slot:actions>
</x-email.layout>
