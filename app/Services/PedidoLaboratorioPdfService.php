<?php

namespace App\Services;

use App\Models\PedidoLaboratorio;
use App\Models\PedidoLaboratorioResultado;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PedidoLaboratorioPdfService
{
    public function generar(PedidoLaboratorio $pedido, PedidoLaboratorioResultado $resultado): string
    {
        $pedido->loadMissing([
            'cita.paciente',
            'cita.dependiente.responsable',
            'cita.doctor',
            'cita.especialidad',
            'doctor',
            'paciente',
        ]);
        $resultado->loadMissing(['laboratorio', 'pedido.cita.paciente', 'pedido.cita.dependiente.responsable', 'pedido.cita.doctor']);

        $identity = app(ClinicIdentityService::class);
        $csvService = app(DocumentoCsvService::class);
        $csv = $csvService->ensureCsv($resultado);

        $html = view('pdf.pedido-laboratorio-resultado', [
            'pedido' => $pedido,
            'resultado' => $resultado,
            'clinica' => $identity->institutionalName(),
            'logoBase64' => $identity->logoBase64ForPdf(),
            'pdfCss' => $this->loadPdfCss('laboratorio/pedido-laboratorio-resultado-pdf.css'),
            'csv' => $csv,
            'verificationUrl' => $csvService->verificationUrl($csv),
            'qrDataUri' => $csvService->qrDataUri($csv),
            'fechaPdf' => now('America/Guayaquil'),
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $directory = 'pedidos-laboratorio-resultados';
        if (! Storage::disk('local')->exists($directory)) {
            Storage::disk('local')->makeDirectory($directory);
        }

        $fileName = Str::slug('resultado-'.$pedido->id.'-v'.$resultado->version, '_').'.pdf';
        $path = $directory.'/'.$fileName;
        Storage::disk('local')->put($path, $dompdf->output());

        return $path;
    }

    public function previewHtml(PedidoLaboratorio $pedido, PedidoLaboratorioResultado $resultado): string
    {
        $pedido->loadMissing([
            'cita.paciente',
            'cita.dependiente.responsable',
            'cita.doctor',
            'cita.especialidad',
            'doctor',
            'paciente',
        ]);
        $resultado->loadMissing(['laboratorio', 'pedido.cita.paciente', 'pedido.cita.dependiente.responsable', 'pedido.cita.doctor']);

        $identity = app(ClinicIdentityService::class);
        $csvService = app(DocumentoCsvService::class);
        $csv = $resultado->csv ?: $csvService->ensureCsv($resultado);

        return view('pdf.pedido-laboratorio-resultado', [
            'pedido' => $pedido,
            'resultado' => $resultado,
            'clinica' => $identity->institutionalName(),
            'logoBase64' => $identity->logoBase64ForPdf(),
            'pdfCss' => $this->loadPdfCss('laboratorio/pedido-laboratorio-resultado-pdf.css'),
            'csv' => $csv,
            'verificationUrl' => $csvService->verificationUrl($csv),
            'qrDataUri' => $csvService->qrDataUri($csv),
            'fechaPdf' => now('America/Guayaquil'),
        ])->render();
    }

    public function renderPdfOutput(string $html): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function loadPdfCss(string $relativePath): string
    {
        $basePath = resource_path('css/pdf/base.css');
        $specificPath = resource_path('css/'.$relativePath);

        $css = is_file($basePath) ? (file_get_contents($basePath) ?: '') : '';
        $css .= is_file($specificPath) ? "\n".(file_get_contents($specificPath) ?: '') : '';

        return $css;
    }
}
