@php
    $rol = $rolReceptor ?? 'paciente';
    $mensajeTxt = $mensaje ?? 'Tu cita fue actualizada.';
    $eventoTxt = $textoEvento ?? ucfirst($evento ?? 'actualizada');
    $nombre = $nombreReceptor ?? ($rol === 'doctor' ? 'Doctor/a' : 'Paciente');
    $citaObj = $cita ?? null;
    $fechaTxt = $fechaCita ?? ($citaObj?->fecha ? $citaObj->fecha->format('d/m/Y') : '');
    $horaTxt = $horaCita ?? ($citaObj?->hora ? \Carbon\Carbon::parse($citaObj->hora)->format('H:i') : '');
    $estadoRaw = strtolower((string) ($citaObj?->estado ?? ($evento ?? 'actualizada')));
    $isLab = mb_strtolower((string) ($citaObj?->especialidad?->nombre ?? '')) === 'laboratorio clínico';
    $labelProfesional = $isLab ? 'Área responsable' : 'Profesional';
    $detalleUrl = $rol === 'doctor' ? route('doctor.citas') : route('paciente.citas');
    $detalleLabel = $rol === 'doctor' ? 'Ir a mi agenda' : 'Ver mis citas';
    $titulo = $rol === 'doctor' ? 'Actualización en tu agenda' : 'Actualización de tu cita';
    $footerNote = $rol === 'doctor'
        ? 'Revisa tu agenda para dar seguimiento a este cambio y mantener tu planificación al día.'
        : 'Si necesitas reprogramar, cancelar o resolver dudas, puedes hacerlo desde tu panel o comunicándote con la clínica.';

    $badgePaleta = [
        'agendada' => ['#ecfeff', '#0f766e'],
        'confirmada' => ['#dcfce7', '#15803d'],
        'pendiente' => ['#fef3c7', '#b45309'],
        'reagendada' => ['#e0e7ff', '#4338ca'],
        'cancelada' => ['#fee2e2', '#b91c1c'],
        'realizada' => ['#dcfce7', '#15803d'],
        'no_se_presento' => ['#ffedd5', '#c2410c'],
        'actualizada' => ['#ccfbf1', '#0f766e'],
    ];

    $estadoLabels = [
        'pendiente' => 'Pendiente',
        'confirmada' => 'Confirmada',
        'cancelada' => 'Cancelada',
        'realizada' => 'Realizada',
        'no_se_presento' => 'No se presentó',
    ];

    [$badgeBg, $badgeColor] = $badgePaleta[$estadoRaw] ?? ['#ccfbf1', '#0f766e'];
    $estadoLabel = $estadoLabels[$estadoRaw] ?? ucfirst(str_replace('_', ' ', $estadoRaw));
@endphp

<x-email.layout
    eyebrow="Gestión de citas"
    :title="$titulo"
    :intro="$mensajeTxt"
    :badge="'Estado actual: '.$estadoLabel"
    :preheader="$mensajeTxt"
    :footer-note="$footerNote"
>
    <p style="margin:0 0 22px; font-size:15px; line-height:1.7; color:#334155;">
        Hola {{ $nombre }}, aquí tienes el resumen actualizado de la cita para que puedas revisarlo rápidamente.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; border-collapse:separate; border-spacing:0; border:1px solid #dbe4ee; border-radius:22px; overflow:hidden; background:#ffffff;">
        <tr>
            <td colspan="2" style="padding:18px 20px; border-bottom:1px solid #e2e8f0; background:#f8fafc;">
                <div style="font-size:13px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#0f766e;">
                    Resumen de la cita
                </div>
                <div style="margin-top:8px;">
                    <span style="display:inline-block; padding:7px 12px; border-radius:999px; background:{{ $badgeBg }}; color:{{ $badgeColor }}; font-size:13px; font-weight:700; text-transform:capitalize;">
                        {{ $eventoTxt }}
                    </span>
                </div>
            </td>
        </tr>
        <tr>
            <td style="width:38%; padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:13px; font-weight:700; color:#64748b;">Paciente</td>
            <td style="padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:14px; font-weight:600; color:#0f172a;">{{ $citaObj?->paciente?->name ?? 'Paciente' }}</td>
        </tr>
        <tr>
            <td style="padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:13px; font-weight:700; color:#64748b;">{{ $labelProfesional }}</td>
            <td style="padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:14px; font-weight:600; color:#0f172a;">{{ $citaObj?->doctor?->name ?? ($isLab ? 'Laboratorio clínico' : 'Doctor/a') }}</td>
        </tr>
        <tr>
            <td style="padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:13px; font-weight:700; color:#64748b;">Especialidad</td>
            <td style="padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:14px; font-weight:600; color:#0f172a;">{{ $citaObj?->especialidad?->nombre ?? '-' }}</td>
        </tr>
        <tr>
            <td style="padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:13px; font-weight:700; color:#64748b;">Fecha y hora</td>
            <td style="padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:14px; font-weight:600; color:#0f172a;">{{ trim($fechaTxt.' '.$horaTxt) ?: 'Por confirmar' }}</td>
        </tr>
        @if (!empty($citaObj?->prioridad_nivel))
            <tr>
                <td style="padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:13px; font-weight:700; color:#64748b;">Prioridad</td>
                <td style="padding:14px 20px; border-bottom:1px solid #eef2f7; font-size:14px; font-weight:600; color:#0f172a; text-transform:capitalize;">
                    {{ strtolower((string) $citaObj->prioridad_nivel) }}
                </td>
            </tr>
        @endif
        <tr>
            <td style="padding:14px 20px; font-size:13px; font-weight:700; color:#64748b;">Estado</td>
            <td style="padding:14px 20px;">
                <span style="display:inline-block; padding:7px 12px; border-radius:999px; background:{{ $badgeBg }}; color:{{ $badgeColor }}; font-size:13px; font-weight:700; text-transform:capitalize;">
                    {{ $estadoLabel }}
                </span>
            </td>
        </tr>
    </table>

    <div style="margin-top:20px; padding:18px 20px; border-radius:18px; background:#f8fafc; border:1px solid #e2e8f0;">
        <div style="font-size:13px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#0f766e;">
            Recomendación
        </div>
        <p style="margin:10px 0 0; font-size:14px; line-height:1.7; color:#475569;">
            Mantén actualizada esta cita desde tu panel para evitar confusiones con horarios, cancelaciones o cambios de prioridad.
        </p>
    </div>

    <x-slot:actions>
        <a href="{{ $detalleUrl }}" style="display:inline-block; padding:14px 24px; border-radius:14px; background:#0f766e; color:#ffffff; text-decoration:none; font-size:14px; font-weight:700;">
            {{ $detalleLabel }}
        </a>
    </x-slot:actions>
</x-email.layout>
