@php($homeUrl = url('/'))
<!DOCTYPE html>
<html lang="es" class="dashboard-root">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>500 | Error interno del sistema</title>
    @include('layouts.partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.min.css">
    <style>
        :root {
            --bg: #f8fafc;
            --surface: #ffffff;
            --ink: #0f172a;
            --muted: #475569;
            --border: #cbd5e1;
            --border-strong: #94a3b8;
            --accent: #0f766e;
            --accent-strong: #14b8a6;
            --sky: #0284c7;
            --shadow: 0 22px 55px rgba(15, 23, 42, .12);
        }

        * { box-sizing: border-box; }

        html { min-height: 100%; font-size: 16px; }

        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            padding: clamp(1rem, 3vw, 2rem);
            color: var(--ink);
            font-family: "Sora", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.6;
            background:
                radial-gradient(circle at top left, rgba(20, 184, 166, .16), transparent 34rem),
                radial-gradient(circle at bottom right, rgba(2, 132, 199, .12), transparent 28rem),
                linear-gradient(180deg, #f8fafc 0%, #eef8f7 100%);
        }

        .error-shell { width: min(100%, 980px); display: grid; gap: 1rem; }

        .error-card {
            position: relative;
            overflow: hidden;
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
            gap: clamp(1.25rem, 4vw, 2.75rem);
            padding: clamp(1.25rem, 4vw, 2.65rem);
            border: 1px solid var(--border);
            border-top: 4px solid var(--accent-strong);
            border-radius: 8px;
            background: rgba(255, 255, 255, .94);
            box-shadow: var(--shadow);
            backdrop-filter: blur(16px);
            transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease;
        }

        .error-card:hover {
            transform: translateY(-3px);
            border-color: var(--border-strong);
            box-shadow: 0 28px 65px rgba(15, 23, 42, .16);
        }

        .error-copy { min-width: 0; }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            min-height: 34px;
            padding: .38rem .72rem;
            border: 1px solid rgba(15, 118, 110, .2);
            border-radius: 8px;
            color: var(--accent);
            background: rgba(204, 251, 241, .62);
            font-size: .78rem;
            font-weight: 800;
            letter-spacing: 0;
        }

        .error-code {
            margin: .95rem 0 .35rem;
            color: var(--accent);
            font-family: "Space Grotesk", "Sora", system-ui, sans-serif;
            font-size: clamp(4.75rem, 17vw, 9rem);
            font-weight: 700;
            line-height: .86;
            letter-spacing: 0;
        }

        h1 {
            margin: 0 0 .72rem;
            color: var(--ink);
            font-family: "Space Grotesk", "Sora", system-ui, sans-serif;
            font-size: clamp(1.75rem, 4vw, 2.75rem);
            font-weight: 700;
            line-height: 1.08;
            letter-spacing: 0;
        }

        .description {
            max-width: 42rem;
            margin: 0;
            color: var(--muted);
            font-size: clamp(1rem, 1.5vw, 1.12rem);
            font-weight: 500;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            margin-top: 1.55rem;
        }

        .btn {
            appearance: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            min-height: 46px;
            padding: .78rem 1rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            font: inherit;
            font-size: .95rem;
            font-weight: 800;
            line-height: 1.2;
            text-decoration: none;
            cursor: pointer;
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease, filter .18s ease, background .18s ease;
        }

        .btn:hover { transform: translateY(-2px); }

        .btn-primary {
            border-color: transparent;
            color: #ffffff;
            background: linear-gradient(135deg, var(--accent), var(--accent-strong));
            box-shadow: 0 16px 30px rgba(15, 118, 110, .24);
        }

        .btn-primary:hover {
            filter: saturate(1.06);
            box-shadow: 0 20px 36px rgba(15, 118, 110, .3);
        }

        .btn-secondary {
            color: var(--ink);
            background: #ffffff;
        }

        .btn-secondary:hover {
            border-color: var(--border-strong);
            background: #f8fafc;
        }

        .error-visual {
            width: clamp(8.5rem, 22vw, 14rem);
            aspect-ratio: 1;
            display: grid;
            place-items: center;
            border: 1px solid rgba(15, 118, 110, .24);
            border-radius: 8px;
            color: var(--accent);
            background: linear-gradient(135deg, rgba(204, 251, 241, .92), rgba(239, 246, 255, .94));
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, .85), 0 20px 38px rgba(15, 118, 110, .14);
        }

        .error-visual i { font-size: clamp(4rem, 11vw, 7rem); line-height: 1; }

        .support-note {
            margin: 0;
            color: #64748b;
            font-size: .9rem;
            font-weight: 600;
            text-align: center;
        }

        @media (max-width: 720px) {
            body { place-items: stretch; padding: 1rem; }
            .error-card { grid-template-columns: 1fr; padding: 1.15rem; }
            .error-visual { order: -1; width: 100%; height: 7.5rem; aspect-ratio: auto; }
            .actions { display: grid; }
            .btn { width: 100%; }
            .support-note { text-align: left; }
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #020617;
                --surface: #0f172a;
                --ink: #f8fafc;
                --muted: #cbd5e1;
                --border: #334155;
                --border-strong: #64748b;
                --accent: #2dd4bf;
                --accent-strong: #38bdf8;
                --shadow: 0 24px 58px rgba(0, 0, 0, .42);
            }

            body {
                background:
                    radial-gradient(circle at top left, rgba(45, 212, 191, .16), transparent 32rem),
                    radial-gradient(circle at bottom right, rgba(56, 189, 248, .12), transparent 28rem),
                    linear-gradient(180deg, #020617 0%, #0f172a 100%);
            }

            .error-card { background: rgba(15, 23, 42, .94); }
            .eyebrow { color: #99f6e4; background: rgba(20, 184, 166, .13); border-color: rgba(45, 212, 191, .28); }
            .btn-secondary { color: var(--ink); background: #0f172a; }
            .btn-secondary:hover { background: #111827; }
            .error-visual { background: linear-gradient(135deg, rgba(15, 118, 110, .24), rgba(2, 132, 199, .16)); border-color: rgba(45, 212, 191, .3); }
            .support-note { color: #94a3b8; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { transition-duration: .01ms !important; animation-duration: .01ms !important; scroll-behavior: auto !important; }
            .error-card:hover, .btn:hover { transform: none; }
        }
    </style>
</head>
<body>
    <main class="error-shell" aria-labelledby="error-title">
        <section class="error-card">
            <div class="error-copy">
                <span class="eyebrow"><i class="ri-heart-pulse-line" aria-hidden="true"></i> Incidencia temporal</span>
                <p class="error-code">500</p>
                <h1 id="error-title">Error interno del sistema</h1>
                <p class="description">No pudimos completar la solicitud en este momento. Intenta nuevamente o vuelve al inicio para continuar.</p>
                <div class="actions" aria-label="Acciones disponibles">
                    <a class="btn btn-primary" href="{{ $homeUrl }}"><i class="ri-home-4-line" aria-hidden="true"></i> Volver al inicio</a>
                    <button class="btn btn-secondary" type="button" data-back-button data-fallback="{{ $homeUrl }}"><i class="ri-arrow-left-line" aria-hidden="true"></i> Regresar</button>
                </div>
            </div>
            <div class="error-visual" aria-hidden="true"><i class="ri-heart-pulse-line"></i></div>
        </section>
        <p class="support-note">Intenta nuevamente en unos minutos o contin&uacute;a desde el inicio del sistema.</p>
    </main>

    <script>
        document.querySelector('[data-back-button]')?.addEventListener('click', function () {
            const fallback = this.getAttribute('data-fallback') || '/';
            if (window.history.length > 1) {
                window.history.back();
                return;
            }
            window.location.assign(fallback);
        });
    </script>
</body>
</html>
