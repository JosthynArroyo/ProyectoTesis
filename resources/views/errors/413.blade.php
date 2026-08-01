@php($editUrl = route('superadmin.personalizacion.servicios.edit'))
<!DOCTYPE html>
<html lang="es" class="dashboard-root">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>413 | Archivo demasiado grande</title>
    @include('layouts.partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.min.css">
    <style>
        :root {
            --ink: #0f172a;
            --muted: #475569;
            --border: #cbd5e1;
            --border-strong: #94a3b8;
            --accent: #0f766e;
            --accent-strong: #14b8a6;
            --warn: #b45309;
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
                radial-gradient(circle at top left, rgba(245, 158, 11, .14), transparent 34rem),
                radial-gradient(circle at bottom right, rgba(20, 184, 166, .10), transparent 28rem),
                linear-gradient(180deg, #f8fafc 0%, #eef8f7 100%);
        }

        .error-shell { width: min(100%, 980px); display: grid; gap: 1rem; }

        .error-card {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
            gap: clamp(1.25rem, 4vw, 2.75rem);
            padding: clamp(1.25rem, 4vw, 2.65rem);
            border: 1px solid var(--border);
            border-top: 4px solid var(--warn);
            border-radius: 8px;
            background: rgba(255, 255, 255, .94);
            box-shadow: var(--shadow);
            backdrop-filter: blur(16px);
        }

        .error-copy { min-width: 0; }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            min-height: 34px;
            padding: .38rem .72rem;
            border: 1px solid rgba(180, 83, 9, .2);
            border-radius: 8px;
            color: var(--warn);
            background: rgba(254, 243, 199, .72);
            font-size: .78rem;
            font-weight: 800;
        }

        .error-code {
            margin: .95rem 0 .35rem;
            color: var(--warn);
            font-family: "Space Grotesk", "Sora", system-ui, sans-serif;
            font-size: clamp(4.75rem, 17vw, 9rem);
            font-weight: 700;
            line-height: .86;
        }

        h1 {
            margin: 0 0 .72rem;
            color: var(--ink);
            font-family: "Space Grotesk", "Sora", system-ui, sans-serif;
            font-size: clamp(1.75rem, 4vw, 2.75rem);
            font-weight: 700;
            line-height: 1.08;
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
        }

        .btn-primary {
            border-color: transparent;
            color: #ffffff;
            background: linear-gradient(135deg, var(--warn), #f59e0b);
            box-shadow: 0 16px 30px rgba(180, 83, 9, .24);
        }

        .btn-secondary {
            color: var(--ink);
            background: #ffffff;
        }

        .error-visual {
            width: clamp(8.5rem, 22vw, 14rem);
            aspect-ratio: 1;
            display: grid;
            place-items: center;
            border: 1px solid rgba(180, 83, 9, .24);
            border-radius: 8px;
            color: var(--warn);
            background: linear-gradient(135deg, rgba(254, 243, 199, .92), rgba(239, 246, 255, .94));
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, .85), 0 20px 38px rgba(180, 83, 9, .14);
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
    </style>
</head>
<body>
    <main class="error-shell" aria-labelledby="error-title">
        <section class="error-card">
            <div class="error-copy">
                <span class="eyebrow"><i class="ri-hard-drive-3-line" aria-hidden="true"></i> Solicitud demasiado grande</span>
                <p class="error-code">413</p>
                <h1 id="error-title">La carga excede el tamaño permitido</h1>
                <p class="description">Las imagenes seleccionadas son demasiado grandes para procesarse en este formulario. Reduce el peso total o vuelve a intentarlo con archivos mas livianos.</p>
                <div class="actions" aria-label="Acciones disponibles">
                    <a class="btn btn-primary" href="{{ $editUrl }}"><i class="ri-arrow-go-back-line" aria-hidden="true"></i> Volver al formulario</a>
                    <a class="btn btn-secondary" href="{{ url('/') }}"><i class="ri-home-4-line" aria-hidden="true"></i> Ir al inicio</a>
                </div>
            </div>
            <div class="error-visual" aria-hidden="true"><i class="ri-file-warning-line"></i></div>
        </section>
        <p class="support-note">Si el problema persiste, revisa el tamano de las imagenes antes de enviarlas nuevamente.</p>
    </main>
</body>
</html>
