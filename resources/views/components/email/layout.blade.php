@props([
    'preheader' => null,
    'eyebrow' => null,
    'title' => '',
    'intro' => null,
    'badge' => null,
    'footerNote' => null,
])

@php
    $emailBranding = is_array($emailBranding ?? null) ? $emailBranding : [];
    $brandName = $emailBranding['brand_name'] ?? 'Nombre de la clínica';
    $brandLogo = $emailBranding['brand_logo'] ?? null;
    $accent = $emailBranding['accent'] ?? '#334155';
    $accentStrong = $emailBranding['accent_strong'] ?? '#475569';
    $accentSoft = $emailBranding['accent_soft'] ?? '#ccfbf1';
    $contactPhone = $emailBranding['contact_phone'] ?? '';
    $contactEmail = $emailBranding['contact_email'] ?? '';
    $contactHours = $emailBranding['contact_hours'] ?? '';
    $footerText = $emailBranding['footer_text'] ?? ('© '.date('Y').' - Todos los derechos reservados.');

    $logoSrc = null;
    if ($brandLogo) {
        $logoPath = app(\App\Services\ClinicIdentityService::class)->logoPath();
        $resolvedLogo = app(\App\Services\ClinicIdentityService::class)->logoBase64();
        if (isset($message) && is_object($message) && $logoPath) {
            $absolute = null;
            $candidate = ltrim(str_replace('\\', '/', $logoPath), '/');
            if (str_starts_with($candidate, 'storage/')) {
                $storagePath = substr($candidate, 8);
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($storagePath)) {
                    $absolute = \Illuminate\Support\Facades\Storage::disk('public')->path($storagePath);
                }
            } elseif (\Illuminate\Support\Facades\Storage::disk('public')->exists($candidate)) {
                $absolute = \Illuminate\Support\Facades\Storage::disk('public')->path($candidate);
            } elseif (is_file(public_path($candidate))) {
                $absolute = public_path($candidate);
            }
            $logoSrc = $absolute && is_file($absolute) ? $message->embed($absolute) : null;
        }

        if (! $logoSrc && $brandLogo) {
            $logoSrc = app(\App\Services\ClinicIdentityService::class)->logoUrl('medium');
        }
    }

    $previewText = \Illuminate\Support\Str::limit(
        trim((string) ($preheader ?? $intro ?? $title ?? $brandName)),
        120
    );
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?: $brandName }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f8fafc; background-image:linear-gradient(180deg, #ecfeff 0%, #f8fafc 55%); font-family:'Segoe UI', Arial, sans-serif; color:#0f172a;">
    <div style="display:none; overflow:hidden; line-height:1px; opacity:0; max-height:0; max-width:0;">
        {{ $previewText }}
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; border-collapse:collapse; background-color:#f8fafc; background-image:linear-gradient(180deg, #ecfeff 0%, #f8fafc 55%);">
        <tr>
            <td align="center" style="padding:28px 12px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; max-width:680px; border-collapse:separate; background:#ffffff; border:1px solid #dbe4ee; border-radius:28px; overflow:hidden; box-shadow:0 24px 60px rgba(15, 23, 42, 0.12);">
                    <tr>
                        <td style="padding:0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; border-collapse:collapse; background-color:{{ $accent }}; background-image:linear-gradient(135deg, {{ $accent }} 0%, {{ $accentStrong }} 100%);">
                                <tr>
                                    <td style="padding:32px 32px 28px;">
                                        @if ($logoSrc)
                                            <img src="{{ $logoSrc }}" alt="{{ $brandName }}" width="170" style="display:block; max-width:170px; border:0; outline:none; text-decoration:none;">
                                        @else
                                            <div style="display:inline-block; padding:10px 14px; border-radius:14px; background:rgba(255,255,255,0.14); color:#ffffff; font-size:20px; font-weight:800;">
                                                {{ $brandName }}
                                            </div>
                                        @endif

                                        @if ($eyebrow)
                                            <div style="margin-top:22px;">
                                                <span style="display:inline-block; padding:8px 14px; border-radius:999px; background:rgba(255,255,255,0.18); color:#ffffff; font-size:12px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase;">
                                                    {{ $eyebrow }}
                                                </span>
                                            </div>
                                        @endif

                                        <h1 style="margin:18px 0 0; font-size:30px; line-height:1.18; font-weight:800; color:#ffffff;">
                                            {{ $title }}
                                        </h1>

                                        @if ($intro)
                                            <p style="margin:14px 0 0; max-width:520px; font-size:15px; line-height:1.7; color:rgba(255,255,255,0.92);">
                                                {{ $intro }}
                                            </p>
                                        @endif

                                        @if ($badge)
                                            <div style="margin-top:18px;">
                                                <span style="display:inline-block; padding:8px 14px; border-radius:999px; background:{{ $accentSoft }}; color:#0f172a; font-size:13px; font-weight:700;">
                                                    {{ $badge }}
                                                </span>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            {{ $slot }}

                            @isset($actions)
                                @if ($actions->isNotEmpty())
                                    <div style="margin-top:28px;">
                                        {{ $actions }}
                                    </div>
                                @endif
                            @endisset

                            @if ($footerNote)
                                <p style="margin:24px 0 0; font-size:13px; line-height:1.7; color:#64748b;">
                                    {{ $footerNote }}
                                </p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:22px 32px 26px; background:#0f172a;">
                            <div style="font-size:13px; font-weight:700; color:#f8fafc;">
                                {{ $brandName }}
                            </div>
                            <div style="margin-top:8px; font-size:12px; line-height:1.7; color:#cbd5e1;">
                                {{ $footerText }}
                            </div>
                            @if ($contactPhone || $contactHours || $contactEmail)
                                <div style="margin-top:8px; font-size:12px; line-height:1.7; color:#94a3b8;">
                                    @if ($contactPhone)
                                        Tel. {{ $contactPhone }}
                                    @endif
                                    @if ($contactPhone && $contactHours)
                                        ·
                                    @endif
                                    @if ($contactHours)
                                        Horario: {{ $contactHours }}
                                    @endif
                                    @if (($contactPhone || $contactHours) && $contactEmail)
                                        ·
                                    @endif
                                    @if ($contactEmail)
                                        {{ $contactEmail }}
                                    @endif
                                </div>
                            @endif
                            <div style="margin-top:8px; font-size:12px; line-height:1.7; color:#94a3b8;">
                                Este es un correo automático. Si necesitas ayuda, comunícate con la clínica.
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
