<?php

namespace App\Services;

use App\Models\CertificadoMedico;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CertificadoMedicoPdfService
{
    public function obtenerOGenerar(CertificadoMedico $certificado): string
    {
        $path = (string) $certificado->pdf_path;
        $docService = app(MedicalCertificateDocumentService::class);
        $diskName = $docService->resolveDisk($certificado->pdf_disk);

        if ($path === '' || ! Storage::disk($diskName)->exists($path)) {
            return $this->generarYGuardar($certificado);
        }

        return $path;
    }

    public function generarYGuardar(CertificadoMedico $certificado): string
    {
        $docService = app(MedicalCertificateDocumentService::class);
        [$pdfOutput, $csv] = $docService->generatePdfOutput($certificado);

        $oldPath = (string) $certificado->getRawOriginal('pdf_path');
        $oldDisk = (string) $certificado->getRawOriginal('pdf_disk');

        $storage = $docService->storeCertificatePdf($certificado, $pdfOutput);

        $certificado->forceFill([
            'csv' => $csv,
            'pdf_path' => $storage['pdf_path'],
            'pdf_disk' => $storage['pdf_disk'],
        ])->saveQuietly();

        $docService->cleanupOldPdf($oldPath, $oldDisk);

        return $storage['pdf_path'];
    }

    protected function renderizarPdf(string $html): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    protected function logoBase64(): ?string
    {
        return app(ClinicIdentityService::class)->logoBase64ForPdf();
    }

    protected function loadPdfCss(string $relativePath): string
    {
        $basePath = resource_path('css/pdf/base.css');
        $specificPath = resource_path('css/'.$relativePath);

        $css = is_file($basePath) ? (file_get_contents($basePath) ?: '') : '';
        $css .= is_file($specificPath) ? "\n".(file_get_contents($specificPath) ?: '') : '';

        return $css;
    }
}
