<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        {!! $pdfCss !!}
    </style>
</head>
<body>
    <div class="header">
        <h1 class="report-title">{{ $title }}</h1>
        <p class="report-meta">Generado por: Rol {{ $role }} | Fecha: {{ now()->format('d/m/Y H:i:s') }}</p>
    </div>

    <hr class="divider">

    <div class="section-title">Resumen de Métricas Clave (KPIs)</div>
    <table class="kpi-table">
        <thead>
            <tr>
                <th>Métrica</th>
                <th class="text-right">Valor</th>
            </tr>
        </thead>
        <tbody>
            @foreach($kpis as $key => $value)
                <tr>
                    <td>{{ $key }}</td>
                    <td class="text-right font-bold">{{ $value }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="section-title" style="margin-top: 25px;">Detalle de Actividad Diaria (Últimos 7 días)</div>
    <table class="activity-table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th class="text-center">Día</th>
                <th class="text-right">Volumen de Citas</th>
                <th class="text-right">Usuarios Registrados</th>
            </tr>
        </thead>
        <tbody>
            @foreach($activity as $item)
                <tr>
                    <td>{{ $item['date'] }}</td>
                    <td class="text-center">{{ $item['label'] }}</td>
                    <td class="text-right font-bold text-teal">{{ $item['citas'] }}</td>
                    <td class="text-right font-bold text-blue">{{ $item['usuarios'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Este es un reporte oficial autogenerado por el sistema de gestión de la clínica.</p>
    </div>
</body>
</html>
